<h1>Followed Designers</h1>
<div class="grid">
    <?php foreach($designers as $d):?>
        <a class="card" href="/store/<?=$d['store_slug']?>">
        <?=$d['display_name']?>
        </a>
    <?php endforeach;?>
</div>
