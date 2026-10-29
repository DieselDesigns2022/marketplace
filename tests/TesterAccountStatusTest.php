<?php
$root=dirname(__DIR__);
$failures=0;

$check=function(bool $ok,string $message)use(&$failures){
    echo ($ok?'PASS':'FAIL').": $message\n";
    if(!$ok)$failures++;
};

$schema=file_get_contents($root.'/database/schema.sql');
$migration=file_get_contents($root.'/database/migrations/2026_10_29_tester_account_status.sql');
$controller=file_get_contents($root.'/app/Controllers/AdminController.php');
$view=file_get_contents($root.'/app/Views/admin/users.php');
$recognition=file_get_contents($root.'/app/Services/CreatorRecognitionService.php');

$check(str_contains($schema,'is_tester TINYINT(1) NOT NULL DEFAULT 0'),'Canonical users schema defaults accounts to non-tester.');
$check(str_contains($migration,'ADD COLUMN IF NOT EXISTS is_tester'),'Tester migration is safely rerunnable.');
$check(str_contains($controller,"action==='save_tester'"),'Admin can change tester status.');
$check(str_contains($controller,"tester_status_change"),'Tester status change refreshes seller recognition.');
$check(str_contains($view,'Mark as Tester')&&str_contains($view,'Remove Tester'),'Admin Users page has tester controls.');
$check(str_contains($recognition,'u.is_tester=1'),'Automatic Founder candidate selection is tester-only.');
$check(str_contains($recognition,'$d[\'status\']===\'approved\'&&$isTester&&$count>=10'),'Automatic Founder earning requires tester status.');
$check(str_contains($recognition,'Founder recognition is available only to tester accounts.'),'Manual Founder grant/restore is tester-protected.');
$check(substr_count($recognition,'$position!==null&&$isTester&&self::founderActive')>=2,'Founder badge activation requires current tester status.');

exit($failures?1:0);
