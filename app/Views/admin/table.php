<h1>
<?=H::e($title)?>
</h1>
<table>
    <?php if($rows):?>
        <tr>
           <?php foreach(array_keys($rows[0]) as $h):?>
               <th>
               <?=$h?>
               </th>
           <?php endforeach;?>
        </tr>
        <?php foreach($rows as $r):?>
           <tr>
               <?php foreach($r as $v):?>
                   <td>
                   <?=H::e((string)$v)?>
                   </td>
               <?php endforeach;?>
           </tr>
        <?php endforeach; else:?>
        <tr>
           <td>No records.</td>
        </tr>
    <?php endif;?>
</table>
