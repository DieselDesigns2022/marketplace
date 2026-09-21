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
<div class="promo-library-grid"><?php foreach($graphics as $graphic):$imageUrl=H::canonical('/promo-library/image/'.$graphic['id']);
$absoluteImageUrl=
    str_starts_with($imageUrl,'http://')||
    str_starts_with($imageUrl,'https://')
        ? $imageUrl
        : 'https://marketplace.dieseldesigns.co'.$imageUrl;
$promoDestination='https://creativemoth.com/';
$promoCaption=(string)($graphic['suggested_caption']??'');

$pinterestUrl=
    'https://www.pinterest.com/pin/create/button/?'.
    http_build_query([
        'url'=>$promoDestination,
        'media'=>$absoluteImageUrl,
        'description'=>$promoCaption
    ]);
?>
<article class="card promo-graphic-card">
<a href="/promo-library/image/<?=(int)$graphic['id']?>" target="_blank" rel="noopener" aria-label="View full size: <?=H::e($graphic['alt_text'])?>"><img src="/promo-library/image/<?=(int)$graphic['id']?>" alt="<?=H::e($graphic['alt_text'])?>" loading="lazy"></a>
<div class="promo-graphic-details"><p class="promo-meta"><?=H::e($graphic['category'])?></p>
<?php if($graphic['suggested_caption']):?>
<div>
<h3>Suggested caption</h3>
<button type="button" class="promo-caption-copybox" aria-label="Copy suggested caption">
<span class="promo-caption-text"><?=nl2br(H::e($graphic['suggested_caption']))?></span>
<span class="promo-caption-hint">Click to copy caption</span>
</button>
</div>
<?php endif;?>
<div class="promo-actions">
<a class="btn" href="/promo-library/download/<?=(int)$graphic['id']?>">Download image</a>
<a
    class="btn promo-social promo-social-pinterest"
    href="<?=H::e($pinterestUrl)?>"
    target="_blank"
    rel="noopener noreferrer"
>
    Pinterest
</a>

<button
    type="button"
    class="btn promo-social promo-social-facebook"
    data-caption="<?=H::e($promoCaption)?>"
    data-download="<?=H::e($imageUrl)?>"
>
    Facebook
</button>

<button
    type="button"
    class="btn promo-social promo-social-instagram"
    data-caption="<?=H::e($promoCaption)?>"
    data-download="<?=H::e($imageUrl)?>"
>
    Instagram
</button>
</div></div></article><?php endforeach;?></div></section><?php endforeach;?></section><?php endforeach;?>
<script>
document.addEventListener('click',async function(event){
 const caption=event.target.closest('.promo-caption-copybox');if(caption){const text=caption.querySelector('.promo-caption-text').innerText;const hint=caption.querySelector('.promo-caption-hint');try{await navigator.clipboard.writeText(text);hint.textContent='Caption copied!';setTimeout(()=>hint.textContent='Click to copy caption',1800);}catch(e){window.prompt('Copy this caption:',text);}return;}

 const social=event.target.closest('.promo-social-facebook,.promo-social-instagram');
 if(social){
     event.preventDefault();

     const caption=social.dataset.caption||'';
     const downloadUrl=social.dataset.download;

     try{
         if(caption){
             await navigator.clipboard.writeText(caption);
         }
     }catch(e){}

     if(downloadUrl){
         const download=document.createElement('a');
         download.href=downloadUrl;
         download.download='';
         document.body.appendChild(download);
         download.click();
         download.remove();
     }

     const originalText=social.textContent.trim();

     if(social.classList.contains('promo-social-facebook')){
         social.textContent='Opening Facebook…';
         setTimeout(()=>{
             window.open(
                 'https://www.facebook.com/',
                 '_blank',
                 'noopener,noreferrer'
             );
             social.textContent=originalText;
         },250);
     }else{
         social.textContent='Opening Instagram…';
         setTimeout(()=>{
             window.open(
                 'https://www.instagram.com/',
                 '_blank',
                 'noopener,noreferrer'
             );
             social.textContent=originalText;
         },250);
     }

     return;
 }
});
</script>
