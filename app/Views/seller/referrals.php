<h1>Designer Referrals</h1>
<p>Your referral link: <code>/apply?designer_ref=<?=H::e(H::user()['id'])?>
</code>
</p>
<p>Rewards activate after a referred designer reaches 10 sales; reward is 1% of marketplace commission.</p>
<table>
    <tr>
        <th>Type</th>
        <th>Status</th>
        <th>Reward</th>
    </tr>
    <?php foreach($refs as $r):?>
        <tr>
           <td>
           <?=$r['referral_type']?>
           </td>
           <td>
           <?=$r['status']?>
           </td>
           <td>
           <?=$r['reward_status']?>
           </td>
        </tr>
    <?php endforeach;?>
</table>
