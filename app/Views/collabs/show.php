<h1><?=H::e($collab['title'])?></h1>
<p>
    <span class="badge"><?=H::e($collab['status'])?></span>
    Deadline <?=H::e($collab['upload_deadline'])?> · closes <?=H::e($collab['sale_close_date'])?>
</p>
<section class="card">
    <h2>Payout calculator</h2>
    <p>Creative Moth fee <?=H::money($estimate['fee_cents']/100)?> · pool <?=H::money($estimate['pool_cents']/100)?> · <?=$estimate['designer_count']?> designers · base each <?=H::money($estimate['per_designer_cents']/100)?></p>
</section>
<h2>Participants</h2>
<?php foreach($participants as $listedParticipant):?>
    <article class="card">
        <strong><?=H::e($listedParticipant['display_name'])?></strong> —
        <?=H::e($listedParticipant['membership_status'])?> / <?=H::e($listedParticipant['eligibility'])?> ·
        <?=intval($listedParticipant['qualifying_file_count']??0)?> files ·
        Terms/license: <?=((int)($listedParticipant['terms_count']??0)>0)?'ready':'missing'?>
        <?php if($changesOpen && (int)$collab['host_designer_id']===(int)$participant['designer_id'] && in_array($listedParticipant['membership_status'],['requested','invited'],true)):?>
            <form method="post" action="/seller/collabs/<?=$collab['id']?>/participants/<?=$listedParticipant['id']?>">
                <input type="hidden" name="_csrf" value="<?=H::csrf()?>">
                <button name="decision" value="accept">Accept</button>
                <button name="decision" value="deny">Deny</button>
            </form>
        <?php endif;?>
    </article>
<?php endforeach;?>
<?php $termsReady=(bool)array_filter($files,fn($file)=>$file['file_kind']==='terms');?>
<p><strong>Your Terms/license readiness:</strong> <?=$termsReady?'Ready':'Missing — required for final eligibility'?></p>
<?php if($changesOpen):?>
<section class="card">
    <h2>Add contribution or Terms/license</h2>
    <p><?=count(array_filter($files,fn($file)=>$file['file_kind']==='contribution'))?> of <?=$collab['minimum_file_count']?> required contribution files.</p>
    <form method="post" enctype="multipart/form-data" action="/seller/collabs/<?=$collab['id']?>/files">
        <input type="hidden" name="_csrf" value="<?=H::csrf()?>">
        <select name="file_kind"><option value="contribution">Contribution</option><option value="terms">Terms/license</option></select>
        <input required type="file" name="file"><button>Add file</button>
    </form>
    <h3>Your uploaded files</h3>
    <?php foreach($files as $file):?>
        <article class="card">
            <strong><?=H::e($file['original_name'])?></strong> (<?=H::e($file['file_kind'])?>)
            <form method="post" enctype="multipart/form-data" action="/seller/collabs/<?=$collab['id']?>/files/<?=$file['id']?>/replace">
                <input type="hidden" name="_csrf" value="<?=H::csrf()?>">
                <input type="hidden" name="file_kind" value="<?=H::e($file['file_kind'])?>">
                <label>Replace this file <input required type="file" name="file"></label><button>Replace</button>
            </form>
            <form method="post" action="/seller/collabs/<?=$collab['id']?>/files/<?=$file['id']?>/delete">
                <input type="hidden" name="_csrf" value="<?=H::csrf()?>"><button>Remove</button>
            </form>
        </article>
    <?php endforeach;?>
</section>
<?php else:?><p class="notice warning">The File Upload Deadline has passed. Membership and contribution changes are locked.</p><?php endif;?>
<?php if($changesOpen && (int)$collab['host_designer_id']===(int)$participant['designer_id']):?>
<section class="card">
    <h2>Organizer actions</h2>
    <p><a class="btn" href="/seller/collabs/<?=$collab['id']?>/edit">Edit collab settings</a></p>
    <h3>Invite an approved seller</h3>
    <p>Both Open and Closed collabs may invite sellers directly. Open collabs also appear in Find Collabs.</p>
    <form method="post" action="/seller/collabs/<?=$collab['id']?>/invite">
        <input type="hidden" name="_csrf" value="<?=H::csrf()?>"><input type="email" required name="email"><button>Send invitation</button>
    </form>
    <form method="post" action="/seller/collabs/<?=$collab['id']?>/share-link">
        <input type="hidden" name="_csrf" value="<?=H::csrf()?>"><button>Generate or rotate secure share link</button>
    </form>
    <?php if($inviteToken):?><p>New share link: <code><?=H::e(H::baseUrl().'/collabs/invite/'.$inviteToken)?></code></p><?php endif;?>
</section>
<?php endif;?>
