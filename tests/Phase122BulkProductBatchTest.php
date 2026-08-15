<?php
$root=dirname(__DIR__);
$fail=0;

$check=function(bool $ok,string $name)use(&$fail){
    echo($ok?'PASS':'FAIL').": $name\n";
    if(!$ok)$fail++;
};

$service=file_get_contents("$root/app/Services/ProductBatchService.php");
$submission=file_get_contents("$root/app/Services/ProductSubmissionService.php");
$controller=file_get_contents("$root/app/Controllers/SellerController.php");
$batchView=file_get_contents("$root/app/Views/seller/product_batch.php");
$batchesView=file_get_contents("$root/app/Views/seller/product_batches.php");
$bulkForm=file_get_contents("$root/app/Views/seller/bulk_product_form.php");
$bulkCount=file_get_contents("$root/app/Views/seller/bulk_product_count.php");
$routes=file_get_contents("$root/public/index.php");

$check(
    str_contains($controller,'bulk_product_wizard')
    && str_contains($controller,'presetLicensesForProductForm')
    && str_contains($controller,'syncProductLicenses'),
    'SOURCE: guided wizard carries shared product data and seller license configuration'
);

$check(
    str_contains($routes,"/seller/product-bulk/template")
    && str_contains($routes,"/seller/product-bulk/count")
    && str_contains($routes,"/seller/product-bulk/item/{step}"),
    'SOURCE: guided bulk wizard routes are wired'
);

$check(
    substr_count($controller,'new ProductSubmissionService()')===2,
    'SOURCE: single and batch paths call the same submission service'
);

$check(
    str_contains($submission,"'pending_review' : 'approved'"),
    'SOURCE: shared submission service retains normal clean/IP-review status choice'
);

$check(
    str_contains($controller,"['submit_selected','submit_all']")
    && str_contains($batchView,'value="submit_selected"')
    && str_contains($batchView,'value="submit_all"')
    && str_contains($batchView,"Submit My Selected Products")
    && str_contains($batchView,"Submit Every Product That's Ready"),
    'SOURCE: selected and all-valid actions are wired'
);

$check(
    str_contains($service,'b.designer_id=?')
    && str_contains($controller,'p.designer_id=?'),
    'SOURCE: batch queries include seller ownership constraints'
);

$csrfCount =
    substr_count($batchView,'H::csrf()')
    + substr_count($batchesView,'H::csrf()')
    + substr_count($bulkForm,'H::csrf()')
    + substr_count($bulkCount,'H::csrf()');

$check(
    $csrfCount >= 5,
    'SOURCE: bulk wizard and batch mutation forms include CSRF inputs'
);

$check(
    str_contains($controller,"delete_batch")
    && str_contains($batchView,'value="delete_batch"')
    && str_contains($batchesView,'value="delete_batch"'),
    'SOURCE: bulk batch deletion is wired from list and individual batch views'
);

echo "NOTE: Behavioral workflow assertions run only in Phase122DatabaseIntegrationTest.php and live testing.\n";
exit($fail?1:0);
