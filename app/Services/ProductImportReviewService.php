<?php

namespace App\Services;

use App\Core\Database as DB;
use PDOException;
use Throwable;

/** Durable seller-review gates used only by CSV-imported products. */
class ProductImportReviewService
{
    public const LABELS=[
        'ai'=>'Review and explicitly choose an AI disclosure.',
        'licenses'=>'Review and explicitly configure Asset Moth licenses.',
        'fulfillment'=>'Review and explicitly choose Asset Moth fulfillment/delivery.',
        'price'=>'Review and explicitly save a valid Asset Moth price.',
        'source_type'=>'Review source product type and configure an eligible Asset Moth delivery method.',
    ];
    private function missing(Throwable $e): bool{return $e instanceof PDOException&&(($e->errorInfo[0]??null)==='42S02'||str_contains($e->getMessage(),'not found'));}
    public function add(int $productId,array $keys): void{foreach(array_unique($keys) as $key)if(isset(self::LABELS[$key]))DB::exec('insert ignore into product_import_requirements (product_id,requirement_key) values (?,?)',[$productId,$key]);}
    public function openKeys(int $productId): array{try{return array_column(DB::rows('select requirement_key from product_import_requirements where product_id=? and cleared_at is null order by id',[$productId]),'requirement_key');}catch(Throwable $e){if($this->missing($e))return[];throw $e;}}
    public function clearAfterExplicitSave(int $productId,array $keys): void{$keys=array_values(array_unique(array_filter($keys,fn($key)=>isset(self::LABELS[$key]))));if(!$keys)return;try{DB::exec('update product_import_requirements set cleared_at=now() where product_id=? and requirement_key in ('.implode(',',array_fill(0,count($keys),'?')).') and cleared_at is null',array_merge([$productId],$keys));}catch(Throwable $e){if(!$this->missing($e))throw $e;}}
    public function copyOpen(int $sourceId,int $targetId): void{$this->add($targetId,$this->openKeys($sourceId));}
    public function context(int $productId): array{try{$row=DB::row('select s.source_platform,i.normalized_data from product_import_sources s join product_import_items i on i.id=s.import_item_id where s.product_id=? limit 1',[$productId]);if(!$row)return[];$data=json_decode($row['normalized_data'],true,512,JSON_THROW_ON_ERROR);return['source_platform'=>$row['source_platform'],'source_type'=>$data['product_type']??''];}catch(Throwable $e){if($this->missing($e))return[];throw $e;}}
    public function errors(int $productId): array{try{$rows=DB::rows('select requirement_key from product_import_requirements where product_id=? and cleared_at is null order by id',[$productId]);$imported=(bool)DB::row('select id from product_import_requirements where product_id=? limit 1',[$productId]);}catch(Throwable $e){if($this->missing($e))return[];throw $e;}$errors=array_values(array_map(fn($r)=>self::LABELS[$r['requirement_key']]??'Complete imported-product review.',$rows));if($imported){$p=DB::row('select * from products where id=?',[$productId]);if($p){if(empty($p['category_id']))$errors[]='Choose an Asset Moth category.';if($p['price']===null||!is_numeric($p['price'])||(float)$p['price']<0)$errors[]='Review and explicitly save a valid Asset Moth price.';if(!in_array($p['ai_disclosure']??null,['No AI Used','AI Assisted','AI Generated'],true))$errors[]='Review and explicitly choose an AI disclosure.';if(!DB::row('select id from product_images where product_id=? limit 1',[$productId]))$errors[]='Add at least one preview image.';if(($p['fulfillment_type']??'downloadable')==='downloadable'&&!DB::row('select id from product_files where product_id=? limit 1',[$productId]))$errors[]='Upload at least one protected downloadable file.';}}return array_values(array_unique($errors));}
}
