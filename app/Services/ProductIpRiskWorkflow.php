<?php
namespace App\Services;

use App\Core\Database as DB;
use App\Core\Helpers as H;
use App\Repositories\IpRiskRepository;

class ProductIpRiskWorkflow
{
    public const CONFIRMATION_TEXT = 'I confirm I have the legal right to sell this design and any included wording, artwork, or references.';

    private IpRiskRepository $repo;
    private IpRiskScanner $scanner;

    public function __construct(?IpRiskRepository $repo = null, ?IpRiskScanner $scanner = null)
    {
        $this->repo = $repo ?: new IpRiskRepository();
        $this->scanner = $scanner ?: new IpRiskScanner();
    }

    public function scanProduct(int $productId, int $sellerUserId): array
    {
        $owner = $this->ownedProduct($productId, $sellerUserId);
        $input = $this->input($productId, $owner);
        $matches = $this->scanner->scan($input, $this->repo->enabledTermsWithAliases());
        $contentFingerprint = $this->contentFingerprint($input);
        $matchFingerprint = $this->matchFingerprint($matches);
        $scanId = $this->repo->saveScan($productId, (int)$owner['seller_user_id'], $contentFingerprint, $matchFingerprint, $matches);

        return [
            'scan_id' => $scanId,
            'matches' => $matches,
            'state' => $this->repo->state($productId),
            'requires_confirmation' => count($matches) > 0 && !$this->repo->hasConfirmation($productId, $scanId, (int)$owner['seller_user_id']),
        ];
    }

    public function currentReview(int $productId, int $sellerUserId): array
    {
        $owner = $this->ownedProduct($productId, $sellerUserId);
        $state = $this->repo->state($productId);
        $active = $this->repo->activeDetections($productId);
        $scanId = (int)($state['latest_scan_id'] ?? 0);

        return [
            'state' => $state,
            'matches' => $active,
            'requires_confirmation' => count($active) > 0 && $scanId > 0 && !$this->repo->hasConfirmation($productId, $scanId, (int)$owner['seller_user_id']),
        ];
    }

    public function recordCurrentConfirmation(int $productId, int $sellerUserId): void
    {
        $owner = $this->ownedProduct($productId, $sellerUserId);
        $state = $this->repo->state($productId);
        $scanId = (int)($state['latest_scan_id'] ?? 0);
        if ($scanId > 0) {
            $this->recordConfirmationForScan($productId, $sellerUserId, $scanId);
        }
    }

    public function recordConfirmationForScan(int $productId, int $sellerUserId, int $scanId): void
    {
        $owner = $this->ownedProduct($productId, $sellerUserId);
        $state = $this->repo->state($productId);
        if ((int)($state['latest_scan_id'] ?? 0) !== $scanId) {
            throw new \InvalidArgumentException('Seller confirmation must reference the latest authoritative IP risk scan.');
        }
        $this->repo->recordConfirmation($productId, $scanId, (int)$owner['seller_user_id'], self::CONFIRMATION_TEXT);
    }

    private function ownedProduct(int $productId, int $sellerUserId): array
    {
        $product = DB::row(
            'select p.*,d.user_id seller_user_id from products p join designers d on d.id=p.designer_id where p.id=? limit 1',
            [$productId]
        );
        if (!$product) {
            H::abort(404);
        }
        if ((int)$product['seller_user_id'] !== $sellerUserId) {
            H::abort(403);
        }
        return $product;
    }

    private function input(int $productId, array $product): array
    {
        $tags = array_column(
            DB::rows('select t.name from tags t join product_tags pt on pt.tag_id=t.id where pt.product_id=? order by t.name', [$productId]),
            'name'
        );
        $files = array_column(
            DB::rows('select original_name from product_files where product_id=? order by original_name', [$productId]),
            'original_name'
        );

        return [
            'title' => $product['title'] ?? '',
            'description' => trim(($product['short_description'] ?? '') . ' ' . ($product['description'] ?? '')),
            'tags' => $tags,
            'seo_title' => $product['seo_title'] ?? '',
            'seo_description' => $product['seo_description'] ?? '',
            'file_names' => $files,
        ];
    }

    private function contentFingerprint(array $input): string
    {
        foreach (['tags', 'file_names'] as $key) {
            $input[$key] = array_map(fn($value) => IpRiskScanner::normalize((string)$value, $key === 'file_names'), $input[$key] ?? []);
            sort($input[$key]);
        }
        foreach (['title', 'description', 'seo_title', 'seo_description'] as $key) {
            $input[$key] = IpRiskScanner::normalize((string)($input[$key] ?? ''));
        }
        return hash('sha256', json_encode($input, JSON_UNESCAPED_UNICODE));
    }

    private function matchFingerprint(array $matches): string
    {
        $stable = [];
        foreach ($matches as $match) {
            $stable[] = [
                (int)$match['risk_term_id'],
                (string)$match['matched_value_key'],
                (string)$match['category'],
                (string)$match['source_field'],
            ];
        }
        sort($stable);
        return hash('sha256', json_encode($stable, JSON_UNESCAPED_UNICODE));
    }
}
