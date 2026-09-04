<?php

namespace App\Services;

use App\Core\Database as DB;
use RuntimeException;

/** Keeps bulk listings as ordinary products while storing only batch membership. */
class ProductBatchService
{
    public const COPY_FIELDS = [
        'short_description', 'description', 'price', 'category_id', 'ai_disclosure',
        'seo_title', 'seo_description', 'fulfillment_type', 'manual_delivery_instructions',
        'is_hand_drawn',
    ];

    public function batch(int $batchId, int $designerId): ?array
    {
        return DB::row('select * from product_batches where id=? and designer_id=?', [$batchId, $designerId]);
    }

    public function products(int $batchId, int $designerId): array
    {
        return DB::rows('select p.*,bpi.sort_order,bpi.validation_errors,bpi.submission_errors,(select count(*) from product_images i where i.product_id=p.id) image_count,(select count(*) from product_files f where f.product_id=p.id) file_count from product_batch_items bpi join product_batches b on b.id=bpi.batch_id join products p on p.id=bpi.product_id and p.designer_id=b.designer_id where bpi.batch_id=? and b.designer_id=? order by bpi.sort_order,bpi.id', [$batchId, $designerId]);
    }

    public function copy(int $batchId, int $designerId, int $sourceId, array $fields, array $targetIds = []): int
    {
        $batch = $this->batch($batchId, $designerId);
        $source = DB::row('select p.* from products p join product_batch_items i on i.product_id=p.id where i.batch_id=? and p.id=? and p.designer_id=?', [$batchId, $sourceId, $designerId]);
        if (!$batch || !$source) throw new RuntimeException('Batch or template product was not found.');
        $requested = $fields;
        $fields = array_values(array_intersect(self::COPY_FIELDS, $fields));
        $copyTags = in_array('tags', $requested, true);
        $copyLicenses = in_array('licenses', $requested, true);
        $targetIds = array_values(array_unique(array_map('intval', $targetIds)));
        $count = 0;
        foreach ($this->products($batchId, $designerId) as $target) {
            if ((int)$target['id'] === $sourceId || $target['status'] !== 'draft') continue;
            if ($targetIds && !in_array((int)$target['id'], $targetIds, true)) continue;
            if ($fields) {
                $sets = implode(',', array_map(fn($f) => "$f=?", $fields));
                $params = array_map(fn($f) => $source[$f], $fields);
                array_push($params, $target['id'], $designerId);
                DB::exec("update products set $sets,updated_at=now() where id=? and designer_id=? and status='draft'", $params);
            }
            if ($copyTags) {
                DB::exec('delete from product_tags where product_id=?', [$target['id']]);
                DB::exec('insert ignore into product_tags (product_id,tag_id) select ?,tag_id from product_tags where product_id=?', [$target['id'], $sourceId]);
            }
            if ($copyLicenses) {
                DB::exec(
                    'delete from product_license_types where product_id=?',
                    [$target['id']]
                );

                DB::exec(
                    'insert into product_license_types
                     (product_id,license_type_id,is_enabled,is_default,price,custom_name,description,sort_order)
                     select ?,license_type_id,is_enabled,is_default,price,custom_name,description,sort_order
                     from product_license_types
                     where product_id=?',
                    [$target['id'], $sourceId]
                );

                DB::exec(
                    'update products
                     set commercial_license_enabled=?,
                         commercial_license_price=?,
                         pod_allowed=?,
                         updated_at=now()
                     where id=? and designer_id=?',
                    [
                        $source['commercial_license_enabled'],
                        $source['commercial_license_price'],
                        $source['pod_allowed'],
                        $target['id'],
                        $designerId
                    ]
                );

                (new ProductImportReviewService())
                    ->clearAfterExplicitSave(
                        (int)$target['id'],
                        ['licenses']
                    );
            }
            $count++;
        }
        return $count;
    }
}
