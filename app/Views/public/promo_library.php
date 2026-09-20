<?php use App\Core\Helpers as H; ?>
<section class="promo-library-hero">
<p class="eyebrow">Spread the word</p><h1>Creative Moth Promotional Graphics</h1>
<p>Download ready-to-share graphics for your social posts, website, email, or profile. Use the suggested captions as-is or make them your own.</p>
</section>
<?php if(!$groups):?><div class="card promo-empty"><h2>Graphics are coming soon</h2><p>Check back for new Creative Moth graphics.</p></div><?php endif;?>
<?php foreach($platforms as $key=>$label):if(empty($groups[$key]))continue;$sizeGroups=[];foreach($groups[$key] as $graphic)$sizeGroups[$graphic['size_label']][]=$graphic;?>
<section class="promo-library-section" aria-labelledby="promo-<?=H::e($key)?>"><h2 id="promo-<?=H::e($key)?>"><?=H::e($label)?></h2>
<?php foreach($sizeGroups as $sizeLabel=>$graphics):$sizeId='promo-'.$key.'-'.substr(hash('sha256',$sizeLabel),0,12);?>
<section class="promo-size-group" aria-labelledby="<?=H::e($sizeId)?>"><h3 id="<?=H::e($sizeId)?>"><?=H::e($sizeLabel)?></h3>
<div class="promo-library-grid"><?php foreach($graphics as $graphic):$imageUrl=H::canonical('/promo-library/image/'.$graphic['id']);?>
<article class="card promo-graphic-card">
<a href="/promo-library/image/<?=(int)$graphic['id']?>" target="_blank" rel="noopener" aria-label="View full size: <?=H::e($graphic['alt_text'])?>"><img src="/promo-library/image/<?=(int)$graphic['id']?>" alt="<?=H::e($graphic['alt_text'])?>" loading="lazy"></a>
<div class="promo-graphic-details"><p class="promo-meta"><?=H::e($graphic['category'])?></p>
<?php if($graphic['suggested_caption']):?><div><h3>Suggested caption</h3><p><?=nl2br(H::e($graphic['suggested_caption']))?></p></div><?php endif;?>
<div class="promo-actions">
<button type="button" class="promo-copy" data-copy-url="<?=H::e($imageUrl)?>" aria-live="polite">Copy link</button>
<a class="btn" href="/promo-library/download/<?=(int)$graphic['id']?>">Download image</a>
<button type="button" class="promo-share" data-share-url="<?=H::e($imageUrl)?>" data-share-title="<?=H::e($label.' — Creative Moth')?>">Share</button>
</div></div></article><?php endforeach;?></div></section><?php endforeach;?></section><?php endforeach;?>
<script>
document.addEventListener('click',async function(event){
 const copy=event.target.closest('.promo-copy');if(copy){try{await navigator.clipboard.writeText(copy.dataset.copyUrl);copy.textContent='Link copied';setTimeout(()=>copy.textContent='Copy link',1800);}catch(e){window.prompt('Copy this image link:',copy.dataset.copyUrl);}return;}
 const share=event.target.closest('.promo-share');if(share){if(navigator.share){try{await navigator.share({title:share.dataset.shareTitle,url:share.dataset.shareUrl});}catch(e){if(e.name!=='AbortError')window.prompt('Copy this image link:',share.dataset.shareUrl);}}else{window.prompt('Copy this image link:',share.dataset.shareUrl);}}
});
</script>
