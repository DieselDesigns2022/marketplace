<article class="card collab-public-card">
    <?php
    $collabPreviewImages =
        \App\Services\CollabService::coverUrls(
            (int)$collab['id']
        );
    ?>

    <?php if($collabPreviewImages):?>
        <div class="collab-preview-gallery">
            <?php foreach($collabPreviewImages as $index => $preview):?>
                <img
                    class="collab-public-cover"
                    src="<?=H::e($preview)?>"
                    alt="<?=H::e($collab['title'])?> collab bundle preview <?=($index + 1)?>"
                    loading="<?=$index === 0 ? 'eager' : 'lazy'?>"
                    decoding="async"
                >
            <?php endforeach;?>
        </div>
    <?php endif;?>

    <span class="badge">Collab Bundle</span>
    <h1><?=H::e($collab['title'])?></h1>
    <p><?=nl2br(H::e($collab['description']))?></p>
    <p><strong><?=H::money($collab['price_cents']/100)?></strong></p>
    <p>
        Sale starts
        <strong><?=H::e($saleStartsDisplay)?></strong>
        · available through
        <strong><?=H::e($saleCloseDisplay)?></strong>
    </p>

    <?php if($collab['quantity_limit'] !== null):?>
        <p>
            <strong>
                <?=number_format((int)$soldCount)?> /
                <?=number_format((int)$collab['quantity_limit'])?>
                sold
            </strong>
            ·
            <strong>
                <?=number_format((int)$remainingCount)?>
                remaining
            </strong>
        </p>
    <?php endif;?>

    <section class="collab-included-files">
        <h2>What's Included</h2>

        <p>
            <strong>
                <?=number_format((int)$totalContributionFiles)?>
                digital file<?=$totalContributionFiles === 1 ? '' : 's'?>
            </strong>
        </p>

        <?php if(!empty($contributionCounts)):?>
            <ul>
                <?php foreach($contributionCounts as $row):?>
                    <li>
                        <strong><?=number_format((int)$row['file_count'])?></strong>
                        <?=H::e($row['category_name'])?>
                    </li>
                <?php endforeach;?>
            </ul>
        <?php endif;?>
    </section>
    <h2>Eligible participating designers</h2>
    <ul><?php foreach($participants as $participant):?><li><a href="/store/<?=H::e($participant['store_slug'])?>"><?=H::e($participant['display_name'])?></a></li><?php endforeach;?></ul>
    <a class="btn" href="/collab/<?=H::e($collab['slug'])?>/checkout<?= $storefront ? '?store='.(int)$storefront : '' ?>">Purchase Collab Bundle</a>
</article>
