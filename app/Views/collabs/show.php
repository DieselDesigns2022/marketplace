<?php
$collabDisplayUtc = new \DateTimeZone('UTC');

$collabHostTimezoneName =
    (string)($collab['host_timezone'] ?? 'UTC');

$collabHostTimezone =
    new \DateTimeZone($collabHostTimezoneName);

$collabDeadlineHost =
    (new \DateTimeImmutable($collab['upload_deadline'], $collabDisplayUtc))
        ->setTimezone($collabHostTimezone);

$collabSaleStartHost =
    (new \DateTimeImmutable($collab['sale_starts_at'], $collabDisplayUtc))
        ->setTimezone($collabHostTimezone);
?>

<?php
$collabCategories = \App\Core\Database::rows(
    'select *
     from categories
     where is_active=1
       and slug not in (?, ?)
       and not (lower(name) in (?, ?) and slug<>?)
     order by sort_order,name',
    ['sublimation','png','png','png files','png-files']
);

$finalContributionCount = 0;
$finalMockupCount = 0;
$finalTermsCount = 0;
$finalCategoryCounts = [];

if (!empty($collab['snapshot_at'])) {
    $finalContributionCount = (int)(\App\Core\Database::row(
        'select count(*) total
         from collab_files
         where collab_id=? and included_in_snapshot=1 and file_kind="contribution"',
        [$collab['id']]
    )['total'] ?? 0);

    $finalMockupCount = (int)(\App\Core\Database::row(
        'select count(*) total
         from collab_files
         where collab_id=? and included_in_snapshot=1 and file_kind="mockup"',
        [$collab['id']]
    )['total'] ?? 0);

    $finalTermsCount = (int)(\App\Core\Database::row(
        'select count(*) total
         from collab_files
         where collab_id=? and included_in_snapshot=1 and file_kind="terms"',
        [$collab['id']]
    )['total'] ?? 0);

    $finalCategoryCounts = \App\Core\Database::rows(
        'select coalesce(c.name,"Uncategorized") category_name,count(*) total
         from collab_files f
         left join categories c on c.id=f.category_id
         where f.collab_id=?
           and f.included_in_snapshot=1
           and f.file_kind="contribution"
         group by coalesce(c.name,"Uncategorized")
         order by total desc,category_name',
        [$collab['id']]
    );
}
?>
<style>
.collab-meta-card{padding:16px 20px;margin:0 0 18px 0}
.collab-meta-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px}
.collab-meta-item{background:#fff;border:1px solid #ece6f6;border-radius:14px;padding:12px 14px}
.collab-meta-label{display:block;font-weight:700;margin-bottom:4px}
.participant-title{display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:8px}
.participant-pill{display:inline-block;padding:4px 10px;border-radius:999px;background:#f3eefb;font-weight:700}
.readiness-ready{color:#15803d;font-weight:900}
.readiness-pending{color:#dc2626;font-weight:900}
.readiness-excluded{color:#b45309;font-weight:900}
.participant-meta{display:flex;flex-wrap:wrap;gap:16px;margin:0}
.participant-meta span{display:inline-block}
.meta-strong{font-weight:700}
.collab-preview-grid{display:flex;flex-wrap:wrap;gap:10px;margin-top:12px}
.collab-preview-item{width:92px}
.collab-preview-item img{width:92px;height:92px;object-fit:cover;border-radius:10px;border:1px solid #e4dcef;display:block}
.collab-file-placeholder{width:92px;height:92px;border-radius:10px;border:1px solid #e4dcef;background:#f8f6fc;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;gap:3px}
.collab-file-icon{font-size:32px;line-height:1}
.collab-file-placeholder strong{font-size:11px;max-width:82px;overflow-wrap:anywhere}
.collab-preview-name{font-size:12px;line-height:1.2;margin-top:4px;overflow-wrap:anywhere}

.host-notes-card{
    margin-bottom:18px;
    background:#fff7e8;
    border:1px solid #f3d28f;
    border-left:6px solid #d4a017;
    box-shadow:0 4px 14px rgba(0,0,0,0.04);
}

.host-notes-title{
    margin:0 0 10px 0;
    color:#6b4f00;
    display:flex;
    align-items:center;
    gap:8px;
}

.host-notes-body{
    white-space:pre-wrap;
    background:rgba(255,255,255,0.55);
    border:1px solid #f1dfb3;
    border-radius:12px;
    padding:14px 16px;
    line-height:1.5;
}

.host-notes-badge{
    display:inline-block;
    font-size:12px;
    font-weight:800;
    letter-spacing:.04em;
    text-transform:uppercase;
    color:#7a5a00;
    background:#fde7a9;
    border:1px solid #efcf74;
    border-radius:999px;
    padding:4px 10px;
    margin-bottom:10px;
}

.your-upload-grid{
    display:grid;
    grid-template-columns:repeat(auto-fill,minmax(170px,1fr));
    gap:14px;
    margin-top:12px;
}

.your-upload-item{
    border:1px solid #e8e1f1;
    border-radius:14px;
    background:#fff;
    padding:12px;
    min-width:0;
}

.your-upload-preview,
.your-upload-placeholder{
    width:110px;
    height:110px;
    margin:0 auto 8px;
    display:flex;
    align-items:center;
    justify-content:center;
}

.your-upload-preview img{
    width:110px;
    height:110px;
    object-fit:cover;
    display:block;
    border-radius:10px;
    border:1px solid #e4dcef;
}

.your-upload-placeholder{
    flex-direction:column;
    gap:4px;
    border-radius:10px;
    border:1px solid #e4dcef;
    background:#f8f6fc;
}

.your-upload-placeholder span{
    font-size:34px;
    line-height:1;
}

.your-upload-placeholder strong{
    font-size:12px;
}

.your-upload-name{
    font-size:12px;
    font-weight:700;
    line-height:1.2;
    overflow-wrap:anywhere;
    margin-bottom:2px;
}

.your-upload-kind{
    font-size:11px;
    color:#6b637a;
    margin-bottom:8px;
}

.your-upload-category select,
.your-upload-category button,
.your-upload-replace button,
.your-upload-remove button{
    width:100%;
}

.your-upload-category,
.your-upload-replace,
.your-upload-remove{
    margin:6px 0 0;
}

.your-upload-file-button{
    display:block;
    text-align:center;
    padding:7px;
    border:1px solid #ddd4e8;
    border-radius:8px;
    cursor:pointer;
    font-size:11px;
    margin-bottom:6px;
}

.your-upload-file-button input{
    display:none;
}

@media(max-width:600px){
    .your-upload-grid{
        grid-template-columns:repeat(2,minmax(0,1fr));
        gap:10px;
    }
}
</style>

<h1><?=H::e($collab['title'])?></h1>
<section class="card collab-meta-card">
    <div class="collab-meta-grid">
        <div class="collab-meta-item">
            <span class="collab-meta-label">Status</span>
            <span class="badge"><?=H::e($collab['status'])?></span>
        </div>

        <div class="collab-meta-item">
            <span class="collab-meta-label">File Upload Deadline</span>
            <strong>Host time:</strong> <?=$collabDeadlineHost->format('F j, Y \a\t g:i A T')?>
            <div
                class="js-local-collab-time"
                data-utc="<?=$collabDeadlineHost->setTimezone($collabDisplayUtc)->format('Y-m-d\TH:i:s\Z')?>"
                data-host-timezone="<?=H::e($collabHostTimezoneName)?>"
                style="font-size:13px;margin-top:3px"
            ></div>
        </div>

        <div class="collab-meta-item">
            <span class="collab-meta-label">Sales Close Date</span>
            <?=H::e($collab['sale_close_date'])?>
        </div>

        <div class="collab-meta-item">
            <span class="collab-meta-label">Quantity</span>
            <?php if(!empty($collab['quantity_limit'])):?>
                <?=$soldCount?> / <?=intval($collab['quantity_limit'])?> sold
                <?php if($soldCount >= (int)$collab['quantity_limit']):?> — <strong>Sold Out</strong><?php endif;?>
            <?php else:?>
                Unlimited
            <?php endif;?>
        </div>
    </div>
</section>

<?php if(trim((string)($collab['host_notes'] ?? '')) !== ''):?>
<section class="card host-notes-card">
    <div class="host-notes-badge">Important</div>
    <h2 class="host-notes-title">📌 Host Notes & Instructions</h2>
    <div class="host-notes-body"><?=H::e($collab['host_notes'])?></div>
</section>
<?php endif;?>

<?php if((int)$collab['host_designer_id']===(int)$participant['designer_id']):?>
<section class="card" style="padding:18px 20px">

    <div style="
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:12px;
        flex-wrap:wrap;
        margin-bottom:16px;
    ">
        <h2 style="margin:0">Organizer actions</h2>

        <?php if($changesOpen):?>
            <div style="
                display:flex;
                gap:8px;
                flex-wrap:wrap;
            ">
                <a
                    class="btn small"
                    href="/seller/collabs/<?=$collab['id']?>/edit"
                >
                    Edit settings
                </a>

                <form
                    method="post"
                    action="/seller/collabs/<?=$collab['id']?>/share-link"
                    style="margin:0"
                >
                    <input
                        type="hidden"
                        name="_csrf"
                        value="<?=H::csrf()?>"
                    >

                    <button
                        type="submit"
                        class="btn small"
                    >
                        Generate / rotate share link
                    </button>
                </form>
            </div>
        <?php endif;?>
    </div>


    <?php if($inviteToken):?>
        <div style="
            padding:9px 12px;
            margin-bottom:14px;
            border-radius:8px;
            background:rgba(0,0,0,.04);
            font-size:13px;
            overflow-wrap:anywhere;
        ">
            <strong>New share link:</strong>
            <code><?=H::e(H::baseUrl().'/collabs/invite/'.$inviteToken)?></code>
        </div>
    <?php endif;?>


    <div style="
        display:grid;
        grid-template-columns:repeat(auto-fit,minmax(300px,1fr));
        gap:18px;
        align-items:start;
    ">

        <?php if($changesOpen):?>
            <div>
                <div style="
                    font-weight:700;
                    margin-bottom:6px;
                ">
                    Invite approved seller
                </div>

                <div style="
                    font-size:13px;
                    opacity:.75;
                    margin-bottom:8px;
                ">
                    Invite an approved seller directly to this collab.
                </div>

                <form
                    method="post"
                    action="/seller/collabs/<?=$collab['id']?>/invite"
                    style="
                        display:flex;
                        gap:8px;
                        align-items:center;
                        flex-wrap:wrap;
                        margin:0;
                    "
                >
                    <input
                        type="hidden"
                        name="_csrf"
                        value="<?=H::csrf()?>"
                    >

                    <input
                        type="email"
                        required
                        name="email"
                        placeholder="Seller email"
                        style="
                            flex:1 1 220px;
                            min-width:0;
                            margin:0;
                        "
                    >

                    <button
                        type="submit"
                        class="btn small"
                        style="white-space:nowrap"
                    >
                        Send invitation
                    </button>
                </form>
            </div>
        <?php endif;?>


        <div>
            <div style="
                font-weight:700;
                margin-bottom:6px;
            ">
                File Upload Deadline
            </div>

            <?php if(
                $changesOpen
                && $soldCount === 0
            ):?>
                <div style="
                    margin-bottom:18px;
                    padding:14px;
                    border:1px solid #d8cfee;
                    border-radius:10px;
                    background:#faf8ff;
                ">
                    <div style="font-weight:700;margin-bottom:5px">
                        Everyone finished early?
                    </div>

                    <div style="
                        font-size:13px;
                        opacity:.8;
                        margin-bottom:10px;
                    ">
                        Close normal file submissions now and immediately run
                        the final IP check, eligibility snapshot, and bundle ZIP.
                    </div>

                    <form
                        method="post"
                        action="/seller/collabs/<?=$collab['id']?>/close-submissions"
                        style="margin:0"
                        onsubmit="return confirm('Close submissions and finalize this collab now? Normal uploads will immediately lock.');"
                    >
                        <input
                            type="hidden"
                            name="_csrf"
                            value="<?=H::csrf()?>"
                        >

                        <button
                            type="submit"
                            class="btn"
                        >
                            Close Submissions & Finalize Now
                        </button>
                    </form>
                </div>
            <?php endif;?>

            <?php if($soldCount === 0):?>

                <div style="
                    font-size:13px;
                    opacity:.75;
                    margin-bottom:8px;
                ">
                    Can be extended while this collab has 0 sales.
                    Reopening uploads rebuilds the bundle after IP review.
                </div>

                <form
                    method="post"
                    action="/seller/collabs/<?=$collab['id']?>/extend-deadline"
                    onsubmit="return confirm('Extend/reopen this collab deadline? Any existing pre-sale bundle snapshot will be rebuilt.');"
                    style="
                        display:flex;
                        gap:8px;
                        align-items:center;
                        flex-wrap:wrap;
                        margin:0;
                    "
                >
                    <input
                        type="hidden"
                        name="_csrf"
                        value="<?=H::csrf()?>"
                    >

                    <input
                        type="datetime-local"
                        name="upload_deadline"
                        required
                        style="
                            flex:1 1 220px;
                            min-width:0;
                            margin:0;
                        "
                    >

                    <button
                        type="submit"
                        class="btn small"
                        style="white-space:nowrap"
                    >
                        Extend Deadline
                    </button>
                </form>

            <?php else:?>

                <div style="
                    font-size:13px;
                    opacity:.75;
                ">
                    <strong>Deadline extension unavailable.</strong>
                    This collab already has sales. Sellers can use file
                    update requests instead.
                </div>

            <?php endif;?>
        </div>

    </div>

</section>
<?php endif;?>


<?php
$pendingFileUpdates = [];

if (
    (int)$collab['host_designer_id'] ===
    (int)$participant['designer_id']
) {
    $pendingFileUpdates = \App\Core\Database::rows(
        'select
            r.*,
            d.display_name,
            f.original_name current_name
         from collab_file_update_requests r
         join designers d
           on d.id=r.designer_id
         join collab_files f
           on f.id=r.collab_file_id
         where r.collab_id=?
           and r.status in ("pending","ip_review")
         order by r.created_at',
        [(int)$collab['id']]
    );
}
?>

<?php if($pendingFileUpdates):?>
<section class="card">
    <h2>Pending File Update Requests</h2>

    <?php foreach($pendingFileUpdates as $update):?>
        <div style="
            padding:12px 0;
            border-bottom:1px solid rgba(0,0,0,.08);
        ">
            <strong>
                <?=H::e($update['display_name'])?>
            </strong>

            <div style="font-size:13px;margin-top:4px">
                Current:
                <strong>
                    <?=H::e($update['current_name'])?>
                </strong>
            </div>

            <div style="font-size:13px">
                Proposed:
                <strong>
                    <?=H::e($update['original_name'])?>
                </strong>
            </div>

            <?php if(trim((string)$update['reason']) !== ''):?>
                <div style="font-size:13px;margin-top:5px">
                    Reason:
                    <?=H::e($update['reason'])?>
                </div>
            <?php endif;?>

            <?php if($update['status']==='ip_review'):?>

                <div
                    class="notice warning"
                    style="margin-top:9px"
                >
                    Host approved — waiting for Admin IP review.
                    The existing file remains unchanged until review passes.
                </div>

            <?php else:?>

                <div style="
                    display:flex;
                    gap:8px;
                    margin-top:9px;
                    flex-wrap:wrap;
                ">
                    <form
                        method="post"
                        action="/seller/collabs/<?=$collab['id']?>/file-update-requests/<?=$update['id']?>/approve"
                        style="margin:0"
                    >
                        <input
                            type="hidden"
                            name="_csrf"
                            value="<?=H::csrf()?>"
                        >

                        <button type="submit">
                            Approve Update
                        </button>
                    </form>

                    <form
                        method="post"
                        action="/seller/collabs/<?=$collab['id']?>/file-update-requests/<?=$update['id']?>/deny"
                        style="margin:0"
                        onsubmit="return confirm('Deny this file update request?');"
                    >
                        <input
                            type="hidden"
                            name="_csrf"
                            value="<?=H::csrf()?>"
                        >

                        <button
                            type="submit"
                            style="background:#b91c1c;color:#fff"
                        >
                            Deny Update
                        </button>
                    </form>
                </div>

            <?php endif;?>
        </div>
    <?php endforeach;?>
</section>
<?php endif;?>

<h2>Participants</h2>
<?php foreach($participants as $listedParticipant):?>
    <article class="card">
        <div class="participant-title">
            <strong><a href="/store/<?=H::e($listedParticipant['store_slug'])?>"><?=H::e($listedParticipant['display_name'])?></a></strong>

            <?php if(
                (int)$collab['host_designer_id'] === (int)$participant['designer_id']
                && (int)$listedParticipant['designer_id'] !== (int)$participant['designer_id']
            ):?>
                <form method="post" action="/messages/start/store/<?=intval($listedParticipant['designer_id'])?>" style="display:inline;margin:0">
                    <input type="hidden" name="_csrf" value="<?=H::csrf()?>">
                    <button type="submit" class="btn small">Message participant</button>
                </form>
            <?php endif;?>

            <?php if(
                $changesOpen
                && (int)$collab['host_designer_id'] === (int)$participant['designer_id']
                && (int)$listedParticipant['designer_id'] !== (int)$participant['designer_id']
                && in_array($listedParticipant['membership_status'], ['invited','requested','accepted'], true)
            ):?>
                <form
                    method="post"
                    action="/seller/collabs/<?=$collab['id']?>/participants/<?=$listedParticipant['id']?>/remove"
                    style="display:inline;margin:0"
                    onsubmit="return confirm('Remove this participant from the collab? Their uploaded collab files will also be removed.');"
                >
                    <input type="hidden" name="_csrf" value="<?=H::csrf()?>">
                    <button type="submit" class="btn small" style="background:#b91c1c;color:#fff">Remove participant</button>
                </form>
            <?php endif;?>

            <?php
                $liveContributionCount = (int)($listedParticipant['live_contribution_count'] ?? 0);
                $liveTermsCount = (int)($listedParticipant['terms_count'] ?? 0);

                $liveMockupCount = (int)(\App\Core\Database::row(
                    'select count(*) total
                     from collab_files
                     where participant_id=? and file_kind="mockup"',
                    [$listedParticipant['id']]
                )['total'] ?? 0);

                $liveUncategorizedCount = (int)(\App\Core\Database::row(
                    'select count(*) total
                     from collab_files
                     where participant_id=?
                       and file_kind="contribution"
                       and category_id is null',
                    [$listedParticipant['id']]
                )['total'] ?? 0);

                if (!empty($collab['snapshot_at'])) {
                    $readinessLabel = ($listedParticipant['eligibility'] ?? '') === 'eligible'
                        ? 'Ready'
                        : (($listedParticipant['eligibility'] ?? '') === 'excluded' ? 'Excluded' : 'Pending');
                } else {
                    $readinessLabel = (
                        in_array($listedParticipant['membership_status'], ['host','accepted'], true)
                        && $liveContributionCount >= (int)$collab['minimum_file_count']
                        && $liveUncategorizedCount === 0
                        && $liveTermsCount >= 1
                        && (
                            empty($collab['require_mockup'])
                            || $liveMockupCount >= 1
                        )
                    ) ? 'Ready' : 'Pending';
                }
            ?>
            <span class="participant-pill">
                <?=H::e($listedParticipant['membership_status'])?> /
                <strong class="<?=
                    $readinessLabel === 'Ready'
                        ? 'readiness-ready'
                        : ($readinessLabel === 'Excluded' ? 'readiness-excluded' : 'readiness-pending')
                ?>"><?=H::e($readinessLabel)?></strong>
            </span>
        </div>

        <p class="participant-meta">
            <span><span class="meta-strong">Contribution files:</span> <?=intval($collab['snapshot_at'] ? ($listedParticipant['qualifying_file_count'] ?? 0) : ($listedParticipant['live_contribution_count'] ?? 0))?></span>
            <span><span class="meta-strong">Terms & Conditions & About Me:</span> <?=((int)($listedParticipant['terms_count']??0)>0)?'Ready':'Missing'?></span>
            <span>
                <span class="meta-strong">Categories:</span>
                <?=$liveUncategorizedCount===0?'Ready':'Missing — '.$liveUncategorizedCount.' uncategorized'?>
            </span>

            <span>
                <span class="meta-strong">Mockups:</span>
                <?php if(!empty($collab['require_mockup'])):?>
                    <?=$liveMockupCount>0?'Ready':'Missing — required'?>
                <?php else:?>
                    <?=$liveMockupCount?> uploaded — optional
                <?php endif;?>
            </span>
        </p>

        <?php if((int)$collab['host_designer_id']===(int)$participant['designer_id'] && !empty($hostPreviewFiles[(int)$listedParticipant['designer_id']])):?>
            <div class="collab-preview-grid">
                <?php foreach($hostPreviewFiles[(int)$listedParticipant['designer_id']] as $previewFile):?>
                    <div class="collab-preview-item">
                        <?php
                            $isPreviewImage = str_starts_with(
                                strtolower((string)$previewFile['mime_type']),
                                'image/'
                            );

                            $fileExtension = strtoupper(
                                pathinfo(
                                    (string)$previewFile['original_name'],
                                    PATHINFO_EXTENSION
                                )
                            );

                            if ($fileExtension === '') {
                                $fileExtension = 'FILE';
                            }
                        ?>

                        <?php if($isPreviewImage):?>
                            <a
                                href="/seller/collabs/<?=$collab['id']?>/files/<?=$previewFile['id']?>/preview"
                                target="_blank"
                                rel="noopener"
                            >
                                <?php
                                    $thumbExt = match(
                                        strtolower((string)$previewFile['mime_type'])
                                    ) {
                                        'image/jpeg' => 'jpg',
                                        'image/png' => 'png',
                                        'image/webp' => 'webp',
                                        default => null,
                                    };

                                    $thumbUrl = $thumbExt
                                        ? '/uploads/collab-thumbnails/c'
                                            .(int)$collab['id']
                                            .'-f'
                                            .(int)$previewFile['id']
                                            .'.'
                                            .$thumbExt
                                        : '/seller/collabs/'
                                            .(int)$collab['id']
                                            .'/files/'
                                            .(int)$previewFile['id']
                                            .'/preview';
                                ?>
                                <img
                                    src="<?=H::e($thumbUrl)?>"
                                    alt="<?=H::e($previewFile['original_name'])?>"
                                    loading="lazy"
                                    decoding="async"
                                >
                            </a>
                        <?php else:?>
                            <div class="collab-file-placeholder">
                                <div class="collab-file-icon">📄</div>
                                <strong><?=H::e($fileExtension)?></strong>
                            </div>
                        <?php endif;?>

                        <div class="collab-preview-name"><?=H::e($previewFile['original_name'])?></div>

                        <?php
                            $previewCategoryName = 'Uncategorized';

                            foreach($collabCategories as $category){
                                if((int)$category['id'] === (int)($previewFile['category_id'] ?? 0)){
                                    $previewCategoryName = $category['name'];
                                    break;
                                }
                            }
                        ?>

                        <div style="font-size:11px;margin-top:5px">
                            <strong>Category:</strong>
                            <?=H::e($previewCategoryName)?>
                        </div>
                    </div>
                <?php endforeach;?>
            </div>
        <?php endif;?>

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
<p><strong>Your Terms & Conditions & About Me readiness:</strong> <span class="meta-strong"><?=$termsReady?'Ready':'Missing — at least one file is required for final eligibility'?></span></p>

<?php if(
    $changesOpen
    && (int)$collab['host_designer_id'] !== (int)$participant['designer_id']
    && ($participant['membership_status'] ?? '') === 'accepted'
):?>
    <form
        method="post"
        action="/seller/collabs/<?=$collab['id']?>/leave"
        onsubmit="return confirm('Leave this collab? Your uploaded collab files will also be removed.');"
    >
        <input type="hidden" name="_csrf" value="<?=H::csrf()?>">
        <button type="submit" class="btn small" style="background:#b91c1c;color:#fff">Leave collab</button>
    </form>
<?php endif;?>
<?php if($changesOpen):?>
<section class="card">
    <h2>Add files</h2>

    <?php if(!empty($collab['require_mockup'])):?>
        <p class="notice warning"><strong>Mockup required:</strong> Each participant must upload at least 1 mockup to be READY.</p>
    <?php else:?>
        <p><strong>Mockups are optional for this collab.</strong></p>
    <?php endif;?>
    <p><?=count(array_filter($files,fn($file)=>$file['file_kind']==='contribution'))?> of <?=$collab['minimum_file_count']?> required contribution files.</p>
    <p><strong>Only Contribution files count toward the required minimum.</strong> Terms/About files and Mockups do not count.</p>
    <p>You may upload multiple files at once. Contribution files uploaded together must use the same category.</p>
    <form method="post" enctype="multipart/form-data" action="/seller/collabs/<?=$collab['id']?>/files">
        <input type="hidden" name="_csrf" value="<?=H::csrf()?>">
        <label>
            File type
            <select name="file_kind" id="collab-file-kind">
                <option value="contribution">Contribution</option>
                <option value="terms">Terms & Conditions & About Me</option>
                <option value="mockup">Mockup</option>
            </select>
        </label>

        <label id="collab-category-field">
            Category
            <select name="category_id">
                <option value="">Choose a category</option>
                <?php foreach($collabCategories as $category):?>
                    <option value="<?=$category['id']?>"><?=H::e($category['name'])?></option>
                <?php endforeach;?>
            </select>
        </label>

        <input required type="file" name="files[]" multiple>
        <button>Add files</button>

        <script>
        (() => {
            const kind = document.getElementById('collab-file-kind');
            const category = document.getElementById('collab-category-field');

            function syncCollabCategory() {
                category.hidden = kind.value !== 'contribution';
                category.querySelector('select').required = kind.value === 'contribution';
            }

            kind.addEventListener('change', syncCollabCategory);
            syncCollabCategory();
        })();
        </script>
    </form>
    <h3>Your uploaded files</h3>

    <div class="your-upload-grid">
    <?php foreach($files as $file):?>
        <?php
            $mime = strtolower((string)($file['mime_type'] ?? ''));
            $isImage = str_starts_with($mime, 'image/');

            $ext = strtoupper(
                (string)pathinfo(
                    (string)$file['original_name'],
                    PATHINFO_EXTENSION
                )
            );

            if ($ext === '') {
                $ext = 'FILE';
            }

            $currentCategoryName = 'Uncategorized';

            if ($file['file_kind'] === 'contribution') {
                foreach ($collabCategories as $category) {
                    if ((int)$category['id'] === (int)($file['category_id'] ?? 0)) {
                        $currentCategoryName = $category['name'];
                        break;
                    }
                }
            }
        ?>

        <article class="your-upload-item">

            <?php if($isImage):?>
                <a
                    href="/seller/collabs/<?=$collab['id']?>/files/<?=$file['id']?>/preview"
                    target="_blank"
                    rel="noopener"
                    class="your-upload-preview"
                >
                    <?php
                        $ownThumbExt = match(
                            strtolower((string)$file['mime_type'])
                        ) {
                            'image/jpeg' => 'jpg',
                            'image/png' => 'png',
                            'image/webp' => 'webp',
                            default => null,
                        };

                        $ownThumbUrl = $ownThumbExt
                            ? '/uploads/collab-thumbnails/c'
                                .(int)$collab['id']
                                .'-f'
                                .(int)$file['id']
                                .'.'
                                .$ownThumbExt
                            : '/seller/collabs/'
                                .(int)$collab['id']
                                .'/files/'
                                .(int)$file['id']
                                .'/preview';
                    ?>
                    <img
                        src="<?=H::e($ownThumbUrl)?>"
                        alt="<?=H::e($file['original_name'])?>"
                        loading="lazy"
                        decoding="async"
                    >
                </a>
            <?php else:?>
                <div class="your-upload-placeholder">
                    <span>📄</span>
                    <strong><?=H::e($ext)?></strong>
                </div>
            <?php endif;?>

            <div class="your-upload-name">
                <?=H::e($file['original_name'])?>
            </div>

            <?php if($changesOpen):?>
                <form
                    method="post"
                    action="/seller/collabs/<?=$collab['id']?>/files/<?=$file['id']?>/name"
                    style="display:grid;gap:6px;margin-top:8px"
                >
                    <input
                        type="hidden"
                        name="_csrf"
                        value="<?=H::csrf()?>"
                    >

                    <label
                        style="font-size:.78rem;font-weight:700"
                    >
                        Edit file name
                    </label>

                    <input
                        type="text"
                        name="original_name"
                        value="<?=H::e($file['original_name'])?>"
                        maxlength="255"
                        required
                        autocomplete="off"
                        style="width:100%;min-width:0"
                    >

                    <button type="submit">
                        Save name
                    </button>
                </form>
            <?php endif;?>

            <div class="your-upload-kind">
                <?=H::e(ucfirst((string)$file['file_kind']))?>
            </div>

            <?php if($file['file_kind']==='contribution'):?>

                <form
                    method="post"
                    action="/seller/collabs/<?=$collab['id']?>/files/<?=$file['id']?>/category"
                    class="your-upload-category"
                >
                    <input
                        type="hidden"
                        name="_csrf"
                        value="<?=H::csrf()?>"
                    >

                    <select name="category_id" required>
                        <option value="">Choose category</option>

                        <?php foreach($collabCategories as $category):?>
                            <option
                                value="<?=$category['id']?>"
                                <?=(int)$category['id']===(int)($file['category_id']??0)?'selected':''?>
                            >
                                <?=H::e($category['name'])?>
                            </option>
                        <?php endforeach;?>
                    </select>

                    <button type="submit">Save</button>
                </form>

            <?php endif;?>

            <details style="margin-top:8px">
                <summary style="cursor:pointer;font-weight:700">
                    Request file update
                </summary>

                <form
                    method="post"
                    enctype="multipart/form-data"
                    action="/seller/collabs/<?=$collab['id']?>/files/<?=$file['id']?>/update-request"
                    style="display:grid;gap:7px;margin-top:8px"
                >
                    <input
                        type="hidden"
                        name="_csrf"
                        value="<?=H::csrf()?>"
                    >

                    <input
                        type="file"
                        name="file"
                        required
                    >

                    <textarea
                        name="reason"
                        maxlength="1000"
                        rows="2"
                        placeholder="Optional reason for the update"
                    ></textarea>

                    <button type="submit">
                        Submit Update Request
                    </button>
                </form>
            </details>

            <form
                method="post"
                action="/seller/collabs/<?=$collab['id']?>/files/<?=$file['id']?>/delete"
                class="your-upload-remove"
                onsubmit="return confirm('Remove this file?');"
            >
                <input
                    type="hidden"
                    name="_csrf"
                    value="<?=H::csrf()?>"
                >

                <button type="submit">Remove</button>
            </form>

        </article>

    <?php endforeach;?>
    </div>
</section>
<?php else:?><p class="notice warning">The File Upload Deadline has passed. Membership and contribution changes are locked.</p><?php endif;?>
<?php if(
    !empty($collab['snapshot_at'])
    && (int)$collab['host_designer_id']===(int)$participant['designer_id']
):?>
<section class="card">
    <h2>Final Bundle File Count</h2>

    <p><strong><?=$finalContributionCount?> total contribution files</strong></p>

    <?php if($finalCategoryCounts):?>
        <ul>
            <?php foreach($finalCategoryCounts as $row):?>
                <li><strong><?=intval($row['total'])?></strong> <?=H::e($row['category_name'])?></li>
            <?php endforeach;?>
        </ul>
    <?php endif;?>

    <p>
        Plus
        <strong><?=$finalMockupCount?></strong> mockup<?=$finalMockupCount===1?'':'s'?>
        and
        <strong><?=$finalTermsCount?></strong> Terms & Conditions & About Me file<?=$finalTermsCount===1?'':'s'?>.
    </p>

    <p><small>Mockups and Terms/About files are included in the final ZIP but are not counted toward contribution minimums.</small></p>
</section>
<?php endif;?>



<script>
document.querySelectorAll('.js-local-collab-time').forEach((element) => {
    const date = new Date(element.dataset.utc);

    const formatter = new Intl.DateTimeFormat(undefined,{
        year:'numeric',
        month:'long',
        day:'numeric',
        hour:'numeric',
        minute:'2-digit',
        timeZoneName:'short'
    });

    const viewerZone = Intl.DateTimeFormat().resolvedOptions().timeZone;
    const hostZone = element.dataset.hostTimezone;

    if (viewerZone === hostZone) {
        element.textContent = '';
        return;
    }

    element.textContent = 'Your time: ' + formatter.format(date);
});
</script>
