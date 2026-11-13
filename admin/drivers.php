<?php
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('admin');
if (is_post()) {
    post_guard(); $act = inp('action'); $id = (int)inp('id');
    if ($act === 'toggle') {
        $t = row("SELECT status FROM users WHERE id=? AND role='driver'", [$id]);
        if ($t) { $n = $t['status'] === 'active' ? 'suspended' : 'active'; q('UPDATE users SET status=? WHERE id=?', [$n, $id]); if ($n === 'suspended') q("UPDATE drivers SET availability='offline' WHERE user_id=?", [$id]); audit('driver.' . $n, '#' . $id); flash('success', 'Driver ' . $n . '.'); }
        redirect('admin/drivers.php');
    }
    $name = inp('name'); $em = strtolower(inp('email')); $ph = norm_phone(inp('phone')); $pw = $_POST['password'] ?? '';
    $err = [];
    if (mb_strlen($name) < 3) $err[] = 'Enter the driver\'s full name.';
    if (!filter_var($em, FILTER_VALIDATE_EMAIL)) $err[] = 'Enter a valid email.';
    if (!$ph) $err[] = 'Enter a valid Kenyan phone number.';
    if (!$id && strlen($pw) < 8) $err[] = 'Set a starting password of at least 8 characters.';
    if ($id && $pw !== '' && strlen($pw) < 8) $err[] = 'A new password needs at least 8 characters.';
    if (!inp('licence_no') || !strtotime(inp('licence_expiry'))) $err[] = 'Enter the licence number and expiry date.';
    if (val('SELECT id FROM users WHERE email=? AND id<>?', [$em, $id])) $err[] = 'That email is already in use.';
    if ($err) { flash('error', implode(' ', $err)); redirect('admin/drivers.php?' . ($id ? 'edit=' . $id : 'new=1')); }
    $psvx = strtotime(inp('psv_expiry')) ? inp('psv_expiry') : null;
    db()->beginTransaction();
    if ($id) {
        q('UPDATE users SET name=?, email=?, phone=? WHERE id=? AND role=?', [$name, $em, $ph, $id, 'driver']); if ($pw !== '') q('UPDATE users SET password=? WHERE id=?', [password_hash($pw, PASSWORD_DEFAULT), $id]);
        q('UPDATE drivers SET licence_no=?, licence_expiry=?, psv_badge=?, psv_expiry=?, base_area=? WHERE user_id=?', [inp('licence_no'), inp('licence_expiry'), inp('psv_badge'), $psvx, inp('base_area'), $id]);
    } else {
        q("INSERT INTO users (role, name, email, phone, password) VALUES ('driver',?,?,?,?)", [$name, $em, $ph, password_hash($pw, PASSWORD_DEFAULT)]); $id = (int)db()->lastInsertId();
        q('INSERT INTO drivers (user_id, licence_no, licence_expiry, psv_badge, psv_expiry, base_area, joined_on) VALUES (?,?,?,?,?,?,CURDATE())', [$id, inp('licence_no'), inp('licence_expiry'), inp('psv_badge'), $psvx, inp('base_area')]);
    }
    db()->commit(); audit('driver.save', $name); flash('success', 'Driver saved.'); redirect('admin/drivers.php');
}
$edit = (int)($_GET['edit'] ?? 0); $f = $edit ? row("SELECT u.*, d.* FROM users u JOIN drivers d ON d.user_id=u.id WHERE u.id=? AND u.role='driver'", [$edit]) : null;
$show = isset($_GET['new']) || $f; $v = fn($k) => e($f[$k] ?? '');
$list = rows("SELECT u.id, u.name, u.email, u.phone, u.status, d.licence_expiry, d.psv_expiry, d.availability, d.base_area, v.plate,
  (SELECT COUNT(*) FROM bookings b WHERE b.driver_id=u.id AND b.status='completed') trips, (SELECT AVG(stars) FROM ratings r WHERE r.driver_id=u.id) rating
  FROM users u JOIN drivers d ON d.user_id=u.id LEFT JOIN vehicles v ON v.driver_id=u.id AND v.status<>'retired' WHERE u.role='driver' ORDER BY u.name");
page_start('Drivers', 'admin/drivers.php', '<a class="btn" href="' . url('admin/drivers.php?new=1') . '">' . icon('plus') . 'Add driver</a>', count($list) . ' drivers on the TaxiRide team.'); ?>
<?php if ($show): ?><form class="card mb" method="post"><h3><?= $f ? 'Edit ' . e($f['name']) : 'Add a driver' ?></h3><?= csrf_field() ?><input type="hidden" name="id" value="<?= $edit ?>">
  <div class="row3"><div class="field"><label for="name">Full name</label><input id="name" name="name" required value="<?= $v('name') ?>"></div><div class="field"><label for="email">Email (used to log in)</label><input id="email" name="email" type="email" required value="<?= $v('email') ?>"></div><div class="field"><label for="phone">Phone</label><input id="phone" name="phone" required value="<?= $v('phone') ?>"></div></div>
  <div class="row3"><div class="field"><label for="licence_no">Driving licence number</label><input id="licence_no" name="licence_no" required value="<?= $v('licence_no') ?>"></div><div class="field"><label for="licence_expiry">Licence expiry</label><input id="licence_expiry" type="date" name="licence_expiry" required value="<?= $v('licence_expiry') ?>"></div><div class="field"><label for="base_area">Base area</label><input id="base_area" name="base_area" value="<?= $v('base_area') ?>"></div></div>
  <div class="row3"><div class="field"><label for="psv_badge">PSV badge number</label><input id="psv_badge" name="psv_badge" value="<?= $v('psv_badge') ?>"></div><div class="field"><label for="psv_expiry">PSV badge expiry</label><input id="psv_expiry" type="date" name="psv_expiry" value="<?= $v('psv_expiry') ?>"></div>
  <div class="field"><label for="password"><?= $f ? 'New password (leave blank to keep)' : 'Starting password' ?></label><input id="password" name="password" type="text" minlength="8" <?= $f ? '' : 'required' ?>></div></div>
  <div class="flex"><button class="btn">Save driver</button><a class="btn line" href="<?= url('admin/drivers.php') ?>">Cancel</a></div></form><?php endif; ?>
<div class="toolbar"><input data-filter="#drv" placeholder="Search drivers"></div>
<div class="card"><div class="tbl-wrap"><table class="tbl" id="drv"><thead><tr><th>Driver</th><th>Vehicle</th><th>Availability</th><th>Documents</th><th class="num">Trips</th><th>Rating</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ($list as $d): ?><tr><td><b><?= e($d['name']) ?></b><span class="sub"><?= e($d['phone']) ?> | <?= e($d['email']) ?></span></td><td><?= $d['plate'] ? e($d['plate']) : '<span class="muted">None</span>' ?></td>
<td><span class="badge <?= ['available' => 'b-ok', 'busy' => 'b-primary', 'offline' => 'b-muted'][$d['availability']] ?>"><?= $d['availability'] ?></span></td><td><?= doc_badge($d['licence_expiry']) ?><span class="sub">PSV: <?= $d['psv_expiry'] ? dt($d['psv_expiry'], 'd M Y') : 'not set' ?></span></td>
<td class="num"><?= (int)$d['trips'] ?></td><td><?= $d['rating'] ? stars($d['rating']) . ' ' . round($d['rating'], 1) : '<span class="muted">New</span>' ?></td><td><?= $d['status'] === 'active' ? '<span class="badge b-ok">Active</span>' : '<span class="badge b-bad">Suspended</span>' ?></td>
<td class="nowrap"><a class="btn sm line" href="?edit=<?= $d['id'] ?>">Edit</a> <form method="post" style="display:inline" data-confirm="<?= $d['status'] === 'active' ? 'Suspend' : 'Reactivate' ?> this driver?"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $d['id'] ?>"><button class="btn sm <?= $d['status'] === 'active' ? 'bad' : 'line' ?>"><?= $d['status'] === 'active' ? 'Suspend' : 'Reactivate' ?></button></form></td></tr><?php endforeach; ?></tbody></table></div></div>
<?php page_end();
