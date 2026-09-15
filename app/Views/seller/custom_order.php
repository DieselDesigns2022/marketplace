<section><h1>Custom order #<?=$order['id']?></h1><p><strong>Status:</strong> <?=H::e(str_replace('_',' ',$order['status']))?> · <strong>Payment:</strong> <?=H::e($order['payment_status'])?> · <strong>Agreed price:</strong> <?=H::money($order['agreed_price'])?></p><p><?=$order['turnaround_days']?> day turnaround · <?=$order['revisions_used']?> of <?=$order['included_revisions']?> included revisions used<?php if($order['revisions_used']>$order['included_revisions']):?> <strong>(included amount exceeded; no automatic charge)</strong><?php endif?></p><?php
$licenseRows=json_decode(
    (string)($order['license_snapshot']??'[]'),
    true
);

if(is_array($licenseRows)&&array_is_list($licenseRows)&&$licenseRows):
?>
<h2>License</h2>

<?php foreach($licenseRows as $license): ?>
    <p>
        <strong><?=H::e($license['name']??'License')?></strong>

        <?php if(!empty($license['included'])): ?>
            · included
        <?php elseif((float)($license['price']??0)>0): ?>
            · +<?=H::money($license['price'])?>
        <?php else: ?>
            · $0.00 add-on
        <?php endif; ?>

        <?php if(!empty($license['description'])): ?>
            <br><?=nl2br(H::e($license['description']))?>
        <?php endif; ?>
    </p>
<?php endforeach; ?>

<?php elseif(!empty($order['license_name'])): ?>
<h2>License</h2>

<p>
    <strong><?=H::e($order['license_name'])?></strong>

    <?php if((float)($order['license_price']??0)>0): ?>
        · +<?=H::money($order['license_price'])?>
    <?php endif; ?>

    <?php if(!empty($order['license_description'])): ?>
        <br><?=nl2br(H::e($order['license_description']))?>
    <?php endif; ?>
</p>
<?php endif; ?>

<h2>Saved buyer brief</h2><?php foreach($brief as $key=>$value):if($key==='answers'):foreach($value as $a):?><p><strong><?=H::e($a['question'])?><?=$a['required']?' *':''?>:</strong> <?=nl2br(H::e($a['answer']))?></p><?php endforeach;else:?><p><strong><?=H::e(ucwords(str_replace('_',' ',$key)))?>:</strong> <?=nl2br(H::e($value))?></p><?php endif;endforeach?><h2>Protected files</h2><?php foreach($files as $f):?><p><?=H::e(ucfirst($f['file_kind']))?>: <a href="/custom-order-files/<?=$f['id']?>"><?=H::e($f['original_name'])?></a></p><?php endforeach?><?php if(in_array($order['payment_status'],['paid','partially_refunded'],true)):?><form method="post" action="/messages/start/custom-order/<?=$order['id']?>"><input type="hidden" name="_csrf" value="<?=H::csrf()?>"><button>Open order messages</button></form><?php elseif($conversation):?><p><a href="/<?=str_contains($view,'seller/')?'seller':'buyer'?>/messages/<?=$conversation['id']?>">Open retained order conversation</a></p><?php else:?><p class="muted">Order messages become available after eligible payment.</p><?php endif?><h2>Workflow</h2><form method="post" enctype="multipart/form-data"><input type="hidden" name="_csrf" value="<?=H::csrf()?>"><?php $sellerView=str_contains($view,'seller/');if($sellerView&&$order['status']==='new'):?><button name="action" value="start">Start work</button><?php elseif($sellerView&&$order['status']==='revision_requested'):?><button name="action" value="start_revision">Start revision</button><?php elseif($sellerView&&$order['status']==='in_progress'):?><label>Proof<input type="file" name="proof[]" multiple accept=".jpg,.jpeg,.png,.webp"><small class="help-text">Proof images are watermarked automatically before the buyer receives them.</small></label><button name="action" value="proof">Send proof</button><label>Final files<input type="file" name="final[]" multiple></label><button name="action" value="final">Deliver final</button><?php elseif(!$sellerView&&$order['status']==='proof_review'):?><button name="action" value="approve">Approve proof</button><label>Revision notes<textarea name="note"></textarea></label><button name="action" value="revision">Request revision</button><?php elseif($sellerView&&$order['status']==='proof_review'):?>
<p class="notice">
    Proof sent. Waiting for the buyer to approve the proof or request a revision.
</p>
<?php elseif($sellerView&&$order['status']==='completed'):?>
<p class="notice success">
    Final design delivered. This custom order is complete.
</p>
<?php elseif(in_array($order['status'],['cancelled','refunded'],true)):?>
<p class="notice warning">
    This custom order is <?=H::e(str_replace('_',' ',$order['status']))?> and no further workflow action is available.
</p>
<?php else:?><p>No workflow action is currently available.</p><?php endif?></form></section>
