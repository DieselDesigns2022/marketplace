<?php
if(getenv('PHASE123_RUN_DATABASE_TESTS')!=='1'){fwrite(STDOUT,"SKIP: set PHASE123_RUN_DATABASE_TESTS=1 with a disposable MariaDB server.\n");exit(0);}
require dirname(__DIR__).'/app/bootstrap.php';

use App\Controllers\WaitlistController;
use App\Core\Database as DB;
use App\Services\EmailDigestService;
use App\Services\EmailPreferenceService;
use App\Services\EmailQueueService;
use App\Services\UnsubscribeService;

if(getenv('PHASE123_ALLOW_FIXTURE')!=='1'){fwrite(STDOUT,"SKIP: set PHASE123_ALLOW_FIXTURE=1 to permit creation of an isolated disposable database.\n");exit(0);}
$failures=[];$check=function(bool $ok,string $name)use(&$failures){echo($ok?'PASS':'FAIL').": $name\n";if(!$ok)$failures[]=$name;};
try{$pdo=DB::pdo();}catch(Throwable $e){echo 'SKIP: disposable MariaDB unavailable: '.preg_replace('/[\r\n]+/',' ',$e->getMessage())."\n";exit(0);}
$original=(string)$pdo->query('select database()')->fetchColumn();$fixture='phase123_fixture_'.bin2hex(random_bytes(5));
$_ENV['APP_URL']='https://assetmoth.example';$_ENV['EMAIL_UNSUBSCRIBE_SECRET']=str_repeat('phase-12.3-db-secret-',2);$_ENV['MAIL_TRANSPORT']='log';
try{
    $pdo->exec("create database `$fixture` character set utf8mb4 collate utf8mb4_unicode_ci");$pdo->exec("use `$fixture`");
    $pdo->exec("create table users(id bigint primary key auto_increment,name varchar(120) not null,email varchar(190) unique not null,password_hash varchar(255) not null,role enum('buyer','designer','admin') default 'buyer',status enum('active','disabled') default 'active',created_at timestamp default current_timestamp,updated_at timestamp default current_timestamp on update current_timestamp);
create table email_preferences(user_id bigint primary key,marketing_opt_in boolean not null default 0,marketing_opted_in_at timestamp null,marketing_opted_out_at timestamp null,unsubscribe_nonce char(64) not null,created_at timestamp default current_timestamp,updated_at timestamp default current_timestamp on update current_timestamp,unique key uq_email_preferences_nonce(unsubscribe_nonce));
create table designers(id bigint primary key auto_increment,user_id bigint not null,display_name varchar(120),store_slug varchar(140),status enum('approved','disabled','inactive','deleted') default 'approved');
create table products(id bigint primary key auto_increment,designer_id bigint not null,title varchar(190),slug varchar(210),price decimal(10,2),status enum('draft','approved','published','disabled') default 'draft',created_at timestamp default current_timestamp,updated_at timestamp default current_timestamp on update current_timestamp);
create table follows(id bigint primary key auto_increment,user_id bigint not null,designer_id bigint not null,created_at timestamp default current_timestamp,unique key uq_follow(user_id,designer_id));
create table email_messages(id bigint primary key auto_increment,classification enum('transactional','marketing') not null,recipient_email varchar(190),recipient_name varchar(120),subject varchar(190),template varchar(80),template_data json,campaign_id bigint null,campaign_recipient_id bigint null,waitlist_entry_id bigint null,deduplication_key varchar(190) unique,status enum('pending','processing','sent','failed','cancelled') default 'pending',attempt_count tinyint unsigned default 0,next_attempt_at timestamp null,last_error varchar(500),created_at timestamp default current_timestamp,claimed_at timestamp null,sent_at timestamp null,updated_at timestamp default current_timestamp on update current_timestamp);
create table waitlist_entries(id bigint primary key auto_increment,status varchar(30),unsubscribed_at timestamp null,unsubscribe_nonce char(64),confirmation_sent_at timestamp null,invited_at timestamp null);
create table email_campaign_recipients(id bigint primary key,status varchar(30),last_error varchar(500));
create table email_campaigns(id bigint primary key,status varchar(30),sent_at timestamp null,completed_at timestamp null);
create table notifications(id bigint primary key auto_increment,user_id bigint,notification_type varchar(80),audience varchar(30),title varchar(190),message text,action_url varchar(500),event_key varchar(190) unique,read_at timestamp null,created_at timestamp default current_timestamp);");
    $nonce1=str_repeat('1',64);$nonce2=str_repeat('2',64);
    $pdo->exec("insert into users(id,name,email,password_hash,status) values(1,'Legacy on','on@example.test','x','active'),(2,'Legacy off','off@example.test','x','active');");
    $pdo->prepare('insert into email_preferences(user_id,marketing_opt_in,marketing_opted_in_at,marketing_opted_out_at,unsubscribe_nonce) values(1,1,now(),null,?),(2,0,null,now(),?)')->execute([$nonce1,$nonce2]);
    $pdo->exec(file_get_contents(app_path('database/migrations/2026_08_15_phase_12_3_email_preferences_digests.sql')));
    $pdo->exec(file_get_contents(app_path('database/migrations/2026_08_16_phase_12_3_digest_content_claims.sql')));
    $columns=$pdo->query('show columns from email_preferences')->fetchAll(PDO::FETCH_COLUMN);
    $check(count(array_intersect(['weekly_emails','monthly_emails','favorite_shop_emails'],$columns))===3,'all three preference columns exist');
    $legacyOn=$pdo->query('select * from email_preferences where user_id=1')->fetch();$legacyOff=$pdo->query('select * from email_preferences where user_id=2')->fetch();
    $check((int)$legacyOn['weekly_emails']===1&&(int)$legacyOn['monthly_emails']===1&&(int)$legacyOn['favorite_shop_emails']===1,'legacy marketing opt-in migrates all categories on');
    $check((int)$legacyOff['weekly_emails']===0&&(int)$legacyOff['monthly_emails']===0&&(int)$legacyOff['favorite_shop_emails']===0,'legacy marketing opt-out migrates all categories off');

    EmailPreferenceService::save(2,['weekly'=>true,'favorite_shop'=>true]);$combo=$pdo->query('select * from email_preferences where user_id=2')->fetch();$changed=$combo['preference_changed_at'];
    $check((int)$combo['weekly_emails']===1&&(int)$combo['monthly_emails']===0&&(int)$combo['favorite_shop_emails']===1&&(int)$combo['marketing_opt_in']===1,'preference combination saves independently and any enabled keeps marketing opt-in on');
    sleep(1);EmailPreferenceService::save(2,['weekly'=>true,'favorite_shop'=>true]);$check($pdo->query('select preference_changed_at from email_preferences where user_id=2')->fetchColumn()===$changed,'unchanged preference save preserves preference_changed_at');
    sleep(1);EmailPreferenceService::save(2,[]);$none=$pdo->query('select * from email_preferences where user_id=2')->fetch();$check($none['preference_changed_at']!==$changed&&(int)$none['marketing_opt_in']===0,'actual preference change advances timestamp and none enabled turns marketing opt-in off');

    $unsubscribe=function(string $kind,string $nonce)use($pdo){$_SESSION['_csrf']='phase123-csrf';$_SERVER['REQUEST_METHOD']='POST';$_POST=['_csrf'=>'phase123-csrf','token'=>UnsubscribeService::issue($kind,1,$nonce)];$_GET=[];ob_start();(new WaitlistController)->unsubscribe();ob_end_clean();return $pdo->query('select * from email_preferences where user_id=1')->fetch();};
    $pdo->exec('update email_preferences set weekly_emails=1,monthly_emails=1,favorite_shop_emails=1,marketing_opt_in=1,preference_changed_at=null where user_id=1');
    $weekly=$unsubscribe('uw',$nonce1);$check((int)$weekly['weekly_emails']===0&&(int)$weekly['monthly_emails']===1&&(int)$weekly['favorite_shop_emails']===1,'weekly unsubscribe changes weekly only');
    $monthly=$unsubscribe('um',$nonce1);$check((int)$monthly['weekly_emails']===0&&(int)$monthly['monthly_emails']===0&&(int)$monthly['favorite_shop_emails']===1,'monthly unsubscribe changes monthly only');
    $favorite=$unsubscribe('uf',$nonce1);$favoriteChanged=$favorite['preference_changed_at'];$check((int)$favorite['favorite_shop_emails']===0&&(int)$favorite['marketing_opt_in']===0,'favorite-shop unsubscribe changes favorite only and clears aggregate opt-in');
    sleep(1);$repeat=$unsubscribe('uf',$nonce1);$check($repeat['preference_changed_at']===$favoriteChanged,'repeated scoped unsubscribe is idempotent');
    $before=json_encode($repeat);$tampered=UnsubscribeService::issue('uw',1,$nonce1);$tampered=substr($tampered,0,-1).($tampered[-1]==='a'?'b':'a');$_POST=['_csrf'=>'phase123-csrf','token'=>$tampered];ob_start();(new WaitlistController)->unsubscribe();ob_end_clean();$check(json_encode($pdo->query('select * from email_preferences where user_id=1')->fetch())===$before,'tampered unsubscribe token changes no preferences');

    $pdo->exec("insert into users(id,name,email,password_hash,status) values(3,'Seller','seller@example.test','x','active'),(4,'Follower','follower@example.test','x','active'),(5,'Disabled later','disabled@example.test','x','active');insert into designers(id,user_id,display_name,store_slug,status) values(10,3,'Real Shop','real-shop','approved');insert into products(id,designer_id,title,slug,price,status,created_at) values(20,10,'Real Product','real-product',5,'published','2026-08-10');insert into email_preferences(user_id,marketing_opt_in,weekly_emails,monthly_emails,favorite_shop_emails,unsubscribe_nonce) values(4,1,1,0,1,'".str_repeat('4',64)."'),(5,1,1,0,0,'".str_repeat('5',64)."');insert into follows(user_id,designer_id) values(4,10);");
    $check(EmailDigestService::queueFavoriteShops('2026-08-15')===1,'favorite-shop producer requires and accepts an existing follow');
    $firstCount=(int)$pdo->query("select count(*) from email_messages where deduplication_key='favorite-shops:2026-08-08:4'")->fetchColumn();EmailDigestService::queueFavoriteShops('2026-08-15');$check((int)$pdo->query("select count(*) from email_messages where deduplication_key='favorite-shops:2026-08-08:4'")->fetchColumn()===$firstCount,'stable dedupe prevents favorite producer replay duplicates');
    $pdo->exec('delete from follows where user_id=4 and designer_id=10');$check(EmailDigestService::queueFavoriteShops('2026-08-15')===0,'unfollow removes eligibility for future favorite-shop queueing');
    $pdo->exec("update email_messages set status='cancelled';insert into follows(user_id,designer_id) values(4,10)");EmailDigestService::queueFavoriteShops('2026-08-16');$pdo->exec('delete from follows where user_id=4 and designer_id=10');EmailQueueService::process(20);$check($pdo->query("select status from email_messages where deduplication_key='favorite-shops:2026-08-09:4'")->fetchColumn()==='cancelled','queued favorite-shop mail is suppressed after qualifying follow removal');

    $pdo->exec("update email_messages set status='cancelled'");EmailDigestService::queueDigest('weekly','2026-08-15');$pdo->exec("update users set status='disabled' where id=5");EmailQueueService::process(20);$check($pdo->query("select status from email_messages where deduplication_key='digest:weekly:2026-08-08:5'")->fetchColumn()==='cancelled','disabled user queued marketing is suppressed before delivery');
    $pdo->exec("update email_preferences set weekly_emails=0,monthly_emails=1,favorite_shop_emails=0,marketing_opt_in=1 where user_id=4");$independent=$pdo->query('select * from email_preferences where user_id=4')->fetch();$check((int)$independent['weekly_emails']===0&&(int)$independent['monthly_emails']===1&&(int)$independent['favorite_shop_emails']===0,'weekly monthly and favorite preferences remain independent');

    $pdo->exec("insert into users(id,name,email,password_hash,status) values(6,'Weekly Favorite','wf@example.test','x','active'),(7,'Weekly Monthly','wm@example.test','x','active'),(8,'All Preferences','all@example.test','x','active'),(9,'Monthly Only','monthly@example.test','x','active'),(10,'Other Seller','other-seller@example.test','x','active');
insert into designers(id,user_id,display_name,store_slug,status) values(11,10,'Other Shop','other-shop','approved');
insert into email_preferences(user_id,marketing_opt_in,weekly_emails,monthly_emails,favorite_shop_emails,unsubscribe_nonce) values(6,1,1,0,1,'".str_repeat('6',64)."'),(7,1,1,1,0,'".str_repeat('7',64)."'),(8,1,1,1,1,'".str_repeat('8',64)."'),(9,1,0,1,0,'".str_repeat('9',64)."');
insert into follows(user_id,designer_id) values(6,10),(8,10);
insert into products(id,designer_id,title,slug,price,status,created_at) values(21,10,'September Followed','september-followed',6,'published','2026-09-10'),(22,11,'August Marketplace','august-marketplace',7,'published','2026-08-28'),(23,10,'October Followed','october-followed',8,'published','2026-10-10'),(24,11,'October Marketplace','october-marketplace',9,'published','2026-10-10'),(25,11,'November Monthly','november-monthly',10,'published','2026-11-10');");
    $deliveredProductCopies=function(int $userId,int $productId)use($pdo):int{$count=0;$statement=$pdo->prepare('select template_data from email_messages where status="sent"');$statement->execute();foreach($statement->fetchAll(PDO::FETCH_COLUMN) as $json){$data=json_decode($json,true)?:[];if((int)($data['user_id']??0)!==$userId)continue;foreach($data['products']??[] as $product)if((int)($product['id']??0)===$productId)$count++;}return $count;};
    EmailDigestService::queueFavoriteShops('2026-09-15');EmailDigestService::queueDigest('weekly','2026-09-15');EmailDigestService::queueFavoriteShops('2026-09-15');EmailDigestService::queueDigest('weekly','2026-09-15');EmailQueueService::process(100);
    $check($deliveredProductCopies(6,21)===1,'weekly plus favorite delivers one copy of a followed product across producer replays');
    EmailDigestService::queueDigest('weekly','2026-09-01');EmailDigestService::queueDigest('monthly','2026-09-15');EmailDigestService::queueDigest('monthly','2026-09-15');EmailQueueService::process(100);
    $check($deliveredProductCopies(7,22)===1,'overlapping weekly and monthly windows deliver one product copy');
    EmailDigestService::queueDigest('monthly','2026-11-15');EmailDigestService::queueDigest('weekly','2026-10-15');EmailDigestService::queueFavoriteShops('2026-10-15');EmailDigestService::queueDigest('weekly','2026-10-15');EmailDigestService::queueFavoriteShops('2026-10-15');EmailDigestService::queueDigest('monthly','2026-11-15');EmailQueueService::process(100);
    $check($deliveredProductCopies(8,23)===1&&$deliveredProductCopies(8,24)===1,'all three preferences assign followed and marketplace products once across replays');
    $check($deliveredProductCopies(8,24)===1,'a different marketplace product still delivers normally');
    EmailDigestService::queueDigest('monthly','2026-12-15');EmailQueueService::process(100);$check($deliveredProductCopies(9,25)===1,'single-preference monthly user receives the appropriate digest normally');
    $pdo->exec("update email_messages set status='cancelled'");$queued=EmailQueueService::queue('transactional','disabled@example.test','Required account notice','seller_notification',['name'=>'Disabled later','title'=>'Required','message'=>'Required account message'],'phase123:transactional');EmailQueueService::process(20);$check($queued&&$pdo->query("select status from email_messages where deduplication_key='phase123:transactional'")->fetchColumn()==='sent','transactional delivery remains independent of user status and marketing preferences');
}catch(Throwable $e){echo 'FAIL: integration exception: '.preg_replace('/[\r\n]+/',' ',$e->getMessage())."\n";$failures[]='integration exception';}
finally{try{$pdo->exec("use `$original`");$pdo->exec("drop database if exists `$fixture`");}catch(Throwable){}}
exit($failures?1:0);
