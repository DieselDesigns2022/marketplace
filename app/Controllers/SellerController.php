<?php

namespace App\Controllers;
use App\Core\Database as DB;
use App\Core\Helpers as H;
use App\Services\LicenseService;
use App\Services\WatermarkService;
use App\Services\StripeService;
use App\Services\ProductIpRiskWorkflow;
use App\Services\IpRiskScanner;
use App\Repositories\IpRiskRepository;
use App\Services\NotificationService;
use App\Services\SellerReceiptService;
use Throwable;
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
        if (($_FILES[$field]['size'] ?? 0) > 25 * 1024 * 1024)
        {
            $errors[] = ucfirst($field) . ' must be 25MB or smaller.';
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

    private function uploadStoreBanner(array &$errors): ?string
    {
        if (empty($_FILES['banner']['tmp_name'])) return null;
        $file=$_FILES['banner'];
        if (($file['error']??UPLOAD_ERR_OK)!==UPLOAD_ERR_OK) { $errors[]='Banner upload failed.'; return null; }
        if (($file['size']??0)>25*1024*1024) { $errors[]='Banner must be 25MB or smaller.'; return null; }
        if (!is_uploaded_file($file['tmp_name'])) { $errors[]='Banner must be a genuine uploaded file.'; return null; }
        if (!extension_loaded('gd')||!function_exists('imagecreatefromstring')) { $errors[]='Banner processing is unavailable because GD is not installed.'; return null; }
        $mime=(new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $encoders=['image/jpeg'=>['jpg','imagejpeg'],'image/png'=>['png','imagepng'],'image/webp'=>['webp','imagewebp']];
        if (!isset($encoders[$mime])||!function_exists($encoders[$mime][1])) { $errors[]='Banner must be a valid JPG, PNG, or WEBP image.'; return null; }
        $info=@getimagesize($file['tmp_name']);
        if (!$info||($info['mime']??'')!==$mime||$info[0]<1||$info[1]<1||$info[0]*$info[1]>40000000) { $errors[]='Banner must be a valid image file.'; return null; }
        $bytes=@file_get_contents($file['tmp_name']); $source=$bytes===false?false:@imagecreatefromstring($bytes);
        if (!$source) { $errors[]='Banner could not be decoded.'; return null; }
        $canvas=imagecreatetruecolor(2400,800);
        if (!$canvas) { imagedestroy($source); $errors[]='Banner could not be processed.'; return null; }
        if (in_array($mime,['image/png','image/webp'],true)) { imagealphablending($canvas,false); imagesavealpha($canvas,true); $transparent=imagecolorallocatealpha($canvas,0,0,0,127); imagefilledrectangle($canvas,0,0,2399,799,$transparent); }
        $scale=max(2400/$info[0],800/$info[1]);
        $srcW=2400/$scale; $srcH=800/$scale; $srcX=($info[0]-$srcW)/2; $srcY=($info[1]-$srcH)/2;
        $ok=imagecopyresampled($canvas,$source,0,0,(int)floor($srcX),(int)floor($srcY),2400,800,(int)ceil($srcW),(int)ceil($srcH));
        imagedestroy($source);
        $dir=public_path('uploads/store_banners');
        if (!$ok||(!is_dir($dir)&&!mkdir($dir,0755,true))) { imagedestroy($canvas); $errors[]='Banner could not be processed.'; return null; }
        [$ext,$encoder]=$encoders[$mime]; $name=bin2hex(random_bytes(16)).'.'.$ext; $absolute=$dir.'/'.$name;
        $saved=$mime==='image/jpeg'?$encoder($canvas,$absolute,90):$encoder($canvas,$absolute);
        imagedestroy($canvas);
        if (!$saved) { @unlink($absolute); $errors[]='Banner could not be saved.'; return null; }
        return '/uploads/store_banners/'.$name;
    }

    public static function isSafeStoreBannerPath(string $path): bool
    {
        return (bool)preg_match('#^/uploads/store_banners/[a-f0-9]{24,64}\.(?:jpe?g|png|webp)$#',$path);
    }

    private function removeStoreBanner(?string $path): void
    {
        if (!$path||!self::isSafeStoreBannerPath($path)) return;
        $base=realpath(public_path('uploads/store_banners')); $file=realpath(public_path(ltrim($path,'/')));
        if ($base&&$file&&str_starts_with($file,$base.DIRECTORY_SEPARATOR)&&is_file($file)) @unlink($file);
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
            $errors[] = 'Store URL name is required.';

        }
        elseif (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $v['desired_slug']))
        {
            $errors[] = 'Store URL name can only contain lowercase letters, numbers, and hyphens.';

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

    private function approvedDesigner(): array
    {
        H::requireSeller();
        $d = $this->d();
        if (!$d || $d['status'] !== 'approved') {
            H::flash('warning', 'You need an approved designer account before accessing seller onboarding.');
            H::redirect('/apply');
        }
        return $d;
    }

    private function readiness(array $d): array
    {
        $products = (int)(DB::row('select count(*) c from products where designer_id=? and status in ("draft","pending_review","approved")', [$d['id']])['c'] ?? 0);
        return [
            'profile' => trim((string)($d['display_name'] ?? '')) !== '' && trim((string)($d['bio'] ?? '')) !== '',
            'stripe' => !empty($d['stripe_connect_account_id']) && !empty($d['stripe_details_submitted']) && !empty($d['stripe_payouts_enabled']),
            'stripe_started' => !empty($d['stripe_connect_account_id']),
            'store' => trim((string)($d['store_slug'] ?? '')) !== '',
            'payout' => !empty($d['stripe_connect_account_id']) && !empty($d['stripe_details_submitted']) && !empty($d['stripe_payouts_enabled']),
            'products' => $products > 0,
            'product_count' => $products,
            'complete' => trim((string)($d['display_name'] ?? '')) !== ''
                && trim((string)($d['bio'] ?? '')) !== ''
                && trim((string)($d['store_slug'] ?? '')) !== ''
                && !empty($d['stripe_connect_account_id'])
                && !empty($d['stripe_details_submitted'])
                && !empty($d['stripe_payouts_enabled']),
        ];
    }


    private function requireOnboardingComplete(?array $d = null): array
    {
        $d ??= $this->approvedDesigner();
        $readiness = $this->readiness($d);
        if (empty($readiness['complete'])) {
            H::flash('warning', 'Complete seller onboarding before using seller dashboard tools or adding products.');
            H::redirect('/seller/onboarding');
        }
        return $d;
    }


    private function productCategoryRows(): array
    {
        return DB::rows('select * from categories where is_active=1 and slug not in (?, ?) and not (lower(name) in (?, ?) and slug<>?) order by sort_order,name', ['sublimation','png','png','png files','png-files']);
    }

    private function renderProductForm(?array $p, array $errors, array $d, ?array $ipRiskOverride = null): void
    {
        $productId = $p['id'] ?? 0;
        H::view('seller/edit_product', [
            'p' => $p,
            'errors' => $errors,
            'cats' => $this->productCategoryRows(),
            'images' => $productId ? DB::rows('select * from product_images where product_id=? order by sort_order,id', [$productId]) : [],
            'files' => $productId ? DB::rows('select * from product_files where product_id=? order by created_at desc', [$productId]) : [],
            'tagText' => $productId ? $this->tagText((int)$productId) : '',
            'licenseTypes' => LicenseService::platformTypes(),
            'productLicenses' => $p ? LicenseService::productLicenses($p) : LicenseService::presetLicensesForProductForm((int)$d['id']),
            'ipRisk' => $ipRiskOverride ?: ($productId ? (new ProductIpRiskWorkflow())->currentReview((int)$productId, (int)H::user()['id']) : ['matches'=>[], 'requires_confirmation'=>false, 'state'=>null]),
        ]);
    }

    public function onboarding(): void
    {
        $d = $this->approvedDesigner();
        H::view('seller/onboarding', ['d' => $d, 'readiness' => $this->readiness($d), 'commissionPercent' => (int)round(StripeService::commissionRate() * 100)]);
    }

    public function stripe(): void
    {
        $d = $this->approvedDesigner();
        H::view('seller/stripe', ['d' => $d, 'readiness' => $this->readiness($d), 'commissionPercent' => (int)round(StripeService::commissionRate() * 100)]);
    }

    public function stripeConnect(): void
    {
        $d = $this->approvedDesigner();
        try {
            $accountId = $d['stripe_connect_account_id'] ?? '';
            if ($accountId === '') {
                $account = StripeService::createConnectedAccount($d, H::user());
                $accountId = (string)($account['id'] ?? '');
                StripeService::syncConnectedAccountStatus((int)$d['id'], $account);
            }
            $base = StripeService::appUrl();
            $link = StripeService::createAccountLink($accountId, $base . '/seller/stripe/refresh', $base . '/seller/stripe/return');
            header('Location: ' . $link['url'], true, 303); exit;
        } catch (Throwable $e) {
            H::flash('error', 'Stripe setup could not be started: ' . $e->getMessage());
            H::redirect('/seller/stripe');
        }
    }

    public function stripeReturn(): void
    {
        $d = $this->approvedDesigner();
        if (!empty($d['stripe_connect_account_id'])) {
            try {
                StripeService::syncConnectedAccountStatus((int)$d['id'], StripeService::retrieveConnectedAccount($d['stripe_connect_account_id']));
                $this->refreshPendingPayouts((int)$d['id']);
                H::flash('success', 'Stripe payout setup status was refreshed.');
            } catch (Throwable $e) { H::flash('warning', 'Stripe returned you to Asset Moth, but status refresh failed: ' . $e->getMessage()); }
        }
        H::redirect('/seller/stripe');
    }

    public function stripeRefresh(): void
    {
        $this->stripeConnect();
    }

    private function refreshPendingPayouts(int $designerId): void
    {
        $d = DB::row('select * from designers where id=?', [$designerId]);
        $ready = $d && !empty($d['stripe_connect_account_id']) && !empty($d['stripe_details_submitted']) && !empty($d['stripe_payouts_enabled']);
        if ($ready) {
            StripeService::attemptPendingTransfersForDesigner($designerId);
        }
    }

    public function apply()
    {
        if (!H::user()) {
            $_SESSION['after_login_redirect'] = '/apply';
            $_SESSION['seller_intent'] = true;
            H::flash('warning', 'Create or log in to an account first. That is Step 1; you will return here to complete the seller application.');
            H::redirect('/register');
        }
        H::requireLogin();
        $designer = $this->d();
        if ($designer && $designer['status'] === 'approved')
        {
            if (empty($this->readiness($designer)['complete'])) {
                H::flash('success', 'Your seller application is approved. Complete onboarding to unlock seller tools.');
                H::redirect('/seller/onboarding');
            }
            H::flash('success', 'Your seller application is approved. You can now access your seller dashboard.');
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
                try { NotificationService::admins('seller_application','New seller application','A new seller application is ready for review.',"seller-application:$applicationId",'/admin/applications/'.$applicationId); } catch(Throwable $e) { NotificationService::reportFailure('seller_application',$e); }
                H::flash('success', 'Your designer application has been submitted and is waiting for review.');
                H::redirect('/apply');

           }

        }
        H::view('seller/apply', [ 'application' => $application, 'errors' => $errors, 'values' => $values, 'meta' => ['title' => 'Apply to Sell | Asset Moth', 'description' => 'Apply for a reviewed designer storefront on Asset Moth.', 'canonical' => H::canonical('/apply'), 'robots' => 'noindex,follow'] ]);

    }
    private function refundSummary(int $designerId): array
    {
        $rows=DB::rows('select oi.id,oi.order_id,oi.designer_id,oi.total_price,oi.commission_rate,o.tax_amount,r.cumulative_refund from order_items oi join orders o on o.id=oi.order_id join (select order_id,max(amount) cumulative_refund from payment_transactions where transaction_type in ("partial_refund","refund") group by order_id) r on r.order_id=oi.order_id order by oi.order_id,oi.id');
        $orders=[];foreach($rows as $row){$orderId=(int)$row['order_id'];$orders[$orderId]['tax_cents']=StripeService::cents($row['tax_amount']??0);$orders[$orderId]['refund_cents']=StripeService::cents($row['cumulative_refund']??0);$orders[$orderId]['items'][]=$row;}
        $summary=['gross_refund_cents'=>0,'seller_refund_cents'=>0,'by_order'=>[],'original_by_order'=>[]];
        foreach($orders as $orderId=>$order){$allocation=StripeController::allocateSellerRefund($order['items'],$order['refund_cents'],$order['tax_cents']);foreach($order['items'] as $item){if((int)$item['designer_id']!==$designerId)continue;$share=$allocation[(int)$item['id']]??['gross_refund_cents'=>0,'seller_refund_cents'=>0];$gross=StripeService::cents($item['total_price']);$originalSeller=$gross-(int)round($gross*(float)$item['commission_rate']);$summary['gross_refund_cents']+=$share['gross_refund_cents'];$summary['seller_refund_cents']+=$share['seller_refund_cents'];$summary['by_order'][$orderId]=($summary['by_order'][$orderId]??0)+$share['seller_refund_cents'];$summary['original_by_order'][$orderId]=($summary['original_by_order'][$orderId]??0)+$originalSeller;}}
        return $summary;
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
        $readiness = $this->readiness($d);
        if (empty($readiness['complete'])) {
            H::view('seller/onboarding', ['d' => $d, 'readiness' => $readiness, 'commissionPercent' => (int)round(StripeService::commissionRate() * 100), 'dashboardGate' => true]);
            return;
        }
        $id=(int)$d['id']; $userId=(int)H::user()['id'];
        $stats=DB::row('select count(*) total_products,sum(status="draft") draft_products,sum(status="pending_review") pending_products,sum(status in ("approved","published")) published_products,sum(status="rejected") rejected_products,sum(status="archived") archived_products from products where designer_id=? and status<>"deleted"',[$id]);
        $stats=array_merge($stats??[],DB::row('select count(*) sales_count,coalesce(sum(gross_sale),0) gross_sales,coalesce(sum(seller_earning),0) seller_earnings from seller_earnings where designer_id=?',[$id])??[],DB::row('select sum(review_status in ("pending_review","published_flagged")) flagged_products from product_ip_risk_states s join products p on p.id=s.product_id where p.designer_id=?',[$id])??[]);
        $refunds=$this->refundSummary($id);$stats['gross_sales']=max(0,(float)$stats['gross_sales']-$refunds['gross_refund_cents']/100);$stats['seller_earnings']=max(0,(float)$stats['seller_earnings']-$refunds['seller_refund_cents']/100);$stats['refund_adjustments']=$refunds['seller_refund_cents']/100;
        $stats['pending_payouts']=0.0;$stats['transferred_payouts']=0.0;$stats['payout_issues']=0;
        foreach(DB::rows('select order_id,seller_payout_amount,payout_status,stripe_transfer_error from seller_payouts where designer_id=?',[$id]) as $payout){$orderId=(int)$payout['order_id'];$amount=(float)$payout['seller_payout_amount'];if(in_array($payout['payout_status'],['pending_payment','pending_transfer','pending_stripe_onboarding'],true))$stats['pending_payouts']+=isset($refunds['original_by_order'][$orderId])?max(0,($refunds['original_by_order'][$orderId]-($refunds['by_order'][$orderId]??0))/100):$amount;if($payout['payout_status']==='transferred')$stats['transferred_payouts']+=$amount;if($payout['payout_status']==='transfer_failed'||!empty($payout['stripe_transfer_error']))$stats['payout_issues']++;}
        $accountStatus=(string)($d['stripe_account_status']??'');
        if (empty($d['stripe_connect_account_id'])) $stripeState='not_connected';
        elseif (in_array($accountStatus,['restricted','disabled','error'],true)) $stripeState='payout_issue';
        elseif ($accountStatus==='information_required') $stripeState='information_required';
        elseif (!empty($d['stripe_payouts_enabled']) && !empty($readiness['payout'])) $stripeState='payout_ready';
        elseif ($accountStatus==='details_submitted'||!empty($d['stripe_details_submitted'])) $stripeState='connected';
        else $stripeState='onboarding_started';
        H::view('seller/home',['d'=>$d,'stats'=>$stats,'readiness'=>$readiness,'stripeState'=>$stripeState,'recentOrders'=>DB::rows('select oi.id,oi.order_id,oi.product_title,oi.total_price,oi.seller_payout_amount,oi.seller_payout_status,o.payment_status,o.created_at from order_items oi join orders o on o.id=oi.order_id where oi.designer_id=? and o.payment_status in ("paid","partially_refunded") order by o.created_at desc,oi.id desc limit 5',[$id]),'notifications'=>DB::rows('select * from notifications where user_id=? order by created_at desc limit 5',[$userId]),'unreadCount'=>(int)(DB::row('select count(*) c from notifications where user_id=? and read_at is null',[$userId])['c']??0)]);

    }
    public function receiptSettings(): void
    {
        $d=$this->requireOnboardingComplete();
        $errors=[]; $note=$d['receipt_note'] ?? '';
        if ($_SERVER['REQUEST_METHOD']==='POST') {
            H::verifyCsrf(); $service=new SellerReceiptService(); $action=$_POST['action'] ?? 'save';
            $newImage=null;
            try {
                if (!in_array($action,['save','remove_image','remove_note','restore'],true)) throw new \RuntimeException('Unknown receipt settings action.');
                $note=in_array($action,['remove_note','restore'],true) ? null : SellerReceiptService::normalizeNote($_POST['receipt_note'] ?? '');
                $image=$d['receipt_image_path'] ?? null;
                if (in_array($action,['remove_image','restore'],true)) $image=null;
                if ($action==='save' && !empty($_FILES['receipt_image']['tmp_name'])) $image=$newImage=$service->storeUpload($_FILES['receipt_image']);
                if (!DB::exec('update designers set receipt_note=?,receipt_image_path=? where id=? and user_id=?',[$note,$image,$d['id'],H::user()['id']])) throw new \RuntimeException('Receipt settings could not be saved.');
                if (($d['receipt_image_path'] ?? null)!==$image) $service->removeIfUnreferenced($d['receipt_image_path'] ?? null);
                H::flash('success',$action==='restore'?'Standard receipt restored.':'Receipt customization saved for future purchases.');
                H::redirect('/seller/receipt-settings');
            } catch (Throwable $e) { if ($newImage) $service->removeStoredFile($newImage); $errors[]=$e instanceof \RuntimeException?$e->getMessage():'Receipt settings could not be saved. Please try again.'; }
        }
        $preview=$d; $preview['receipt_note']=$note;
        H::view('seller/receipt_settings',['d'=>$preview,'errors'=>$errors]);
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
            if (($_POST['settings_section'] ?? 'store') === 'licenses') {
                $errors = LicenseService::syncSellerPresets((int)$d['id'], $_POST);
                if (!$errors) { H::flash('success', 'License presets updated.'); H::redirect('/seller/store'); }
                H::view('seller/store', [ 'd' => $this->d(), 'errors' => $errors, 'licenseTypes' => LicenseService::platformTypes(), 'licensePresets' => LicenseService::sellerPresets((int)$d['id']), ]);
                return;
            }
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
                $errors[] = 'Store URL name must use lowercase letters, numbers, and hyphens only.';

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
            $banner = $this->uploadStoreBanner($errors);
            if (!$errors)
           {
                try {
                    if (!DB::exec( 'update designers set display_name=?,store_slug=?,bio=?,website_url=?,social_links=?,facebook_url=?,instagram_url=?,tiktok_url=?,pinterest_url=?,etsy_url=?,shopify_url=?,announcement=?,seo_title=?,seo_description=?,avatar_path=coalesce(?,avatar_path),banner_path=coalesce(?,banner_path),updated_at=now() where id=? and user_id=?', [ $display, $slug, $bio, $website, $social, $socialFields['facebook_url'], $socialFields['instagram_url'], $socialFields['tiktok_url'], $socialFields['pinterest_url'], $socialFields['etsy_url'], $socialFields['shopify_url'], $announcement, $seoTitle, $seoDescription, $avatar, $banner, $d['id'], H::user()['id'], ] )) throw new \RuntimeException('Store update failed.');
                } catch (Throwable $e) {
                    $this->removeStoreBanner($banner);
                    $errors[]='Store settings could not be saved.';
                }
                if ($errors) { H::view('seller/store', [ 'd'=>$this->d(), 'errors'=>$errors, 'licenseTypes'=>LicenseService::platformTypes(), 'licensePresets'=>LicenseService::sellerPresets((int)$d['id']) ]); return; }
                if ($banner) $this->removeStoreBanner($d['banner_path']??null);
                H::flash('success', 'Store settings updated.');
                H::redirect('/seller/store');

           }

        }
        H::view('seller/store', [ 'd' => $this->d(), 'errors' => $errors, 'licenseTypes' => LicenseService::platformTypes(), 'licensePresets' => LicenseService::sellerPresets((int)$d['id']), ]);

    }
    private function productValues(array $existing = []): array
    {
        $price = $_POST['price'] ?? ($existing['price'] ?? '0.00');
        $commercialEnabled = isset($_POST['license_enabled']['commercial']);
        $commercialLicensePrice = $_POST['license_price']['commercial'] ?? ($existing['commercial_license_price'] ?? '0.00');
        return [ 'title' => trim($_POST['title'] ?? $existing['title'] ?? ''), 'slug' => trim((string)($existing['slug'] ?? '')), 'short_description' => trim($_POST['short_description'] ?? $existing['short_description'] ?? ''), 'description' => trim($_POST['description'] ?? $existing['description'] ?? ''), 'price' => $price, 'fulfillment_type' => in_array(($_POST['fulfillment_type'] ?? ($existing['fulfillment_type'] ?? 'downloadable')), ['downloadable','google_drive'], true) ? ($_POST['fulfillment_type'] ?? ($existing['fulfillment_type'] ?? 'downloadable')) : 'downloadable', 'manual_delivery_instructions' => trim($_POST['manual_delivery_instructions'] ?? ($existing['manual_delivery_instructions'] ?? '')), 'category_id' => ($_POST['category_id'] ?? ($existing['category_id'] ?? '')) ?: null, 'tags' => trim($_POST['tags'] ?? ''), 'file_types' => [], 'commercial_license_enabled' => $commercialEnabled ? 1 : 0, 'commercial_license_price' => $commercialLicensePrice, 'pod_allowed' => isset($_POST['pod_allowed']) || isset($_POST['license_enabled']['pod']) ? 1 : 0, 'ai_disclosure' => trim($_POST['ai_disclosure'] ?? $existing['ai_disclosure'] ?? ''), 'seo_title' => trim($_POST['seo_title'] ?? $existing['seo_title'] ?? ''), 'seo_description' => trim($_POST['seo_description'] ?? $existing['seo_description'] ?? ''), ];

    }

    private function uniqueProductSlug(string $title, ?int $ignoreId = null): string
    {
        $base = H::slug($title) ?: 'product';
        $slug = mb_substr($base, 0, 190);
        $i = 2;
        while (true) {
            $params = [$slug];
            $sql = 'select id from products where slug=?';
            if ($ignoreId) { $sql .= ' and id<>?'; $params[] = $ignoreId; }
            if (!DB::row($sql . ' limit 1', $params)) return $slug;
            $suffix = '-' . $i++;
            $slug = mb_substr($base, 0, 190 - mb_strlen($suffix)) . $suffix;
        }
    }

    private function uploadedPreviewFilesValid(array &$errors): bool
    {
        if (empty($_FILES['preview_images']['name'][0])) return true;
        $before = count($errors);
        foreach ($_FILES['preview_images']['name'] as $idx => $original) {
            $err = $_FILES['preview_images']['error'][$idx] ?? UPLOAD_ERR_NO_FILE;
            if ($err === UPLOAD_ERR_NO_FILE) continue;
            if ($err !== UPLOAD_ERR_OK) { $errors[] = 'Preview image upload failed.'; continue; }
            if (($_FILES['preview_images']['size'][$idx] ?? 0) > 25 * 1024 * 1024) { $errors[] = 'Preview images must be 25MB or smaller.'; continue; }
            $tmp = $_FILES['preview_images']['tmp_name'][$idx] ?? '';
            $ext = strtolower(pathinfo((string)$original, PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg','jpeg','png','webp'], true) || !@getimagesize($tmp)) $errors[] = 'Preview images must be valid JPG, PNG, or WEBP files.';
        }
        return count($errors) === $before;
    }
    private function validateProduct(array $v, ?int $ignoreId = null): array
    {
        $errors = [];
        if ($v['title'] === '')
        {
            $errors[] = 'Product Title is required.';

        }
        if (mb_strlen($v['title']) > 190)
        {
            $errors[] = 'Product Title must be 190 characters or fewer.';

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
        if ($v['seo_title'] !== '' && mb_strlen($v['seo_title']) > 70)
        {
            $errors[] = 'SEO title must be 70 characters or fewer.';

        }
        if ($v['seo_description'] !== '' && mb_strlen($v['seo_description']) > 170)
        {
            $errors[] = 'SEO description must be 170 characters or fewer.';

        }
        return $errors;

    }
    private function deletePreviewPaths(?string $imagePath, ?string $originalImagePath): void
    {
        if ($imagePath) {
            $path = public_path(ltrim($imagePath, '/'));
            $base = realpath(public_path('uploads/product_previews'));
            $real = realpath($path);
            if ($base && $real && is_file($real) && ($real === $base || str_starts_with($real, $base . DIRECTORY_SEPARATOR))) @unlink($real);
        }
        if ($originalImagePath) {
            $originalPath = app_path('storage/app/private/' . ltrim($originalImagePath, '/'));
            $originalBase = realpath(app_path('storage/app/private/product_previews'));
            $originalReal = realpath($originalPath);
            if ($originalBase && $originalReal && is_file($originalReal) && ($originalReal === $originalBase || str_starts_with($originalReal, $originalBase . DIRECTORY_SEPARATOR))) @unlink($originalReal);
        }
    }

    private function savePreviewImages(int $productId, array &$errors): array
    {
        $createdImageIds = [];
        if (empty($_FILES['preview_images']['name'][0])) return $createdImageIds;
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
                try {
                    DB::exec('insert into product_images (product_id,image_path,original_image_path,watermark_status,watermark_error,alt_text,sort_order) values (?,?,?,?,?,?,?)', [$productId, $saved['image_path'], $saved['original_image_path'], $saved['watermark_status'], $saved['watermark_error'], $alt, $sort]);
                    $createdImageIds[] = (int)DB::id();
                } catch (Throwable $e) {
                    $this->deletePreviewPaths($saved['image_path'] ?? null, $saved['original_image_path'] ?? null);
                    $errors[] = 'Preview image could not be attached to the product.';
                }
            }
        }
        return $createdImageIds;
    }

    private function cleanupCreatedPreviewImages(int $productId, array $imageIds): void
    {
        foreach ($imageIds as $imageId) $this->deletePreviewImage((int)$imageId, $productId);
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
            $this->deletePreviewPaths($img['image_path'] ?? null, $img['original_image_path'] ?? null);
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
        if ($base && $real && is_file($real) && ($real === $base || str_starts_with($real, $base . DIRECTORY_SEPARATOR)))
        {
            @unlink($real);

        }
        DB::exec('delete from product_files where id=? and product_id=?', [$fileId, $productId]);
        return true;

    }

    private function cleanupProductUploadRowsAndFiles(int $productId): void
    {
        foreach (DB::rows('select id from product_images where product_id=?', [$productId]) as $img) $this->deletePreviewImage((int)$img['id'], $productId);
        foreach (DB::rows('select id from product_files where product_id=?', [$productId]) as $file) $this->deleteProductFile((int)$file['id'], $productId);
    }

    private function cleanupNewProductAfterFailedSave(int $productId, int $designerId): void
    {
        (new IpRiskRepository())->cleanupProductRecords($productId);
        $this->cleanupProductUploadRowsAndFiles($productId);
        DB::exec('delete from product_tags where product_id=?', [$productId]);
        DB::exec('delete from product_license_types where product_id=?', [$productId]);
        DB::exec('delete from products where id=? and designer_id=?', [$productId, $designerId]);
    }
    private function saveProductFiles(int $productId, array &$errors): array
    {
        $createdFileIds = [];
        if (empty($_FILES['product_files']['name'][0]))
        {
            return $createdFileIds;

        }
        $dir = app_path('storage/protected_uploads/products');
        if (!is_dir($dir))
        {
            mkdir($dir, 0750, true);

        }
        foreach ($_FILES['product_files']['name'] as $idx => $original)
        {
            $error = $_FILES['product_files']['error'][$idx] ?? UPLOAD_ERR_NO_FILE;
            if ($error === UPLOAD_ERR_NO_FILE) continue;
            if ($error !== UPLOAD_ERR_OK)
           {
                $errors[] = 'Product file upload failed for ' . basename((string)$original) . '.';
                continue;

           }
            $name = bin2hex(random_bytes(12)) . '-' . basename($original);
            $absolutePath = $dir . '/' . $name;
            if (!move_uploaded_file($_FILES['product_files']['tmp_name'][$idx], $absolutePath))
           {
                $errors[] = 'Product file could not be saved: ' . basename((string)$original) . '.';
                continue;

           }
            try {
                DB::exec( 'insert into product_files (product_id,storage_path,original_name,file_size,mime_type) values (?,?,?,?,?)', [ $productId, 'products/' . $name, $original, $_FILES['product_files']['size'][$idx], $_FILES['product_files']['type'][$idx] ?? 'application/octet-stream', ] );
                $createdFileIds[] = (int)DB::id();
            } catch (Throwable $e) {
                @unlink($absolutePath);
                $errors[] = 'Product file could not be attached to the product: ' . basename((string)$original) . '.';
            }

        }
        return $createdFileIds;

    }

    private function cleanupCreatedProductFiles(int $productId, array $fileIds): void
    {
        foreach ($fileIds as $fileId) $this->deleteProductFile((int)$fileId, $productId);
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
        if (in_array($existing['status'], ['disabled','archived','deleted'], true))
        {
            return $existing['status'];

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
        $this->requireOnboardingComplete();
        H::requireSeller();
        $d = $this->d();
        $status = $_GET['status'] ?? 'all';
        $allowed = ['draft', 'pending_review', 'approved', 'published', 'rejected', 'disabled', 'archived'];
        $params = [$d['id']];
        $where = 'where p.designer_id=? and p.status<>"deleted"';
        if (in_array($status, $allowed, true))
        {
            $where .= ' and p.status=?';
            $params[] = $status;

        }
        H::view('seller/products', [ 'status' => $status, 'products' => DB::rows( 'select p.*,c.name category_name,(select count(*) from order_items oi join orders o on o.id=oi.order_id where oi.product_id=p.id and o.payment_status in ("paid","partially_refunded")) completed_order_count,(select image_path from product_images pi where pi.product_id=p.id order by pi.sort_order,pi.id limit 1) thumbnail from products p left join categories c on c.id=p.category_id ' . $where . ' order by p.updated_at desc', $params ), ]);

    }
    public function editProduct($id = null)
    {
        $this->requireOnboardingComplete();
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
            $this->uploadedPreviewFilesValid($errors);
            if (!$errors && $p && in_array($p['status'], ['pending_review','approved','published'], true)) {
                $preRisk = $this->submittedIpRiskPreview((int)$p['id'], $values);
                $wantsReview = ($_POST['action'] ?? 'draft') === 'review';
                $publicationSensitive = $wantsReview || in_array($p['status'], ['approved','published'], true);
                if ($publicationSensitive && $preRisk['requires_confirmation'] && empty($_POST['ip_rights_confirmation'])) {
                    $errors[] = 'Please confirm your legal right to sell this design before submitting or saving risk-bearing changes.';
                    $this->renderProductForm($p, $errors, $d, $preRisk);
                    return;
                }
            }
            if (!$errors)
           {
                $createdPreviewIds = [];
                $createdFileIds = [];

                if ($p)
               {
                    $productId = (int)$p['id'];
                    $createdPreviewIds = $this->savePreviewImages($productId, $errors);
                    if ($errors) {
                        $this->cleanupCreatedPreviewImages($productId, $createdPreviewIds);
                        $this->renderProductForm($p, $errors, $d);
                        return;
                    }

                    $createdFileIds = $this->saveProductFiles($productId, $errors);
                    if ($errors) {
                        $this->cleanupCreatedPreviewImages($productId, $createdPreviewIds);
                        $this->cleanupCreatedProductFiles($productId, $createdFileIds);
                        $this->renderProductForm($p, $errors, $d);
                        return;
                    }
                }

                $status = $this->productStatusForSave($p, $values);
                $values['slug'] = $p ? (string)$p['slug'] : $this->uniqueProductSlug($values['title']);
                $fileTypes = implode(',', $values['file_types']);
                $ipWorkflow = new ProductIpRiskWorkflow();
                $publicationSensitive = $status === 'pending_review' || ($p && in_array($p['status'], ['approved','published'], true));

                if ($p) {
                    $productId = (int)$p['id'];
                    try {
                        DB::begin();
                        DB::exec( 'update products set title=?,slug=?,short_description=?,description=?,price=?,fulfillment_type=?,manual_delivery_instructions=?,category_id=?,tags_text=null,file_types=?,commercial_license_enabled=?,commercial_license_price=?,pod_allowed=?,digital_resale_prohibited=1,ai_disclosure=?,seo_title=?,seo_description=?,status=?,rejection_reason=case when ?="pending_review" then null else rejection_reason end,updated_at=now() where id=?', [ $values['title'], $values['slug'], $values['short_description'], $values['description'], $values['price'], $values['fulfillment_type'], $values['manual_delivery_instructions'], $values['category_id'], $fileTypes, $values['commercial_license_enabled'], $values['commercial_license_price'], $values['pod_allowed'], $values['ai_disclosure'], $values['seo_title'], $values['seo_description'], $status, $status, $p['id'], ] );
                        $this->syncTags($productId, $values['tags']);
                        LicenseService::syncProductLicenses($productId, $postedLicenses);
                        $ipRiskResult = $ipWorkflow->scanProduct($productId, (int)H::user()['id']);
                        if ($ipRiskResult['matches'] && ($publicationSensitive || !empty($_POST['ip_rights_confirmation']))) {
                            if (empty($_POST['ip_rights_confirmation'])) {
                                DB::rollBack();
                                $this->cleanupCreatedPreviewImages($productId, $createdPreviewIds);
                                $this->cleanupCreatedProductFiles($productId, $createdFileIds);
                                $errors[] = 'Please confirm your legal right to sell this design before saving or submitting a flagged product.';
                                $this->renderProductForm($p, $errors, $d, $ipRiskResult);
                                return;
                            }
                            $ipWorkflow->recordConfirmationForScan($productId, (int)H::user()['id'], (int)$ipRiskResult['scan_id']);
                        }
                        $requiresIpReview = !empty($ipRiskResult['matches']) && !in_array($ipRiskResult['state']['review_status'] ?? '', ['approved','published_flagged'], true);
                        if ($status === 'pending_review' && !$requiresIpReview) {
                            $status = 'approved';
                            DB::exec('update products set status="approved",rejection_reason=null,updated_at=now() where id=? and designer_id=?', [$productId, $d['id']]);
                        }
                        DB::commit();
                    } catch (Throwable $e) {
                        if (DB::pdo()->inTransaction()) {
                            DB::rollBack();
                        }
                        error_log(
                            'Seller product save failed for product ' . $productId .
                            ': ' . get_class($e) .
                            ': ' . $e->getMessage() .
                            ' in ' . $e->getFile() .
                            ':' . $e->getLine()
                        );
                        $this->cleanupCreatedPreviewImages($productId, $createdPreviewIds);
                        $this->cleanupCreatedProductFiles($productId, $createdFileIds);
                        $errors[] = 'Product could not be saved safely. Please try again.';
                        $this->renderProductForm($p, $errors, $d);
                        return;
                    }
                } else {
                    $productId = 0;
                    try {
                        DB::exec( 'insert into products (designer_id,category_id,title,slug,short_description,description,price,fulfillment_type,manual_delivery_instructions,tags_text,file_types,commercial_license_enabled,commercial_license_price,pod_allowed,digital_resale_prohibited,ai_disclosure,seo_title,seo_description,status) values (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [ $d['id'], $values['category_id'], $values['title'], $values['slug'], $values['short_description'], $values['description'], $values['price'], $values['fulfillment_type'], $values['manual_delivery_instructions'], null, $fileTypes, $values['commercial_license_enabled'], $values['commercial_license_price'], $values['pod_allowed'], 1, $values['ai_disclosure'], $values['seo_title'], $values['seo_description'], $status, ] );
                        $productId = (int)DB::id();
                        $this->syncTags($productId, $values['tags']);
                        LicenseService::syncProductLicenses($productId, $postedLicenses);
                        $createdPreviewIds = $this->savePreviewImages($productId, $errors);
                        if ($errors) {
                            $this->cleanupNewProductAfterFailedSave($productId, (int)$d['id']);
                            $this->renderProductForm(null, $errors, $d);
                            return;
                        }

                        $createdFileIds = $this->saveProductFiles($productId, $errors);
                        if ($errors) {
                            $this->cleanupNewProductAfterFailedSave($productId, (int)$d['id']);
                            $this->renderProductForm(null, $errors, $d);
                            return;
                        }

                        $ipRiskResult = $ipWorkflow->scanProduct($productId, (int)H::user()['id']);
                        if ($ipRiskResult['matches'] && ($publicationSensitive || !empty($_POST['ip_rights_confirmation']))) {
                            if (empty($_POST['ip_rights_confirmation'])) {
                                $safeStatus = $status === 'pending_review' ? 'draft' : $status;
                                DB::exec('update products set status=?,updated_at=now() where id=? and designer_id=?', [$safeStatus, $productId, $d['id']]);
                                $errors[] = 'Please confirm your legal right to sell this design before submitting a flagged product for review.';
                                H::flash('error', 'Please confirm your legal right to sell this design before submitting a flagged product for review.');
                                H::redirect('/seller/product/' . $productId);
                            }
                            $ipWorkflow->recordConfirmationForScan($productId, (int)H::user()['id'], (int)$ipRiskResult['scan_id']);
                        }
                        $requiresIpReview = !empty($ipRiskResult['matches']) && !in_array($ipRiskResult['state']['review_status'] ?? '', ['approved','published_flagged'], true);
                        if ($status === 'pending_review' && !$requiresIpReview) {
                            $status = 'approved';
                            DB::exec('update products set status="approved",rejection_reason=null,updated_at=now() where id=? and designer_id=?', [$productId, $d['id']]);
                        }
                    } catch (Throwable $e) {
                        error_log(
                            'New seller product save failed for product ' . $productId .
                            ': ' . get_class($e) .
                            ': ' . $e->getMessage() .
                            ' in ' . $e->getFile() .
                            ':' . $e->getLine()
                        );
                        if ($productId > 0) {
                            $this->cleanupNewProductAfterFailedSave($productId, (int)$d['id']);
                        }
                        $errors[] = 'Product could not be saved safely. Please try again.';
                        $this->renderProductForm(null, $errors, $d);
                        return;
                    }
                }

                H::flash('success', $this->flashMessageForProductStatus($status));
                H::redirect('/seller/products');

           }

        }
        $this->renderProductForm($p, $errors, $d);

    }

    private function submittedIpRiskPreview(int $productId, array $values): array
    {
        $repo = new IpRiskRepository();
        $scanner = new IpRiskScanner();
        $tags = array_values(array_filter(array_map('trim', preg_split('/[,\n]+/', (string)$values['tags']))));
        $fileNames = array_column(DB::rows('select original_name from product_files where product_id=? order by original_name', [$productId]), 'original_name');
        foreach (($_FILES['product_files']['name'] ?? []) as $name) {
            if (trim((string)$name) !== '') {
                $fileNames[] = (string)$name;
            }
        }
        $matches = $scanner->scan([
            'title' => $values['title'] ?? '',
            'description' => trim(($values['short_description'] ?? '') . ' ' . ($values['description'] ?? '')),
            'tags' => $tags,
            'seo_title' => $values['seo_title'] ?? '',
            'seo_description' => $values['seo_description'] ?? '',
            'file_names' => $fileNames,
        ], $repo->enabledTermsWithAliases());

        return [
            'state' => null,
            'matches' => $matches,
            'requires_confirmation' => count($matches) > 0,
        ];
    }

    private function productHasCompletedOrders(int $productId): bool
    {
        return (bool) DB::row('select oi.id from order_items oi join orders o on o.id=oi.order_id where oi.product_id=? and o.payment_status in ("paid","partially_refunded") limit 1', [$productId]);
    }

    private function permanentlyDeleteProduct(int $productId): void
    {
        (new IpRiskRepository())->cleanupProductRecords($productId);
        DB::exec('delete from cart_items where product_id=?', [$productId]);
        DB::exec('delete from wishlists where product_id=?', [$productId]);
        DB::exec('delete from product_tags where product_id=?', [$productId]);
        DB::exec('delete from product_license_types where product_id=?', [$productId]);
        $this->cleanupProductUploadRowsAndFiles($productId);
        DB::exec('delete from products where id=?', [$productId]);
    }

    private function duplicateProductTitle(string $title): string
    {
        $suffix = ' Copy';
        $base = trim($title) !== '' ? trim($title) : 'Product';
        $maxBaseLength = 190 - mb_strlen($suffix);
        return rtrim(mb_substr($base, 0, $maxBaseLength)) . $suffix;
    }


    public function duplicateProduct($id)
    {
        $this->requireOnboardingComplete();
        H::requireSeller();
        $d = $this->d();
        $source = DB::row('select * from products where id=? and designer_id=? and status<>"deleted"', [(int)$id, $d['id']]) ?? H::abort(404);
        $copyTitle = $this->duplicateProductTitle((string)$source['title']);
        $slug = $this->uniqueProductSlug($copyTitle);
        try {
            DB::begin();
            DB::exec('insert into products (designer_id,category_id,title,slug,short_description,description,price,fulfillment_type,manual_delivery_instructions,tags_text,file_types,commercial_license_enabled,commercial_license_price,pod_allowed,digital_resale_prohibited,ai_disclosure,seo_title,seo_description,status) values (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,"draft")', [
                $d['id'], $source['category_id'], $copyTitle, $slug, $source['short_description'], $source['description'], $source['price'], $source['fulfillment_type'], $source['manual_delivery_instructions'], null, $source['file_types'], $source['commercial_license_enabled'], $source['commercial_license_price'], $source['pod_allowed'], $source['digital_resale_prohibited'], $source['ai_disclosure'], $source['seo_title'], $source['seo_description'],
            ]);
            $newId = (int)DB::id();
            foreach (DB::rows('select tag_id from product_tags where product_id=?', [(int)$source['id']]) as $tag) {
                DB::exec('insert ignore into product_tags (product_id,tag_id) values (?,?)', [$newId, $tag['tag_id']]);
            }
            foreach (DB::rows('select license_type_id,is_enabled,is_default,price,custom_name,description,sort_order from product_license_types where product_id=?', [(int)$source['id']]) as $license) {
                DB::exec('insert into product_license_types (product_id,license_type_id,is_enabled,is_default,price,custom_name,description,sort_order) values (?,?,?,?,?,?,?,?)', [$newId, $license['license_type_id'], $license['is_enabled'], $license['is_default'], $license['price'], $license['custom_name'], $license['description'], $license['sort_order']]);
            }
            DB::commit();
            H::flash('success', 'Product duplicated as a draft. Preview images and downloadable files were not copied; add or upload files before submitting the copy for review.');
            H::redirect('/seller/product/' . $newId);
        } catch (Throwable $e) {
            DB::rollBack();
            H::flash('error', 'Product could not be duplicated. Please try again.');
            H::redirect('/seller/products');
        }
    }

    public function archiveProduct($id)
    {
        $this->requireOnboardingComplete();
        H::requireSeller();
        $d = $this->d();
        $productId = (int) $id;
        $p = DB::row('select id,status from products where id=? and designer_id=?', [$productId, $d['id']]) ?? H::abort(404);
        if (($p['status'] ?? '') === 'deleted') {
            H::flash('error', 'Deleted products cannot be archived.');
            H::redirect('/seller/products');
        }
        DB::exec('update products set status="archived",updated_at=now() where id=? and designer_id=?', [$productId, $d['id']]);
        H::flash('success', 'Product archived and hidden from public listings. Historical order records remain available.');
        H::redirect('/seller/products?status=archived');
    }

    public function restoreProduct($id)
    {
        $this->requireOnboardingComplete();
        H::requireSeller();
        $d = $this->d();
        $productId = (int) $id;
        $p = DB::row('select id,status from products where id=? and designer_id=?', [$productId, $d['id']]) ?? H::abort(404);
        if (!in_array($p['status'], ['archived','deleted'], true)) {
            H::flash('error', 'Only archived or deleted products can be restored to draft.');
            H::redirect('/seller/products');
        }
        DB::exec('update products set status="draft",updated_at=now() where id=? and designer_id=? and status in ("archived","deleted")', [$productId, $d['id']]);
        H::flash('success', 'Product restored as a draft. Submit it for review before publishing again.');
        H::redirect('/seller/products?status=draft');
    }

    public function deleteProduct($id)
    {
        $this->requireOnboardingComplete();
        H::requireSeller();
        $d = $this->d();
        $productId = (int) $id;
        $p = DB::row('select id,status from products where id=? and designer_id=?', [$productId, $d['id']]) ?? H::abort(404);
        if ($this->productHasCompletedOrders($productId)) {
            DB::exec('update products set status="archived",updated_at=now() where id=? and designer_id=?', [$productId, $d['id']]);
            H::flash('warning', 'This product has completed orders, so it was archived instead of permanently deleted. Buyer, seller, and admin order history remain intact.');
            H::redirect('/seller/products?status=archived');
        }
        if (!in_array($p['status'], ['draft','rejected','archived','disabled','deleted'], true)) {
            H::flash('error', 'Only draft, rejected, disabled, or archived products with no completed orders can be permanently deleted. Archive this product instead.');
            H::redirect('/seller/products');
        }
        $this->permanentlyDeleteProduct($productId);
        H::flash('success', 'Product permanently deleted because it had no completed orders.');
        H::redirect('/seller/products');
    }

    public function submitProduct($id)
    {
        $this->requireOnboardingComplete();
        H::requireSeller();
        $d = $this->d();
        $productId = (int)$id;
        $p = DB::row('select status,fulfillment_type,manual_delivery_instructions from products where id=? and designer_id=?', [$productId, $d['id']]) ?? H::abort(404);
        if (in_array($p['status'], ['archived','deleted'], true)) {
            H::flash('error', 'Archived or deleted products must be restored to draft before they can be submitted.');
            H::redirect('/seller/products?status='.$p['status']);
        }
        if (($p['status'] ?? '') === 'disabled') {
            H::flash('error', 'Disabled products cannot be submitted. Contact an admin if this product should be re-enabled.');
            H::redirect('/seller/products?status=disabled');
        }
        if (($p['fulfillment_type'] ?? 'downloadable') === 'google_drive' && mb_strlen(trim((string)($p['manual_delivery_instructions'] ?? ''))) < 5) {
            H::flash('error','Manual delivery instructions are required before submitting a Google Drive delivery product.');
            H::redirect('/seller/product/'.$productId);
        }

        $ipWorkflow = new ProductIpRiskWorkflow();
        try {
            DB::begin();
            $ipRiskResult = $ipWorkflow->scanProduct($productId, (int)H::user()['id']);
            if ($ipRiskResult['requires_confirmation']) {
                if (empty($_POST['ip_rights_confirmation'])) {
                    DB::rollBack();
                    H::flash('error','Please confirm your legal right to sell this design before submitting a flagged product for review.');
                    H::redirect('/seller/product/'.$productId);
                }
                $ipWorkflow->recordConfirmationForScan($productId, (int)H::user()['id'], (int)$ipRiskResult['scan_id']);
            }

            $requiresIpReview = !empty($ipRiskResult['matches']) && !in_array($ipRiskResult['state']['review_status'] ?? '', ['approved','published_flagged'], true);
            $nextStatus = $requiresIpReview ? 'pending_review' : 'approved';
            DB::exec('update products set status=?,rejection_reason=null,updated_at=now() where id=? and designer_id=?', [$nextStatus, $productId, $d['id']]);
            DB::commit();
        } catch (Throwable $e) {
            if (DB::pdo()->inTransaction()) {
                DB::rollBack();
            }
            H::flash('error','Product could not be submitted safely. Please try again.');
            H::redirect('/seller/product/'.$productId);
        }

        H::flash('success', $nextStatus === 'approved' ? 'Product published.' : 'Product flagged and submitted for IP review.');
        H::redirect('/seller/products');
    }

    public function disableProduct($id)
    {
        $this->requireOnboardingComplete();
        H::requireSeller();
        DB::exec( 'update products set status="disabled",updated_at=now() where id=? and designer_id=?', [$id, $this->d()['id']] );
        H::redirect('/seller/products');

    }
    public function sales()
    {
        $this->requireOnboardingComplete();
        H::requireSeller();
        H::view('seller/sales', [ 'sales' => DB::rows( 'select oi.*,o.status order_status,o.payment_status,u.email,sp.payout_status from order_items oi join orders o on o.id=oi.order_id join users u on u.id=o.user_id left join seller_payouts sp on sp.order_id=oi.order_id and sp.designer_id=oi.designer_id where oi.designer_id=? and o.payment_status in ("paid","partially_refunded") order by oi.created_at desc', [$this->d()['id']] ), ]);

    }

    public function saleDetail($id)
    {
        $this->requireOnboardingComplete();
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
        $this->requireOnboardingComplete();
        H::requireSeller();
        H::view('seller/referrals', [ 'refs' => DB::rows('select * from referrals where referrer_user_id=?', [H::user()['id']]), ]);

    }
    public function rank()
    {
        $this->requireOnboardingComplete();
        H::requireSeller();
        H::view('seller/rank', [ 'd' => $this->d(), ]);

    }

    private function sellerCouponRestrictions(int $id): array
    {
        $out = ['product'=>'','category'=>''];
        foreach (DB::rows('select restrictable_type, group_concat(restrictable_id order by restrictable_id) ids from coupon_restrictions where coupon_id=? group by restrictable_type', [$id]) as $r) $out[$r['restrictable_type']] = $r['ids'];
        return $out;
    }

    private function saveSellerRestrictions(int $couponId, int $designerId): void
    {
        DB::exec('delete from coupon_restrictions where coupon_id=?', [$couponId]);
        foreach (array_filter(array_map('intval', preg_split('/\s*,\s*/', trim($_POST['product_ids'] ?? ''), -1, PREG_SPLIT_NO_EMPTY))) as $pid) {
            if (DB::row('select id from products where id=? and designer_id=?', [$pid,$designerId])) DB::exec('insert ignore into coupon_restrictions (coupon_id,restrictable_type,restrictable_id) values (?,"product",?)', [$couponId,$pid]);
        }
        foreach (array_filter(array_map('intval', preg_split('/\s*,\s*/', trim($_POST['category_ids'] ?? ''), -1, PREG_SPLIT_NO_EMPTY))) as $cid) {
            if (DB::row('select id from products where category_id=? and designer_id=? limit 1', [$cid,$designerId])) DB::exec('insert ignore into coupon_restrictions (coupon_id,restrictable_type,restrictable_id) values (?,"category",?)', [$couponId,$cid]);
        }
    }

    public function coupons($id = null)
    {
        $d = $this->requireOnboardingComplete();
        $creating = ($id === 'new');
        if ($creating) $id = null;
        $ownedCoupon = $id ? DB::row('select * from coupons where id=? and seller_id=? and scope="seller"', [(int)$id,$d['id']]) : null;
        if ($id && !$ownedCoupon) H::abort(404);
        if ($_POST) {
            $errors = [];
            $code = \App\Services\CouponService::normalizeCode($_POST['code'] ?? '');
            $type = in_array($_POST['discount_type'] ?? '', ['percent','fixed'], true) ? $_POST['discount_type'] : 'percent';
            $value = (float)($_POST['discount_value'] ?? 0);
            $starts = $_POST['starts_at'] ?: null;
            $ends = $_POST['ends_at'] ?: null;
            $usageLimitRaw = trim((string)($_POST['usage_limit'] ?? ''));
            $perUserLimitRaw = trim((string)($_POST['per_user_limit'] ?? ''));
            $usageLimit = null; $perUserLimit = null;
            if ($code === '') $errors[] = 'Coupon code is required.';
            if ($value <= 0 || ($type === 'percent' && $value > 100)) $errors[] = 'Discount value is invalid.';
            if ($starts && $ends && $ends < $starts) $errors[] = 'End date cannot be before start date.';
            if ($usageLimitRaw !== '' && (!ctype_digit($usageLimitRaw) || (int)$usageLimitRaw < 1)) $errors[] = 'Total usage limit must be blank or a positive integer.'; else $usageLimit = $usageLimitRaw === '' ? null : (int)$usageLimitRaw;
            if ($perUserLimitRaw !== '' && (!ctype_digit($perUserLimitRaw) || (int)$perUserLimitRaw < 1)) $errors[] = 'Per-user usage limit must be blank or a positive integer.'; else $perUserLimit = $perUserLimitRaw === '' ? null : (int)$perUserLimitRaw;
            if ($errors) H::flash('error', implode(' ', $errors));
            else { try { DB::begin();
                if ($id) DB::exec('update coupons set code=?,discount_type=?,discount_value=?,starts_at=?,ends_at=?,is_active=?,min_cart_amount=?,usage_limit=?,per_user_limit=? where id=? and seller_id=? and scope="seller"', [$code,$type,max(0.01,$value),$starts,$ends,isset($_POST['is_active'])?1:0,max(0,(float)($_POST['min_cart_amount'] ?? 0)),$usageLimit,$perUserLimit,(int)$id,$d['id']]);
                else { DB::exec('insert into coupons (code,scope,seller_id,discount_type,discount_value,starts_at,ends_at,is_active,min_cart_amount,usage_limit,per_user_limit,created_by) values (?,"seller",?,?,?,?,?,?,?,?,?,?)', [$code,$d['id'],$type,max(0.01,$value),$starts,$ends,isset($_POST['is_active'])?1:0,max(0,(float)($_POST['min_cart_amount'] ?? 0)),$usageLimit,$perUserLimit,H::user()['id']]); $id=DB::id(); }
                $this->saveSellerRestrictions((int)$id,(int)$d['id']); DB::commit(); H::flash('success','Coupon saved.'); H::redirect('/seller/coupons');
            } catch (Throwable $e) { DB::rollBack(); H::flash('error','Coupon code already exists or could not be saved.'); } }
        }
        if ($creating || $id) H::view('seller/coupon_form',['coupon'=>$id ? ($ownedCoupon ?: DB::row('select * from coupons where id=? and seller_id=? and scope="seller"',[(int)$id,$d['id']]) ?? H::abort(404)) : [],'restrictions'=>$id ? $this->sellerCouponRestrictions((int)$id) : ['product'=>'','category'=>'']]);
        else H::view('seller/coupons',['coupons'=>DB::rows('select c.*,(select group_concat(concat(restrictable_type,":",restrictable_id) separator ", ") from coupon_restrictions cr where cr.coupon_id=c.id) restriction_summary from coupons c where c.seller_id=? and c.scope="seller" order by c.created_at desc',[$d['id']])]);
    }

}
