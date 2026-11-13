<?php
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('admin');
if (is_post()) {
    post_guard(); $id = (int)inp('id'); $act = inp('action');
    if ($act === 'delete') {
        if (val('SELECT COUNT(*) FROM bookings WHERE pickup_location_id=? OR dropoff_location_id=?', [$id, $id])) flash('error', 'This place is used by bookings. Switch it off instead of deleting it.');
        else { q('DELETE FROM locations WHERE id=?', [$id]); audit('location.delete', '#' . $id); flash('success', 'Place deleted.'); }
    } elseif ($act === 'toggle') { q('UPDATE locations SET active=1-active WHERE id=?', [$id]); flash('success', 'Place updated.'); }
    else {
        $n = inp('name'); $a = inp('area');
        if (mb_strlen($n) < 3 || !in_array($a, ['Town', 'Intertown', 'Intercounty'], true)) flash('error', 'Enter a place name and choose where it is.');
        elseif (val('SELECT id FROM locations WHERE name=? AND id<>?', [$n, $id])) flash('error', 'That place already exists.');
        else { if ($id) q('UPDATE locations SET name=?, area=?, landmark=? WHERE id=?', [$n, $a, inp('landmark'), $id]); else q('INSERT INTO locations (name, area, landmark) VALUES (?,?,?)', [$n, $a, inp('landmark')]); audit('location.save', $n); flash('success', 'Place saved.'); }
    }
    redirect('admin/locations.php');
}
$edit = (int)($_GET['edit'] ?? 0); $f = $edit ? row('SELECT * FROM locations WHERE id=?', [$edit]) : null;
$list = rows("SELECT l.*, (SELECT COUNT(*) FROM bookings b WHERE b.pickup_location_id=l.id OR b.dropoff_location_id=l.id) uses FROM locations l ORDER BY FIELD(area,'Town','Intertown','Intercounty'), name");
page_start('Places', 'admin/locations.php', '', 'Pickup and drop-off places across Homa Bay and nearby areas.'); ?>
<div class="grid g-side">
  <form class="card" method="post"><h3><?= $f ? 'Edit place' : 'Add a place' ?></h3><?= csrf_field() ?><input type="hidden" name="id" value="<?= $edit ?>">
    <div class="field"><label for="name">Name</label><input id="name" name="name" required value="<?= e($f['name'] ?? '') ?>"></div>
    <div class="field"><label for="area">Where is it?</label><select id="area" name="area"><?php foreach (['Town' => 'In Homa Bay town', 'Intertown' => 'Nearby town', 'Intercounty' => 'Another county'] as $k => $l): ?><option value="<?= $k ?>" <?= ($f['area'] ?? '') === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
    <div class="field"><label for="landmark">Landmark or description</label><input id="landmark" name="landmark" value="<?= e($f['landmark'] ?? '') ?>"></div><div class="flex"><button class="btn">Save place</button><?php if ($f): ?><a class="btn line" href="<?= url('admin/locations.php') ?>">Cancel</a><?php endif; ?></div></form>
  <div class="card"><div class="tbl-wrap"><table class="tbl"><thead><tr><th>Place</th><th>Area</th><th class="num">Used</th><th>Status</th><th></th></tr></thead><tbody>
  <?php foreach ($list as $l): ?><tr><td><b><?= e($l['name']) ?></b><span class="sub"><?= e($l['landmark']) ?></span></td><td><?= e($l['area']) ?></td><td class="num"><?= (int)$l['uses'] ?></td><td><?= $l['active'] ? '<span class="badge b-ok">On</span>' : '<span class="badge b-muted">Off</span>' ?></td>
  <td class="nowrap"><a class="btn sm line" href="?edit=<?= $l['id'] ?>">Edit</a> <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $l['id'] ?>"><input type="hidden" name="action" value="toggle"><button class="btn sm line"><?= $l['active'] ? 'Switch off' : 'Switch on' ?></button></form>
  <form method="post" style="display:inline" data-confirm="Delete this place?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $l['id'] ?>"><input type="hidden" name="action" value="delete"><button class="btn sm bad">Delete</button></form></td></tr><?php endforeach; ?></tbody></table></div></div>
</div>
<?php page_end();
