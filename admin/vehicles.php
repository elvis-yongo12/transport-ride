<?php
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('admin');
$types = rows('SELECT * FROM vehicle_types ORDER BY multiplier');
if (is_post()) {
    post_guard(); $id = (int)inp('id'); $plate = strtoupper(inp('plate')); $drv = (int)inp('driver') ?: null;
    if (inp('action') === 'delete') {
        if (val('SELECT COUNT(*) FROM bookings WHERE vehicle_id=?', [$id])) { q("UPDATE vehicles SET status='retired', driver_id=NULL WHERE id=?", [$id]); flash('info', 'This vehicle has trip history, so it was retired instead of deleted.'); }
        else { q('DELETE FROM vehicles WHERE id=?', [$id]); flash('success', 'Vehicle deleted.'); }
        audit('vehicle.remove', '#' . $id); redirect('admin/vehicles.php');
    }
    if (strlen($plate) < 5 || !strtotime(inp('insurance')) || !strtotime(inp('inspection')) || !(int)inp('type')) { flash('error', 'Enter the plate, vehicle type and both expiry dates.'); redirect('admin/vehicles.php?' . ($id ? 'edit=' . $id : 'new=1')); }
    if (val('SELECT id FROM vehicles WHERE plate=? AND id<>?', [$plate, $id])) { flash('error', 'A vehicle with that plate already exists.'); redirect('admin/vehicles.php?' . ($id ? 'edit=' . $id : 'new=1')); }
    $st = in_array(inp('status'), ['active', 'maintenance', 'retired'], true) ? inp('status') : 'active';
    if ($id) q('UPDATE vehicles SET plate=?, make=?, model=?, colour=?, year=?, vehicle_type_id=?, insurance_expiry=?, inspection_expiry=?, status=?, driver_id=? WHERE id=?', [$plate, inp('make'), inp('model'), inp('colour'), (int)inp('year'), (int)inp('type'), inp('insurance'), inp('inspection'), $st, $drv, $id]);
    else { q('INSERT INTO vehicles (plate, make, model, colour, year, vehicle_type_id, insurance_expiry, inspection_expiry, status, driver_id) VALUES (?,?,?,?,?,?,?,?,?,?)', [$plate, inp('make'), inp('model'), inp('colour'), (int)inp('year'), (int)inp('type'), inp('insurance'), inp('inspection'), $st, $drv]); $id = (int)db()->lastInsertId(); }
    if ($drv) q('UPDATE vehicles SET driver_id=NULL WHERE driver_id=? AND id<>?', [$drv, $id]);
    audit('vehicle.save', $plate); flash('success', 'Vehicle saved.'); redirect('admin/vehicles.php');
}
$edit = (int)($_GET['edit'] ?? 0); $f = $edit ? row('SELECT * FROM vehicles WHERE id=?', [$edit]) : null; $show = isset($_GET['new']) || $f; $v = fn($k) => e($f[$k] ?? '');
$drivers = rows("SELECT id, name FROM users WHERE role='driver' ORDER BY name");
$list = rows('SELECT v.*, t.name tname, t.seats, u.name dname FROM vehicles v JOIN vehicle_types t ON t.id=v.vehicle_type_id LEFT JOIN users u ON u.id=v.driver_id ORDER BY v.status, v.plate');
page_start('Vehicles', 'admin/vehicles.php', '<a class="btn" href="' . url('admin/vehicles.php?new=1') . '">' . icon('plus') . 'Add vehicle</a>', 'Fleet, assigned drivers and document dates.'); ?>
<?php if ($show): ?><form class="card mb" method="post"><h3><?= $f ? 'Edit ' . e($f['plate']) : 'Add a vehicle' ?></h3><?= csrf_field() ?><input type="hidden" name="id" value="<?= $edit ?>">
  <div class="row3"><div class="field"><label for="plate">Number plate</label><input id="plate" name="plate" required placeholder="KDA 123A" value="<?= $v('plate') ?>"></div><div class="field"><label for="make">Make</label><input id="make" name="make" value="<?= $v('make') ?>"></div><div class="field"><label for="model">Model</label><input id="model" name="model" value="<?= $v('model') ?>"></div></div>
  <div class="row3"><div class="field"><label for="colour">Colour</label><input id="colour" name="colour" value="<?= $v('colour') ?>"></div><div class="field"><label for="year">Year</label><input id="year" type="number" name="year" min="1990" max="<?= date('Y') + 1 ?>" value="<?= $v('year') ?>"></div>
  <div class="field"><label for="type">Type</label><select id="type" name="type"><?php foreach ($types as $t): ?><option value="<?= $t['id'] ?>" <?= (int)($f['vehicle_type_id'] ?? 0) === (int)$t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?> (<?= $t['seats'] ?> seats)</option><?php endforeach; ?></select></div></div>
  <div class="row3"><div class="field"><label for="insurance">Insurance expiry</label><input id="insurance" type="date" name="insurance" required value="<?= $v('insurance_expiry') ?>"></div><div class="field"><label for="inspection">Inspection expiry</label><input id="inspection" type="date" name="inspection" required value="<?= $v('inspection_expiry') ?>"></div>
  <div class="field"><label for="status">Status</label><select id="status" name="status"><?php foreach (['active', 'maintenance', 'retired'] as $s): ?><option <?= ($f['status'] ?? 'active') === $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?></select></div></div>
  <div class="field" style="max-width:360px"><label for="driver">Assigned driver</label><select id="driver" name="driver"><option value="">No driver</option><?php foreach ($drivers as $d): ?><option value="<?= $d['id'] ?>" <?= (int)($f['driver_id'] ?? 0) === (int)$d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option><?php endforeach; ?></select></div>
  <div class="flex"><button class="btn">Save vehicle</button><a class="btn line" href="<?= url('admin/vehicles.php') ?>">Cancel</a></div></form><?php endif; ?>
<div class="card"><div class="tbl-wrap"><table class="tbl"><thead><tr><th>Plate</th><th>Car</th><th>Driver</th><th>Insurance</th><th>Inspection</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ($list as $x): ?><tr><td><b><?= e($x['plate']) ?></b><span class="sub"><?= e($x['tname']) ?>, <?= $x['seats'] ?> seats</span></td><td><?= e($x['colour'] . ' ' . $x['make'] . ' ' . $x['model']) ?><span class="sub"><?= (int)$x['year'] ?></span></td><td><?= $x['dname'] ? e($x['dname']) : '<span class="muted">Unassigned</span>' ?></td>
<td><?= doc_badge($x['insurance_expiry']) ?></td><td><?= doc_badge($x['inspection_expiry']) ?></td><td><span class="badge <?= ['active' => 'b-ok', 'maintenance' => 'b-warn', 'retired' => 'b-muted'][$x['status']] ?>"><?= $x['status'] ?></span></td>
<td class="nowrap"><a class="btn sm line" href="?edit=<?= $x['id'] ?>">Edit</a> <form method="post" style="display:inline" data-confirm="Remove this vehicle?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $x['id'] ?>"><button class="btn sm bad">Remove</button></form></td></tr><?php endforeach; ?></tbody></table></div></div>
<?php page_end();
