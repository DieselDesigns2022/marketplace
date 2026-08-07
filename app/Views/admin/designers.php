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
           <?=H::e($d['display_name'])?>
           </td>
           <td>
           <?=H::e($d['email'])?>
           </td>
           <td>
           <a href="/store/<?=H::e($d['store_slug'])?>">/store/<?=H::e($d['store_slug'])?>
           </a>
           </td>
           <td>
           <?=H::e($d['status'])?>
           </td>
           <td>
           <?=$d['follower_count']??0?>
           </td>
           <td>
           <?=H::e($d['creator_rank'])?> effective / <?=H::e($d['calculated_rank'])?> calculated<br>
           <?=number_format((int)$d['qualifying_sales_count'])?> qualifying sales
           <?php if($d['founder_position']):?><br>Founder #<?=(int)$d['founder_position']?> — <?=$d['founder_active']?'active':'inactive'?> (<?=H::e($d['founder_override_state'])?>)<br><small>Earned <?=H::e($d['founder_earned_at'])?>; latest sale <?=H::e($d['last_qualifying_sale_at']??'none')?></small><?php endif;?>
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
               <option>Diamond</option>
               </select>
               <input name="reason" minlength="3" maxlength="500" required placeholder="Required audit reason">
               <button name="action" value="set_rank_override">Set rank override</button>
               <button name="action" value="remove_rank_override">Remove override</button>
               <button name="action" value="grant">Grant Founder</button>
               <button name="action" value="force_active">Lock Founder active</button>
               <button name="action" value="force_inactive">Force Founder inactive</button>
               <button name="action" value="restore">Restore Founder</button>
               <button name="action" value="automatic">Return Founder to automatic</button>
               <?php if(($d['status'] ?? '') === 'disabled'): ?>
                   <button formnovalidate name="action" value="enable" onclick="return confirm('Enable this seller?');">Enable seller</button>
               <?php else: ?>
                   <button formnovalidate name="action" value="disable" onclick="return confirm('Disable this seller? Their seller account will no longer be approved.');">Disable seller</button>
                   <button formnovalidate name="action" value="inactive" onclick="return confirm('Mark this store permanently inactive? Referral commission can never restart.');">Mark inactive</button>
                   <button formnovalidate name="action" value="delete" onclick="return confirm('Mark this store deleted? Financial history will remain.');">Mark deleted</button>
               <?php endif; ?>
           </form>
           </td>
        </tr>
    <?php endforeach;?>
</table>
