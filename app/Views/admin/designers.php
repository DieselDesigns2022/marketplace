<h1>Designer Management</h1>
<nav class="tabs">
    <?php foreach(['approved'=>'Approved','disabled'=>'Disabled','all'=>'All'] as $key=>$label): ?>
        <a class="<?=($status??'approved')===$key?'active':''?>" href="/admin/designers?status=<?=$key?>"><?=H::e($label)?></a>
    <?php endforeach; ?>
</nav>
<p class="muted">Approved sellers show by default. Disabled/test sellers stay preserved for payment history but are hidden from the default view.</p>
<table>
    <tr>
        <th>Designer</th>
        <th>Email</th>
        <th>Store</th>
        <th>Status</th>
        <th>Followers</th>
        <th>Rank</th>
        <th>Stripe Connect</th>
        <th>Payout-ready</th>
        <th>Actions</th>
    </tr>
    <?php foreach($designers as $d):?>
        <tr>
           <td>
           <?=$d['display_name']?>
           </td>
           <td>
           <?=$d['email']?>
           </td>
           <td>
           <a href="/store/<?=$d['store_slug']?>">/store/<?=$d['store_slug']?>
           </a>
           </td>
           <td>
           <?=$d['status']?>
           </td>
           <td>
           <?=$d['follower_count']??0?>
           </td>
           <td>
           <?=$d['creator_rank']?>
           </td>
           <td><?=H::e($d['stripe_account_status'] ?? 'not_connected')?><br><span class="muted"><?=!empty($d['stripe_connect_account_id']) ? H::e($d['stripe_connect_account_id']) : 'Not connected'?></span></td>
           <td><?=(!empty($d['stripe_details_submitted']) && !empty($d['stripe_payouts_enabled'])) ? '<span class="badge ok">payout-ready</span>' : '<span class="badge pending">onboarding incomplete</span>'?></td>
           <td>
           <form method="post">
               <input type="hidden" name="_csrf" value="<?=H::csrf()?>">
               <input type="hidden" name="id" value="<?=$d['id']?>">
               <select name="creator_rank">
               <option>Bronze</option>
               <option>Silver</option>
               <option>Gold</option>
               <option>Platinum</option>
               <option>Legend</option>
               </select>
               <button name="action" value="change_rank">Change rank</button>
               <?php if(($d['status'] ?? '') === 'disabled'): ?>
                   <button name="action" value="enable" onclick="return confirm('Enable this seller?');">Enable seller</button>
               <?php else: ?>
                   <button name="action" value="disable" onclick="return confirm('Disable this seller? Their seller account will no longer be approved.');">Disable seller</button>
               <?php endif; ?>
           </form>
           </td>
        </tr>
    <?php endforeach;?>
</table>
