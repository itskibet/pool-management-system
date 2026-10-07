<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/response.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../controllers/TableController.php';
require_once __DIR__ . '/../controllers/PaymentController.php';
require_once __DIR__ . '/../controllers/GameController.php';
require_once __DIR__ . '/../controllers/PaymentSettingsController.php';
require_once __DIR__ . '/../controllers/MpesaController.php';
require_once __DIR__ . '/../controllers/PayHeroController.php';

$method=$_SERVER['REQUEST_METHOD']; $path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH)??'/';
$path=rtrim($path,'/'); $path=preg_replace('#^/index.php#','',$path); $path=preg_replace('#^/api#','',$path); $path=$path===''?'/':$path;

if($method==='GET'&&$path==='/health'){try{db()->query('SELECT 1');jsonResponse(['name'=>'Pool Management API','status'=>'ok','version'=>'0.4.0','database'=>'connected']);}catch(Throwable $e){jsonResponse(['name'=>'Pool Management API','status'=>'error','version'=>'0.4.0','database'=>'disconnected'],503);}}
if($method==='GET'&&$path==='/auth/setup-status')setupStatus();
if($method==='POST'&&$path==='/auth/bootstrap')bootstrapOwner();
if($method==='POST'&&$path==='/auth/login')login();
if($method==='POST'&&$path==='/auth/logout')logout();
if($method==='GET'&&$path==='/auth/me')me();
if($method==='GET'&&$path==='/users')listUsers();
if($method==='POST'&&$path==='/users')createUser();
if($method==='PUT'&&preg_match('#^/users/(\d+)$#',$path,$m))updateUser((int)$m[1]);
if($method==='GET'&&$path==='/tables'){requireAuth();listTables();}
if($method==='GET'&&preg_match('#^/tables/(\d+)$#',$path,$m)){requireAuth();getTable((int)$m[1]);}
if($method==='GET'&&$path==='/payments'){requireAuth();listPayments();}
if($method==='POST'&&$path==='/payments'){requireAuth();createPendingPayment();}
if($method==='GET'&&$path==='/games'){requireAuth();listGames();}
if($method==='POST'&&preg_match('#^/games/(\d+)/end$#',$path,$m)){requireAuth();endGame((int)$m[1]);}
if($method==='GET'&&$path==='/payment-settings'){requireAuth();getPaymentSettings();}
if($method==='POST'&&$path==='/payhero/stk-push'){requireAuth();initiatePayHeroStkPush();}
if($method==='POST'&&$path==='/payhero/callback')payHeroCallback();
if($method==='POST'&&$path==='/mpesa/c2b/validation')mpesaC2BValidation();
if($method==='POST'&&$path==='/mpesa/c2b/confirmation')mpesaC2BConfirmation();
jsonResponse(['error'=>'Route not found'],404);
