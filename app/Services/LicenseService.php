<?php

namespace App\Services;

use App\Core\Database as DB;
use PDOException;
use Throwable;

class LicenseService
{
    public const PERSONAL_KEY = 'personal';

    private static function personalFallbackType(): array
    {
        return [
            'id' => 0,
            'license_key' => self::PERSONAL_KEY,
            'name' => 'Personal',
            'description' => 'Personal use for one buyer. Digital resale, sharing, and redistribution are prohibited.',
            'sort_order' => 10,
            'is_active' => 1,
        ];
    }

    private static function missingTable(Throwable $e): bool
    {
        return $e instanceof PDOException && (($e->errorInfo[0] ?? null) === '42S02' || str_contains($e->getMessage(), "Base table or view not found"));
    }

    public static function platformTypes(): array
    {
        try {
            $types = DB::rows('select * from license_types where is_active=1 order by sort_order,name');
            if ($types) return $types;
        } catch (Throwable $e) {
            if (!self::missingTable($e)) throw $e;
        }
        return [self::personalFallbackType()];
    }

    public static function productLicenses(array $product): array
    {
        try {
            $rows = DB::rows('select plt.*,lt.license_key,lt.name platform_name,lt.description platform_description from product_license_types plt join license_types lt on lt.id=plt.license_type_id where plt.product_id=? and plt.is_enabled=1 and lt.is_active=1 order by plt.sort_order,lt.sort_order,lt.name', [(int)$product['id']]);
        } catch (Throwable $e) {
            if (!self::missingTable($e)) throw $e;
            $rows = [];
        }
        if ($rows) {
            return array_map(function ($row) {
                return [
                    'product_license_id' => (int)$row['id'],
                    'license_type_id' => (int)$row['license_type_id'],
                    'license_key' => $row['license_key'],
                    'name' => $row['custom_name'] ?: $row['platform_name'],
                    'description' => $row['description'] ?: $row['platform_description'],
                    'price' => (float)$row['price'],
                    'is_default' => (int)$row['is_default'] === 1,
                    'sort_order' => (int)$row['sort_order'],
                ];
            }, $rows);
        }

        $personal = [
            'product_license_id' => null,
            'license_type_id' => 0,
            'license_key' => self::PERSONAL_KEY,
            'name' => 'Personal',
            'description' => 'Personal use for one buyer. Digital resale, sharing, and redistribution are prohibited.',
            'price' => (float)($product['price'] ?? 0),
            'is_default' => true,
            'sort_order' => 10,
        ];
        $licenses = [$personal];
        if (!empty($product['commercial_license_enabled'])) {
            $licenses[] = [
                'product_license_id' => null,
                'license_type_id' => 0,
                'license_key' => 'commercial',
                'name' => 'Commercial',
                'description' => 'Commercial use is allowed under this product license. Digital resale, sharing, and redistribution are prohibited.',
                'price' => (float)($product['price'] ?? 0) + (float)($product['commercial_license_price'] ?? 0),
                'is_default' => false,
                'sort_order' => 20,
            ];
        }
        return $licenses;
    }

    public static function defaultLicense(array $product): array
    {
        $licenses = self::productLicenses($product);
        foreach ($licenses as $license) if ($license['is_default']) return $license;
        return $licenses[0];
    }

    public static function purchasableLicense(int $productId, ?string $licenseKey): ?array
    {
        $product = DB::row('select * from products where id=? and status="approved"', [$productId]);
        if (!$product) return null;
        $licenseKey = trim((string)$licenseKey);
        $licenses = self::productLicenses($product);
        if ($licenseKey === '') return self::defaultLicense($product);
        foreach ($licenses as $license) if ($license['license_key'] === $licenseKey) return $license;
        return null;
    }

    public static function normalizePosted(array $product, array $post): array
    {
        $types = self::platformTypes();
        if (!$types || min(array_map(static fn($type) => (int)$type['id'], $types)) <= 0) {
            return [[], ['License settings are temporarily unavailable until the licensing database migration has been run.']];
        }
        $enabled = $post['license_enabled'] ?? [];
        $prices = $post['license_price'] ?? [];
        $descriptions = $post['license_description'] ?? [];
        $orders = $post['license_sort_order'] ?? [];
        $default = trim((string)($post['default_license_key'] ?? self::PERSONAL_KEY));
        $licenses = [];
        $errors = [];
        foreach ($types as $type) {
            $key = $type['license_key'];
            if (!isset($enabled[$key])) continue;
            $price = $prices[$key] ?? $product['price'] ?? '0.00';
            if (!is_numeric($price) || (float)$price < 0) $errors[] = $type['name'].' license price must be a valid non-negative amount.';
            $licenses[$key] = [
                'license_type_id' => (int)$type['id'],
                'license_key' => $key,
                'price' => number_format((float)$price, 2, '.', ''),
                'description' => trim((string)($descriptions[$key] ?? '')),
                'sort_order' => (int)($orders[$key] ?? $type['sort_order'] ?? 0),
                'is_default' => $key === $default,
            ];
        }
        if (!$licenses) $errors[] = 'At least one license must be enabled.';
        if (!isset($licenses[$default])) $errors[] = 'Default license must be one of the enabled licenses.';
        return [$licenses, $errors];
    }

    public static function syncProductLicenses(int $productId, array $licenses): void
    {
        foreach ($licenses as $license) {
            if ((int)($license['license_type_id'] ?? 0) <= 0) {
                throw new \InvalidArgumentException('Product license rows require a persisted platform license type.');
            }
        }

        DB::begin();
        try {
            DB::exec('delete from product_license_types where product_id=?', [$productId]);
            foreach ($licenses as $license) {
                DB::exec('insert into product_license_types (product_id,license_type_id,is_enabled,is_default,price,description,sort_order) values (?,?,?,?,?,?,?)', [$productId,$license['license_type_id'],1,$license['is_default'] ? 1 : 0,$license['price'],$license['description'],$license['sort_order']]);
            }
            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
