<?php

namespace App\Controllers;
use App\Core\Database as DB;
use App\Core\Helpers as H;
use App\Services\LicenseService;
use App\Services\WatermarkService;
class SellerController
{
    private function d()
    {
        return DB::row('select * from designers where user_id=?', [H::user()['id']]);

    }
    private function latestApplication()
    {
        return DB::row( 'select * from designer_applications where user_id=? order by created_at desc, id desc limit 1', [H::user()['id']] );

    }
    private function slugTaken(string $slug, ?int $ignoreApplicationId = null): bool
    {
        $designer = DB::row('select id from designers where store_slug=? limit 1', [$slug]);
        if ($designer)
        {
            return true;

        }
        $params = [$slug];
        $sql = 'select id from designer_applications where desired_slug=? and status in ("pending","approved")';
        if ($ignoreApplicationId)
        {
            $sql .= ' and id<>?';
            $params[] = $ignoreApplicationId;

        }
        return (bool) DB::row($sql . ' limit 1', $params);

    }
    private function storeSlugTaken(string $slug, int $designerId): bool
    {
        return (bool) DB::row( 'select id from designers where store_slug=? and id<>? limit 1', [$slug, $designerId] );

    }
    private function normalizeUrl(string $url): string
    {
        $url = trim($url);
        if ($url !== '' && !preg_match('#^https?://#i', $url))
        {
            $url = 'https://' . $url;

        }
        return $url;

    }

    private function normalizeSocialUrl(string $url, string $label, array &$errors): string
    {
        $url = $this->normalizeUrl($url);
        if ($url === '') return '';
        if (!filter_var($url, FILTER_VALIDATE_URL) || !in_array(strtolower(parse_url($url, PHP_URL_SCHEME) ?: ''), ['http','https'], true)) {
            $errors[] = $label . ' URL must be a valid http or https link.';
            return '';
        }
        return $url;
    }
    private function socialValues(array &$errors): array
    {
        $labels = ['facebook_url'=>'Facebook','instagram_url'=>'Instagram','tiktok_url'=>'TikTok','pinterest_url'=>'Pinterest','etsy_url'=>'Etsy','shopify_url'=>'Shopify'];
        $values = [];
        foreach ($labels as $field => $label) $values[$field] = $this->normalizeSocialUrl($_POST[$field] ?? '', $label, $errors);
        return $values;
    }
    private function uploadPublicImage(string $field, string $folder, array &$errors): ?string
    {
        if (empty($_FILES[$field]['tmp_name']))
        {
            return null;

        }
        if (($_FILES[$field]['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK)
        {
            $errors[] = ucfirst($field) . ' upload failed.';
            return null;

        }
        if (($_FILES[$field]['size'] ?? 0) > 15 * 1024 * 1024)
        {
            $errors[] = ucfirst($field) . ' must be 15MB or smaller.';
            return null;

        }
        $ext = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true))
        {
            $errors[] = ucfirst($field) . ' must be a JPG, PNG, or WEBP image.';
            return null;

        }
        if (!@getimagesize($_FILES[$field]['tmp_name']))
        {
            $errors[] = ucfirst($field) . ' must be a valid image file.';
            return null;

        }
        $dir = public_path('uploads/' . $folder);
        if (!is_dir($dir))
        {
            mkdir($dir, 0755, true);

        }
        $name = bin2hex(random_bytes(12)) . '.' . $ext;
        if (!move_uploaded_file($_FILES[$field]['tmp_name'], $dir . '/' . $name))
        {
            $errors[] = ucfirst($field) . ' could not be saved.';
            return null;

        }
        return '/uploads/' . $folder . '/' . $name;

    }
    private function applicationValues(): array
    {
        return [ 'display_name' => trim($_POST['display_name'] ?? ''), 'desired_slug' => trim($_POST['desired_slug'] ?? ''), 'bio' => trim($_POST['bio'] ?? ''), 'portfolio_url' => trim($_POST['portfolio_url'] ?? ''), 'social_links' => trim($_POST['social_links'] ?? ''), 'design_types' => trim($_POST['design_types'] ?? ''), 'uses_ai' => trim($_POST['uses_ai'] ?? ''), 'agreement' => isset($_POST['agreement']), ];

    }
    private function validateApplication(array $v, ?int $ignoreApplicationId = null): array
    {
        $errors = [];
        if ($v['display_name'] === '')
        {
            $errors[] = 'Display name is required.';

        }
        if ($v['desired_slug'] === '')
        {
            $errors[] = 'Desired store slug is required.';

        }
        elseif (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $v['desired_slug']))
        {
            $errors[] = 'Store URL can only contain lowercase letters, numbers, and hyphens.';

        }
        elseif ($this->slugTaken($v['desired_slug'], $ignoreApplicationId))
        {
            $errors[] = 'That store URL is already taken. Please choose another.';

        }
        if (mb_strlen($v['bio']) < 25)
        {
            $errors[] = 'Bio must be at least 25 characters.';

        }
        if ($v['design_types'] === '')
        {
            $errors[] = 'Please tell us what type of designs you sell.';

        }
        if (!in_array($v['uses_ai'], ['No', 'Yes, AI assisted', 'Yes, AI generated', 'Sometimes'], true))
        {
            $errors[] = 'Please select how you use AI in your design process.';

        }
        if (!$v['agreement'])
        {
            $errors[] = 'You must agree to the original work and product approval requirements.';

        }
        return $errors;

    }
    public function apply()
    {
        H::requireLogin();
        $designer = $this->d();
        if ($designer && $designer['status'] === 'approved')
        {
            H::flash('success', 'Your designer application has been approved. You can now access your seller dashboard.');
            H::redirect('/seller');

        }
        $application = $this->latestApplication();
        $errors = [];
        $values = $application ?: [ 'display_name' => '', 'desired_slug' => '', 'bio' => '', 'portfolio_url' => '', 'social_links' => '', 'design_types' => '', 'uses_ai' => '', 'agreement' => false, ];
        if ($_POST)
        {
            if ($application && $application['status'] === 'pending')
           {
                H::flash('warning', 'Your application is currently waiting for review.');
                H::redirect('/apply');

           }
            $values = $this->applicationValues();
            $errors = $this->validateApplication( $values, $application && $application['status'] === 'denied' ? (int) $application['id'] : null );
            if (!$errors)
           {
                DB::exec( 'insert into designer_applications (user_id,display_name,desired_slug,bio,portfolio_url,social_links,design_types,uses_ai,agreement,status) values (?,?,?,?,?,?,?,?,?,"pending")', [ H::user()['id'], $values['display_name'], $values['desired_slug'], $values['bio'], $values['portfolio_url'], $values['social_links'], $values['design_types'], $values['uses_ai'], 1, ] );
                $applicationId = DB::id();
                DB::exec( 'insert into admin_logs (admin_user_id,action,entity_type,entity_id,metadata) values (?,?,?,?,?)', [ null, 'submitted_designer_application', 'designer_application', $applicationId, json_encode(['user_id' => H::user()['id']]), ] );
                H::flash('success', 'Your designer application has been submitted and is waiting for review.');
                H::redirect('/apply');

           }

        }
        H::view('seller/apply', [ 'application' => $application, 'errors' => $errors, 'values' => $values, 'meta' => ['title' => 'Apply to Sell | Asset Moth', 'description' => 'Apply for a reviewed designer storefront on Asset Moth.', 'canonical' => H::canonical('/apply'), 'robots' => 'noindex,follow'] ]);

    }
    public function home()
    {
        H::requireSeller();
        $d = $this->d();
        if (!$d || $d['status'] !== 'approved')
        {
            H::flash('warning', 'You need an approved designer account before accessing the seller dashboard.');
            H::redirect('/apply');

        }
        $stats = DB::row( 'select count(*) product_count, coalesce(sum(sales_count),0) sales_count from products where designer_id=?', [$d['id']] );
        H::view('seller/home', [ 'd' => $d, 'stats' => $stats, ]);

    }
    public function storeSettings()
    {
        H::requireSeller();
        $d = $this->d();
        if (!$d || $d['status'] !== 'approved')
        {
            H::flash('warning', 'You need an approved designer account before accessing the seller dashboard.');
            H::redirect('/apply');

        }
        $errors = [];
        if ($_POST)
        {
            $display = trim($_POST['display_name'] ?? '');
            $slug = trim($_POST['store_slug'] ?? '');
            $bio = trim($_POST['bio'] ?? '');
            $website = $this->normalizeSocialUrl($_POST['website_url'] ?? '', 'Website', $errors);
            $social = trim($_POST['social_links'] ?? '');
            $socialFields = $this->socialValues($errors);
            $announcement = trim($_POST['announcement'] ?? '');
            $seoTitle = trim($_POST['seo_title'] ?? '');
            $seoDescription = trim($_POST['seo_description'] ?? '');
            if ($display === '')
           {
                $errors[] = 'Store display name is required.';

           }
            if ($slug === '' || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug))
           {
                $errors[] = 'Store slug must use lowercase letters, numbers, and hyphens only.';

           }
            elseif ($this->storeSlugTaken($slug, (int) $d['id']))
           {
                $errors[] = 'That store URL is already taken. Please choose another.';

           }
            if (mb_strlen($bio) > 1200)
           {
                $errors[] = 'Bio must be 1,200 characters or fewer.';

           }
            if (mb_strlen($seoTitle) > 70)
           {
                $errors[] = 'SEO title must be 70 characters or fewer.';

           }
            if (mb_strlen($seoDescription) > 170)
           {
                $errors[] = 'SEO description must be 170 characters or fewer.';

           }

            $avatar = $this->uploadPublicImage('avatar', 'store_avatars', $errors);
            $banner = $this->uploadPublicImage('banner', 'store_banners', $errors);
            if (!$errors)
           {
                DB::exec( 'update designers set display_name=?,store_slug=?,bio=?,website_url=?,social_links=?,facebook_url=?,instagram_url=?,tiktok_url=?,pinterest_url=?,etsy_url=?,shopify_url=?,announcement=?,seo_title=?,seo_description=?,avatar_path=coalesce(?,avatar_path),banner_path=coalesce(?,banner_path),updated_at=now() where id=? and user_id=?', [ $display, $slug, $bio, $website, $social, $socialFields['facebook_url'], $socialFields['instagram_url'], $socialFields['tiktok_url'], $socialFields['pinterest_url'], $socialFields['etsy_url'], $socialFields['shopify_url'], $announcement, $seoTitle, $seoDescription, $avatar, $banner, $d['id'], H::user()['id'], ] );
                H::flash('success', 'Store settings updated.');
                H::redirect('/seller/store');

           }

        }
        H::view('seller/store', [ 'd' => $this->d(), 'errors' => $errors, ]);

    }
    private function productValues(array $existing = []): array
    {
        $price = $_POST['price'] ?? ($existing['price'] ?? '0.00');
        $commercialEnabled = isset($_POST['license_enabled']['commercial']);
        $commercialLicensePrice = $_POST['license_price']['commercial'] ?? ($existing['commercial_license_price'] ?? '0.00');
        return [ 'title' => trim($_POST['title'] ?? $existing['title'] ?? ''), 'slug' => H::slug(trim($_POST['slug'] ?? $existing['slug'] ?? '')), 'short_description' => trim($_POST['short_description'] ?? $existing['short_description'] ?? ''), 'description' => trim($_POST['description'] ?? $existing['description'] ?? ''), 'price' => $price, 'fulfillment_type' => in_array(($_POST['fulfillment_type'] ?? ($existing['fulfillment_type'] ?? 'downloadable')), ['downloadable','google_drive'], true) ? ($_POST['fulfillment_type'] ?? ($existing['fulfillment_type'] ?? 'downloadable')) : 'downloadable', 'manual_delivery_instructions' => trim($_POST['manual_delivery_instructions'] ?? ($existing['manual_delivery_instructions'] ?? '')), 'category_id' => ($_POST['category_id'] ?? ($existing['category_id'] ?? '')) ?: null, 'tags' => trim($_POST['tags'] ?? ''), 'file_types' => [], 'commercial_license_enabled' => $commercialEnabled ? 1 : 0, 'commercial_license_price' => $commercialLicensePrice, 'pod_allowed' => isset($_POST['pod_allowed']) || isset($_POST['license_enabled']['pod']) ? 1 : 0, 'ai_disclosure' => trim($_POST['ai_disclosure'] ?? $existing['ai_disclosure'] ?? ''), 'seo_title' => trim($_POST['seo_title'] ?? $existing['seo_title'] ?? ''), 'seo_description' => trim($_POST['seo_description'] ?? $existing['seo_description'] ?? ''), ];

    }
    private function validateProduct(array $v, ?int $ignoreId = null): array
    {
        $errors = [];
        if ($v['title'] === '')
        {
            $errors[] = 'Product Name is required.';

        }
        if ($v['slug'] === '')
        {
            $errors[] = 'Product Slug is required.';

        }
        elseif (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $v['slug']))
        {
            $errors[] = 'Product Slug can only contain lowercase letters, numbers, and hyphens.';

        }
        else
        {
            $params = [$v['slug']];
            $sql = 'select id from products where slug=?';
            if ($ignoreId)
           {
                $sql .= ' and id<>?';
                $params[] = $ignoreId;

           }
            if (DB::row($sql . ' limit 1', $params))
           {
                $errors[] = 'Product Slug must be unique.';

           }

        }
        if ($v['description'] === '')
        {
            $errors[] = 'Full Description is required.';

        }
        if (!is_numeric($v['price']) || (float) $v['price'] < 0)
        {
            $errors[] = 'Base Price must be a valid amount.';

        }
        $manualDeliveryInstructions = trim((string)($v['manual_delivery_instructions'] ?? ''));
        if (($v['fulfillment_type'] ?? 'downloadable') === 'google_drive' && mb_strlen($manualDeliveryInstructions) < 5)
        {
            $errors[] = 'Manual delivery instructions are required for Google Drive delivery products.';
        }
        if (!in_array($v['ai_disclosure'], ['No AI Used', 'AI Assisted', 'AI Generated'], true))
        {
            $errors[] = 'AI Disclosure is required.';

        }
        if (mb_strlen($v['seo_title']) > 70)
        {
            $errors[] = 'SEO title must be 70 characters or fewer.';

        }
        if (mb_strlen($v['seo_description']) > 170)
        {
            $errors[] = 'SEO description must be 170 characters or fewer.';

        }
        return $errors;

    }
    private function savePreviewImages(int $productId, array &$errors): void
    {
        if (empty($_FILES['preview_images']['name'][0])) return;
        foreach ($_FILES['preview_images']['name'] as $idx => $original) {
            if (($_FILES['preview_images']['error'][$idx] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
            $file = [
                'name' => $original,
                'tmp_name' => $_FILES['preview_images']['tmp_name'][$idx] ?? '',
                'error' => $_FILES['preview_images']['error'][$idx] ?? UPLOAD_ERR_NO_FILE,
                'size' => $_FILES['preview_images']['size'][$idx] ?? 0,
                'type' => $_FILES['preview_images']['type'][$idx] ?? '',
            ];
            $saved = WatermarkService::applyUploadedPreview($file, 'product_previews', $errors);
            if ($saved) {
                $alt = trim($_POST['preview_alt'][$idx] ?? pathinfo((string)$original, PATHINFO_FILENAME));
                $sort = (int) ($_POST['preview_sort'][$idx] ?? $idx);
                DB::exec('insert into product_images (product_id,image_path,original_image_path,watermark_status,watermark_error,alt_text,sort_order) values (?,?,?,?,?,?,?)', [$productId, $saved['image_path'], $saved['original_image_path'], $saved['watermark_status'], $saved['watermark_error'], $alt, $sort]);
            }
        }
    }
    private function syncTags(int $productId, string $tagsText): void
    {
        DB::exec('delete from product_tags where product_id=?', [$productId]);
        $names = array_values( array_unique( array_filter( array_map('trim', preg_split('/[,\n]+/', $tagsText)) ) ) );
        foreach ($names as $name)
        {
            $slug = H::slug($name);
            if ($slug === '')
           {
                continue;

           }
            DB::exec( 'insert into tags (name,slug) values (?,?) on duplicate key update name=values(name)', [$name, $slug] );
            $tag = DB::row('select id from tags where slug=? limit 1', [$slug]);
            if ($tag)
           {
                DB::exec( 'insert ignore into product_tags (product_id,tag_id) values (?,?)', [$productId, $tag['id']] );

           }

        }

    }
    private function tagText(int $productId): string
    {
        return implode( ', ', array_column( DB::rows( 'select t.name from tags t join product_tags pt on pt.tag_id=t.id where pt.product_id=? order by t.name', [$productId] ), 'name' ) );

    }
    private function deletePreviewImage(int $imageId, int $productId): void
    {
        $img = DB::row('select image_path,original_image_path from product_images where id=? and product_id=?', [$imageId, $productId]);
        if ($img)
        {
            $path = public_path(ltrim($img['image_path'], '/'));
            $base = realpath(public_path('uploads/product_previews'));
            $real = realpath($path);
            if ($base && $real && is_file($real) && str_starts_with($real, $base))
           {
                @unlink($real);

           }
            if (!empty($img['original_image_path'])) {
                $originalPath = app_path('storage/app/private/' . ltrim($img['original_image_path'], '/'));
                $originalBase = realpath(app_path('storage/app/private/product_previews'));
                $originalReal = realpath($originalPath);
                if ($originalBase && $originalReal && is_file($originalReal) && str_starts_with($originalReal, $originalBase)) @unlink($originalReal);
            }
            DB::exec('delete from product_images where id=? and product_id=?', [$imageId, $productId]);

        }

    }

    private function regeneratePreviewImage(int $imageId, int $productId): void
    {
        $img = DB::row('select * from product_images where id=? and product_id=?', [$imageId, $productId]);
        if (!$img || empty($img['original_image_path'])) { H::flash('error', 'Original private preview image is unavailable.'); return; }
        $result = WatermarkService::regenerate($img['original_image_path'], $img['image_path']);
        DB::exec('update product_images set watermark_status=?,watermark_error=?,updated_at=now() where id=? and product_id=?', [$result['ok'] ? WatermarkService::STATUS_WATERMARKED : WatermarkService::STATUS_FAILED, $result['ok'] ? null : $result['message'], $imageId, $productId]);
        H::flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'Watermark regenerated from the private original preview.' : 'Watermark regeneration failed: ' . $result['message']);
    }
    private function deleteProductFile(int $fileId, int $productId): bool
    {
        $file = DB::row('select storage_path from product_files where id=? and product_id=?', [$fileId, $productId]);
        if (!$file)
        {
            return false;

        }
        $path = app_path('storage/protected_uploads/' . ltrim($file['storage_path'], '/'));
        $base = realpath(app_path('storage/protected_uploads/products'));
        $real = realpath($path);
        if ($base && $real && is_file($real) && str_starts_with($real, $base))
        {
            @unlink($real);

        }
        DB::exec('delete from product_files where id=? and product_id=?', [$fileId, $productId]);
        return true;

    }
    private function saveProductFiles(int $productId): bool
    {
        if (empty($_FILES['product_files']['name'][0]))
        {
            return false;

        }
        $saved = false;
        $dir = app_path('storage/protected_uploads/products');
        if (!is_dir($dir))
        {
            mkdir($dir, 0750, true);

        }
        foreach ($_FILES['product_files']['name'] as $idx => $original)
        {
            if (($_FILES['product_files']['error'][$idx] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK)
           {
                continue;

           }
            $name = bin2hex(random_bytes(12)) . '-' . basename($original);
            if (move_uploaded_file($_FILES['product_files']['tmp_name'][$idx], $dir . '/' . $name))
           {
                DB::exec( 'insert into product_files (product_id,storage_path,original_name,file_size,mime_type) values (?,?,?,?,?)', [ $productId, 'products/' . $name, $original, $_FILES['product_files']['size'][$idx], $_FILES['product_files']['type'][$idx] ?? 'application/octet-stream', ] );
                $saved = true;

           }

        }
        return $saved;

    }
    private function productReviewContentChanged(array $existing, array $values): bool
    {
        return (string) ($existing['title'] ?? '') !== (string) $values['title'] || (string) ($existing['short_description'] ?? '') !== (string) $values['short_description'] || (string) ($existing['description'] ?? '') !== (string) $values['description'];

    }
    private function productStatusForSave(?array $existing, array $values): string
    {
        $submittedForReview = ($_POST['action'] ?? 'draft') === 'review';
        if (!$existing)
        {
            return $submittedForReview ? 'pending_review' : 'draft';

        }
        if ($existing['status'] === 'disabled')
        {
            return 'disabled';

        }
        if ($existing['status'] === 'approved')
        {
            if ($this->productReviewContentChanged($existing, $values))
           {
                return 'pending_review';

           }
            return 'approved';

        }
        if ($submittedForReview)
        {
            return 'pending_review';

        }
        return $existing['status'];

    }
    private function flashMessageForProductStatus(string $status): string
    {
        if ($status === 'pending_review')
        {
            return 'Product submitted for review.';

        }
        if ($status === 'draft')
        {
            return 'Product draft saved.';

        }
        if ($status === 'approved')
        {
            return 'Product updated successfully.';

        }
        return 'Product updated.';

    }
    public function products()
    {
        H::requireSeller();
        $d = $this->d();
        $status = $_GET['status'] ?? 'all';
        $allowed = ['draft', 'pending_review', 'approved', 'rejected', 'disabled'];
        $params = [$d['id']];
        $where = 'where p.designer_id=?';
        if (in_array($status, $allowed, true))
        {
            $where .= ' and p.status=?';
            $params[] = $status;

        }
        H::view('seller/products', [ 'status' => $status, 'products' => DB::rows( 'select p.*,c.name category_name,(select image_path from product_images pi where pi.product_id=p.id order by pi.sort_order,pi.id limit 1) thumbnail from products p left join categories c on c.id=p.category_id ' . $where . ' order by p.updated_at desc', $params ), ]);

    }
    public function editProduct($id = null)
    {
        H::requireSeller();
        $d = $this->d();
        $p = $id ? DB::row('select * from products where id=? and designer_id=?', [$id, $d['id']]) : null;
        $errors = [];
        if ($id && !$p)
        {
            H::abort(404);

        }
        if ($_POST)
        {
            if (($_POST['delete_image'] ?? ''))
           {
                $this->deletePreviewImage((int) $_POST['delete_image'], (int) $p['id']);
                H::redirect('/seller/product/' . $p['id']);

           }
            if (($_POST['regenerate_image'] ?? ''))
           {
                $this->regeneratePreviewImage((int) $_POST['regenerate_image'], (int) $p['id']);
                H::redirect('/seller/product/' . $p['id']);

           }
            if (($_POST['delete_file'] ?? ''))
           {
                $this->deleteProductFile((int) $_POST['delete_file'], (int) $p['id']);
                H::redirect('/seller/product/' . $p['id']);

           }
            $values = $this->productValues($p ?: []);
            $errors = $this->validateProduct($values, $p ? (int) $p['id'] : null);
            [$postedLicenses, $licenseErrors] = LicenseService::normalizePosted($values, $_POST);
            $errors = array_merge($errors, $licenseErrors);
            if (!$errors)
           {
                if ($p)
               {
                    $this->saveProductFiles((int) $p['id']);

               }
                $status = $this->productStatusForSave($p, $values);
                $fileTypes = implode(',', $values['file_types']);
                if ($p)
               {
                    DB::exec( 'update products set title=?,slug=?,short_description=?,description=?,price=?,fulfillment_type=?,manual_delivery_instructions=?,category_id=?,tags_text=null,file_types=?,commercial_license_enabled=?,commercial_license_price=?,pod_allowed=?,digital_resale_prohibited=1,ai_disclosure=?,seo_title=?,seo_description=?,status=?,rejection_reason=case when ?="pending_review" then null else rejection_reason end,updated_at=now() where id=?', [ $values['title'], $values['slug'], $values['short_description'], $values['description'], $values['price'], $values['fulfillment_type'], $values['manual_delivery_instructions'], $values['category_id'], $fileTypes, $values['commercial_license_enabled'], $values['commercial_license_price'], $values['pod_allowed'], $values['ai_disclosure'], $values['seo_title'], $values['seo_description'], $status, $status, $p['id'], ] );
                    $productId = (int) $p['id'];

               }
                else
               {
                    DB::exec( 'insert into products (designer_id,category_id,title,slug,short_description,description,price,fulfillment_type,manual_delivery_instructions,tags_text,file_types,commercial_license_enabled,commercial_license_price,pod_allowed,digital_resale_prohibited,ai_disclosure,seo_title,seo_description,status) values (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [ $d['id'], $values['category_id'], $values['title'], $values['slug'], $values['short_description'], $values['description'], $values['price'], $values['fulfillment_type'], $values['manual_delivery_instructions'], null, $fileTypes, $values['commercial_license_enabled'], $values['commercial_license_price'], $values['pod_allowed'], 1, $values['ai_disclosure'], $values['seo_title'], $values['seo_description'], $status, ] );
                    $productId = DB::id();

               }
                $this->syncTags($productId, $values['tags']);
                LicenseService::syncProductLicenses($productId, $postedLicenses);
                $this->savePreviewImages($productId, $errors);
                if (!$p)
               {
                    $this->saveProductFiles($productId);

               }
                if (!$errors)
               {
                    H::flash('success', $this->flashMessageForProductStatus($status));
                    H::redirect('/seller/products');

               }

           }

        }
        $productId = $p['id'] ?? 0;
        H::view('seller/edit_product', [ 'p' => $p, 'errors' => $errors, 'cats' => DB::rows('select * from categories where is_active=1'), 'images' => $productId ? DB::rows( 'select * from product_images where product_id=? order by sort_order,id', [$productId] ) : [], 'files' => $productId ? DB::rows( 'select * from product_files where product_id=? order by created_at desc', [$productId] ) : [], 'tagText' => $productId ? $this->tagText((int) $productId) : '', 'licenseTypes' => LicenseService::platformTypes(), 'productLicenses' => $p ? LicenseService::productLicenses($p) : [], ]);

    }
    public function submitProduct($id)
    {
        H::requireSeller();
        $d = $this->d();
        $p = DB::row('select fulfillment_type,manual_delivery_instructions from products where id=? and designer_id=?', [(int)$id, $d['id']]) ?? H::abort(404);
        if (($p['fulfillment_type'] ?? 'downloadable') === 'google_drive' && mb_strlen(trim((string)($p['manual_delivery_instructions'] ?? ''))) < 5)
        {
            H::flash('error','Manual delivery instructions are required before submitting a Google Drive delivery product.');
            H::redirect('/seller/product/'.(int)$id);
        }
        DB::exec( 'update products set status="pending_review",rejection_reason=null,updated_at=now() where id=? and designer_id=?', [$id, $d['id']] );
        H::redirect('/seller/products');

    }
    public function disableProduct($id)
    {
        H::requireSeller();
        DB::exec( 'update products set status="disabled",updated_at=now() where id=? and designer_id=?', [$id, $this->d()['id']] );
        H::redirect('/seller/products');

    }
    public function sales()
    {
        H::requireSeller();
        H::view('seller/sales', [ 'sales' => DB::rows( 'select oi.*,o.status order_status,o.payment_status,u.email,sp.payout_status from order_items oi join orders o on o.id=oi.order_id join users u on u.id=o.user_id left join seller_payouts sp on sp.order_id=oi.order_id and sp.designer_id=oi.designer_id where oi.designer_id=? and o.payment_status in ("paid","partially_refunded") order by oi.created_at desc', [$this->d()['id']] ), ]);

    }

    public function saleDetail($id)
    {
        H::requireSeller();
        $d=$this->d();
        if ($_POST && ($_POST['action'] ?? '') === 'mark_delivered') {
            DB::exec('update order_items oi join orders o on o.id=oi.order_id set oi.manual_delivery_status="delivered", oi.delivered_at=now(), oi.delivery_notes=? where oi.id=? and oi.designer_id=? and oi.fulfillment_type="google_drive" and o.payment_status="paid"', [trim($_POST['delivery_notes'] ?? ''), (int)$id, $d['id']]);
            H::flash('success','Manual delivery item marked delivered.');
            H::redirect('/seller/order-item/'.(int)$id);
        }
        $item=DB::row('select oi.*,o.user_id buyer_id,o.status order_status,o.payment_status,o.created_at order_created,u.email buyer_email,u.name buyer_name from order_items oi join orders o on o.id=oi.order_id join users u on u.id=o.user_id where oi.id=? and oi.designer_id=? and o.payment_status in ("paid","partially_refunded")',[(int)$id,$d['id']]) ?? H::abort(404);
        H::view('seller/order_item',['item'=>$item]);
    }
    public function referrals()
    {
        H::requireSeller();
        H::view('seller/referrals', [ 'refs' => DB::rows('select * from referrals where referrer_user_id=?', [H::user()['id']]), ]);

    }
    public function rank()
    {
        H::requireSeller();
        H::view('seller/rank', [ 'd' => $this->d(), ]);

    }

}
