<?php use App\Core\Helpers as H; $meta=$meta??[]; ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,follow">
<title><?=H::e($meta['title']??'Waitlist')?></title>
<link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="minimal-page waitlist-page">
<main class="minimal-shell">
<?php foreach(H::flashes() as $flash):?><div class="notice <?=H::e($flash['type'])?>"><?=H::e($flash['message'])?></div><?php endforeach;?>
<?php require app_path('app/Views/'.$view.'.php'); ?>
</main>
</body>
</html>
