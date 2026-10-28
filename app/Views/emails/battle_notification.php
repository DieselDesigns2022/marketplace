<h1><?=htmlspecialchars($data['battle_title']??'Battle of the Designers',ENT_QUOTES,'UTF-8')?></h1>

<p>
    Hi <?=htmlspecialchars($data['name']??'Designer',ENT_QUOTES,'UTF-8')?>,
</p>

<p>
    <?=htmlspecialchars($data['message']??'',ENT_QUOTES,'UTF-8')?>
</p>

<p>
    <strong>
        Round <?=number_format((int)($data['round_number']??0))?>
        ·
        <?=htmlspecialchars($data['phase_label']??'24-hour voting window',ENT_QUOTES,'UTF-8')?>
    </strong>
</p>

<p>
    <a href="<?=htmlspecialchars($data['battle_url']??'#',ENT_QUOTES,'UTF-8')?>">
        View Your Battle
    </a>
</p>

<p>
    Good luck!<br>
    Creative Moth
</p>

<?php if(!empty($data['voting_window'])): ?><p><strong>Eastern Time, Detroit:</strong><br><?=htmlspecialchars($data['voting_window'],ENT_QUOTES,'UTF-8')?></p><?php endif; ?>
