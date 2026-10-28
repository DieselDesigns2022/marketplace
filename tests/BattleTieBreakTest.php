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

$fixture('users',['id'=>1,'name'=>'Admin Fixture','email'=>'admin@example.invalid','role'=>'admin','status'=>'active']);
for($i=2;$i<=12;$i++){
 $fixture('users',['id'=>$i,'name'=>'Fixture '.$i,'email'=>'user'.$i.'@example.invalid','role'=>$i<=5?'designer':'buyer','status'=>'active','referral_code'=>'TIE'.$i]);
 if($i<=5)$fixture('designers',['id'=>$i,'user_id'=>$i,'display_name'=>'Shop '.$i,'store_slug'=>'shop-'.$i,'status'=>'approved']);
}
$service=new Battle();
$setup=static function(int $id)use($fixture,$service):array{
 $fixture('battle_events',['id'=>$id,'title'=>'Fixture '.$id,'slug'=>'fixture-'.$id,'status'=>'signup','created_by_admin_id'=>1]);
 for($i=2;$i<=5;$i++)$fixture('battle_participants',['battle_id'=>$id,'designer_id'=>$i,'logo_path'=>'fixture.png','status'=>'active']);
 $service->generateBracket($id,1);
 DB::exec('update battle_events set status="active",current_round=1,first_round_opens_at=? where id=?',[(new DateTimeImmutable('yesterday noon',new DateTimeZone('America/Detroit')))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),$id]);
 DB::exec('update battle_rounds set status="open",voting_opens_at=date_sub(utc_timestamp(),interval 60 second),voting_closes_at=date_add(utc_timestamp(),interval 60 second) where battle_id=? and round_number=1',[$id]);
 DB::exec('update battle_matchups set status="open",voting_opens_at=date_sub(utc_timestamp(),interval 60 second),voting_closes_at=date_add(utc_timestamp(),interval 60 second) where battle_id=? and round_number=1',[$id]);
 return DB::rows('select * from battle_matchups where battle_id=? and round_number=1 order by bracket_position',[$id]);
};
$expire=static function(int $id):void{
 DB::exec('update battle_rounds set voting_closes_at=date_sub(utc_timestamp(),interval 1 second) where battle_id=? and round_number=1',[$id]);
 DB::exec('update battle_matchups set voting_closes_at=date_sub(utc_timestamp(),interval 1 second) where battle_id=? and round_number=1 and status="open" and voting_phase<2',[$id]);
};
$cast=static fn(array $m,int $side,int $user,int $phase)=>$service->vote((int)$m['id'],(int)$m[$side===1?'participant_one_id':'participant_two_id'],$user,'198.51.100.'.$user,'Tie fixture '.$user,$phase);
[$a,$b]=$setup(1);
$cast($a,1,6,0);$cast($a,2,7,0);$cast($b,1,8,0);
$expire(1);$result=$service->closeRound(1,1,1);
$check(!$result['completed']&&$result['ties']===1,'Tied matchup pauses advancement');
$phase=DB::row('select * from battle_matchups where id=?',[$a['id']]);
$check((int)$phase['voting_phase']===1 && $phase['status']==='open','First tie starts re-battle');
$check(strtotime($phase['voting_closes_at'].' UTC')-strtotime($phase['voting_opens_at'].' UTC')===86400,'Re-battle lasts 24 hours');
$check(DB::row('select status from battle_matchups where id=?',[$b['id']])['status']==='completed','Untied winner remains recorded');
$check((int)DB::row('select count(*) n from battle_votes where matchup_id=? and voting_phase=0',[$a['id']])['n']===2,'Original votes retained');
$check($service->userVotes(1,6)===[],'Original vote does not block a re-battle vote');
$bracket=$service->bracket(1);$visible=$bracket[0]['matchups'][0];
$check((int)$visible['participant_one_votes']===0&&(int)$visible['participant_two_votes']===0,'Re-battle displays fresh counts');
$mailCount=(int)DB::row('select count(*) n from email_messages')['n'];
$service->processSchedule();
$check((int)DB::row('select count(*) n from email_messages')['n']===$mailCount,'Worker retry does not repeat tie notices');
$denied(fn()=>$cast($a,1,6,0),'Voting is not open');
$cast($a,1,6,1);$denied(fn()=>$cast($a,1,6,1),'already voted');$cast($a,2,7,1);
$check(count($service->userVotes(1,6))===1,'Current phase vote is shown');
$expire(1);$service->closeRound(1,1,1);
$phase=DB::row('select * from battle_matchups where id=?',[$a['id']]);
$check((int)$phase['voting_phase']===2&&$phase['voting_closes_at']===null,'Second tie opens overtime without a fixed deadline');
$check(DB::row('select voting_closes_at from battle_rounds where battle_id=1 and round_number=1')['voting_closes_at']===null,'Scheduler waits for overtime vote');
$check((int)DB::row('select count(*) n from battle_votes where matchup_id=?',[$a['id']])['n']===4,'Both previous vote histories retained');
$denied(fn()=>$cast($a,1,8,1),'Voting is not open');
$denied(fn()=>$service->vote((int)$a['id'],999999,9,'198.51.100.9','Invalid choice',2),'not part');
DB::exec('update users set status="disabled" where id=10');
$denied(fn()=>$cast($a,1,10,2),'active Creative Moth account');
$denied(fn()=>$service->resolveTie((int)$a['id'],(int)$a['participant_one_id'],1),'Admins cannot select');
// Concurrent new votes compete under the same event/round/matchup locks.
$barrier=sys_get_temp_dir().'/cm-tie-'.bin2hex(random_bytes(8));$jobs=[];
try{
 foreach([[6,1],[7,2]] as [$user,$side]){
  $pipes=[];
  $process=proc_open([PHP_BINARY,__DIR__.'/helpers/BattleOvertimeVoteProbe.php',(string)$a['id'],(string)$a[$side===1?'participant_one_id':'participant_two_id'],(string)$user,$barrier],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
  if(!is_resource($process))throw new RuntimeException('Unable to start concurrency probe.');
  fclose($pipes[0]);$jobs[]=[$process,$pipes,$user];
 }
 $until=microtime(true)+10;
 while((!is_file($barrier.'.6')||!is_file($barrier.'.7'))&&microtime(true)<$until)usleep(10000);
 if(!is_file($barrier.'.6')||!is_file($barrier.'.7'))throw new RuntimeException('Concurrency probes did not become ready.');
 touch($barrier);$answers=[];
 foreach($jobs as [$process,$pipes,$user]){
  $out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$code=proc_close($process);
  if($code!==0)throw new RuntimeException('Vote probe failed: '.$err);
  $answers[]=json_decode($out,true,512,JSON_THROW_ON_ERROR);
 }
 $jobs=[];
 $check(count(array_filter($answers,fn($x)=>$x['accepted']))===1,'Simultaneous overtime votes produce exactly one winner');
 $loser=array_values(array_filter($answers,fn($x)=>!$x['accepted']))[0];
 $check(str_contains($loser['message'],'Voting is not open'),'Second concurrent vote is rejected as closed');
}finally{
 foreach($jobs as [$process,$pipes,$user]){if(is_resource($process)){proc_terminate($process);proc_close($process);}}
 foreach([$barrier,$barrier.'.6',$barrier.'.7'] as $file)if(is_file($file))unlink($file);
}
$winner=DB::row('select * from battle_matchups where id=?',[$a['id']]);
$overtime=DB::rows('select * from battle_votes where matchup_id=? and voting_phase=2',[$a['id']]);
$check(count($overtime)===1&&(int)$winner['winner_participant_id']===(int)$overtime[0]['participant_id'],'Winner is the first accepted overtime vote');
$check(DB::row('select status from battle_rounds where battle_id=1 and round_number=1')['status']==='completed','Round advances after every matchup has a winner');
$next=DB::row('select * from battle_rounds where battle_id=1 and round_number=2');
$check(strtotime($next['voting_opens_at'].' UTC')>time()&&str_contains(Battle::eastern($next['voting_opens_at']),'12:00 PM'),'After overtime next round starts at the next Detroit noon');
$denied(fn()=>$cast($a,1,11,2),'Voting is not open');
$ledger=$service->voterLedger(1);$check(count(array_unique(array_column($ledger,'voting_phase')))===3,'Admin history exposes all three phases');
// A second event proves simultaneous overtime matchups wait for each other.
DB::exec('delete from battle_vote_attempts');
[$c,$d]=$setup(2);$expire(2);$service->closeRound(2,1,1);
$check((int)DB::row('select count(*) n from battle_matchups where battle_id=2 and voting_phase=1')['n']===2,'Zero-vote ties also re-battle');
$expire(2);$service->closeRound(2,1,1);
$cast($c,1,6,2);
$check(DB::row('select status from battle_rounds where battle_id=2 and round_number=1')['status']==='open','First overtime winner does not advance unfinished round');
$cast($d,2,7,2);
$check(DB::row('select status from battle_rounds where battle_id=2 and round_number=1')['status']==='completed','Last overtime winner completes the round');
// A re-battle with a clear winner must not enter overtime.
[$e,$f]=$setup(3);$cast($f,1,8,0);$expire(3);$service->closeRound(3,1,1);$cast($e,2,9,1);$expire(3);$service->closeRound(3,1,1);
$check((int)DB::row('select winner_participant_id from battle_matchups where id=?',[$e['id']])['winner_participant_id']===(int)$e['participant_two_id'],'Clear re-battle result wins without overtime');
$check((int)DB::row('select count(*) n from battle_matchups where battle_id=3 and voting_phase=2')['n']===0,'Clear re-battle creates no overtime');
// Account merge must retain votes from different phases.
$fixture('battle_votes',['battle_id'=>3,'matchup_id'=>$e['id'],'user_id'=>11,'participant_id'=>$e['participant_one_id'],'ip_address'=>'198.51.100.11','user_agent'=>'merge','fingerprint_hash'=>hash('sha256','merge11'),'voting_phase'=>0]);
$fixture('battle_votes',['battle_id'=>3,'matchup_id'=>$e['id'],'user_id'=>12,'participant_id'=>$e['participant_two_id'],'ip_address'=>'198.51.100.12','user_agent'=>'merge','fingerprint_hash'=>hash('sha256','merge12'),'voting_phase'=>1]);
$merge=new ReflectionMethod(\App\Services\AccountMergeService::class,'moveBattleVotes');$merge->setAccessible(true);
DB::begin();try{$merge->invoke(new \App\Services\AccountMergeService(),11,12);DB::commit();}catch(Throwable $error){DB::rollBack();throw $error;}
$check((int)DB::row('select count(*) n from battle_votes where matchup_id=? and user_id=12',[$e['id']])['n']===2,'Account merge preserves different-phase votes');

// Championship overtime issues the existing fixed prize exactly once.
DB::exec('delete from battle_vote_attempts');
DB::exec('update battle_rounds set status="open",voting_opens_at=date_sub(utc_timestamp(),interval 60 second),voting_closes_at=date_sub(utc_timestamp(),interval 1 second) where battle_id=3 and round_number=2');
DB::exec('update battle_matchups set status="open",voting_opens_at=date_sub(utc_timestamp(),interval 60 second),voting_closes_at=date_sub(utc_timestamp(),interval 1 second) where battle_id=3 and round_number=2');
$service->closeRound(3,2,1);
DB::exec('update battle_rounds set voting_closes_at=date_sub(utc_timestamp(),interval 1 second) where battle_id=3 and round_number=2');
DB::exec('update battle_matchups set voting_closes_at=date_sub(utc_timestamp(),interval 1 second) where battle_id=3 and round_number=2');
$service->closeRound(3,2,1);
$final=DB::row('select * from battle_matchups where battle_id=3 and round_number=2');
$cast($final,1,6,2);
$check(DB::row('select status from battle_events where id=3')['status']==='completed','Overtime championship completes the tournament');
$prizes=DB::rows('select * from advertising_credit_transactions where idempotency_key=?',['battle:3:champion:advertising-credit:100']);
$check(count($prizes)===1 && (string)$prizes[0]['amount']==='100.00','Champion receives exactly $100 Advertising Credit');
$denied(fn()=>$cast($final,2,7,2),'Voting is not open');
$check((int)DB::row('select count(*) n from advertising_credit_transactions where idempotency_key=?',['battle:3:champion:advertising-credit:100'])['n']===1,'Repeat vote cannot repeat the prize');
echo "PASS: $checks Battle re-battle/overtime checks\n";
