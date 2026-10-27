<?php
require __DIR__.'/GrandOpeningPricingBootstrap.php';
use App\Services\StripeService;
use App\Controllers\CartController;

$tracePath=$argv[1]??'';
if ($tracePath==='' || !is_dir(dirname($tracePath))) throw new RuntimeException('Trace path required.');
$trace=[];
StripeService::setTestTransport(static function(string $method,string $path,array $params,?string $key) use (&$trace,$tracePath): array {
    $trace[]=['path'=>$path,'params'=>$params,'key'=>$key];
    file_put_contents($tracePath,json_encode($trace,JSON_THROW_ON_ERROR));
    if ($path==='/v1/tax/calculations') {
        $merchandise=array_sum(array_column($params['line_items'],'amount'));
        $tax=intdiv($merchandise*6+50,100);
        return ['id'=>'tax_fixture_'.count($trace),'tax_amount_exclusive'=>$tax,'tax_amount_inclusive'=>0,'amount_total'=>$merchandise+$tax];
    }
    if ($path==='/v1/checkout/sessions') {
        return ['id'=>'cs_fixture_'.bin2hex(random_bytes(4)),'url'=>'https://example.invalid/test-checkout'];
    }
    throw new RuntimeException('Unexpected Stripe request: '.$path);
});
$_SESSION=['user'=>['id'=>1,'name'=>'Fixture Buyer','email'=>'buyer@example.invalid','role'=>'buyer']];
if (($argv[2]??'')!=='') $_SESSION['coupon_code']=$argv[2];
$_SERVER['REQUEST_METHOD']='POST';
$_POST=['billing_line1'=>'123 Test Street','billing_city'=>'Detroit','billing_state'=>'MI','billing_postal_code'=>'48239','billing_country'=>'US'];
(new CartController())->checkout();
throw new RuntimeException('Checkout did not redirect after creating a session.');
