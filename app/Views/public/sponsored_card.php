<?php use App\Core\Helpers as H; $promo=$sponsoredPromo??$gridSponsoredPromo??null;if($promo):?>
<article class="card product-card sponsored-card"><span class="sponsored-label">Sponsored</span><a href="/promo/click/<?=H::e($promo['click_token'])?>"><?php if($promo['image']):?><img src="<?=H::e(H::assetUrl($promo['image']))?>" alt=""><?php endif;?><h3><?=H::e($promo['title'])?></h3></a><p><?=H::e($promo['store'])?></p><?php if($promo['type']==='product'):?><strong><?=H::money($promo['price'])?></strong><?php endif;?></article>
<?php endif;?>
