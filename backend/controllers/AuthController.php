<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/env.php';

function startAuthSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_name('poolpilot_session');
    session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
    session_start();
}

function authenticatedUser(): ?array
{
    startAuthSession();
    $id = (int) ($_SESSION['user_id'] ?? 0);
    if ($id < 1) return null;
    static $cached = false, $user = null;
    if ($cached) return $user;
    $stmt = db()->prepare('SELECT id, organization_id, name, email, role, active, is_system_admin, created_at, updated_at FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $user = $stmt->fetch() ?: null;
    $cached = true;
    if (!$user || !(int) $user['active']) { $_SESSION=[]; return null; }
    return $user;
}

function requireAuth(): array
{
    $user = authenticatedUser();
    if (!$user) jsonResponse(['error'=>'Authentication required'],401);
    return $user;
}

function requireRoles(array $roles): array
{
    $user=requireAuth();
    if(!in_array($user['role'],$roles,true)) jsonResponse(['error'=>'You do not have permission to perform this action'],403);
    return $user;
}

function requireSystemAdmin(): array
{
    $user=requireAuth();
    if(!(int)($user['is_system_admin']??0)) jsonResponse(['error'=>'System administrator permission required'],403);
    return $user;
}

function login(): never
{
    startAuthSession();
    $input=json_decode(file_get_contents('php://input'),true)?:[];
    $email=strtolower(trim((string)($input['email']??'')));
    $password=(string)($input['password']??'');
    if(!filter_var($email,FILTER_VALIDATE_EMAIL)||$password==='') jsonResponse(['error'=>'Enter a valid email and password'],422);
    $stmt=db()->prepare('SELECT id, organization_id, name, email, password_hash, role, active, is_system_admin, created_at, updated_at FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]); $user=$stmt->fetch();
    if(!$user||!(int)$user['active']||!password_verify($password,$user['password_hash'])){usleep(250000);jsonResponse(['error'=>'Invalid email or password'],401);}
    session_regenerate_id(true);
    $_SESSION['user_id']=(int)$user['id'];
    $_SESSION['organization_id']=$user['organization_id']!==null?(int)$user['organization_id']:null;
    unset($user['password_hash']); jsonResponse(['data'=>$user]);
}

function logout(): never
{
    startAuthSession(); $_SESSION=[];
    if(ini_get('session.use_cookies')){$p=session_get_cookie_params();setcookie(session_name(),' ',time()-42000,$p['path'],'',(bool)$p['secure'],(bool)$p['httponly']);}
    session_destroy(); jsonResponse(['message'=>'Logged out']);
}

function me(): never { jsonResponse(['data'=>requireAuth()]); }

function setupStatus(): never
{
    $count=(int)db()->query('SELECT COUNT(*) FROM users')->fetchColumn();
    jsonResponse(['data'=>['setup_required'=>$count===0]]);
}

function bootstrapOwner(): never
{
    $input=json_decode(file_get_contents('php://input'),true)?:[];
    $key=(string)($input['setup_key']??''); $expected=(string)env('BOOTSTRAP_KEY','');
    if($expected===''||!hash_equals($expected,$key)) jsonResponse(['error'=>'Invalid setup key'],403);
    $pdo=db(); if((int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn()>0) jsonResponse(['error'=>'Initial account has already been created'],409);
    $name=trim((string)($input['name']??'')); $email=strtolower(trim((string)($input['email']??''))); $password=(string)($input['password']??'');
    $org=max(1,(int)($input['organization_id']??env('DEFAULT_ORGANIZATION_ID','1')));
    if($name===''||!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($password)<8) jsonResponse(['error'=>'Name, valid email and a password of at least 8 characters are required'],422);
    $check=$pdo->prepare('SELECT id FROM organizations WHERE id=?'); $check->execute([$org]);
    if(!$check->fetchColumn()) jsonResponse(['error'=>'Organization not found'],422);
    try{$stmt=$pdo->prepare('INSERT INTO users (organization_id,name,email,password_hash,role,active,is_system_admin) VALUES (?,?,?,?, "owner",1,1)');$stmt->execute([$org,$name,$email,password_hash($password,PASSWORD_DEFAULT)]);}
    catch(PDOException $e){if((int)($e->errorInfo[1]??0)===1062)jsonResponse(['error'=>'An account with that email already exists'],409);throw $e;}
    jsonResponse(['message'=>'Owner account created. You can now log in.'],201);
}

function listUsers(): never
{
    $actor=requireRoles(['owner','admin']);
    $stmt=db()->prepare('SELECT id,name,email,role,active,is_system_admin,created_at,updated_at FROM users WHERE organization_id=? ORDER BY name');
    $stmt->execute([(int)$actor['organization_id']]); jsonResponse(['data'=>$stmt->fetchAll()]);
}

function createUser(): never
{
    $actor=requireRoles(['owner','admin']); $input=json_decode(file_get_contents('php://input'),true)?:[];
    $name=trim((string)($input['name']??''));$email=strtolower(trim((string)($input['email']??'')));$password=(string)($input['password']??'');$role=(string)($input['role']??'attendant');
    $allowed=$actor['role']==='owner'?['owner','admin','attendant','accountant']:['admin','attendant','accountant'];
    if($name===''||!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($password)<8||!in_array($role,$allowed,true))jsonResponse(['error'=>'Provide a name, valid email, password of at least 8 characters, and an allowed role'],422);
    try{$stmt=db()->prepare('INSERT INTO users (organization_id,name,email,password_hash,role,active,is_system_admin) VALUES (?,?,?,?,?,1,0)');$stmt->execute([(int)$actor['organization_id'],$name,$email,password_hash($password,PASSWORD_DEFAULT),$role]);}
    catch(PDOException $e){if((int)($e->errorInfo[1]??0)===1062)jsonResponse(['error'=>'An account with that email already exists'],409);throw $e;}
    jsonResponse(['message'=>'User created'],201);
}

function updateUser(int $id): never
{
    $actor=requireRoles(['owner','admin']);$input=json_decode(file_get_contents('php://input'),true)?:[];
    $stmt=db()->prepare('SELECT id,organization_id,role,is_system_admin FROM users WHERE id=? LIMIT 1');$stmt->execute([$id]);$target=$stmt->fetch();
    if(!$target||(int)$target['organization_id']!==(int)$actor['organization_id'])jsonResponse(['error'=>'User not found'],404);
    if((int)$target['is_system_admin']&&!(int)$actor['is_system_admin'])jsonResponse(['error'=>'Only a system administrator can modify a system administrator account'],403);
    if($target['role']==='owner'&&$actor['role']!=='owner')jsonResponse(['error'=>'Only the owner can modify an owner account'],403);
    if($id===(int)$actor['id']&&array_key_exists('active',$input)&&!(bool)$input['active'])jsonResponse(['error'=>'You cannot deactivate your own account'],422);
    $sets=[];$values=[];
    if(isset($input['name'])&&trim((string)$input['name'])!==''){$sets[]='name=?';$values[]=trim((string)$input['name']);}
    if(isset($input['role'])){$role=(string)$input['role'];$allowed=$actor['role']==='owner'?['owner','admin','attendant','accountant']:['admin','attendant','accountant'];if(!in_array($role,$allowed,true))jsonResponse(['error'=>'Invalid role'],422);if($target['role']==='owner'&&$role!=='owner')jsonResponse(['error'=>'The owner account cannot be demoted'],403);$sets[]='role=?';$values[]=$role;}
    if(array_key_exists('active',$input)){$active=(bool)$input['active'];if((int)$target['is_system_admin']&&!$active)jsonResponse(['error'=>'A system administrator cannot be deactivated'],422);$sets[]='active=?';$values[]=(int)$active;}
    if(isset($input['password'])&&(string)$input['password']!==''){if(strlen((string)$input['password'])<8)jsonResponse(['error'=>'Password must be at least 8 characters'],422);$sets[]='password_hash=?';$values[]=password_hash((string)$input['password'],PASSWORD_DEFAULT);}
    if(!$sets)jsonResponse(['error'=>'No changes supplied'],422);
    $values[]=$id;$values[]=(int)$actor['organization_id'];db()->prepare('UPDATE users SET '.implode(', ',$sets).' WHERE id=? AND organization_id=?')->execute($values);
    jsonResponse(['message'=>'User updated']);
}
