<?php use App\Core\Helpers as H;?>
<h1>New from shops you follow</h1>
<p>Hello <?=H::e($data['name']??'there')?>,</p>
<p>These currently available products were added by shops you follow.</p>
<ul><?php foreach($data['products'] as $product):?><li><a href="<?=H::e(H::baseUrl().'/product/'.rawurlencode($product['slug']))?>"><?=H::e($product['title'])?></a> by <a href="<?=H::e(H::baseUrl().'/store/'.rawurlencode($product['store_slug']))?>"><?=H::e($product['display_name'])?></a> — <?=H::money($product['price'])?></li><?php endforeach;?></ul>
<p><a href="<?=H::e($data['manage_preferences_url'])?>">Manage Email Preferences</a> · <a href="<?=H::e($data['unsubscribe_url'])?>">Unsubscribe from favorite-shop emails</a></p>
