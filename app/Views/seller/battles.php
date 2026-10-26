<?php use App\Core\Helpers as H; ?>

<header class="dashboard-heading">
    <div>
        <h1>Battle of the Designers</h1>
        <p class="muted">
            Sign your store up for an open Battle and upload the logo that will represent you in every matchup.
        </p>
    </div>
</header>

<?php foreach($events as $event): ?>
<section class="card">
    <h2><?=H::e($event['title'])?></h2>

    <p>
        Status:
        <strong><?=H::e(ucwords($event['status']))?></strong>
    </p>

    <p>
        <strong>Champion Prize:</strong>
        $100 Creative Moth Advertising Credit
    </p>

    <?php if($event['participant_id']): ?>
        <p>
            <span class="status-badge ok">
                Registered
            </span>
        </p>

        <img
            src="<?=H::e($event['logo_path'])?>"
            alt="Your Battle logo"
            style="width:240px;height:240px;object-fit:contain"
        >

        <?php if($event['seed_position']): ?>
            <p>
                Bracket seed position:
                #<?=(int)$event['seed_position']?>
            </p>
        <?php endif; ?>

        <?php if(
            $event['status']==='signup' &&
            $event['participant_status']==='active'
        ): ?>
            <form
                method="post"
                action="/seller/battle-of-the-designers/<?=(int)$event['id']?>/withdraw"
            >
                <input
                    type="hidden"
                    name="_csrf"
                    value="<?=H::e(H::csrf())?>"
                >

                <button class="btn alt">
                    Withdraw From Battle
                </button>
            </form>
        <?php endif; ?>

    <?php elseif($event['status']==='signup'): ?>

        <form
            method="post"
            enctype="multipart/form-data"
            action="/seller/battle-of-the-designers/<?=(int)$event['id']?>/signup"
            class="form"
        >
            <input
                type="hidden"
                name="_csrf"
                value="<?=H::e(H::csrf())?>"
            >

            <label>
                Your store logo
                <input
                    type="file"
                    name="logo"
                    accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                    required
                >
            </label>

            <p class="muted">
                Required. JPG, PNG, or WEBP, maximum 10 MB.
                Creative Moth automatically places your logo on a consistent square canvas for Battle matchups.
            </p>

            <button class="btn">
                Sign Up for Battle
            </button>
        </form>

    <?php endif; ?>

    <p>
        <a href="/battle-of-the-designers/<?=H::e($event['slug'])?>">
            View Battle
        </a>
    </p>
</section>
<?php endforeach; ?>

<?php if(!$events): ?>
<section class="card">
    <p>No Battle of the Designers event is available yet.</p>
</section>
<?php endif; ?>
