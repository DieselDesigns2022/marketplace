<h1>Coupons</h1>
<p><a class="btn" href="/admin/coupons/new">Create coupon</a></p>
<table><tr><th>Code</th><th>Scope</th><th>Type</th><th>Dates</th><th>Limits</th><th>Restrictions</th><th>Uses</th><th>Status</th><th></th></tr>
<?php foreach($coupons as $c):?><tr>
<td><?=H::e($c['code'])?></td><td><?=H::e($c['scope'])?> <?=H::e($c['seller_name'] ?? '')?></td><td><?=H::e($c['discount_type'])?> <?=H::e($c['discount_value'])?></td><td><?=H::e($c['starts_at'] ?? '')?> - <?=H::e($c['ends_at'] ?? '')?></td><td>Min <?=H::money($c['min_cart_amount'])?> / total <?=H::e($c['usage_limit'] ?? '∞')?> / user <?=H::e($c['per_user_limit'] ?? '∞')?></td><td><?=H::e($c['restriction_summary'] ?? 'none')?></td><td><?=H::e($c['usage_count'])?></td><td><?=!empty($c['is_active'])?'Active':'Inactive'?></td><td><a href="/admin/coupons/<?=$c['id']?>">Edit</a></td>
</tr><?php endforeach;?></table>
