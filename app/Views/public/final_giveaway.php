<?php
$wheelAdmin=($wheelOperator??false)&&H::user()&&H::canAdmin('prizes.view')&&H::canAdmin('prizes.manage');
$wheelRemoveUrl='/admin/final-giveaway/remove-entry';
?>
<section class="card" id="live-giveaway" data-state-url="/live-giveaway/state" data-remove-url="<?=$wheelAdmin?H::e($wheelRemoveUrl):''?>" data-csrf="<?=$wheelAdmin?H::e(H::csrf()):''?>">
<h1>Creative Moth Live Grand Prize Drawing</h1>
<p id="drawing-status" role="status" aria-live="polite">Connecting to the drawing…</p>
<div id="drawing-stage" hidden>
<div class="final-wheel-frame"><div class="final-pointer" aria-hidden="true">▼</div><canvas id="final-wheel" width="900" height="900" aria-label="Giveaway wheel with one slice per entry"></canvas></div>
<h2 id="drawing-prize"></h2><p id="drawing-result" aria-live="polite"></p>
<button id="show-winner" hidden>Show winner</button>
<p id="wheel-entry-count"></p>
<details><summary>View entries on the wheel</summary><ol id="wheel-entry-list"></ol></details>
<h2>Winners</h2><div id="drawing-winners"></div>
</div>
<dialog id="winner-popup" aria-labelledby="winner-popup-title">
<h2 id="winner-popup-title"></h2><p id="winner-popup-prize"></p><p id="winner-popup-prompt"></p>
<p id="winner-popup-error" role="alert"></p>
<?php if($wheelAdmin): ?><button id="winner-remove" hidden>Remove entry</button><?php endif; ?> <button id="winner-close">Close</button>
</dialog>
</section>
<style>
.final-wheel-frame{position:relative;width:min(620px,100%);margin:30px auto 20px;aspect-ratio:1}.final-pointer{position:absolute;top:-25px;left:calc(50% - 17px);font-size:38px;z-index:1;color:#011a4b}#final-wheel{width:100%;height:100%;display:block;border:6px solid #011a4b;border-radius:50%;background:#ffffff;box-shadow:0 10px 30px #011a4b20}#winner-popup{border:4px solid #2060fb;border-radius:24px;background:#ffffff;color:#011a4b;max-width:min(540px,90vw);padding:28px;text-align:center}#winner-popup::backdrop{background:#011a4b99}#winner-popup h2{font-size:clamp(1.8rem,5vw,2.8rem);margin-top:0}#winner-remove{background:#011a4b}#winner-close{background:#2060fb;color:#fff}#live-giveaway [hidden]{display:none!important}#wheel-entry-list{max-height:220px;overflow:auto;text-align:left}
</style>
<script src="/assets/final-giveaway-live.js?v=6" defer></script>
