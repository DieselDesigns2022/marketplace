<?php
use App\Core\Helpers as H;

$u = H::user();
$meta = $meta ?? [];
$defaultDescription = H::DEFAULT_DESCRIPTION;
$pageTitle = trim($meta['title'] ?? H::SITE_NAME);
if ($pageTitle === '') {
    $pageTitle = H::SITE_NAME;
}
$metaDescription = trim($meta['description'] ?? $defaultDescription);
if ($metaDescription === '') {
    $metaDescription = $defaultDescription;
}
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$canonical = $meta['canonical'] ?? H::canonical($path === '/browse' ? '/browse' : $path);
$noindexPrefixes = ['/admin', '/dashboard', '/seller', '/account', '/cart', '/checkout', '/download', '/login', '/register', '/forgot-password', '/apply'];
$robots = $meta['robots'] ?? null;
foreach ($noindexPrefixes as $prefix) {
    if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
        $robots = $robots ?: 'noindex,follow';
        break;
    }
}
$ogTitle = trim($meta['og_title'] ?? $pageTitle);
$ogDescription = trim($meta['og_description'] ?? $metaDescription);
$ogImage = H::assetUrl($meta['og_image'] ?? '');
$twitterCard = $meta['twitter_card'] ?? ($ogImage ? 'summary_large_image' : 'summary');
$twitterTitle = trim($meta['twitter_title'] ?? $ogTitle);
$twitterDescription = trim($meta['twitter_description'] ?? $ogDescription);
$twitterImage = H::assetUrl($meta['twitter_image'] ?? ($meta['og_image'] ?? ''));
$schemas = [];
foreach (['schema', 'json_ld'] as $schemaKey) {
    if (!empty($meta[$schemaKey])) {
        $schemas[] = $meta[$schemaKey];
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=H::e($pageTitle)?></title>
<meta name="description" content="<?=H::e($metaDescription)?>">
<?php if ($robots): ?><meta name="robots" content="<?=H::e($robots)?>">
<?php endif; ?>
<link rel="canonical" href="<?=H::e($canonical)?>">
<meta property="og:site_name" content="<?=H::e(H::SITE_NAME)?>">
<meta property="og:type" content="<?=H::e($meta['og_type'] ?? 'website')?>">
<meta property="og:title" content="<?=H::e($ogTitle)?>">
<meta property="og:description" content="<?=H::e($ogDescription)?>">
<meta property="og:url" content="<?=H::e($canonical)?>">
<?php if ($ogImage): ?><meta property="og:image" content="<?=H::e($ogImage)?>">
<?php endif; ?>
<meta name="twitter:card" content="<?=H::e($twitterCard)?>">
<meta name="twitter:title" content="<?=H::e($twitterTitle)?>">
<meta name="twitter:description" content="<?=H::e($twitterDescription)?>">
<?php if ($twitterImage): ?><meta name="twitter:image" content="<?=H::e($twitterImage)?>">
<?php endif; ?>
<?php foreach ($schemas as $schema): ?>
<?php
$json = null;
if (is_string($schema)) {
    $decodedSchema = json_decode($schema, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        $json = json_encode($decodedSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    }
} else {
    $json = json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
}
if ($json && json_decode($json) !== null):
?>
<script type="application/ld+json"><?=$json?></script>
<?php endif; ?>
<?php endforeach; ?>
<link rel="stylesheet" href="/assets/css/app.css">
<script defer src="/assets/js/app.js"></script>
</head>
<body>
<header class="top">
    <a class="brand brand-logo" href="/" aria-label="Asset Moth home">
        <img src="/assets/img/asset-moth-logo.png" alt="Asset Moth" width="190" height="42" style="display:block;max-height:42px;width:auto;max-width:190px;object-fit:contain;" onerror="this.hidden=true;this.nextElementSibling.hidden=false;">
        <span hidden>Asset Moth</span>
    </a>
    <nav>
        <a href="/browse">Browse</a>
        <a href="/sell">Sell</a>
        <a href="/about">About</a>
        <a href="/cart">Cart</a>
        <?php if($u): ?>
        <a href="/dashboard">Dashboard</a>
        <?php if($u['role']==='designer'||$u['role']==='admin'):?><a href="/seller">Seller</a><?php endif; ?>
        <?php if($u['role']==='admin'):?><a href="/admin">Admin</a><?php endif; ?>
        <form method="post" action="/logout"><input type="hidden" name="_csrf" value="<?=H::csrf()?>"><button>Logout</button></form>
        <?php else: ?>
        <a href="/login">Login</a>
        <a class="btn" href="/register">Register</a>
        <?php endif; ?>
    </nav>
</header>
<main class="wrap">
<?php foreach (H::flashes() as $flash): ?><div class="notice <?=H::e($flash['type'])?>"><?=H::e($flash['message'])?></div><?php endforeach; ?>
<?php require app_path('app/Views/'.$view.'.php'); ?>
</main>
<footer class="site-footer">
    <div>
        <strong>Asset Moth</strong>
        <p class="muted">A digital design marketplace for downloadable creative files from reviewed independent designers.</p>
    </div>
    <nav aria-label="Footer navigation">
        <a href="/browse">Browse</a>
        <a href="/sell">Sell</a>
        <a href="/about">About</a>
        <a href="/contact">Contact</a>
        <a href="/terms">Terms</a>
        <a href="/privacy">Privacy</a>
        <a href="/buyer-faq">Buyer FAQ</a>
        <a href="/seller-faq">Seller FAQ</a>
        <a href="/licensing-help">Licensing Help</a>
    </nav>
</footer>
</body>
</html>
