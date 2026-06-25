<h1>Categories</h1>
<form method="post" class="card form">
    <input type="hidden" name="_csrf" value="<?=H::csrf()?>">
    <input name="name" placeholder="Name">
    <input name="slug" placeholder="slug">
    <textarea name="description" placeholder="Description">
    </textarea>
    <input disabled placeholder="Image/icon placeholder">
    <button>Save category</button>
</form>
<div class="grid">
    <?php foreach($cats as $c):?>
        <div class="card">
           <h3>
           <?=$c['name']?>
           </h3>
           <p>
           <?=$c['slug']?>
           </p>
           <p>
           <?=$c['description']?>
           </p>
        </div>
    <?php endforeach;?>
</div>
