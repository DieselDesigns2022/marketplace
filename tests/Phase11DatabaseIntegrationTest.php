<?php

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database as DB;
use App\Services\CreditService;
use App\Services\OrderFinalizationService;
use App\Services\PlatformCreditPayoutService;
use App\Services\ReferralService;
use App\Services\StripeService;

$failures = [];
$check = function (bool $condition, string $message) use (&$failures): void {
    echo ($condition ? 'PASS' : 'FAIL') . ": $message\n";
    if (!$condition) $failures[] = $message;
};

try {
    $pdo = DB::pdo();
} catch (Throwable $error) {
    echo "SKIP: disposable MariaDB is unavailable: " . $error->getMessage() . "\n";
    exit(0);
}

if (($_ENV['PHASE11_ALLOW_FIXTURE'] ?? getenv('PHASE11_ALLOW_FIXTURE') ?: '') !== '1') {
    echo "SKIP: set PHASE11_ALLOW_FIXTURE=1 with disposable-database privileges to run migration/rerun integration.\n";
    exit(0);
}
$originalDatabase = (string)$pdo->query('select database()')->fetchColumn();
$fixture = 'phase11_fixture_' . bin2hex(random_bytes(5));
$canonicalFixture = $fixture . '_canonical';
$pdo->exec('create database `' . $fixture . '` character set utf8mb4 collate utf8mb4_unicode_ci');
$pdo->exec('use `' . $fixture . '`');
try {
    $pdo->exec("create table users(id bigint primary key auto_increment,name varchar(120),email varchar(190),password_hash varchar(255),role enum('buyer','designer','admin'),status enum('active','disabled') default 'active',referral_code varchar(40),created_at timestamp default current_timestamp);create table orders(id bigint primary key auto_increment,user_id bigint,status varchar(40),payment_status varchar(60),subtotal decimal(10,2),coupon_discount decimal(10,2),tax_amount decimal(10,2),credits_applied decimal(10,2),total decimal(10,2),manual_review_required tinyint default 0,stripe_charge_id varchar(255),refunded_at timestamp null,partially_refunded_at timestamp null,created_at timestamp default current_timestamp);create table order_items(id bigint primary key auto_increment,order_id bigint,designer_id bigint,product_id bigint,seller_payout_status varchar(60),stripe_transfer_id varchar(255),stripe_transfer_error text);create table referrals(id bigint primary key auto_increment,referrer_user_id bigint,referred_user_id bigint,referred_designer_id bigint,referral_type enum('buyer','designer'),status enum('pending','approved','eligible') default 'pending',reward_status enum('pending','active','inactive') default 'pending',sales_count int default 0,estimated_earnings decimal(10,2) default 0,created_at timestamp default current_timestamp,updated_at timestamp default current_timestamp);create table marketplace_credits(id bigint primary key auto_increment,user_id bigint unique,balance decimal(10,2) default 0,created_at timestamp default current_timestamp,updated_at timestamp default current_timestamp);create table credit_transactions(id bigint primary key auto_increment,user_id bigint,amount decimal(10,2),type varchar(40),description text,created_at timestamp default current_timestamp,updated_at timestamp default current_timestamp);create table designers(id bigint primary key auto_increment,user_id bigint,status varchar(40),stripe_connect_account_id varchar(255),stripe_details_submitted tinyint default 0,stripe_payouts_enabled tinyint default 0);create table seller_payouts(id bigint primary key auto_increment,order_id bigint not null,designer_id bigint not null,gross_amount decimal(10,2),platform_commission_amount decimal(10,2),seller_payout_amount decimal(10,2),currency varchar(10),payout_status varchar(60),stripe_transfer_id varchar(255),stripe_transfer_error text,admin_resolved_at timestamp null,admin_resolved_by bigint null,admin_resolution_note varchar(500),created_at timestamp default current_timestamp,updated_at timestamp default current_timestamp on update current_timestamp,unique key seller_payouts_order_designer_phase10(order_id,designer_id));create table admin_logs(id bigint primary key auto_increment,admin_user_id bigint,action varchar(120),entity_type varchar(120),entity_id bigint,metadata text,created_at timestamp default current_timestamp);");
    $pdo->exec("alter table orders add payment_provider varchar(40),add payment_processor varchar(40),add payment_mode varchar(40),add coupon_id bigint null,add coupon_code varchar(80),add tax_status varchar(40) default 'pending',add tax_provider varchar(40),add tax_snapshot text,add tax_collected_at timestamp null,add stripe_currency varchar(10) default 'usd',add paid_at timestamp null;alter table order_items add total_price decimal(10,2) default 0,add commission_rate decimal(8,6) default 0.18,add platform_commission_amount decimal(10,2) default 0,add seller_payout_amount decimal(10,2) default 0,add fulfillment_type varchar(40) default 'downloadable',add manual_delivery_status varchar(60) default 'not_applicable',add coupon_discount decimal(10,2) default 0,add paid_at timestamp null,add payout_ready_at timestamp null;create table coupons(id bigint primary key auto_increment,code varchar(80),scope varchar(40) default 'platform',seller_id bigint null,usage_count int default 0);create table coupon_usages(id bigint primary key auto_increment,coupon_id bigint,user_id bigint,order_id bigint,code_snapshot varchar(80),discount_amount decimal(10,2),unique key coupon_order(coupon_id,order_id));create table seller_earnings(id bigint primary key auto_increment,order_id bigint,product_id bigint,designer_id bigint,buyer_id bigint,gross_sale decimal(10,2),marketplace_commission decimal(10,2),seller_earning decimal(10,2),status varchar(60));create table platform_commissions(id bigint primary key auto_increment,order_id bigint,product_id bigint,designer_id bigint,gross_sale decimal(10,2),commission_amount decimal(10,2),unique key commission_once(order_id,product_id,designer_id));create table notifications(id bigint primary key auto_increment,user_id bigint,notification_type varchar(80),audience varchar(40),title varchar(190),message text,action_url varchar(500),event_key varchar(190) unique);create table email_queue(id bigint primary key auto_increment);");
    $pdo->exec("insert into users(id,name,email,password_hash,role,status,referral_code) values (1,'Legacy ref','legacy-ref@example.test','x','buyer','active','DUPLICATE'),(2,'Legacy buyer','legacy-buyer@example.test','x','buyer','active','DUPLICATE');insert into referrals(id,referrer_user_id,referred_user_id,referral_type,status,reward_status) values(1,1,2,'buyer','eligible','active');insert into marketplace_credits(user_id,balance) values(1,10.00);insert into credit_transactions(id,user_id,amount,type,description) values(1,1,10.00,'legacy','legacy grant');");
    $migration = file_get_contents(dirname(__DIR__) . '/database/migrations/2026_07_31_phase_11_referrals_credits_store_credit.sql');
    $pdo->exec('insert into marketplace_credits(user_id,balance) values (null,1.00)');
    try { $pdo->exec($migration); $nullUserRejected=false; } catch (PDOException) { $nullUserRejected=true; }
    $pdo->exec('delete from marketplace_credits where user_id is null');
    $pdo->exec("insert into credit_transactions(user_id,amount,type,description) values (999999,1.00,'legacy','orphan fixture')");
    try { $pdo->exec($migration); $orphanUserRejected=false; } catch (PDOException) { $orphanUserRejected=true; }
    $pdo->exec('delete from credit_transactions where user_id=999999');
    $pdo->exec("insert into credit_transactions(user_id,amount,type,description) values (1,1.00,null,'null type fixture')");
    try { $pdo->exec($migration); $nullTypeRejected=false; } catch (PDOException) { $nullTypeRejected=true; }
    $pdo->exec('delete from credit_transactions where type is null');
    $check($nullUserRejected && $orphanUserRejected && $nullTypeRejected, 'migration rejects null/orphan credit actors and null transaction types before changing legacy data');
    $pdo->exec($migration);
    $snapshot = static function (PDO $pdo): string {
        $schema = $pdo->query("select table_name,column_name,column_type,is_nullable,column_default from information_schema.columns where table_schema=database() order by table_name,ordinal_position")->fetchAll(PDO::FETCH_ASSOC);
        $indexes = $pdo->query("select table_name,index_name,column_name,non_unique from information_schema.statistics where table_schema=database() order by table_name,index_name,seq_in_index")->fetchAll(PDO::FETCH_ASSOC);
        $foreignKeys = $pdo->query("select table_name,constraint_name,column_name,referenced_table_name,referenced_column_name from information_schema.key_column_usage where table_schema=database() and referenced_table_name is not null order by table_name,constraint_name,ordinal_position")->fetchAll(PDO::FETCH_ASSOC);
        $data = ['referrals'=>$pdo->query('select * from referrals order by id')->fetchAll(PDO::FETCH_ASSOC),'credits'=>$pdo->query('select * from marketplace_credits order by id')->fetchAll(PDO::FETCH_ASSOC),'ledger'=>$pdo->query('select * from credit_transactions order by id')->fetchAll(PDO::FETCH_ASSOC)];
        return hash('sha256', json_encode([$schema,$indexes,$foreignKeys,$data], JSON_THROW_ON_ERROR));
    };
    $first = $snapshot($pdo);
    $pdo->exec($migration);
    $second = $snapshot($pdo);
    $pdo->exec($migration);
    $third = $snapshot($pdo);
    $check($first === $second && $second === $third, 'migration second and third runs preserve schema and data');
    $phase11Structure = static function(PDO $pdo): array {
        $columns=$pdo->query("select table_name,column_name,column_type,is_nullable,column_default from information_schema.columns where table_schema=database() and ((table_name='referrals' and column_name in ('buyer_status','seller_status','buyer_qualifying_order_id','seller_qualifying_order_id','seller_qualifying_order_item_id','buyer_referrer_reward_amount','buyer_referred_reward_amount','seller_referrer_reward_amount','seller_referred_reward_amount','buyer_reward_event_key','seller_reward_event_key')) or (table_name='marketplace_credits') or (table_name='credit_transactions') or (table_name='orders' and column_name in ('credit_reserved','credit_payment_status','internally_completed','stripe_paid_amount','tax_calculation_id','tax_transaction_id','tax_transaction_status','billing_address_snapshot','finalization_key','finalized_at')) or (table_name='seller_payouts' and column_name in ('platform_credit_attempt_key','platform_credit_settled_at','platform_credit_settled_by'))) order by table_name,column_name")->fetchAll(PDO::FETCH_ASSOC);
        $indexes=$pdo->query("select table_name,index_name,column_name,non_unique,seq_in_index from information_schema.statistics where table_schema=database() and (index_name like 'referrals_%' or index_name in ('credit_transactions_idempotency_unique','credit_transactions_user_created_idx','credit_transactions_order_idx','credit_transactions_referral_idx','credit_transactions_related_idx','orders_finalization_key_unique','seller_payouts_platform_credit_attempt_unique')) order by table_name,index_name,seq_in_index")->fetchAll(PDO::FETCH_ASSOC);
        $foreignKeys=$pdo->query("select table_name,constraint_name,column_name,referenced_table_name,referenced_column_name from information_schema.key_column_usage where table_schema=database() and constraint_name like 'phase11_%' order by table_name,constraint_name,ordinal_position")->fetchAll(PDO::FETCH_ASSOC);
        return [$columns,$indexes,$foreignKeys];
    };
    $migratedStructure=$phase11Structure($pdo);
    $pdo->exec('create database `'.$canonicalFixture.'` character set utf8mb4 collate utf8mb4_unicode_ci');
    $pdo->exec('use `'.$canonicalFixture.'`');
    $pdo->exec(file_get_contents(dirname(__DIR__).'/database/schema.sql'));
    $canonicalStructure=$phase11Structure($pdo);
    $pdo->exec('use `'.$fixture.'`');
    $check($migratedStructure===$canonicalStructure,'migrated Phase 11 column types, nullability, defaults, indexes, uniqueness, and foreign keys match canonical schema');
    $legacy = $pdo->query('select * from referrals where id=1')->fetch(PDO::FETCH_ASSOC);
    $check($legacy['status']==='qualified' && $legacy['reward_status']==='rewarded' && $legacy['buyer_status']==='rewarded', 'legacy qualified/rewarded referral migrates once and remains stable');
    $legacyLedger = $pdo->query('select * from credit_transactions where id=1')->fetch(PDO::FETCH_ASSOC);
    $check($legacyLedger['status']==='finalized' && $legacyLedger['idempotency_key']==='legacy-credit:1', 'legacy ledger status and key remain stable');

    $suffix = bin2hex(random_bytes(6));
    $pdo->prepare('insert into users(name,email,password_hash,role,status,referral_code) values (?,?,?,?,?,?)')->execute(['Phase 11 admin','phase11-admin-'.$suffix.'@example.test','x','admin','active','AMADMIN'.$suffix]);
    $adminId = (int)$pdo->lastInsertId();

    // Genuine service/database tests run against the migrated disposable fixture and roll back their rows.
    $pdo->beginTransaction();
    $pdo->prepare('insert into users(name,email,password_hash,role,status,referral_code) values (?,?,?,?,?,?)')->execute(['Referrer','referrer-'.$suffix.'@example.test','x','buyer','active','AMREF'.$suffix]);
    $referrer = (int)$pdo->lastInsertId();
    $pdo->prepare('insert into users(name,email,password_hash,role,status,referral_code) values (?,?,?,?,?,?)')->execute(['Referred','referred-'.$suffix.'@example.test','x','buyer','active','AMNEW'.$suffix]);
    $referred = (int)$pdo->lastInsertId();
    $pdo->prepare('insert into orders(user_id,status,payment_status,subtotal,coupon_discount,tax_amount,credits_applied,total) values (?,"pending","pending",10.00,0.00,0.00,0.00,10.00)')->execute([$referrer]);
    $orderId = (int)$pdo->lastInsertId();
    $credits = new CreditService();
    $credits->adjust($referrer, '10.00', 'test:admin:'.$suffix, ['admin_user_id'=>$adminId,'description'=>'Integration test grant']);
    $check($credits->balances($referrer)['available_cents'] === 1000, 'positive adjustment changes exact balance');
    $reserved = $credits->reserve($referrer, '7.00', $orderId, 'test:reserve:'.$suffix);
    $check($reserved === '7.00' && $credits->balances($referrer)['reserved_cents'] === 700, 'reservation updates reserved balance');
    $check($credits->reserve($referrer, '7.00', $orderId, 'test:reserve:'.$suffix) === '7.00', 'reservation replay is idempotent');
    $credits->releaseReservation($referrer, $orderId, 'test:release:'.$suffix);
    $ledger = $credits->ledger($referrer, 20);
    $reservation = array_values(array_filter($ledger, fn($row) => $row['type'] === 'reservation'))[0];
    $release = array_values(array_filter($ledger, fn($row) => $row['type'] === 'release'))[0];
    $check((int)$release['related_transaction_id'] === (int)$reservation['id'], 'release is a separate linked append-only entry');
    try { $credits->adjust($referrer, '-11.00', 'test:negative:'.$suffix, ['admin_user_id'=>$adminId,'description'=>'Must fail']); $negativeRejected=false; } catch (DomainException) { $negativeRejected=true; }
    $check($negativeRejected, 'negative available balance is rejected');
    $referrals = new ReferralService($credits);
    $attachment = $referrals->attach($referred, 'AMREF'.$suffix, 'buyer');
    $check($attachment > 0, 'buyer referral attachment persists');
    $check($referrals->attach($referred, 'AMREF'.$suffix, 'seller') === $attachment, 'seller intent retains same attachment');
    $pdo->prepare('insert into users(name,email,password_hash,role,status,referral_code) values (?,?,?,?,?,?)')->execute(['Conflict','conflict-'.$suffix.'@example.test','x','buyer','active','AMCONFLICT'.$suffix]);
    $conflictingReferrer = (int)$pdo->lastInsertId();
    try { $referrals->attach($referred, 'AMCONFLICT'.$suffix, 'seller'); $conflictRejected=false; } catch (DomainException) { $conflictRejected=true; }
    $check($conflictRejected, 'one immutable referrer rejects a conflicting buyer/seller attachment');
    $pdo->rollBack();

    // Actual credit mutation/replay/race behavior. Each terminal operation uses the real service.
    $pdo->beginTransaction();
    $pdo->prepare('insert into users(name,email,password_hash,role,status,referral_code) values (?,?,?,?,?,?)')->execute(['Credit user','credit-'.$suffix.'@example.test','x','buyer','active','AMCREDIT'.$suffix]);
    $creditUser = (int)$pdo->lastInsertId();
    $pdo->prepare('insert into orders(user_id,status,payment_status,subtotal,coupon_discount,tax_amount,credits_applied,total) values (?,' . $pdo->quote('pending') . ',' . $pdo->quote('pending') . ',10,0,0,0,10)')->execute([$creditUser]);
    $creditOrder = (int)$pdo->lastInsertId();
    $credits->adjust($creditUser,'10.00','race:grant:'.$suffix,['admin_user_id'=>$adminId,'description'=>'Race fixture']);
    $check($credits->reserve($creditUser,'10.00',$creditOrder,'race:reserve:'.$suffix)==='10.00','first reservation spends available credit');
    $check($credits->reserve($creditUser,'10.00',$creditOrder,'race:reserve:'.$suffix)==='10.00' && count(array_filter($credits->ledger($creditUser),fn($r)=>$r['type']==='reservation'))===1,'reservation replay creates no duplicate ledger row');
    $reservedLedgerCount=count($credits->ledger($creditUser));
    try{$credits->adjust($creditUser,'-0.01','race:reserved-negative:'.$suffix,['admin_user_id'=>$adminId]);$reservedInvariantRejected=false;}catch(DomainException){$reservedInvariantRejected=true;}
    $check($reservedInvariantRejected && count($credits->ledger($creditUser))===$reservedLedgerCount,'adjustment cannot make total lower than reserved or create negative available balance');
    $check($credits->finalizeReservation($creditUser,$creditOrder,'race:redeem:'.$suffix),'redemption succeeds once');
    $check(!$credits->finalizeReservation($creditUser,$creditOrder,'race:redeem:'.$suffix) && !$credits->releaseReservation($creditUser,$creditOrder,'race:release-loser:'.$suffix),'duplicate redemption and losing release cannot spend or release twice');
    $check($credits->balances($creditUser)===['total_cents'=>0,'reserved_cents'=>0,'available_cents'=>0,'total'=>'0.00','reserved'=>'0.00','available'=>'0.00'],'finalized credit balance remains nonnegative');
    $beforeFailedLedger=count($credits->ledger($creditUser));
    try{$credits->adjust($creditUser,'-0.01','race:guard-fail:'.$suffix,['admin_user_id'=>$adminId]);$guardRejected=false;}catch(DomainException){$guardRejected=true;}
    $check($guardRejected && count($credits->ledger($creditUser))===$beforeFailedLedger,'failed guarded update writes no ledger entry');
    $pdo->rollBack();

    $pdo->prepare('insert into users(name,email,password_hash,role,status,referral_code) values (?,?,?,?,?,?)')->execute(['Concurrent credit','concurrent-'.$suffix.'@example.test','x','buyer','active','AMCONCURRENT'.$suffix]);
    $concurrentUser=(int)$pdo->lastInsertId();
    $pdo->prepare('insert into orders(user_id,status,payment_status,subtotal,coupon_discount,tax_amount,credits_applied,total) values (?,' . $pdo->quote('pending') . ',' . $pdo->quote('pending') . ',10,0,0,0,10),(?,' . $pdo->quote('pending') . ',' . $pdo->quote('pending') . ',10,0,0,0,10)')->execute([$concurrentUser,$concurrentUser]);
    $concurrentOrderA=(int)$pdo->lastInsertId();
    $concurrentOrderB=$concurrentOrderA+1;
    $pdo->beginTransaction();$credits->grant($concurrentUser,'10.00','concurrent:grant:'.$suffix);$pdo->commit();
    $pdo->beginTransaction();
    $credits->reserve($concurrentUser,'10.00',$concurrentOrderA,'concurrent:reserve:a:'.$suffix);
    $environment=[
        'DB_HOST'=>(string)($_ENV['DB_HOST']??getenv('DB_HOST')?:'127.0.0.1'),
        'DB_NAME'=>$fixture,
        'DB_USER'=>(string)($_ENV['DB_USER']??getenv('DB_USER')?:'root'),
        'DB_PASS'=>(string)($_ENV['DB_PASS']??getenv('DB_PASS')?:''),
        'DB_CHARSET'=>(string)($_ENV['DB_CHARSET']??getenv('DB_CHARSET')?:'utf8mb4'),
    ];
    $command=[PHP_BINARY,__DIR__.'/helpers/Phase11CreditReservationProbe.php',(string)$concurrentUser,'10.00',(string)$concurrentOrderB,'concurrent:reserve:b:'.$suffix];
    $pipes=[];$process=proc_open($command,[1=>['pipe','w'],2=>['pipe','w']],$pipes,null,$environment);
    usleep(250000);
    $pdo->commit();
    $concurrentOutput=stream_get_contents($pipes[1]);$concurrentError=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$concurrentExit=proc_close($process);
    $check($concurrentExit===0 && trim($concurrentOutput)==='0.00' && $concurrentError==='','separate simultaneous service connections cannot reserve the same available credit twice');
    $check((int)$pdo->query("select count(*) from credit_transactions where user_id=$concurrentUser and type='reservation'")->fetchColumn()===1,'losing concurrent reservation creates no ledger entry');
    $pdo->prepare('insert into orders(user_id,status,payment_status,subtotal,coupon_discount,tax_amount,credits_applied,total) values (?,' . $pdo->quote('pending') . ',' . $pdo->quote('pending') . ',5,0,0,0,5)')->execute([$concurrentUser]);
    $terminalOrder=(int)$pdo->lastInsertId();
    $pdo->beginTransaction();$credits->grant($concurrentUser,'5.00','terminal:grant:'.$suffix);$credits->reserve($concurrentUser,'5.00',$terminalOrder,'terminal:reserve:'.$suffix);$pdo->commit();
    $pdo->beginTransaction();$credits->finalizeReservation($concurrentUser,$terminalOrder,'terminal:redeem:'.$suffix);
    $terminalCommand=[PHP_BINARY,__DIR__.'/helpers/Phase11CreditReservationProbe.php',(string)$concurrentUser,'0.00',(string)$terminalOrder,'terminal:release:'.$suffix,'release'];
    $terminalPipes=[];$terminalProcess=proc_open($terminalCommand,[1=>['pipe','w'],2=>['pipe','w']],$terminalPipes,null,$environment);
    usleep(250000);$pdo->commit();
    $terminalOutput=stream_get_contents($terminalPipes[1]);$terminalError=stream_get_contents($terminalPipes[2]);fclose($terminalPipes[1]);fclose($terminalPipes[2]);$terminalExit=proc_close($terminalProcess);
    $check($terminalExit===0 && trim($terminalOutput)==='false' && $terminalError==='','separate redemption/release race has one terminal winner');
    $check((int)$pdo->query("select count(*) from credit_transactions where order_id=$terminalOrder and type in ('redemption','release')")->fetchColumn()===1,'terminal race appends exactly one linked terminal ledger row');

    // Buyer and later seller rewards remain independent on one immutable relationship.
    $pdo->beginTransaction();
    $pdo->prepare('insert into users(name,email,password_hash,role,status,referral_code) values (?,?,?,?,?,?)')->execute(['Reward ref','reward-ref-'.$suffix.'@example.test','x','buyer','active','AMRREF'.$suffix]);
    $rewardRef=(int)$pdo->lastInsertId();
    $pdo->prepare('insert into users(name,email,password_hash,role,status,referral_code) values (?,?,?,?,?,?)')->execute(['Reward user','reward-user-'.$suffix.'@example.test','x','buyer','active','AMRUSER'.$suffix]);
    $rewardUser=(int)$pdo->lastInsertId();
    $relationship=$referrals->attach($rewardUser,'AMRREF'.$suffix,'seller');
    $pdo->prepare('insert into orders(user_id,status,payment_status,subtotal,coupon_discount,tax_amount,credits_applied,total,stripe_paid_amount,manual_review_required) values (?,' . $pdo->quote('paid') . ',' . $pdo->quote('paid') . ',10,0,0,0,10,10,0)')->execute([$rewardUser]);
    $buyerRewardOrder=(int)$pdo->lastInsertId();
    $check($referrals->qualifyBuyer($buyerRewardOrder,'reward:buyer:'.$suffix) && !$referrals->qualifyBuyer($buyerRewardOrder,'reward:buyer:'.$suffix),'buyer referral rewards once');
    $buyerSnapshot=$pdo->query('select buyer_reward_event_key,buyer_referrer_reward_amount,buyer_referred_reward_amount,buyer_qualifying_order_id from referrals where id='.$relationship)->fetch(PDO::FETCH_ASSOC);
    $pdo->prepare('insert into designers(user_id,status,stripe_connect_account_id,stripe_details_submitted,stripe_payouts_enabled) values (?,' . $pdo->quote('approved') . ',' . $pdo->quote('acct_reward') . ',1,1)')->execute([$rewardUser]);
    $rewardDesigner=(int)$pdo->lastInsertId();
    $pdo->prepare('insert into orders(user_id,status,payment_status,subtotal,coupon_discount,tax_amount,credits_applied,total,stripe_paid_amount,manual_review_required) values (?,' . $pdo->quote('paid') . ',' . $pdo->quote('paid') . ',20,0,0,0,20,20,0)')->execute([$rewardRef]);
    $sellerRewardOrder=(int)$pdo->lastInsertId();
    $pdo->prepare('insert into order_items(order_id,designer_id,product_id,total_price,commission_rate) values (?,?,1,10,.18),(?,?,2,10,.18)')->execute([$sellerRewardOrder,$rewardDesigner,$sellerRewardOrder,$rewardDesigner]);
    $check($referrals->qualifySeller($sellerRewardOrder,$rewardDesigner,'reward:seller:'.$suffix) && !$referrals->qualifySeller($sellerRewardOrder,$rewardDesigner,'reward:seller:'.$suffix),'multiple same-seller items and replay reward seller once');
    $rewardRow=$pdo->query('select * from referrals where id='.$relationship)->fetch(PDO::FETCH_ASSOC);
    $check($rewardRow['buyer_referrer_reward_amount']==='1.50' && $rewardRow['buyer_referred_reward_amount']==='1.50' && $rewardRow['seller_referrer_reward_amount']==='5.00' && $rewardRow['seller_referred_reward_amount']==='5.00','buyer and seller grant exact independent dual-party amounts');
    $check($rewardRow['buyer_reward_event_key']===$buyerSnapshot['buyer_reward_event_key'] && $rewardRow['buyer_qualifying_order_id']===$buyerSnapshot['buyer_qualifying_order_id'],'seller qualification does not overwrite buyer reward history');
    $grantRows=$pdo->query("select amount from credit_transactions where referral_id=$relationship and type='grant' order by amount")->fetchAll(PDO::FETCH_COLUMN);
    $check($grantRows===['1.50','1.50','5.00','5.00'],'ledger is financial source for both reward pairs exactly once');
    $pdo->rollBack();

    $pdo->beginTransaction();
    foreach ([
        ['failed','failed','0.00',0],
        ['cancelled','canceled','0.00',0],
        ['refunded','refunded','0.00',0],
        ['pending','pending','0.00',0],
        ['paid','paid','10.00',1],
        ['paid','paid','0.00',0],
    ] as $caseIndex=>$case) {
        [$status,$payment,$stripePaid,$manualReview]=$case;
        $pdo->prepare('insert into users(name,email,password_hash,role,status,referral_code) values (?,?,?,?,?,?)')->execute(['Eligibility ref','elig-ref-'.$caseIndex.'-'.$suffix.'@example.test','x','buyer','active','AMER'.$caseIndex.$suffix]);
        $eligRef=(int)$pdo->lastInsertId();
        $pdo->prepare('insert into users(name,email,password_hash,role,status,referral_code) values (?,?,?,?,?,?)')->execute(['Eligibility user','elig-user-'.$caseIndex.'-'.$suffix.'@example.test','x','buyer','active','AMEU'.$caseIndex.$suffix]);
        $eligUser=(int)$pdo->lastInsertId();
        $referrals->attach($eligUser,'AMER'.$caseIndex.$suffix,'buyer');
        $pdo->prepare('insert into orders(user_id,status,payment_status,subtotal,coupon_discount,tax_amount,credits_applied,total,stripe_paid_amount,manual_review_required) values (?,?,?,10,0,0,0,10,?,?)')->execute([$eligUser,$status,$payment,$stripePaid,$manualReview]);
        $check(!$referrals->qualifyBuyer((int)$pdo->lastInsertId(),'eligibility:'.$caseIndex.':'.$suffix),'failed/cancelled/refunded/unpaid/manual-review/credit-only buyer order does not qualify case '.$caseIndex);
    }
    $pdo->rollBack();

    $pdo->beginTransaction();
    $pdo->prepare('insert into users(name,email,password_hash,role,status,referral_code) values (?,?,?,?,?,?)')->execute(['Seller eligibility ref','seller-elig-ref-'.$suffix.'@example.test','x','buyer','active','AMSELR'.$suffix]);
    $sellerEligibilityRef=(int)$pdo->lastInsertId();
    $pdo->prepare('insert into users(name,email,password_hash,role,status,referral_code) values (?,?,?,?,?,?)')->execute(['Seller eligibility','seller-elig-'.$suffix.'@example.test','x','designer','active','AMSELU'.$suffix]);
    $sellerEligibilityUser=(int)$pdo->lastInsertId();
    $referrals->attach($sellerEligibilityUser,'AMSELR'.$suffix,'seller');
    $pdo->prepare('insert into designers(user_id,status) values (?,' . $pdo->quote('approved') . ')')->execute([$sellerEligibilityUser]);
    $sellerEligibilityDesigner=(int)$pdo->lastInsertId();
    foreach([['paid','paid',1,null],['refunded','refunded',0,date('Y-m-d H:i:s')],['cancelled','canceled',0,null]] as $sellerCase=>$state){
        $pdo->prepare('insert into orders(user_id,status,payment_status,subtotal,coupon_discount,tax_amount,credits_applied,total,manual_review_required,refunded_at) values (?,?,?,10,0,0,0,10,?,?)')->execute([$sellerEligibilityRef,$state[0],$state[1],$state[2],$state[3]]);
        $sellerIneligibleOrder=(int)$pdo->lastInsertId();
        $pdo->prepare('insert into order_items(order_id,designer_id,product_id,total_price,commission_rate) values (?,?,?,10,.18)')->execute([$sellerIneligibleOrder,$sellerEligibilityDesigner,100+$sellerCase]);
        $check(!$referrals->qualifySeller($sellerIneligibleOrder,$sellerEligibilityDesigner,'seller-ineligible:'.$sellerCase.':'.$suffix),'manual-review/refunded/cancelled seller sale does not qualify case '.$sellerCase);
    }
    $pdo->rollBack();

    // Shared finalizer: tax, credit, coupon, accounting, payout, fulfillment, referral, and replay.
    $oldStripeKey = $_ENV['STRIPE_SECRET_KEY'] ?? null;
    $_ENV['STRIPE_SECRET_KEY'] = 'sk_test_fixture';
    $taxCalls=[];
    StripeService::setTestTransport(function(string $method,string $path,array $params,?string $key) use (&$taxCalls):array {
        if($path==='/v1/tax/calculations')return ['id'=>'taxcalc_PARTIAL','tax_amount_exclusive'=>80,'tax_amount_inclusive'=>0,'amount_total'=>1080];
        if($path!=='/v1/tax/transactions/create_from_calculation') throw new RuntimeException('Unexpected finalizer Stripe request.');
        $taxCalls[$key]=($taxCalls[$key]??0)+1;
        return ['id'=>'tax_fixture_'.substr(hash('sha256',(string)$key),0,12)];
    });
    $partialTax=StripeService::calculateTax([['id'=>1,'total_price'=>'10.00']],['line1'=>'1 Main St','city'=>'Town','state'=>'CA','postal_code'=>'90210','country'=>'US'],'fixture:partial-tax:'.$suffix);
    $partialBreakdown=CreditService::checkoutBreakdown('10.00','0.00',CreditService::formatCents($partialTax['tax_cents']),'1.00',true);
    $check($partialTax['total_cents']===1080 && $partialBreakdown['final_cents']===980,'partial-credit order total is authoritative tax-before-credit amount');
    $pdo->beginTransaction();
    $pdo->prepare('insert into users(name,email,password_hash,role,status,referral_code) values (?,?,?,?,?,?)')->execute(['Internal buyer','internal-'.$suffix.'@example.test','x','buyer','active','AMIB'.$suffix]);
    $internalBuyer=(int)$pdo->lastInsertId();
    $pdo->prepare('insert into users(name,email,password_hash,role,status,referral_code) values (?,?,?,?,?,?)')->execute(['Seller ref','seller-ref-'.$suffix.'@example.test','x','buyer','active','AMSR'.$suffix]);
    $sellerRef=(int)$pdo->lastInsertId();
    $pdo->prepare('insert into users(name,email,password_hash,role,status,referral_code) values (?,?,?,?,?,?)')->execute(['Internal seller','internal-seller-'.$suffix.'@example.test','x','designer','active','AMIS'.$suffix]);
    $internalSeller=(int)$pdo->lastInsertId();
    $referrals->attach($internalSeller,'AMSR'.$suffix,'seller');
    $pdo->prepare('insert into designers(user_id,status,stripe_connect_account_id,stripe_details_submitted,stripe_payouts_enabled) values (?,' . $pdo->quote('approved') . ',' . $pdo->quote('acct_internal') . ',1,1)')->execute([$internalSeller]);
    $internalDesigner=(int)$pdo->lastInsertId();
    $pdo->exec("insert into coupons(code,scope,usage_count) values ('PHASE11','platform',0)");
    $couponId=(int)$pdo->lastInsertId();
    $pdo->prepare('insert into orders(user_id,status,payment_status,subtotal,coupon_discount,tax_amount,credits_applied,total,stripe_paid_amount,manual_review_required,tax_status,tax_calculation_id,tax_transaction_status,stripe_currency,coupon_id,coupon_code) values (?,' . $pdo->quote('pending') . ',' . $pdo->quote('pending') . ',11,1,0.80,10.80,0,0,0,' . $pdo->quote('calculated') . ',' . $pdo->quote('taxcalc_INTERNAL') . ',' . $pdo->quote('pending') . ',' . $pdo->quote('usd') . ',?,' . $pdo->quote('PHASE11') . ')')->execute([$internalBuyer,$couponId]);
    $internalOrder=(int)$pdo->lastInsertId();
    $pdo->prepare('insert into order_items(order_id,designer_id,product_id,total_price,commission_rate,fulfillment_type,manual_delivery_status,coupon_discount,seller_payout_status) values (?,?,1,10,.18,' . $pdo->quote('google_drive') . ',' . $pdo->quote('pending_delivery') . ',1,' . $pdo->quote('pending_payment') . ')')->execute([$internalOrder,$internalDesigner]);
    $pdo->prepare('insert into seller_earnings(order_id,product_id,designer_id,buyer_id,gross_sale,marketplace_commission,seller_earning,status) values (?,?,?, ?,10,1.80,8.20,' . $pdo->quote('pending_payment') . ')')->execute([$internalOrder,1,$internalDesigner,$internalBuyer]);
    $credits->grant($internalBuyer,'10.80','internal:grant:'.$suffix);
    $credits->reserve($internalBuyer,'10.80',$internalOrder,'internal:reserve:'.$suffix);
    $finalizer=new OrderFinalizationService($credits,$referrals);
    $check($finalizer->finalize($internalOrder,'internal:event:'.$suffix,true),'fully credit-funded internal order finalizes through shared service');
    $pdo->commit();
    $internal=$pdo->query('select * from orders where id='.$internalOrder)->fetch(PDO::FETCH_ASSOC);
    $item=$pdo->query('select * from order_items where order_id='.$internalOrder)->fetch(PDO::FETCH_ASSOC);
    $check($internal['payment_status']==='paid' && $internal['credit_payment_status']==='finalized' && (int)$internal['internally_completed']===1 && $internal['tax_transaction_status']==='created','internal order records paid, redeemed credit, completion mode, and Tax Transaction');
    $check($item['seller_payout_status']==='platform_credit_hold' && $item['manual_delivery_status']==='ready_for_seller_delivery' && $item['paid_at']!==null,'order item is paid, platform-held, and manual fulfillment unlocked');
    $check((int)$pdo->query('select count(*) from coupon_usages where order_id='.$internalOrder)->fetchColumn()===1 && (int)$pdo->query('select usage_count from coupons where id='.$couponId)->fetchColumn()===1,'coupon usage records exactly once');
    $check($pdo->query('select commission_amount from platform_commissions where order_id='.$internalOrder)->fetchColumn()==='1.80' && $pdo->query('select seller_payout_amount from seller_payouts where order_id='.$internalOrder)->fetchColumn()==='8.20','commission and seller payout obligation preserve item economics');
    $check($pdo->query('select status from seller_earnings where order_id='.$internalOrder)->fetchColumn()==='paid_pending_payout','seller earning reaches paid pending payout');
    $check(!$finalizer->finalize($internalOrder,'internal:event:'.$suffix,true) && count($taxCalls)===1,'finalization replay creates no duplicate financial or Tax Transaction work');
    $check((int)$pdo->query("select count(*) from referrals where seller_qualifying_order_id=$internalOrder and seller_status='rewarded'")->fetchColumn()===1,'shared finalizer evaluates seller referral once');
    $finalizer->communicate($internalOrder);
    $check($pdo->query('select payment_status from orders where id='.$internalOrder)->fetchColumn()==='paid','communication storage failure cannot roll back committed financial completion');

    // Injected external dependency failure: outer internal transaction rolls back every mutation.
    StripeService::setTestTransport(static function():array { throw new RuntimeException('injected Tax Transaction failure'); });
    $pdo->beginTransaction();
    $pdo->prepare('insert into orders(user_id,status,payment_status,subtotal,coupon_discount,tax_amount,credits_applied,total,stripe_paid_amount,manual_review_required,tax_status,tax_calculation_id,tax_transaction_status,stripe_currency) values (?,' . $pdo->quote('pending') . ',' . $pdo->quote('pending') . ',5,0,.40,5.40,0,0,0,' . $pdo->quote('calculated') . ',' . $pdo->quote('taxcalc_ROLLBACK') . ',' . $pdo->quote('pending') . ',' . $pdo->quote('usd') . ')')->execute([$internalBuyer]);
    $rollbackOrder=(int)$pdo->lastInsertId();
    $pdo->prepare('insert into order_items(order_id,designer_id,product_id,total_price,commission_rate,fulfillment_type,manual_delivery_status,seller_payout_status) values (?,?,9,5,.18,' . $pdo->quote('downloadable') . ',' . $pdo->quote('not_applicable') . ',' . $pdo->quote('pending_payment') . ')')->execute([$rollbackOrder,$internalDesigner]);
    $pdo->prepare('insert into seller_earnings(order_id,product_id,designer_id,buyer_id,gross_sale,marketplace_commission,seller_earning,status) values (?,9,?,?,5,.90,4.10,' . $pdo->quote('pending_payment') . ')')->execute([$rollbackOrder,$internalDesigner,$internalBuyer]);
    $credits->grant($internalBuyer,'5.40','rollback:grant:'.$suffix);
    $credits->reserve($internalBuyer,'5.40',$rollbackOrder,'rollback:reserve:'.$suffix);
    try{$finalizer->finalize($rollbackOrder,'rollback:event:'.$suffix,true);$rollbackFailed=false;}catch(DomainException){$rollbackFailed=true;}
    $pdo->rollBack();
    $rollbackArtifacts=(int)$pdo->query("select (select count(*) from order_items where order_id=$rollbackOrder)+(select count(*) from seller_earnings where order_id=$rollbackOrder)+(select count(*) from platform_commissions where order_id=$rollbackOrder)+(select count(*) from seller_payouts where order_id=$rollbackOrder)+(select count(*) from coupon_usages where order_id=$rollbackOrder)+(select count(*) from credit_transactions where order_id=$rollbackOrder)")->fetchColumn();
    $check($rollbackFailed && !$pdo->query('select id from orders where id='.$rollbackOrder)->fetchColumn() && $rollbackArtifacts===0,'injected internal failure rolls back order, credit, coupon, earnings, commissions, payout, fulfillment, and referral mutations');

    // Captured payment recovery persists reservation/manual review, then replay completes once.
    $pdo->beginTransaction();
    $credits->grant($internalBuyer,'1.00','recovery:grant:'.$suffix);
    $pdo->prepare('insert into orders(user_id,status,payment_status,subtotal,coupon_discount,tax_amount,credits_applied,total,stripe_paid_amount,manual_review_required,tax_status,tax_calculation_id,tax_transaction_status,stripe_currency,credit_payment_status,credit_reserved) values (?,' . $pdo->quote('pending') . ',' . $pdo->quote('captured_pending_finalization') . ',10,0,.80,1,9.80,9.80,0,' . $pdo->quote('calculated') . ',' . $pdo->quote('taxcalc_RECOVERY') . ',' . $pdo->quote('pending') . ',' . $pdo->quote('usd') . ',' . $pdo->quote('reserved') . ',1)')->execute([$internalBuyer]);
    $recoveryOrder=(int)$pdo->lastInsertId();
    $pdo->prepare('insert into order_items(order_id,designer_id,product_id,total_price,commission_rate,fulfillment_type,manual_delivery_status,seller_payout_status) values (?,?,10,10,.18,' . $pdo->quote('downloadable') . ',' . $pdo->quote('not_applicable') . ',' . $pdo->quote('pending_payment') . ')')->execute([$recoveryOrder,$internalDesigner]);
    $credits->reserve($internalBuyer,'1.00',$recoveryOrder,'recovery:reserve:'.$suffix);
    $pdo->commit();
    try{$finalizer->finalize($recoveryOrder,'stripe:recovery:'.$suffix,false);}catch(DomainException){}
    $recovery=$pdo->query('select * from orders where id='.$recoveryOrder)->fetch(PDO::FETCH_ASSOC);
    $check($recovery['payment_status']==='manual_review' && $recovery['credit_payment_status']==='reserved' && $credits->balances($internalBuyer)['reserved_cents']===100,'captured finalization failure leaves durable manual review and preserves reserved credit');
    $taxCalls=[];
    StripeService::setTestTransport(function(string $method,string $path,array $params,?string $key) use (&$taxCalls):array{$taxCalls[$key]=($taxCalls[$key]??0)+1;return ['id'=>'tax_recovered'];});
    $pdo->exec("update orders set payment_status='captured_pending_finalization',manual_review_required=0,manual_review_reason=null,tax_transaction_status='pending' where id=$recoveryOrder");
    $check($finalizer->finalize($recoveryOrder,'stripe:recovery:'.$suffix,false) && !$finalizer->finalize($recoveryOrder,'stripe:recovery:'.$suffix,false),'captured event replay recovers and finalizes exactly once');
    StripeService::setTestTransport(null);
    if ($oldStripeKey===null) unset($_ENV['STRIPE_SECRET_KEY']); else $_ENV['STRIPE_SECRET_KEY']=$oldStripeKey;

    $oldStripeKey = $_ENV['STRIPE_SECRET_KEY'] ?? null;
    $_ENV['STRIPE_SECRET_KEY'] = 'sk_test_fixture';
    $transferCalls = 0;
    $transferRequest = [];
    StripeService::setTestTransport(function (string $method, string $path, array $params, ?string $key) use (&$transferCalls,&$transferRequest): array {
        if ($path !== '/v1/transfers') throw new RuntimeException('Unexpected Stripe fixture request.');
        $transferCalls++;
        $transferRequest=compact('params','key');
        if (isset($params['source_transaction'])) throw new RuntimeException('Platform-funded transfer included a source transaction.');
        return ['id'=>'tr_fixture_success'];
    });
    $pdo->prepare('insert into users(name,email,password_hash,role,status,referral_code) values (?,?,?,?,?,?)')->execute(['Seller','seller-'.$suffix.'@example.test','x','designer','active','AMSELL'.$suffix]);
    $sellerUserId = (int)$pdo->lastInsertId();
    $pdo->prepare('insert into designers(user_id,status,stripe_connect_account_id,stripe_details_submitted,stripe_payouts_enabled) values (?,' . $pdo->quote('approved') . ',' . $pdo->quote('acct_fixture') . ',1,1)')->execute([$sellerUserId]);
    $designerId = (int)$pdo->lastInsertId();
    $pdo->prepare('insert into orders(user_id,status,payment_status,subtotal,coupon_discount,tax_amount,credits_applied,total,internally_completed,manual_review_required) values (?,' . $pdo->quote('paid') . ',' . $pdo->quote('paid') . ',10.00,0.00,0.80,10.80,0.00,1,0)')->execute([$adminId]);
    $settlementOrderId = (int)$pdo->lastInsertId();
    $pdo->prepare('insert into order_items(order_id,designer_id,product_id,seller_payout_status) values (?,?,1,' . $pdo->quote('platform_credit_hold') . ')')->execute([$settlementOrderId,$designerId]);
    $pdo->prepare('insert into seller_payouts(order_id,designer_id,gross_amount,platform_commission_amount,seller_payout_amount,currency,payout_status) values (?,?,10.00,1.50,8.50,' . $pdo->quote('usd') . ',' . $pdo->quote('platform_credit_hold') . ')')->execute([$settlementOrderId,$designerId]);
    $payoutId = (int)$pdo->lastInsertId();
    $settler = new PlatformCreditPayoutService();
    $result = $settler->settle($payoutId,$adminId);
    $replay = $settler->settle($payoutId,$adminId);
    $settled = $pdo->query('select * from seller_payouts where id=' . $payoutId)->fetch(PDO::FETCH_ASSOC);
    $check($result['ok'] && $replay['replay'] && $transferCalls === 1, 'platform-credit settlement is successful and replay-safe');
    $check($transferRequest['params']['amount']===850 && $transferRequest['params']['transfer_group']==='order_'.$settlementOrderId && !isset($transferRequest['params']['source_transaction']),'settlement transfers exact stored amount with order group and no source transaction');
    $settledItemStatus=$pdo->query('select seller_payout_status from order_items where order_id='.$settlementOrderId.' and designer_id='.$designerId)->fetchColumn();
    $check($settled['payout_status'] === 'transferred' && $settled['stripe_transfer_id'] === 'tr_fixture_success' && (int)$settled['platform_credit_settled_by'] === $adminId && $settled['platform_credit_settled_at']!==null && $settledItemStatus==='transferred', 'platform-credit settlement stores transfer, timestamp, actor, payout state, and order-item state');
    $audit = $pdo->query("select metadata from admin_logs where action='settled_platform_credit_payout' and entity_id=" . $payoutId)->fetchColumn();
    $check(is_string($audit) && str_contains($audit, '8.50') && str_contains($audit, 'transferred'), 'platform-credit settlement writes immutable admin audit metadata');
    try{$settler->settle($payoutId,$sellerUserId);$nonAdminRejected=false;}catch(DomainException){$nonAdminRejected=true;}
    $pdo->exec("update users set status='disabled' where id=$adminId");
    try{$settler->settle($payoutId,$adminId);$inactiveAdminRejected=false;}catch(DomainException){$inactiveAdminRejected=true;}
    $check($nonAdminRejected && $inactiveAdminRejected,'service rejects non-admin and inactive administrator settlement actors');
    $pdo->exec("update users set status='active' where id=$adminId");

    $pdo->prepare('insert into orders(user_id,status,payment_status,subtotal,coupon_discount,tax_amount,credits_applied,total,internally_completed,manual_review_required) values (?,' . $pdo->quote('paid') . ',' . $pdo->quote('paid') . ',10,0,.80,10.80,0,1,0)')->execute([$adminId]);
    $retryOrder=(int)$pdo->lastInsertId();
    $pdo->prepare('insert into order_items(order_id,designer_id,product_id,seller_payout_status) values (?,?,2,' . $pdo->quote('platform_credit_hold') . ')')->execute([$retryOrder,$designerId]);
    $pdo->prepare('insert into seller_payouts(order_id,designer_id,gross_amount,platform_commission_amount,seller_payout_amount,currency,payout_status) values (?,?,10,1.50,8.50,' . $pdo->quote('usd') . ',' . $pdo->quote('platform_credit_hold') . ')')->execute([$retryOrder,$designerId]);
    $retryPayout=(int)$pdo->lastInsertId();
    StripeService::setTestTransport(static function():array{throw new RuntimeException('sk_test_secret card_424242 unsafe details');});
    $failed=$settler->settle($retryPayout,$adminId);
    $failedRow=$pdo->query('select * from seller_payouts where id='.$retryPayout)->fetch(PDO::FETCH_ASSOC);
    $failedAudit=$pdo->query("select metadata from admin_logs where action='settled_platform_credit_payout' and entity_id=$retryPayout order by id desc limit 1")->fetchColumn();
    $check(!$failed['ok'] && $failedRow['payout_status']==='platform_credit_hold' && !str_contains((string)$failedRow['stripe_transfer_error'],'sk_test_secret') && is_string($failedAudit) && str_contains($failedAudit,'failed'),'Stripe failure preserves sanitized retryable obligation and immutable failure audit');
    StripeService::setTestTransport(static fn():array=>['id'=>'tr_fixture_retry']);
    $retried=$settler->settle($retryPayout,$adminId);
    $check($retried['ok'] && $pdo->query('select payout_status from seller_payouts where id='.$retryPayout)->fetchColumn()==='transferred','retry after platform transfer failure succeeds exactly once');

    foreach ([['refunded',0,null,1],['paid',1,null,1],['paid',0,'ch_external',1],['paid',0,null,0]] as $invalidIndex=>$invalid) {
        [$invalidStatus,$invalidReview,$invalidCharge,$payoutReady]=$invalid;
        $pdo->exec('update designers set stripe_payouts_enabled='.(int)$payoutReady.' where id='.$designerId);
        $pdo->prepare('insert into orders(user_id,status,payment_status,subtotal,coupon_discount,tax_amount,credits_applied,total,internally_completed,manual_review_required,stripe_charge_id,refunded_at) values (?,?,?,10,0,0,0,10,1,?,?,?)')->execute([$adminId,$invalidStatus,'paid',$invalidReview,$invalidCharge,$invalidStatus==='refunded'?date('Y-m-d H:i:s'):null]);
        $invalidOrder=(int)$pdo->lastInsertId();
        $pdo->prepare('insert into seller_payouts(order_id,designer_id,seller_payout_amount,currency,payout_status) values (?,?,8.50,' . $pdo->quote('usd') . ',' . $pdo->quote('platform_credit_hold') . ')')->execute([$invalidOrder,$designerId]);
        try{$settler->settle((int)$pdo->lastInsertId(),$adminId);$invalidRejected=false;}catch(DomainException){$invalidRejected=true;}
        $check($invalidRejected,'ineligible refunded/manual-review/external-charge/payout-disabled settlement rejected case '.$invalidIndex);
    }
    $pdo->exec('update designers set stripe_payouts_enabled=1 where id='.$designerId);

    $probe = static function(array $arguments) use ($fixture): string {
        $environment = 'DB_HOST='.escapeshellarg((string)($_ENV['DB_HOST'] ?? '127.0.0.1')).' DB_NAME='.escapeshellarg($fixture).' DB_USER='.escapeshellarg((string)($_ENV['DB_USER'] ?? 'root')).' DB_PASS='.escapeshellarg((string)($_ENV['DB_PASS'] ?? '')).' STRIPE_SECRET_KEY=sk_test_probe ';
        $command=$environment.escapeshellarg(PHP_BINARY).' '.escapeshellarg(__DIR__.'/helpers/Phase11ControllerProbe.php');
        foreach($arguments as $argument)$command.=' '.escapeshellarg((string)$argument);
        return (string)shell_exec($command.' 2>&1');
    };
    $check(str_contains($probe(['settle',$sellerUserId,'buyer','csrf','csrf',$payoutId]),'PROBE_STATUS:403'),'actual controller rejects non-admin settlement request');
    $check(str_contains($probe(['settle',$adminId,'admin','csrf','',$payoutId]),'PROBE_STATUS:419') && str_contains($probe(['settle',$adminId,'admin','csrf','wrong',$payoutId]),'PROBE_STATUS:419'),'actual controller rejects missing and invalid CSRF settlement requests');

    $pdo->prepare('insert into orders(user_id,status,payment_status,subtotal,coupon_discount,tax_amount,credits_applied,total,internally_completed,manual_review_required) values (?,' . $pdo->quote('paid') . ',' . $pdo->quote('paid') . ',10,0,.80,10.80,0,1,0)')->execute([$adminId]);
    $inactiveOrder=(int)$pdo->lastInsertId();
    $pdo->prepare('insert into seller_payouts(order_id,designer_id,seller_payout_amount,currency,payout_status) values (?,?,8.50,' . $pdo->quote('usd') . ',' . $pdo->quote('platform_credit_hold') . ')')->execute([$inactiveOrder,$designerId]);
    $inactivePayout=(int)$pdo->lastInsertId();
    $pdo->exec("update users set status='disabled' where id=$adminId");
    $probe(['settle',$adminId,'admin','csrf','csrf',$inactivePayout]);
    $check($pdo->query('select payout_status from seller_payouts where id='.$inactivePayout)->fetchColumn()==='platform_credit_hold','actual controller path cannot settle for inactive administrator');
    $pdo->exec("update users set status='active' where id=$adminId");

    $pdo->prepare('insert into orders(user_id,status,payment_status,subtotal,coupon_discount,tax_amount,credits_applied,total,internally_completed,manual_review_required) values (?,' . $pdo->quote('paid') . ',' . $pdo->quote('paid') . ',10,0,.80,10.80,0,1,0)')->execute([$adminId]);
    $controllerOrder=(int)$pdo->lastInsertId();
    $pdo->prepare('insert into order_items(order_id,designer_id,product_id,seller_payout_status) values (?,?,20,' . $pdo->quote('platform_credit_hold') . ')')->execute([$controllerOrder,$designerId]);
    $pdo->prepare('insert into seller_payouts(order_id,designer_id,seller_payout_amount,currency,payout_status) values (?,?,8.50,' . $pdo->quote('usd') . ',' . $pdo->quote('platform_credit_hold') . ')')->execute([$controllerOrder,$designerId]);
    $controllerPayout=(int)$pdo->lastInsertId();
    $probe(['settle',$adminId,'admin','csrf','csrf',$controllerPayout]);
    $check($pdo->query('select payout_status from seller_payouts where id='.$controllerPayout)->fetchColumn()==='transferred','valid admin and CSRF settle eligible hold through actual controller');

    $pdo->prepare('insert into orders(user_id,status,payment_status,subtotal,coupon_discount,tax_amount,credits_applied,total,internally_completed,manual_review_required) values (?,' . $pdo->quote('paid') . ',' . $pdo->quote('paid') . ',10,0,.80,10.80,0,1,0)')->execute([$adminId]);
    $concurrentPayoutOrder=(int)$pdo->lastInsertId();
    $pdo->prepare('insert into order_items(order_id,designer_id,product_id,seller_payout_status) values (?,?,21,' . $pdo->quote('platform_credit_hold') . ')')->execute([$concurrentPayoutOrder,$designerId]);
    $pdo->prepare('insert into seller_payouts(order_id,designer_id,seller_payout_amount,currency,payout_status) values (?,?,8.50,' . $pdo->quote('usd') . ',' . $pdo->quote('platform_credit_hold') . ')')->execute([$concurrentPayoutOrder,$designerId]);
    $concurrentPayout=(int)$pdo->lastInsertId();
    $probeBase='DB_HOST='.escapeshellarg((string)($_ENV['DB_HOST']??'127.0.0.1')).' DB_NAME='.escapeshellarg($fixture).' DB_USER='.escapeshellarg((string)($_ENV['DB_USER']??'root')).' DB_PASS='.escapeshellarg((string)($_ENV['DB_PASS']??'')).' STRIPE_SECRET_KEY=sk_test_probe '.escapeshellarg(PHP_BINARY).' '.escapeshellarg(__DIR__.'/helpers/Phase11ControllerProbe.php').' settle '.$adminId.' admin csrf csrf '.$concurrentPayout;
    $concurrentProcesses=[];
    foreach([0,1] as $attempt){$racePipes=[];$raceProcess=proc_open($probeBase,[1=>['pipe','w'],2=>['pipe','w']],$racePipes);$concurrentProcesses[]=[$raceProcess,$racePipes];}
    foreach($concurrentProcesses as [$raceProcess,$racePipes]){stream_get_contents($racePipes[1]);stream_get_contents($racePipes[2]);fclose($racePipes[1]);fclose($racePipes[2]);proc_close($raceProcess);}
    $check($pdo->query('select payout_status from seller_payouts where id='.$concurrentPayout)->fetchColumn()==='transferred' && (int)$pdo->query("select count(*) from admin_logs where action='settled_platform_credit_payout' and entity_id=$concurrentPayout")->fetchColumn()===1,'concurrent controller settlement attempts create one transfer result and one immutable success log');

    $check(str_contains($probe(['adjust',$sellerUserId,'buyer','csrf','csrf',$sellerUserId,'1.00','valid reason']),'PROBE_STATUS:403'),'actual adjustment controller rejects unauthorized user');
    $check(str_contains($probe(['adjust',$adminId,'admin','csrf','',$sellerUserId,'1.00','valid reason']),'PROBE_STATUS:419') && str_contains($probe(['adjust',$adminId,'admin','csrf','wrong',$sellerUserId,'1.00','valid reason']),'PROBE_STATUS:419'),'actual adjustment controller rejects missing and invalid CSRF');
    $pdo->exec("update users set status='disabled' where id=$sellerUserId");
    $probe(['adjust',$adminId,'admin','csrf','csrf',$sellerUserId,'1.00','valid reason']);
    $check(!$pdo->query("select id from credit_transactions where user_id=$sellerUserId and type='admin_adjustment'")->fetchColumn(),'adjustment controller rejects inactive target');
    $pdo->exec("update users set status='active' where id=$sellerUserId");
    foreach([['bad','valid reason'],['1.00','x'],['0.00','valid reason']] as $invalidAdjustment)$probe(['adjust',$adminId,'admin','csrf','csrf',$sellerUserId,$invalidAdjustment[0],$invalidAdjustment[1]]);
    $check(!$pdo->query("select id from credit_transactions where user_id=$sellerUserId and type='admin_adjustment'")->fetchColumn(),'adjustment controller rejects invalid amount, zero, and invalid reason');
    $probe(['adjust',$adminId,'admin','csrf','csrf',$sellerUserId,'2.25','Phase 11 audited adjustment']);
    $adjustment=$pdo->query("select * from credit_transactions where user_id=$sellerUserId and type='admin_adjustment'")->fetch(PDO::FETCH_ASSOC);
    $adjustmentLog=$pdo->query("select metadata from admin_logs where action='store_credit_adjustment' and entity_id=$sellerUserId order by id desc limit 1")->fetchColumn();
    $check($adjustment && $adjustment['amount']==='2.25' && (int)$adjustment['admin_user_id']===$adminId && str_contains((string)$adjustment['description'],'audited'),'successful controller adjustment stores amount, actor, reason, and ledger entry');
    $check(is_string($adjustmentLog) && str_contains($adjustmentLog,'2.25') && str_contains($adjustmentLog,'Phase 11 audited adjustment'),'successful controller adjustment stores immutable admin audit');
    StripeService::setTestTransport(null);
    if ($oldStripeKey === null) unset($_ENV['STRIPE_SECRET_KEY']); else $_ENV['STRIPE_SECRET_KEY'] = $oldStripeKey;

    $columns = $pdo->query("select column_name from information_schema.columns where table_schema=database() and table_name='orders' and column_name in ('tax_calculation_id','tax_transaction_id','tax_transaction_status','billing_address_snapshot')")->fetchAll(PDO::FETCH_COLUMN);
    $check(count($columns) === 4, 'actual migrated database has Tax Calculation and Tax Transaction lifecycle fields');
    $qualificationFks = $pdo->query("select constraint_name from information_schema.key_column_usage where table_schema=database() and table_name='referrals' and constraint_name in ('phase11_referrals_buyer_order_fk','phase11_referrals_seller_order_fk','phase11_referrals_seller_item_fk') and referenced_table_name is not null")->fetchAll(PDO::FETCH_COLUMN);
    $check(count($qualificationFks) === 3, 'buyer order, seller order, and seller item qualification references have foreign keys');
    $qualificationIndexes = $pdo->query("select distinct index_name from information_schema.statistics where table_schema=database() and table_name='referrals' and index_name in ('referrals_buyer_qualifying_order_idx','referrals_seller_qualifying_order_idx','referrals_seller_qualifying_item_idx','referrals_seller_qualification_lookup_idx')")->fetchAll(PDO::FETCH_COLUMN);
    $check(count($qualificationIndexes) === 4, 'qualification and replay lookup indexes exist');
    $payoutColumns = $pdo->query("select column_name from information_schema.columns where table_schema=database() and table_name='seller_payouts' and column_name in ('platform_credit_attempt_key','platform_credit_settled_at','platform_credit_settled_by')")->fetchAll(PDO::FETCH_COLUMN);
    $check(count($payoutColumns) === 3, 'platform-credit settlement audit columns exist');
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo 'FAIL: disposable migration integration exception: ' . $error->getMessage() . "\n";
    $failures[] = 'disposable migration integration exception';
} finally {
    if ($originalDatabase !== '') $pdo->exec('use `' . str_replace('`','``',$originalDatabase) . '`');
    $pdo->exec('drop database if exists `' . $fixture . '`');
    $pdo->exec('drop database if exists `' . $canonicalFixture . '`');
}
exit($failures ? 1 : 0);
