<?php
require __DIR__.'/helpers/GrandOpeningPricingBootstrap.php';
use App\Core\Database as DB;
use App\Services\BattleService as Battle;
$checks=0;
$check=static function(bool $ok,string $name)use(&$checks):void{if(!$ok)throw new RuntimeException($name);++$checks;};
$fixture=static function(string $table,array $fields): void {
    if (!preg_match('/^[a-z_]+$/D',$table)) throw new RuntimeException('Invalid fixture table.');
    foreach (DB::rows('show columns from `'.$table.'`') as $column) {
        $name=$column['Field'];
        if (array_key_exists($name,$fields) || $column['Null']==='YES' || $column['Default']!==null || str_contains($column['Extra'],'auto_increment') || str_contains($column['Extra'],'GENERATED')) continue;
        $type=$column['Type'];
        if (str_starts_with($type,'enum(')) {
            preg_match("/^enum\\('((?:[^'\\\\]|\\\\.)*)'/",$type,$m);
            $fields[$name]=stripslashes($m[1]);
        } elseif (preg_match('/int|decimal|float|double|bit/',$type)) $fields[$name]=0;
        elseif (preg_match('/date|time|year/',$type)) $fields[$name]='2026-01-01 00:00:00';
        elseif ($type==='json') $fields[$name]='{}';
        else $fields[$name]='fixture';
    }
    $columns=implode(',',array_map(static fn(string $s):string=>'`'.$s.'`',array_keys($fields)));
    DB::exec('insert into `'.$table.'`('.$columns.') values('.implode(',',array_fill(0,count($fields),'?')).')',array_values($fields));
};


$denied=static function(callable $fn,string $message)use($check):void{try{$fn();}catch(DomainException $e){$check(str_contains($e->getMessage(),$message),$message);return;}throw new RuntimeException('Expected rejection: '.$message);};
$event=['status'=>'signup','signup_closes_at'=>'2026-11-01 04:00:00','first_round_opens_at'=>'2026-11-01 17:00:00'];
$check(Battle::signupOpen($event,strtotime('2026-11-01 03:59:59 UTC')),'Signup open until last second of October 31 Detroit');
$check(!Battle::signupOpen($event,strtotime('2026-11-01 04:00:00 UTC')),'Signup closes exactly at Detroit midnight');
$check(Battle::eastern($event['signup_closes_at'])==='Nov 1, 2026 12:00 AM EDT','Midnight has accurate daylight label');
$check(Battle::eastern($event['first_round_opens_at'])==='Nov 1, 2026 12:00 PM EST','Noon has accurate standard-time label');
$check(Battle::calendarRoundStart($event,2,strtotime('2026-11-02 17:00:20 UTC'))==='2026-11-02 17:00:00','Normal cron latency does not shift daily noon');
$check(Battle::calendarRoundStart($event,2,strtotime('2026-11-02 18:00:00 UTC'),true)==='2026-11-03 17:00:00','Tie resolution resumes next daily noon');
for($round=1;$round<=6;$round++)$check(str_contains(Battle::eastern(Battle::calendarRoundStart($event,$round,strtotime('2026-10-27 UTC'))),'12:00 PM EST'),'All planned November rounds use noon EST');
ob_start();include app_path('app/Views/partials/battle_calendar.php');$calendarHtml=ob_get_clean();
$check(str_contains($calendarHtml,'12:00 AM EDT')&&str_contains($calendarHtml,'12:00 PM EST'),'Shared public/seller/admin schedule labels DST correctly');
$fixture('users',['id'=>1,'name'=>'Admin Fixture','email'=>'admin@example.invalid','role'=>'admin','status'=>'active']);
for($i=2;$i<=5;$i++){
 $fixture('users',['id'=>$i,'name'=>'Seller '.$i,'email'=>'seller'.$i.'@example.invalid','role'=>'designer','status'=>'active','referral_code'=>'BATTLE'.$i]);
 $fixture('designers',['id'=>$i,'user_id'=>$i,'display_name'=>'Shop '.$i,'store_slug'=>'shop-'.$i,'status'=>'approved']);
}
$firstOpen=(new DateTimeImmutable('now',new DateTimeZone('America/Detroit')))->modify('+2 days')->setTime(12,0)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
$secondOpen=gmdate('Y-m-d H:i:s',strtotime($firstOpen.' UTC')+86400);
$thirdOpen=gmdate('Y-m-d H:i:s',strtotime($firstOpen.' UTC')+172800);
$fixture('battle_events',['id'=>1,'title'=>'Grand Opening','slug'=>'grand-opening','status'=>'signup','created_by_admin_id'=>1,'signup_closes_at'=>gmdate('Y-m-d H:i:s',time()+3600),'first_round_opens_at'=>$firstOpen]);
for($i=2;$i<=5;$i++)$fixture('battle_participants',['id'=>$i,'battle_id'=>1,'designer_id'=>$i,'logo_path'=>'fixture/logo.png','status'=>'active']);
$service=new Battle();
$denied(fn()=>$service->generateBracket(1,1),'Signup remains open');
$service->processSchedule();
$check((int)DB::row('select count(*) n from battle_rounds')['n']===0,'Cron does not seed before cutoff');
DB::exec('update battle_events set signup_closes_at=date_sub(utc_timestamp(),interval 1 second) where id=1');
$denied(fn()=>$service->signup(1,2,[]),'signup is closed');
$denied(fn()=>$service->withdrawSignup(1,2),'registration can no longer');
$result=$service->processSchedule();$check(!$result['errors'],'Automatic bracket and first-round scheduling succeed: '.json_encode($result));
$first=DB::row('select * from battle_rounds where battle_id=1 and round_number=1');
$check($first['voting_opens_at']===$firstOpen&&$first['voting_closes_at']===$secondOpen,'First voting window is exact 24 hours');
$check(DB::row('select status from battle_events where id=1')['status']==='seeded','Automatic seeding persists');
$counts=[DB::row('select count(*) n from battle_rounds')['n'],DB::row('select count(*) n from battle_matchups')['n'],DB::row('select count(*) n from email_messages')['n']];
$service->processSchedule();
$check($counts===[DB::row('select count(*) n from battle_rounds')['n'],DB::row('select count(*) n from battle_matchups')['n'],DB::row('select count(*) n from email_messages')['n']],'Cron retry duplicates no bracket or emails');
$emails=DB::rows('select template_data from email_messages');
foreach($emails as $mail){$data=json_decode($mail['template_data'],true);$check((bool)preg_match('/E[SD]T/',$data['voting_window']),'Battle emails display timezone');}
DB::exec('update battle_matchups set status="completed",winner_participant_id=participant_one_id where battle_id=1 and round_number=1');
$finish=new ReflectionMethod(Battle::class,'completeRound');$finish->setAccessible(true);$finish->invoke($service,1,1,1);
$next=DB::row('select * from battle_rounds where battle_id=1 and round_number=2');
$check($next['voting_opens_at']===$secondOpen&&$next['voting_closes_at']===$thirdOpen,'Winner advancement has no 12-hour gap');
$fixture('battle_events',['id'=>2,'title'=>'Too few','slug'=>'too-few','status'=>'signup','created_by_admin_id'=>1,'signup_closes_at'=>gmdate('Y-m-d H:i:s',time()-1),'first_round_opens_at'=>$firstOpen]);
$result=$service->processSchedule();$check(count($result['errors'])===1&&str_contains($result['errors'][0],'at least two'),'Missing participants reported rather than inventing entries');
$check((int)DB::row('select count(*) n from battle_rounds where battle_id=2')['n']===0,'No invalid one-person bracket');
echo "PASS: $checks Battle calendar checks\n";
