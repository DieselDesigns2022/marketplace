<h1>You won a Creative Moth prize!</h1>
<p>Hello <?=htmlspecialchars((string)$data['name'],ENT_QUOTES,'UTF-8')?>,</p>
<p>Your prize: <strong><?=htmlspecialchars((string)$data['title'],ENT_QUOTES,'UTF-8')?></strong></p>
<p><?=nl2br(htmlspecialchars((string)$data['instructions'],ENT_QUOTES,'UTF-8'))?></p>
<p><a href="<?=htmlspecialchars((string)$data['prize_url'],ENT_QUOTES,'UTF-8')?>">Open your prize details and download</a></p>
<p>Sign in to the Creative Moth account that won. Your prize message is also saved in your account.</p>
