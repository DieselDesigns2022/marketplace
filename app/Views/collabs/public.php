<article class="card">
    <span class="badge">Collab Bundle</span>
    <h1><?=H::e($collab['title'])?></h1>
    <p><?=nl2br(H::e($collab['description']))?></p>
    <p><strong><?=H::money($collab['price_cents']/100)?></strong></p>
    <p>Sale starts <?=H::e($collab['sale_starts_at'])?> · available through <?=H::e($collab['sale_close_date'])?> 11:59 PM marketplace time.</p>
    <h2>Eligible participating designers</h2>
    <ul><?php foreach($participants as $participant):?><li><a href="/store/<?=H::e($participant['store_slug'])?>"><?=H::e($participant['display_name'])?></a></li><?php endforeach;?></ul>
    <a class="btn" href="/collab/<?=H::e($collab['slug'])?>/checkout<?= $storefront ? '?store='.(int)$storefront : '' ?>">Purchase Collab Bundle</a>
</article>
