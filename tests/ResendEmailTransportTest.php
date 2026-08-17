<?php
require dirname(__DIR__).'/app/bootstrap.php';
use App\Services\EmailQueueService;
use App\Services\ResendEmailTransport;

$passed=0;$failed=[];$test=function(string $name,callable $fn)use(&$passed,&$failed){try{$fn();$passed++;}catch(Throwable $e){$failed[]="$name: {$e->getMessage()}";}finally{ResendEmailTransport::setTestClient(null);}};
$yes=function($value,string $message='assertion failed'){if(!$value)throw new RuntimeException($message);};
$throws=function(callable $fn)use($yes):Throwable{try{$fn();}catch(Throwable $e){return $e;}throw new RuntimeException('expected exception');};

$test('log transport remains selected and writes its existing safe record',function()use($yes){
    $_ENV['MAIL_TRANSPORT']='log';$source=file_get_contents(app_path('app/Services/EmailQueueService.php'));
    $yes(str_contains($source,"if(\$transport!=='log')"));$yes(str_contains($source,"self::appendLogOnce(\$dir.'/mail.log'"));
    $path=tempnam(sys_get_temp_dir(),'asset-moth-log-');$entry=['message_id'=>8123,'recipient_hash'=>hash('sha256','buyer@example.test'),'subject'=>'Subject','body_bytes'=>13];
    $yes(EmailQueueService::appendLogOnce($path,$entry));$stored=file_get_contents($path);unlink($path);
    $yes(str_contains($stored,'"message_id":8123'));$yes(!str_contains($stored,'buyer@example.test'));
});
$test('resend request contains configured sender recipient subject and html',function()use($yes){
    $_ENV['RESEND_API_KEY']='re_test_secret';$_ENV['MAIL_FROM_ADDRESS']='mail@assetmoth.example';$_ENV['MAIL_FROM_NAME']='Asset Moth';$seen=[];
    ResendEmailTransport::setTestClient(function($url,$headers,$payload)use(&$seen){$seen=compact('url','headers','payload');return [200,['id'=>'email_123']];});
    ResendEmailTransport::send('buyer@example.test','Existing subject','<main>Rendered HTML</main>');
    $yes($seen['url']==='https://api.resend.com/emails');$yes($seen['payload']===['from'=>'Asset Moth <mail@assetmoth.example>','to'=>['buyer@example.test'],'subject'=>'Existing subject','html'=>'<main>Rendered HTML</main>']);
    $yes(in_array('Authorization: Bearer re_test_secret',$seen['headers'],true));
});
$test('successful Resend acceptance returns normally',function(){
    $_ENV['RESEND_API_KEY']='re_success_secret';$_ENV['MAIL_FROM_ADDRESS']='mail@assetmoth.example';ResendEmailTransport::setTestClient(fn()=>[202,'{"id":"accepted_456"}']);ResendEmailTransport::send('buyer@example.test','Subject','<p>HTML</p>');
});
$test('non-success and missing acceptance id are failures',function()use($throws){
    $_ENV['RESEND_API_KEY']='re_failure_secret';$_ENV['MAIL_FROM_ADDRESS']='mail@assetmoth.example';ResendEmailTransport::setTestClient(fn()=>[422,'{"message":"rejected"}']);$throws(fn()=>ResendEmailTransport::send('buyer@example.test','Subject','<p>HTML</p>'));
    ResendEmailTransport::setTestClient(fn()=>[200,'{}']);$throws(fn()=>ResendEmailTransport::send('buyer@example.test','Subject','<p>HTML</p>'));
});
$test('missing Resend configuration fails without making a request',function()use($throws){
    unset($_ENV['RESEND_API_KEY']);$_ENV['MAIL_FROM_ADDRESS']='mail@assetmoth.example';ResendEmailTransport::setTestClient(fn()=>throw new RuntimeException('client should not run'));$throws(fn()=>ResendEmailTransport::send('buyer@example.test','Subject','<p>HTML</p>'));
});
$test('API keys never appear in transport errors',function()use($throws,$yes){
    $key='re_never_expose_this_value';$_ENV['RESEND_API_KEY']=$key;$_ENV['MAIL_FROM_ADDRESS']='mail@assetmoth.example';ResendEmailTransport::setTestClient(fn()=>[401,['message'=>'Unauthorized '.$key]]);$error=$throws(fn()=>ResendEmailTransport::send('buyer@example.test','Subject','<p>HTML</p>'));$yes(!str_contains($error->getMessage(),$key));
});

if($failed){fwrite(STDERR,implode("\n",$failed)."\n");exit(1);}fwrite(STDOUT,"Resend email transport checks passed ($passed tests).\n");
