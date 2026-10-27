<?php
require_once __DIR__.'/../app/Services/GrandOpeningSaleService.php';
use App\Services\GrandOpeningSaleService as Sale;

$checks=0;
$check=function(bool $ok,string $label) use (&$checks): void {
    if (!$ok) throw new RuntimeException($label);
    $checks++;
};
foreach (['10'=>1000,'15'=>1500,'20'=>2000,'25'=>2500,'0.01'=>1,'99.99'=>9999] as $input=>$expected) {
    $check(Sale::basisPoints((string)$input)===$expected,'percentage '.$input);
}
foreach (['0','100','-5','1e1','10.001','10%','NaN'] as $bad) {
    $caught=false;
    try { Sale::basisPoints($bad); } catch (DomainException $e) { $caught=true; }
    $check($caught,'reject '.$bad);
}
$check(Sale::discountedCents(1000,2500)===750,'25 percent');
$check(Sale::discountedCents(101,5000)===50,'round discount half cent');
$check(Sale::discountedCents(0,1000)===0,'zero price');
$check(Sale::utcInput('2026-10-27T12:00')==='2026-10-27 16:00:00','Eastern daylight time');
$check(Sale::utcInput('2026-12-01T12:00')==='2026-12-01 17:00:00','Eastern standard time');
$check(Sale::utcInput('')===null,'unset dates');
$check(Sale::localInput('2026-12-01 17:00:00')==='2026-12-01T12:00','local display');
foreach (['2026-03-08T02:30','2026-11-01T01:30','2026-02-30T10:00'] as $bad) {
    $caught=false;
    try { Sale::utcInput($bad); } catch (DomainException $e) { $caught=true; }
    $check($caught,'reject ambiguous/invalid date '.$bad);
}
$event=['starts_at'=>'2026-12-01 17:00:00','ends_at'=>'2026-12-08 17:00:00','is_enabled'=>1];
$zone=new DateTimeZone('UTC');
$start=(new DateTimeImmutable($event['starts_at'],$zone))->getTimestamp();
$end=(new DateTimeImmutable($event['ends_at'],$zone))->getTimestamp();
$check(Sale::state($event,$start-1)==='upcoming','before start');
$check(Sale::state($event,$start)==='live','start inclusive');
$check(Sale::state($event,$end-1)==='live','before end');
$check(Sale::state($event,$end)==='ended','end exclusive');
$event['is_enabled']=0;
$check(Sale::state($event,$start)==='draft','disabled');
$check(Sale::state([])==='unscheduled','unscheduled');
$check(!Sale::PRICING_READY,'activation locked');
echo "PASS: $checks Grand Opening Sale control checks\n";
