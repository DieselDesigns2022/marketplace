<?php

namespace App\Services;

use App\Core\Database as DB;

/** Authoritative status transition shared by single and batch product submission. */
class ProductSubmissionService
{
    public function submit(int $productId, int $designerId, int $sellerUserId, bool $confirmRights = false): array
    {
        $workflow = new ProductIpRiskWorkflow();
        $risk = $workflow->scanProduct($productId, $sellerUserId);
        if ($risk['requires_confirmation'] && !$confirmRights) {
            return ['ok'=>false, 'status'=>'draft', 'error'=>'Open this product and confirm your legal right to sell before submission.'];
        }
        if ($risk['requires_confirmation']) {
            $workflow->recordConfirmationForScan($productId, $sellerUserId, (int)$risk['scan_id']);
        }
        $requiresIpReview = !empty($risk['matches']) && !in_array($risk['state']['review_status'] ?? '', ['approved','published_flagged'], true);
        $status = $requiresIpReview ? 'pending_review' : 'approved';
        DB::exec('update products set status=?,rejection_reason=null,updated_at=now() where id=? and designer_id=?', [$status,$productId,$designerId]);
        return ['ok'=>true, 'status'=>$status, 'error'=>null];
    }
}
