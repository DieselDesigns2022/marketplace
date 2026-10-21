<?php
use App\Core\Helpers as H;

$filters = $filters ?? ['q'=>'','category'=>'','status'=>'all'];
$categories = $categories ?? [];
$canManage = H::canAdmin('ip_risk.manage');
?>

<header class="dashboard-heading">
    <div>
        <h1>IP Risk Terms</h1>
        <p class="muted">
            Search, add, and manage advisory IP-risk terms and aliases.
            Matches require context review and are not legal findings.
        </p>
    </div>
</header>

<?php if($canManage):?>
<section class="card" style="margin-bottom:1rem;">
    <h2 style="margin-top:0;">Quick Add Term</h2>
    <p class="muted">
        Use this when you just need to add one word or phrase quickly.
        You can edit aliases, notes, and other details later.
    </p>

    <form method="post"
          action="/admin/ip-risk-terms/quick-add"
          style="display:flex;gap:.75rem;align-items:flex-end;flex-wrap:wrap;">
        <input type="hidden" name="_csrf" value="<?=H::csrf()?>">

        <label style="flex:1;min-width:240px;">
            Term
            <input name="term"
                   required
                   placeholder="Example: Halloweentown"
                   autocomplete="off">
        </label>

        <label style="min-width:220px;">
            Category
            <select name="category">
                <?php foreach($categories as $category):?>
                    <option value="<?=H::e($category)?>"
                        <?=$category==='other_protected_term'?'selected':''?>>
                        <?=H::e(str_replace('_',' ',ucwords($category,'_')))?>
                    </option>
                <?php endforeach;?>
            </select>
        </label>

        <button class="btn" type="submit">Add Term</button>
    </form>
</section>
<?php endif;?>

<section class="card" style="margin-bottom:1rem;">
    <h2 style="margin-top:0;">Find Terms</h2>

    <form method="get"
          action="/admin/ip-risk-terms"
          style="display:flex;gap:.75rem;align-items:flex-end;flex-wrap:wrap;">

        <label style="flex:1;min-width:240px;">
            Search term, alias, or context rule
            <input name="q"
                   value="<?=H::e($filters['q'] ?? '')?>"
                   placeholder="Search Grinch, green guy, energy drink..."
                   autocomplete="off">
        </label>

        <label style="min-width:190px;">
            Category
            <select name="category">
                <option value="">All categories</option>
                <?php foreach($categories as $category):?>
                    <option value="<?=H::e($category)?>"
                        <?=($filters['category']??'')===$category?'selected':''?>>
                        <?=H::e(str_replace('_',' ',ucwords($category,'_')))?>
                    </option>
                <?php endforeach;?>
            </select>
        </label>

        <label style="min-width:150px;">
            Status
            <select name="status">
                <option value="all" <?=($filters['status']??'all')==='all'?'selected':''?>>All</option>
                <option value="enabled" <?=($filters['status']??'')==='enabled'?'selected':''?>>Enabled</option>
                <option value="disabled" <?=($filters['status']??'')==='disabled'?'selected':''?>>Disabled</option>
            </select>
        </label>

        <button class="btn" type="submit">Search</button>
        <a class="btn alt" href="/admin/ip-risk-terms">Clear</a>
    </form>
</section>

<section class="card" style="margin-bottom:1rem;">
    <h2 style="margin-top:0;">Browse by Category</h2>
    <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
        <a class="btn alt" href="/admin/ip-risk-terms?status=<?=H::e($filters['status'] ?? 'all')?>">All</a>

        <?php foreach($categoryCounts ?? [] as $row):?>
            <a class="btn alt"
               href="/admin/ip-risk-terms?category=<?=urlencode($row['category'])?>&status=<?=urlencode($filters['status'] ?? 'all')?>">
                <?=H::e(str_replace('_',' ',ucwords($row['category'],'_')))?>
                (<?=(int)$row['total']?>)
            </a>
        <?php endforeach;?>
    </div>
</section>

<div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;margin-bottom:.75rem;flex-wrap:wrap;">
    <strong>
        <?=$total?> result<?=$total===1?'':'s'?>
        <?php if($pages > 1):?>
            — page <?=$page?> of <?=$pages?>
        <?php endif;?>
    </strong>

    <?php if($canManage):?>
        <a class="btn alt" href="/admin/ip-risk-terms/create">Advanced Add</a>
    <?php endif;?>
</div>

<div class="responsive-table">
<table>
    <thead>
        <tr>
            <th>Term</th>
            <th>Aliases</th>
            <th>Context Rules</th>
            <th>Category</th>
            <th>Status</th>
            <th>Actions</th>
        </tr>
    </thead>

    <tbody>
    <?php foreach($terms as $term):?>
        <tr>
            <td>
                <strong><?=H::e($term['term'])?></strong>
            </td>

            <td style="max-width:420px;">
                <?php if(!empty($term['aliases_text'])):?>
                    <?=H::e($term['aliases_text'])?>
                <?php else:?>
                    <span class="muted">No aliases</span>
                <?php endif;?>

                <?php if($canManage):?>
                    <details style="margin-top:.4rem;">
                        <summary style="cursor:pointer;">+ Add alias</summary>
                        <form method="post"
                              action="/admin/ip-risk-terms/<?=(int)$term['id']?>/aliases"
                              style="display:flex;gap:.4rem;margin-top:.4rem;align-items:center;">
                            <input type="hidden" name="_csrf" value="<?=H::csrf()?>">
                            <input name="alias"
                                   required
                                   placeholder="Common name or workaround"
                                   autocomplete="off"
                                   style="min-width:220px;">
                            <button type="submit">Add</button>
                        </form>
                    </details>
                <?php endif;?>
            </td>

            <td style="min-width:310px;max-width:420px;">
                <?php if(!empty($term['context_rules'])):?>
                    <?php foreach($term['context_rules'] as $rule):?>
                        <div style="margin-bottom:.45rem;">
                            <strong><?=H::e($rule['trigger_text'])?></strong>
                            +
                            <strong><?=H::e($rule['context_text'])?></strong>

                            <?php if(empty($rule['is_enabled'])):?>
                                <span class="muted">(disabled)</span>
                            <?php endif;?>

                            <?php if($canManage):?>
                                <form method="post"
                                      action="/admin/ip-risk-terms/<?=(int)$term['id']?>/context-rules"
                                      class="inline"
                                      style="margin-left:.35rem;">
                                    <input type="hidden" name="_csrf" value="<?=H::csrf()?>">
                                    <input type="hidden" name="rule_id" value="<?=(int)$rule['id']?>">
                                    <input type="hidden"
                                           name="action"
                                           value="<?=!empty($rule['is_enabled'])?'disable':'enable'?>">
                                    <button type="submit">
                                        <?=!empty($rule['is_enabled'])?'Disable':'Enable'?>
                                    </button>
                                </form>
                            <?php endif;?>
                        </div>
                    <?php endforeach;?>
                <?php else:?>
                    <span class="muted">No context rules</span>
                <?php endif;?>

                <?php if($canManage):?>
                    <details style="margin-top:.5rem;">
                        <summary style="cursor:pointer;">+ Add context rule</summary>

                        <form method="post"
                              action="/admin/ip-risk-terms/<?=(int)$term['id']?>/context-rules"
                              style="display:grid;gap:.4rem;margin-top:.5rem;">
                            <input type="hidden" name="_csrf" value="<?=H::csrf()?>">
                            <input type="hidden" name="action" value="add">

                            <input name="trigger_text"
                                   required
                                   placeholder="Trigger: monster"
                                   autocomplete="off">

                            <input name="context_text"
                                   required
                                   placeholder="Context: energy drink"
                                   autocomplete="off">

                            <button type="submit">Add Rule</button>
                        </form>
                    </details>
                <?php endif;?>
            </td>

            <td><?=H::e(str_replace('_',' ',$term['category']))?></td>

            <td>
                <?=!empty($term['is_enabled']) ? 'Enabled' : 'Disabled'?>
            </td>

            <td>
                <?php if($canManage):?>
                    <a href="/admin/ip-risk-terms/<?=(int)$term['id']?>/edit">Edit</a>

                    <form method="post"
                          action="/admin/ip-risk-terms/<?=(int)$term['id']?>/<?=!empty($term['is_enabled'])?'disable':'enable'?>"
                          class="inline">
                        <input type="hidden" name="_csrf" value="<?=H::csrf()?>">
                        <button type="submit">
                            <?=!empty($term['is_enabled'])?'Disable':'Enable'?>
                        </button>
                    </form>
                <?php endif;?>
            </td>
        </tr>
    <?php endforeach;?>

    <?php if(!$terms):?>
        <tr>
            <td colspan="6" class="muted">
                No IP-risk terms matched your search.
            </td>
        </tr>
    <?php endif;?>
    </tbody>
</table>
</div>

<?php if(($pages ?? 1) > 1):?>
<?php
$queryBase = [
    'q' => $filters['q'] ?? '',
    'category' => $filters['category'] ?? '',
    'status' => $filters['status'] ?? 'all',
];
?>
<nav style="display:flex;align-items:center;justify-content:center;gap:.5rem;flex-wrap:wrap;margin:1.25rem 0;">
    <?php if($page > 1):?>
        <?php $prev = http_build_query(array_merge($queryBase,['page'=>$page-1])); ?>
        <a class="btn alt" href="/admin/ip-risk-terms?<?=H::e($prev)?>">← Previous</a>
    <?php endif;?>

    <?php
    $start = max(1, $page - 3);
    $end = min($pages, $page + 3);
    for($p=$start;$p<=$end;$p++):
        $qs = http_build_query(array_merge($queryBase,['page'=>$p]));
    ?>
        <a class="btn <?=$p===$page?'':'alt'?>"
           href="/admin/ip-risk-terms?<?=H::e($qs)?>">
            <?=$p?>
        </a>
    <?php endfor;?>

    <?php if($page < $pages):?>
        <?php $next = http_build_query(array_merge($queryBase,['page'=>$page+1])); ?>
        <a class="btn alt" href="/admin/ip-risk-terms?<?=H::e($next)?>">Next →</a>
    <?php endif;?>
</nav>
<?php endif;?>
