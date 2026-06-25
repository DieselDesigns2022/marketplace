<h1>Designer Management</h1>
<table>
    <tr>
        <th>Designer</th>
        <th>Email</th>
        <th>Store</th>
        <th>Status</th>
        <th>Followers</th>
        <th>Rank</th>
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
               <button>Change rank</button>
               <button disabled>Disable seller</button>
           </form>
           </td>
        </tr>
    <?php endforeach;?>
</table>
