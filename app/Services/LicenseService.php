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


    private static function personalIncludedLicense(): array
    {
        return [
            'product_license_id' => null,
            'license_type_id' => 0,
            'license_key' => self::PERSONAL_KEY,
            'name' => 'Personal',
            'description' => 'Personal use for one buyer. Digital resale, sharing, and redistribution are prohibited.',
            'price' => 0.00,
            'is_default' => true,
            'sort_order' => 10,
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
        $personal = self::personalIncludedLicense();
        if ($rows) {
            $licenses = array_map(function ($row) {
                return [
                    'product_license_id' => (int)$row['id'],
                    'license_type_id' => (int)$row['license_type_id'],
                    'license_key' => $row['license_key'],
                    'name' => $row['custom_name'] ?: $row['platform_name'],
                    'description' => $row['description'] ?: $row['platform_description'],
                    'price' => $row['license_key'] === self::PERSONAL_KEY ? 0.00 : (float)$row['price'],
                    'is_default' => $row['license_key'] === self::PERSONAL_KEY,
                    'sort_order' => (int)$row['sort_order'],
                ];
            }, $rows);
            $ordered = [];
            foreach ($licenses as $license) {
                if ($license['license_key'] === self::PERSONAL_KEY) {
                    $personal = $license;
                    $personal['is_default'] = true;
                    continue;
                }
                $ordered[] = $license;
            }
            array_unshift($ordered, $personal);
            return $ordered;
        }

        $licenses = [$personal];
        if (!empty($product['commercial_license_enabled'])) {
            $licenses[] = [
                'product_license_id' => null,
                'license_type_id' => 0,
                'license_key' => 'commercial',
                'name' => 'Commercial',
                'description' => 'Commercial use is allowed under this product license. Digital resale, sharing, and redistribution are prohibited.',
                'price' => (float)($product['commercial_license_price'] ?? 0),
                'is_default' => false,
                'sort_order' => 20,
            ];
        }
        return $licenses;
    }

    public static function defaultLicense(array $product): array
    {
        return self::selectedLicenses($product, [self::PERSONAL_KEY])[0];
    }

    public static function normalizeLicenseKeys(mixed $licenseKeys): array
    {
        if (is_string($licenseKeys)) $licenseKeys = preg_split('/[,\s]+/', $licenseKeys) ?: [];
        if (!is_array($licenseKeys)) $licenseKeys = [];
        $keys = [self::PERSONAL_KEY];
        foreach ($licenseKeys as $key) {
            $key = trim((string)$key);
            if ($key !== '' && !in_array($key, $keys, true)) $keys[] = $key;
        }
        return $keys;
    }

    public static function selectedLicenses(array $product, mixed $licenseKeys): array
    {
        $requested = self::normalizeLicenseKeys($licenseKeys);
        $available = [];
        foreach (self::productLicenses($product) as $license) $available[$license['license_key']] = $license;
        if (!isset($available[self::PERSONAL_KEY])) $available[self::PERSONAL_KEY] = self::personalIncludedLicense();
        $selected = [];
        foreach ($requested as $key) {
            if (!isset($available[$key])) return [];
            $selected[] = $available[$key];
        }
        return $selected;
    }

    public static function purchasableLicense(int $productId, ?string $licenseKey): ?array
    {
        $licenses = self::purchasableLicenses($productId, $licenseKey);
        return $licenses[0] ?? null;
    }

    public static function purchasableLicenses(int $productId, mixed $licenseKeys): array
    {
        $product = DB::row('select * from products where id=? and status="approved"', [$productId]);
        if (!$product) return [];
        return self::selectedLicenses($product, $licenseKeys);
    }

    public static function keyList(array $licenses): string
    {
        return implode(',', array_column($licenses, 'license_key'));
    }

    public static function nameList(array $licenses): string
    {
        return implode(', ', array_column($licenses, 'name'));
    }

    public static function descriptionList(array $licenses): string
    {
        return implode("\n", array_values(array_filter(array_map(static fn($license) => $license['name'].': '.($license['description'] ?? ''), $licenses))));
    }

    public static function priceTotal(array $licenses): float
    {
        $total = 0.00;
        foreach ($licenses as $license) {
            if (($license['license_key'] ?? '') !== self::PERSONAL_KEY) $total += (float)($license['price'] ?? 0);
        }
        return round($total, 2);
    }

    public static function snapshot(array $licenses): string
    {
        $snapshot = json_encode(array_map(static fn($license) => [
            'key' => $license['license_key'],
            'name' => $license['name'],
            'description' => $license['description'] ?? '',
            'included' => $license['license_key'] === self::PERSONAL_KEY,
            'price' => $license['license_key'] === self::PERSONAL_KEY ? 0.00 : (float)($license['price'] ?? 0),
        ], $licenses), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return $snapshot === false ? '[]' : $snapshot;
    }

    public static function sellerPresets(int $designerId): array
    {
        try {
            return DB::rows('select slp.*,lt.license_key,lt.name,lt.description platform_description,lt.sort_order platform_sort_order from seller_license_presets slp join license_types lt on lt.id=slp.license_type_id where slp.designer_id=? and lt.is_active=1 order by slp.sort_order,lt.sort_order,lt.name', [$designerId]);
        } catch (Throwable $e) {
            if (!self::missingTable($e)) throw $e;
            return [];
        }
    }

    public static function presetLicensesForProductForm(int $designerId): array
    {
        $presets = [];
        foreach (self::sellerPresets($designerId) as $preset) {
            if (!empty($preset['is_enabled'])) {
                $presets[] = [
                    'license_type_id' => (int)$preset['license_type_id'],
                    'license_key' => $preset['license_key'],
                    'price' => (float)($preset['price'] ?? 0),
                    'description' => $preset['description'] ?: ($preset['platform_description'] ?? ''),
                    'sort_order' => (int)($preset['sort_order'] ?? $preset['platform_sort_order'] ?? 0),
                ];
            }
        }
        return $presets;
    }

    public static function syncSellerPresets(int $designerId, array $post): array
    {
        $types = self::platformTypes();
        $enabled = $post['preset_enabled'] ?? [];
        $prices = $post['preset_price'] ?? [];
        $descriptions = $post['preset_description'] ?? [];
        $errors = [];
        $rows = [];
        foreach ($types as $type) {
            $key = $type['license_key'];
            $price = '0.00';
            if ($key !== self::PERSONAL_KEY) {
                $raw = trim((string)($prices[$key] ?? '0.00'));
                if ($raw === '') $raw = '0.00';
                if (!is_numeric($raw) || (float)$raw < 0) { $errors[] = ($type['name'] ?? $key) . ' preset price must be a valid non-negative amount.'; $raw = '0.00'; }
                $price = number_format((float)$raw, 2, '.', '');
            }
            $rows[] = [$designerId, (int)$type['id'], ($key === self::PERSONAL_KEY || isset($enabled[$key])) ? 1 : 0, $price, trim((string)($descriptions[$key] ?? '')), (int)($type['sort_order'] ?? 0)];
        }
        if ($errors) return $errors;
        DB::begin();
        try {
            foreach ($rows as $row) {
                DB::exec('insert into seller_license_presets (designer_id,license_type_id,is_enabled,price,description,sort_order) values (?,?,?,?,?,?) on duplicate key update is_enabled=values(is_enabled),price=values(price),description=values(description),sort_order=values(sort_order),updated_at=now()', $row);
            }
            DB::commit();
        } catch (Throwable $e) { DB::rollBack(); if (!self::missingTable($e)) throw $e; return ['License preset database table is unavailable. Run the latest migration.']; }
        return [];
    }

    public static function normalizePosted(array $product, array $post): array
    {
        $types = self::platformTypes();
        if (!$types || min(array_map(static fn($type) => (int)$type['id'], $types)) <= 0) {
            return [[], ['License settings are temporarily unavailable until the licensing database migration has been run.']];
        }
        $enabled = $post['license_enabled'] ?? [];
        $descriptions = $post['license_description'] ?? [];
        $prices = $post['license_price'] ?? [];
        $licenses = [];
        $errors = [];
        foreach ($types as $type) {
            $key = $type['license_key'];
            if ($key !== self::PERSONAL_KEY && !isset($enabled[$key])) continue;

            $price = '0.00';
            if ($key !== self::PERSONAL_KEY) {
                $rawPrice = trim((string)($prices[$key] ?? '0.00'));
                if ($rawPrice === '') $rawPrice = '0.00';
                if (!is_numeric($rawPrice) || (float)$rawPrice < 0) {
                    $errors[] = ($type['name'] ?? $key) . ' license price must be a valid non-negative amount.';
                    $rawPrice = '0.00';
                }
                $price = number_format((float)$rawPrice, 2, '.', '');
            }

            $licenses[$key] = [
                'license_type_id' => (int)$type['id'],
                'license_key' => $key,
                'price' => $price,
                'description' => trim((string)($descriptions[$key] ?? '')),
                'sort_order' => (int)($type['sort_order'] ?? 0),
                'is_default' => $key === self::PERSONAL_KEY,
            ];
        }
        if (!isset($licenses[self::PERSONAL_KEY])) $errors[] = 'Personal license must be available.';
        return [$licenses, $errors];
    }

    public static function syncProductLicenses(int $productId, array $licenses): void
    {
        foreach ($licenses as $license) {
            if ((int)($license['license_type_id'] ?? 0) <= 0) {
                throw new \InvalidArgumentException('Product license rows require a persisted platform license type.');
            }
        }

        $ownsTransaction = !DB::pdo()->inTransaction();

        try {
            if ($ownsTransaction) {
                DB::begin();
            }

            DB::exec('delete from product_license_types where product_id=?', [$productId]);

            foreach ($licenses as $license) {
                DB::exec(
                    'insert into product_license_types (product_id,license_type_id,is_enabled,is_default,price,description,sort_order) values (?,?,?,?,?,?,?)',
                    [
                        $productId,
                        $license['license_type_id'],
                        1,
                        $license['is_default'] ? 1 : 0,
                        $license['price'],
                        $license['description'],
                        $license['sort_order'],
                    ]
                );
            }

            if ($ownsTransaction) {
                DB::commit();
            }
        } catch (Throwable $e) {
            if ($ownsTransaction && DB::pdo()->inTransaction()) {
                DB::rollBack();
            }

            throw $e;
        }
    }
}
