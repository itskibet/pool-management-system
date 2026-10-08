<?php
declare(strict_types=1);
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../config/response.php';
require_once __DIR__.'/../config/organization.php';

function listDailyClosings(): never
{
    $actor=requireAuth();$s=db()->prepare('SELECT d.id,d.business_date,d.gross_revenue,d.platform_fee_rate,d.platform_fee_amount,d.status,d.closed_at,u.name closed_by_name FROM daily_closings d LEFT JOIN users u ON u.id=d.closed_by WHERE d.organization_id=? ORDER BY d.business_date DESC');$s->execute([(int)$actor['organization_id']]);jsonResponse(['data'=>$s->fetchAll()]);
}
function closeDailyBusiness(): never
{
    $actor=requireRoles(['owner','admin']);$input=json_decode(file_get_contents('php://input'),true)?:[];$date=trim((string)($input['business_date']??date('Y-m-d')));
    if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date))jsonResponse(['error'=>'business_date must use YYYY-MM-DD'],422);
    $today=(new DateTimeImmutable('now',new DateTimeZone('Africa/Nairobi')))->format('Y-m-d');
    if($date>$today)jsonResponse(['error'=>'You cannot close a future business date'],422);
    $org=(int)$actor['organization_id'];$pdo=db();$pdo->beginTransaction();
    try{
        $s=$pdo->prepare('SELECT id FROM daily_closings WHERE organization_id=? AND business_date=? FOR UPDATE');$s->execute([$org,$date]);if($s->fetch()){ $pdo->rollBack();jsonResponse(['error'=>'This business day is already closed'],409);}
        $s=$pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM payments WHERE organization_id=? AND status="confirmed" AND paid_at>=? AND paid_at<DATE_ADD(?,INTERVAL 1 DAY)');$s->execute([$org,$date,$date]);$gross=round((float)$s->fetchColumn(),2);$rate=4.00;$fee=round($gross*$rate/100,2);
        $s=$pdo->prepare('INSERT INTO daily_closings(organization_id,business_date,gross_revenue,platform_fee_rate,platform_fee_amount,status,closed_by) VALUES(?,?,?,?,?,"closed",?)');$s->execute([$org,$date,$gross,$rate,$fee,$actor['id']]);$id=(int)$pdo->lastInsertId();$pdo->commit();
        jsonResponse(['message'=>'Business day closed and 4% platform fee calculated','data'=>['id'=>$id,'business_date'=>$date,'gross_revenue'=>$gross,'platform_fee_rate'=>$rate,'platform_fee_amount'=>$fee,'status'=>'closed']],201);
    }catch(PDOException $e){if($pdo->inTransaction())$pdo->rollBack();if((string)$e->getCode()==='23000')jsonResponse(['error'=>'This business day is already closed'],409);throw $e;}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
