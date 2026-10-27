<?php

use App\Core\Helpers as H;

$completed=(int)($passport['completed']??0);
$total=(int)($passport['total']??8);
$percent=(int)($passport['percent']??0);
?>

<style>
.passport-shell{
    max-width:1000px;
}
.passport-hero{
    padding:28px;
}
.passport-progress{
    height:14px;
    background:#eeeaf3;
    border-radius:999px;
    overflow:hidden;
    margin:18px 0 8px;
}
.passport-progress > span{
    display:block;
    height:100%;
    width:<?=$percent?>%;
    background:var(--accent,#2d1657);
}
.passport-stamps{
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:18px;
    margin-top:22px;
}
.passport-stamp{
    position:relative;
    padding:22px;
    min-height:165px;
    transition:transform .15s ease, box-shadow .15s ease;
}

.passport-stamp:not(.complete){
    opacity:.78;
}

.passport-stamp.complete{
    border:3px solid #2d1657;
    background:
        linear-gradient(
            135deg,
            #f4ecff 0%,
            #eadcff 100%
        );
    box-shadow:
        0 12px 30px rgba(45,22,87,.16),
        inset 0 0 0 2px rgba(255,255,255,.55);
    padding-right:120px;
}

.passport-stamp-status{
    display:inline-block;
    margin-bottom:10px;
    font-weight:800;
    color:#6f6a78;
}

.passport-stamp.complete .passport-stamp-status{
    color:#2d1657;
}

.passport-stamp-seal{
    display:none;
}

.passport-stamp.complete .passport-stamp-seal{
    position:absolute;
    top:18px;
    right:18px;
    width:82px;
    height:82px;
    border:4px double #2d1657;
    border-radius:50%;
    display:grid;
    place-items:center;
    text-align:center;
    font-size:.72rem;
    line-height:1.05;
    font-weight:900;
    letter-spacing:.05em;
    color:#2d1657;
    background:#fff;
    transform:rotate(8deg);
    box-shadow:0 5px 14px rgba(45,22,87,.18);
}

.passport-stamp.complete h2{
    color:#2d1657;
}

.passport-stamp.complete::after{
    content:"✓";
    position:absolute;
    right:45px;
    bottom:14px;
    font-size:2.2rem;
    font-weight:900;
    color:rgba(45,22,87,.13);
}
.passport-counts{
    display:flex;
    flex-wrap:wrap;
    gap:10px 22px;
    margin-top:14px;
}
@media(max-width:720px){
    .passport-stamps{
        grid-template-columns:1fr;
    }

    .passport-stamp.complete{
        padding-right:95px;
    }

    .passport-stamp.complete .passport-stamp-seal{
        width:64px;
        height:64px;
        font-size:.62rem;
    }
}
</style>

<div class="passport-shell">
    <section class="card passport-hero">
        <h1>Grand Opening Passport</h1>

        <p>
            Complete all 8 Grand Opening activities to earn
            <strong>one entry into the Creative Moth Grand Prize Giveaway.</strong>
        </p>

        <div
            class="passport-progress"
            aria-label="<?=$completed?> of <?=$total?> Passport stamps complete"
        >
            <span></span>
        </div>

        <p>
            <strong><?=$completed?> of <?=$total?> stamps complete</strong>
            — <?=$percent?>%
        </p>

        <div class="passport-counts">
            <span>
                Stores followed:
                <strong><?=(int)($passport['follow_count']??0)?> / 3</strong>
            </span>

            <span>
                Different stores visited:
                <strong><?=(int)($passport['visit_count']??0)?> / 5</strong>
            </span>
        </div>
    </section>

    <?php if(!empty($passport['giveaway_entry'])): ?>
        <section class="card attention-panel">
            <h2>Passport Complete!</h2>

            <p>
                You completed every Grand Opening Passport activity and
                earned <strong>one entry into the Grand Prize Giveaway.</strong>
            </p>
        </section>
    <?php endif; ?>

    <div class="passport-stamps">
        <?php foreach(($passport['stamps']??[]) as $stamp): ?>
            <article
                class="card passport-stamp <?=$stamp['complete']?'complete':''?>"
            >
                <span class="passport-stamp-status">
                    <?=$stamp['complete']?'COMPLETED':'Not Stamped Yet'?>
                </span>

                <?php if($stamp['complete']): ?>
                    <span
                        class="passport-stamp-seal"
                        aria-label="Stamped"
                    >
                        STAMPED<br>✓
                    </span>
                <?php endif; ?>

                <h2><?=H::e($stamp['title'])?></h2>

                <p>
                    <?=H::e($stamp['description'])?>
                </p>

                <?php if($stamp['complete']): ?>
                    <small class="muted">
                        Completed
                        <?=H::e(
                            date(
                                'M j, Y',
                                strtotime($stamp['completed_at'])
                            )
                        )?>
                    </small>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
</div>
