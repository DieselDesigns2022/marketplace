<?php
$root=dirname(__DIR__);$fail=0;$check=function(bool $ok,string $name)use(&$fail){echo($ok?'PASS':'FAIL').": $name\n";if(!$ok)$fail++;};
$service=file_get_contents("$root/app/Services/ProductBatchService.php");$submission=file_get_contents("$root/app/Services/ProductSubmissionService.php");$controller=file_get_contents("$root/app/Controllers/SellerController.php");$view=file_get_contents("$root/app/Views/seller/product_batch.php");
$check(str_contains($service,"'short_description'")&&str_contains($service,"'ai_disclosure'")&&str_contains($service,'product_license_types'),'SOURCE: template whitelist includes shared fields and license-row copying');
$check(substr_count($controller,'new ProductSubmissionService()')===2,'SOURCE: single and batch paths call the same submission service');
$check(str_contains($submission,"'pending_review' : 'approved'"),'SOURCE: shared submission service retains normal clean/IP-review status choice');
$check(str_contains($controller,"['submit_selected','submit_all']")&&str_contains($view,'Submit Selected Valid Products')&&str_contains($view,'Submit All Valid Products'),'SOURCE: selected and all-valid actions are wired');
$check(str_contains($service,'b.designer_id=?')&&str_contains($controller,'p.designer_id=?'),'SOURCE: batch queries include seller ownership constraints');
$check(substr_count($view,'H::csrf()')>=4,'SOURCE: every batch mutation form includes CSRF input');
echo "NOTE: Behavioral workflow assertions run only in Phase122DatabaseIntegrationTest.php.\n";exit($fail?1:0);
