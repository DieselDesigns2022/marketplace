<?php
use App\Core\Helpers as H;

$size=(int)$report['page_size'];
$fp=(int)$report['followers_page'];
$wp=(int)$report['favorites_page'];
?>

<div class="card">
    <h1>Followers &amp; Favorites 💜</h1>
    <p class="muted">
        See who's following your shop and who's saving
        your products to their wishlist.
        Only you can access your shop's list.
    </p>

    <div class="dashboard-grid">
        <div class="card">
            <strong><?=number_format($report['follower_count'])?></strong>
            <span>Shop followers</span>
        </div>
        <div class="card">
            <strong><?=number_format($report['favorite_count'])?></strong>
            <span>Product favorites</span>
        </div>
        <div class="card">
            <strong><?=number_format($report['favoriting_buyers'])?></strong>
            <span>Shoppers saving your products</span>
        </div>
    </div>
</div>

<section class="card">
    <h2>People following your shop</h2>

    <?php if(!$report['followers']):?>
        <p class="muted">No followers on this page yet.</p>
    <?php else:?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>Shopper</th><th>Followed on</th></tr>
                </thead>
                <tbody>
                <?php foreach($report['followers'] as $f):?>
                    <tr>
                        <td>
                            <?php if(!empty($f['public_store_slug'])):?>
                                <a href="/store/<?=rawurlencode((string)$f['public_store_slug'])?>">
                                    <?=H::e($f['name'])?>
                                </a>
                            <?php else:?>
                                <?=H::e($f['name'])?>
                            <?php endif;?>
                        </td>
                        <td><?=H::e($f['created_at'])?></td>
                    </tr>
                <?php endforeach;?>
                </tbody>
            </table>
        </div>
    <?php endif;?>

    <div class="quick-actions">
        <?php if($fp>1):?>
            <a class="btn" href="/seller/followers?followers_page=<?=$fp-1?>&favorites_page=<?=$wp?>">
                Previous followers
            </a>
        <?php endif;?>
        <?php if($fp*$size<$report['follower_count']):?>
            <a class="btn" href="/seller/followers?followers_page=<?=$fp+1?>&favorites_page=<?=$wp?>">
                More followers
            </a>
        <?php endif;?>
    </div>
</section>

<section class="card">
    <h2>Shoppers who favorited your products</h2>

    <p class="muted">
        This shows product wishlist saves.
        Following a shop and saving a product are separate actions.
    </p>

    <?php if(!$report['favorites']):?>
        <p class="muted">No product favorites on this page yet.</p>
    <?php else:?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Shopper</th>
                        <th>Product saved</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach($report['favorites'] as $f):?>
                    <tr>
                        <td>
                            <?php if(!empty($f['public_store_slug'])):?>
                                <a href="/store/<?=rawurlencode((string)$f['public_store_slug'])?>">
                                    <?=H::e($f['name'])?>
                                </a>
                            <?php else:?>
                                <?=H::e($f['name'])?>
                            <?php endif;?>
                        </td>
                        <td>
                            <a href="/product/<?=rawurlencode((string)$f['slug'])?>">
                                <?=H::e($f['title'])?>
                            </a>
                        </td>
                        <td><?=H::e($f['created_at'])?></td>
                    </tr>
                <?php endforeach;?>
                </tbody>
            </table>
        </div>
    <?php endif;?>

    <div class="quick-actions">
        <?php if($wp>1):?>
            <a class="btn" href="/seller/followers?followers_page=<?=$fp?>&favorites_page=<?=$wp-1?>">
                Previous favorites
            </a>
        <?php endif;?>
        <?php if($wp*$size<$report['favorite_count']):?>
            <a class="btn" href="/seller/followers?followers_page=<?=$fp?>&favorites_page=<?=$wp+1?>">
                More favorites
            </a>
        <?php endif;?>
    </div>
</section>

<p class="muted">
    These lists reflect current follows and wishlist saves.
    If someone unfollows or removes a favorite,
    they will no longer appear here.
    Private emails and contact details are not displayed.
</p>
