<?php use App\Core\Helpers as H;?>
<h1><?=H::e(ucfirst((string)$data['frequency']))?> marketplace digest</h1>
<p>Hello <?=H::e($data['name']??'there')?>,</p>
<p>Here are products published on Asset Moth during this digest period.</p>
<ul><?php foreach($data['products'] as $product):?><li><a href="<?=H::e(H::baseUrl().'/product/'.rawurlencode($product['slug']))?>"><?=H::e($product['title'])?></a> by <?=H::e($product['display_name'])?> — <?=H::money($product['price'])?></li><?php endforeach;?></ul>
<p><a href="<?=H::e($data['manage_preferences_url'])?>">Manage Email Preferences</a> · <a href="<?=H::e($data['unsubscribe_url'])?>">Unsubscribe from <?=H::e($data['frequency'])?> emails</a></p>
