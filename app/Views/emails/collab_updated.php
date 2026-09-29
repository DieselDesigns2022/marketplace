<?php

use App\Core\Helpers as H;

?>
<h2>Your collab files were updated</h2>

<p>
    Hello <?=H::e($data['name'] ?? '')?>.
</p>

<p>
    An approved file update was made to
    <strong><?=H::e($data['collab_title'] ?? 'your Creative Moth collab bundle')?></strong>.
    Your purchase now gives you access to the newest approved bundle.
</p>

<p>
    <a href="<?=H::e(
        H::baseUrl().
        '/dashboard/order/'.
        (int)($data['order_id'] ?? 0)
    )?>">
        Open your order and download the updated files
    </a>
</p>
