<?php
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('admin');
if (is_post()) {
    post_guard(); $id = (int)inp('id'); $t = row("SELECT status FROM users WHERE id=? AND role='customer'", [$id]);
    if ($t) { $n = $t['status'] === 'active' ? 'suspended' : 'active'; q('UPDATE users SET status=? WHERE id=?', [$n, $id]); audit('customer.' . $n, '#' . $id); flash('success', 'Customer ' . $n . '.'); }
    redirect('admin/customers.php');
}
$list = rows("SELECT u.*, (SELECT COUNT(*) FROM bookings b WHERE b.customer_id=u.id AND b.status='completed') trips, (SELECT COALESCE(SUM(total_fare),0) FROM bookings b WHERE b.customer_id=u.id AND b.status='completed') spent,
  (SELECT MAX(scheduled_at) FROM bookings b WHERE b.customer_id=u.id) last_trip FROM users u WHERE u.role='customer' ORDER BY u.created_at DESC");
page_start('Customers', 'admin/customers.php', '', count($list) . ' registered passengers.'); ?>
<div class="toolbar"><input data-filter="#cus" placeholder="Search customers"></div>
<div class="card"><div class="tbl-wrap"><table class="tbl" id="cus"><thead><tr><th>Customer</th><th>Joined</th><th class="num">Trips</th><th class="num">Spent (KES)</th><th>Last trip</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ($list as $c): ?><tr><td><b><?= e($c['name']) ?></b><span class="sub"><?= e($c['phone']) ?> | <?= e($c['email']) ?></span></td><td class="nowrap"><?= dt($c['created_at'], 'd M Y') ?></td><td class="num"><?= (int)$c['trips'] ?></td><td class="num"><?= number_format($c['spent']) ?></td><td class="nowrap"><?= $c['last_trip'] ? dt($c['last_trip'], 'd M Y') : '-' ?></td>
<td><?= $c['status'] === 'active' ? '<span class="badge b-ok">Active</span>' : '<span class="badge b-bad">Suspended</span>' ?></td><td class="nowrap"><a class="btn sm line" href="<?= url('admin/bookings.php?customer=' . $c['id']) ?>">Bookings</a>
<form method="post" style="display:inline" data-confirm="Change this account status?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $c['id'] ?>"><button class="btn sm <?= $c['status'] === 'active' ? 'bad' : 'line' ?>"><?= $c['status'] === 'active' ? 'Suspend' : 'Reactivate' ?></button></form></td></tr><?php endforeach; ?></tbody></table></div></div>
<?php page_end();
