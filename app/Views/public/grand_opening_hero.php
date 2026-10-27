<?php
$goDate=static function(string $utc): string {
    return (new \DateTimeImmutable($utc,new \DateTimeZone('UTC')))
        ->setTimezone(new \DateTimeZone('America/Detroit'))->format('M j, Y \a\t g:i A T');
};
?>
<section class="hero opening-hero">
<p class="eyebrow">Creative Moth Grand Opening</p>
<h1><?=$openingState==='live'?'The Grand Opening celebration is here!':'Something creative is about to take flight.'?></h1>
<?php if($openingState==='live'): ?>
<p>Shop Grand Opening deals from participating designers, discover new favorites, and join the celebration.</p>
<p><strong>Ends <?=H::e($goDate($openingEvent['ends_at']))?></strong></p>
<p><a class="btn" href="/browse?grand_opening=1">Shop the Grand Opening Sale</a> <a class="btn alt" href="/dashboard/passport">Get Your Passport</a></p>
<p><a href="/battle-of-the-designers">Battle of the Designers</a><?php if(!empty($partySellers)): ?> · <a href="#party-sellers">Prize Wheel Shops</a><?php endif; ?></p>
<?php else: ?>
<p>Save the date for independent designers, digital designs, opening deals, and a little Creative Moth magic.</p>
<p><strong><?=H::e($goDate($openingEvent['starts_at']))?> – <?=H::e($goDate($openingEvent['ends_at']))?></strong></p>
<p><a class="btn" href="/browse">Explore the Marketplace</a> <a class="btn alt" href="/register">Create Your Account</a></p>
<?php endif; ?>
</section>
