<?php use App\Core\Helpers as H; $meta=$meta??[]; ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,follow">
<title><?=H::e($meta['title']??'Waitlist')?></title>
<link rel="stylesheet" href="/assets/css/app.css">
<script defer src="/assets/js/app.js?v=<?=filemtime(public_path('assets/js/app.js'))?>"></script>
</head>
<body class="minimal-page waitlist-page"<?php if(!empty($_SESSION['_visitor_analytics_page_view_id'])): ?>
 data-analytics-page-view="<?= (int)$_SESSION['_visitor_analytics_page_view_id'] ?>"
 data-analytics-csrf="<?= H::e(H::csrf()) ?>"
<?php endif; ?>>
<main class="minimal-shell">
<?php foreach(H::flashes() as $flash):?><div class="notice <?=H::e($flash['type'])?>"><?=H::e($flash['message'])?></div><?php endforeach;?>
<?php require app_path('app/Views/'.$view.'.php'); ?>
</main>
</body>
</html>
