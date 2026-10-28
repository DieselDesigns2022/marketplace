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


use App\Services\PrizeWheelService as Wheel;
use App\Services\GrandOpeningSaleService as Sale;
use App\Services\GrandOpeningPassportService as Passport;
$scheduled=['is_enabled'=>1,'starts_at'=>'2026-11-01 04:00:00','ends_at'=>'2026-11-09 05:00:00'];
foreach([
 ['2026-11-01 03:59:59','upcoming'],['2026-11-01 04:00:00','live'],
 ['2026-11-01 05:59:59','live'],['2026-11-01 06:00:00','live'],
 ['2026-11-09 04:59:00','live'],['2026-11-09 04:59:59','live'],['2026-11-09 05:00:00','ended']
] as [$at,$state]){
 $time=strtotime($at.' UTC');
 $check(Wheel::promotionState($scheduled,$time)===$state,'Wheel boundary '.$at);
 $check(Sale::state($scheduled,$time)===$state,'Sale boundary '.$at);
}
$check(Sale::utcInput('2026-11-01T00:00')===$scheduled['starts_at'],'Detroit midnight accounts for daylight time');
$check(Sale::utcInput('2026-11-09T00:00')===$scheduled['ends_at'],'Closing boundary includes full 11:59 p.m. EST minute');
$check(Wheel::promotionState(['is_enabled'=>1])==='live','Unscheduled future promotions keep manual controls');
$check(Wheel::promotionState(['is_enabled'=>0])==='draft','Manual OFF stays off');
$paused=$scheduled;$paused['is_enabled']=0;
$check(Wheel::promotionState($paused,strtotime('2026-11-02 UTC'))==='draft','Master switch can pause a scheduled wheel');
$fixture('users',['id'=>1,'email'=>'admin@example.invalid','name'=>'Admin','role'=>'admin','status'=>'active','referral_code'=>'AUTO1']);
$fixture('users',['id'=>2,'email'=>'seller@example.invalid','name'=>'Seller','role'=>'designer','status'=>'active','referral_code'=>'AUTO2']);
$fixture('users',['id'=>3,'email'=>'buyer@example.invalid','name'=>'Buyer','role'=>'buyer','status'=>'active','referral_code'=>'AUTO3']);
$fixture('designers',['id'=>2,'user_id'=>2,'display_name'=>'Fixture Store','store_slug'=>'fixture-store','status'=>'approved','prize_wheel_opt_in'=>1]);
$fixture('products',['id'=>1,'designer_id'=>2,'title'=>'Fixture product','slug'=>'fixture-product']);
$start=gmdate('Y-m-d H:i:s',time()-600);$end=gmdate('Y-m-d H:i:s',time()+600);
$fixture('prize_wheel_settings',['id'=>1,'is_enabled'=>1,'updated_by'=>1,'campaign_key'=>'fixture-campaign','campaign_started_at'=>$start,'starts_at'=>$start,'ends_at'=>$end,'minimum_purchase_cents'=>500]);
$fixture('grand_opening_events',['id'=>1,'campaign_key'=>'grand-opening-2026','is_enabled'=>1,'starts_at'=>$start,'ends_at'=>$end]);
$fixture('prize_wheel_prizes',['id'=>1,'title'=>'Giveaway entry','prize_type'=>'grand_opening_entry','weight'=>1,'sort_order'=>0,'is_active'=>1,'created_by'=>1]);
$wheel=new Wheel();$store=DB::row('select * from designers where id=2');
$check($wheel->globallyEnabled()&&$wheel->storefrontVisible($store),'Live scheduled wheel is visible on an opted-in store');
$store['prize_wheel_opt_in']=0;$check(!$wheel->storefrontVisible($store),'Seller opt-out still takes priority');
$paidDates=[gmdate('Y-m-d H:i:s',strtotime($start.' UTC')-1),$start,gmdate('Y-m-d H:i:s',strtotime($end.' UTC')-1),$end];
foreach($paidDates as $i=>$paid){
 $id=$i+1;
 $fixture('orders',['id'=>$id,'user_id'=>3,'payment_status'=>'paid','stripe_paid_amount'=>'10.00','tax_amount'=>'0.00','paid_at'=>$paid]);
 $fixture('order_items',['id'=>$id,'order_id'=>$id,'product_id'=>1,'designer_id'=>2,'total_price'=>'10.00','prize_credit_applied'=>'0.00']);
}
$ids=array_map('intval',array_column($wheel->eligibleOrders(2,3),'order_id'));
$check($ids===[2,3],'Purchase eligibility includes start and excludes the closing boundary');
$check($wheel->unlockedStoresForOrder(1,3)===[],'Pre-event order does not show an unlocked wheel');
$check($wheel->unlockedStoresForOrder(4,3)===[],'Post-event order does not show an unlocked wheel');
$spin=$wheel->spin(2,3);
$check((int)$spin['qualifying_order_id']===2,'Live spin uses the eligible order');
$check((int)DB::row('select count(*) n from grand_opening_giveaway_entries where source_type="prize_wheel"')['n']===1,'Live wheel award still works');
// Existing passport stamps survive both gates. Only entry creation is date-limited.
foreach(array_keys((new ReflectionClass(Passport::class))->getConstant('STAMPS')) as $stamp)$fixture('grand_opening_passport_stamps',['user_id'=>3,'stamp_key'=>$stamp]);
$stampCount=(int)DB::row('select count(*) n from grand_opening_passport_stamps')['n'];
$award=new ReflectionMethod(Passport::class,'awardGiveawayEntry');$award->setAccessible(true);$passport=new Passport();
DB::exec('update grand_opening_events set starts_at=date_add(utc_timestamp(),interval 1 day),ends_at=date_add(utc_timestamp(),interval 8 day) where id=1');
$award->invoke($passport,3);
$check((int)DB::row('select count(*) n from grand_opening_giveaway_entries where source_type="passport"')['n']===0,'Passport entry cannot be awarded before opening');
DB::exec('update grand_opening_events set starts_at=?,ends_at=? where id=1',[$start,$end]);
$award->invoke($passport,3);$award->invoke($passport,3);
$check((int)DB::row('select count(*) n from grand_opening_giveaway_entries where source_type="passport"')['n']===1,'Passport awards exactly one entry while live');
DB::exec('update grand_opening_events set ends_at=date_sub(utc_timestamp(),interval 1 second) where id=1');
$award->invoke($passport,2);
$check((int)DB::row('select count(*) n from grand_opening_giveaway_entries where source_type="passport"')['n']===1,'Passport creates no new entries after closing');
$check((int)DB::row('select count(*) n from grand_opening_passport_stamps')['n']===$stampCount,'All existing passport stamps preserved');
$before=(int)DB::row('select count(*) n from prize_wheel_spins')['n'];
foreach(['future','ended','disabled'] as $state){
 if($state==='future')DB::exec('update prize_wheel_settings set starts_at=date_add(utc_timestamp(),interval 1 day),ends_at=date_add(utc_timestamp(),interval 8 day) where id=1');
 elseif($state==='ended')DB::exec('update prize_wheel_settings set starts_at=date_sub(utc_timestamp(),interval 8 day),ends_at=date_sub(utc_timestamp(),interval 1 second) where id=1');
 else DB::exec('update prize_wheel_settings set starts_at=?,ends_at=?,is_enabled=0 where id=1',[$start,$end]);
 $check(!$wheel->globallyEnabled(),'Wheel not visible when '.$state);
 $check($wheel->remainingSpins(2,3)===0,'No available spins displayed when '.$state);
 $check($wheel->unlockedStoresForOrder(2,3)===[],'No order unlock displayed when '.$state);
 $denied(fn()=>$wheel->spin(2,3),'not currently active');
}
$check((int)DB::row('select count(*) n from prize_wheel_spins')['n']===$before,'Rejected spins do not consume orders or prizes');
$new=$wheel->startNewRound(1);$newSettings=$wheel->settings();
$check($newSettings['starts_at']===null&&$newSettings['ends_at']===null&&$newSettings['campaign_key']===$new,'Future new promotion can use its own schedule');
echo "PASS: $checks automatic-launch checks\n";
