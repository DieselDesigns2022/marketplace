<?php use App\Core\Helpers as H; ?>

<style>
.cm-winners-heading {
    text-align:center;
    padding:32px 20px;
    border:2px solid #35dbcb;
    background:linear-gradient(125deg,#15111d,#39205b);
    color:#fff;
    border-radius:16px;
}
.cm-winners-heading h1,
.cm-winners-heading .eyebrow {
    color:#fff;
}
.cm-winners-grid {
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(min(100%,290px),1fr));
    gap:16px;
}
.cm-winner-card {
    padding:20px;
    min-width:0;
}
.cm-winner-card h3 {
    margin-top:0;
}
.cm-winner-card .cm-prize-title {
    font-size:1.08rem;
    font-weight:700;
    overflow-wrap:anywhere;
}
.cm-winner-card p {
    overflow-wrap:anywhere;
}
</style>

<section class="page-section cm-winners-heading">
    <p class="eyebrow">🎉 Creative Moth Grand Opening</p>
    <h1>🏆 Our Grand Opening Winners!</h1>
    <p>Our Grand Opening celebration has officially ended!</p>
    <p>Thank you to everyone who shopped, participated, supported our designers, and celebrated with us.</p>
    <p>Congratulations to all our winners! 💜</p>
    <a class="btn" href="/browse">Continue Shopping</a>
</section>

<section class="page-section">
    <h2>🎁 Grand Prize Giveaway Winners</h2>
    <?php if(empty($grandPrizes)): ?>
        <p class="card">No awarded Grand Prize results have been published yet.</p>
    <?php else: ?>
        <div class="cm-winners-grid">
            <?php foreach($grandPrizes as $winner): ?>
                <article class="card cm-winner-card">
                    <h3>🏆 <?=H::e($winner['winner_label'])?></h3>
                    <p class="muted">Won:</p>
                    <p class="cm-prize-title"><?=H::e($winner['prize_title'])?></p>
                    <p>
                        Prize from
                        <a href="/store/<?=H::e(rawurlencode($winner['donor_slug']))?>">
                            <?=H::e($winner['donor_store'])?>
                        </a>
                    </p>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php if(!empty($champions)): ?>
<section class="page-section">
    <h2>⚔️ Battle of the Designers Champion</h2>
    <div class="cm-winners-grid">
        <?php foreach($champions as $champion): ?>
            <article class="card cm-winner-card">
                <h3>🏆 <?=H::e($champion['winner_label'])?></h3>
                <p class="cm-prize-title">$100 Creative Moth Advertising Credit</p>
                <p>
                    <a href="/store/<?=H::e(rawurlencode($champion['store_slug']))?>">
                        Visit the winning store
                    </a>
                </p>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php if(!empty($wheelPrizes)): ?>
<section class="page-section">
    <h2>🎡 Prize Wheel Winners</h2>
    <div class="cm-winners-grid">
        <?php foreach($wheelPrizes as $winner): ?>
            <article class="card cm-winner-card">
                <h3>🎉 <?=H::e($winner['winner_label'])?></h3>
                <p class="muted">Won:</p>
                <p class="cm-prize-title"><?=H::e($winner['prize_title'])?></p>
                <p>
                    Reward from
                    <a href="/store/<?=H::e(rawurlencode($winner['donor_slug']))?>">
                        <?=H::e($winner['donor_store'])?>
                    </a>
                </p>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<section class="card page-section" style="text-align:center">
    <h2>Thank You for Celebrating With Us! 🦋</h2>
    <p>Our Grand Opening may be over, but there's so much more creativity to discover.</p>
    <a class="btn" href="/browse">Explore Creative Moth</a>
</section>
