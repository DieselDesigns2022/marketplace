<?php

namespace App\Services;

use App\Core\Database as DB;

/** Authoritative status transition shared by single and batch product submission. */
class ProductSubmissionService
{
    public function submit(int $productId, int $designerId, int $sellerUserId, bool $confirmRights = false): array
    {
        $product=DB::row('select * from products where id=? and designer_id=?',[$productId,$designerId]);
        if(!$product)return ['ok'=>false,'status'=>'draft','error'=>'Product was not found.'];
        $errors=[];
        if(trim((string)$product['title'])===''||mb_strlen((string)$product['title'])>190)$errors[]='Enter a valid product title.';
        if(trim((string)$product['description'])==='')$errors[]='Full Description is required.';
        if($product['price']===null||!is_numeric($product['price'])||(float)$product['price']<0)$errors[]='Base Price must be reviewed and saved.';
        if(empty($product['category_id']))$errors[]='Category is required.';
        if(!in_array($product['ai_disclosure']??null,['No AI Used','AI Assisted','AI Generated'],true))$errors[]='AI Disclosure is required.';
        if(!DB::row('select id from product_images where product_id=? limit 1',[$productId]))$errors[]='At least one preview image is required.';
        if(($product['fulfillment_type']??'downloadable')==='downloadable'&&!DB::row('select id from product_files where product_id=? limit 1',[$productId]))$errors[]='At least one protected downloadable file is required.';
        if(($product['fulfillment_type']??'downloadable')==='google_drive'&&mb_strlen(trim((string)($product['manual_delivery_instructions']??'')))<5)$errors[]='Manual delivery instructions are required.';
        $reviewErrors=(new ProductImportReviewService())->errors($productId);
        $errors=array_values(array_unique(array_merge($errors,$reviewErrors)));
        if($errors)return ['ok'=>false,'status'=>'draft','error'=>implode(' ',$errors)];
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
