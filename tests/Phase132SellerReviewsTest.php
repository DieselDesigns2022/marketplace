<?php
declare(strict_types=1);
$root=dirname(__DIR__);$service=file_get_contents($root.'/app/Services/SellerReviewService.php');$migration=file_get_contents($root.'/database/migrations/2026_09_18_phase_13_2_seller_reviews.sql');$liveFix=file_get_contents($root.'/database/migrations/2026_09_20_phase_13_2_custom_design_reviews_live_fix.sql');$custom=file_get_contents($root.'/app/Controllers/CustomDesignController.php');$buyer=file_get_contents($root.'/app/Controllers/BuyerController.php');$controller=file_get_contents($root.'/app/Controllers/ReviewController.php');$public=file_get_contents($root.'/app/Views/public/store.php');$routes=file_get_contents($root.'/public/index.php');
$checks=[
 'Phase 13.2 uses a separate live-fix migration'=>count(glob($root.'/database/migrations/*phase_13_2*'))===2,
 'custom review linkage is nullable-product safe'=>str_contains($liveFix,'MODIFY product_id BIGINT NULL')&&str_contains($liveFix,'custom_service_id BIGINT NULL')&&str_contains($liveFix,'seller_reviews_purchase_link_check')&&str_contains($service,"\$item['custom_service_id']"),
 'custom final delivery completes before eligibility'=>strpos($custom,'$delivered=readfile($real)')<strpos($custom,'markCustomFinalDownloaded'),
 'only buyer final custom delivery activates eligibility'=>str_contains($custom,"\$f['file_kind']==='final'")&&str_contains($service,'co.status="completed"')&&str_contains($service,'f.file_kind="final"'),
 'review form preserves type-specific payment eligibility'=>str_contains($controller,'oi.custom_service_id is null and o.payment_status="paid"')&&str_contains($controller,'oi.custom_service_id is not null and o.payment_status in ("paid","partially_refunded")')&&!str_contains($controller,'and o.payment_status in ("paid","partially_refunded") and oi.review_eligible_at'),
 'delivery completes before eligibility'=>strpos($buyer,'$delivered=readfile($real)')<strpos($buyer,'markDownloaded'),
 'failed read does not activate eligibility'=>str_contains($buyer,'if($delivered===false)')&&strpos($buyer,'if($delivered===false)')<strpos($buyer,'markDownloaded'),
 'notification failures are isolated'=>str_contains($service,"reportFailure('review availability notification'")&&str_contains($buyer,"reportFailure('post-download review eligibility'"),
 'self-review rejected server-side'=>str_contains($service,"seller_user_id']===\$buyerId"),
 'one review per item'=>str_contains($migration,'seller_reviews_order_item_unique(order_item_id)'),
 'query indexes include buyer product order rating status and dates'=>count(array_filter(['seller_reviews_buyer_idx','seller_reviews_product_idx','seller_reviews_order_idx','seller_reviews_rating_idx','seller_reviews_status_idx','seller_reviews_reviewed_idx','seller_reviews_created_idx'],fn($v)=>str_contains($migration,$v)))===7,
 'whole-star and text validation'=>str_contains($service,"preg_match('/^[1-5]$/',\$raw)")&&str_contains($service,'mb_strlen($text)>2000'),
 'centralized recalculation'=>substr_count($service,'function recalculate(')===1,
 'reply moderation preserved'=>str_contains($migration,'seller_review_reply_moderation_audits')&&!str_contains($service,'delete from seller_review_replies'),
 'removed replies stay removed'=>str_contains($service,'An Admin-removed response cannot be edited or republished.'),
 'report lifecycle persisted'=>str_contains($service,'function resolveReport')&&str_contains($migration,'resolved_at DATETIME'),
 'moderation note required'=>str_contains($service,"\$note===''" )&&str_contains($migration,'internal_note VARCHAR(2000) NOT NULL'),
 'safe unexpected error handling'=>str_contains($controller,'private function safeMessage')&&str_contains($controller,'NotificationService::reportFailure'),
 'complete Admin filters'=>count(array_filter(['seller','buyer','product','rating','date_from','date_to','reported','status'],fn($v)=>str_contains($controller,"'$v'")))===8,
 'seller has no destructive review route'=>!preg_match("#/seller/reviews[^'\"]*(delete|hide|rating)#",$routes),
 'public hides removed replies'=>str_contains($public,"reply_moderation_status']==='published'"),
 'historical served evidence only'=>str_contains($migration,"WHERE status='served'")&&str_contains($migration,'intentionally emits no notifications'),
];
$failed=[];foreach($checks as $name=>$ok){echo($ok?'PASS':'FAIL').": $name\n";if(!$ok)$failed[]=$name;}if($failed){fwrite(STDERR,count($failed)." Phase 13.2 checks failed.\n");exit(1);}echo count($checks)." Phase 13.2 structural checks passed.\n";
