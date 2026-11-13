<?php
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('admin');
$s = row("SELECT SUM(DATE(scheduled_at)=CURDATE() AND status<>'cancelled') today, SUM(status='pending') pending,
  SUM(status IN ('assigned','accepted','on_the_way','in_progress')) active,
  COALESCE(SUM(CASE WHEN status='completed' AND DATE(completed_at)=CURDATE() THEN total_fare END),0) rev_today,
  COALESCE(SUM(CASE WHEN status='completed' AND completed_at>=DATE_FORMAT(NOW(),'%Y-%m-01') THEN total_fare END),0) rev_month FROM bookings");
$dr = row("SELECT COUNT(*) total, SUM(d.availability<>'offline') online FROM drivers d JOIN users u ON u.id=d.user_id WHERE u.status='active'");
$open = (int)val("SELECT COUNT(*) FROM tickets WHERE status<>'resolved'");
$days = []; for ($i = 6; $i >= 0; $i--) $days[date('Y-m-d', strtotime("-$i day"))] = 0;
foreach (rows("SELECT DATE(completed_at) d, SUM(total_fare) g FROM bookings WHERE status='completed' AND completed_at>=DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY DATE(completed_at)") as $r) $days[$r['d']] = (int)$r['g'];
$chart = []; foreach ($days as $k => $v) $chart[date('D', strtotime($k))] = $v;
$bySt = []; foreach (rows("SELECT status, COUNT(*) n FROM bookings WHERE created_at>=DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY status ORDER BY n DESC") as $r) $bySt[STATUS[$r['status']][0]] = (int)$r['n'];
$byType = []; foreach (rows("SELECT trip_type, COUNT(*) n FROM bookings WHERE status<>'cancelled' AND created_at>=DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY trip_type ORDER BY n DESC") as $r) $byType[$r['trip_type']] = (int)$r['n'];
$pend = rows("SELECT b.*, pl.name pickup_name, dl.name drop_name FROM bookings b LEFT JOIN locations pl ON pl.id=b.pickup_location_id LEFT JOIN locations dl ON dl.id=b.dropoff_location_id WHERE b.status='pending' ORDER BY b.scheduled_at LIMIT 6");
$docs = expiring_docs(30); $act = rows('SELECT a.*, u.name FROM audit_log a LEFT JOIN users u ON u.id=a.user_id ORDER BY a.id DESC LIMIT 7');
page_start('Dashboard', 'admin/dashboard.php', '<a class="btn" href="' . url('admin/booking_new.php') . '">' . icon('plus') . 'New booking</a>', 'Homa Bay operations for ' . date('l, j F') . '.'); ?>
<div class="grid g4 mb">
  <div class="stat"><span class="chip"><?= icon('list', 22) ?></span><div><div class="v"><?= (int)$s['today'] ?></div><div class="l">Trips scheduled today</div></div></div>
  <div class="stat y"><span class="chip"><?= icon('clock', 22) ?></span><div><div class="v"><?= (int)$s['pending'] ?></div><div class="l">Waiting for a driver</div></div></div>
  <div class="stat"><span class="chip"><?= icon('car', 22) ?></span><div><div class="v"><?= (int)$s['active'] ?></div><div class="l">Trips on the road</div></div></div>
  <div class="stat g"><span class="chip"><?= icon('users', 22) ?></span><div><div class="v"><?= (int)$dr['online'] ?>/<?= (int)$dr['total'] ?></div><div class="l">Drivers online</div></div></div>
  <div class="stat g"><span class="chip"><?= icon('wallet', 22) ?></span><div><div class="v"><?= number_format($s['rev_today']) ?></div><div class="l">KES earned today</div></div></div>
  <div class="stat g"><span class="chip"><?= icon('chart', 22) ?></span><div><div class="v"><?= number_format($s['rev_month']) ?></div><div class="l">KES this month</div></div></div>
  <div class="stat r"><span class="chip"><?= icon('shield', 22) ?></span><div><div class="v"><?= count($docs) ?></div><div class="l">Documents to renew</div></div></div>
  <div class="stat r"><span class="chip"><?= icon('life', 22) ?></span><div><div class="v"><?= $open ?></div><div class="l">Open support tickets</div></div></div>
</div>
<div class="grid g-main mb">
  <div class="card"><div class="head"><h3>Bookings waiting for a driver</h3><a class="small" href="<?= url('admin/dispatch.php') ?>">Open dispatch board</a></div>
  <?php if (!$pend): ?><div class="empty"><b>All clear</b>Every booking has a driver.</div><?php else: ?><div class="tbl-wrap"><table class="tbl"><thead><tr><th>Reference</th><th>Route</th><th>Pickup time</th><th class="num">Fare</th><th></th></tr></thead><tbody>
  <?php foreach ($pend as $b): ?><tr><td><b><?= e($b['ref']) ?></b><span class="sub"><?= e($b['customer_name']) ?></span></td><td><?= e(route_label($b)) ?></td><td class="nowrap"><?= dt($b['scheduled_at'], 'd M, H:i') ?></td><td class="num"><?= number_format($b['total_fare']) ?></td><td><a class="btn sm" href="<?= url('admin/booking_view.php?id=' . $b['id']) ?>">Assign</a></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div>
  <div class="card"><h3>Revenue, last 7 days (KES)</h3><?= col_chart($chart, true) ?></div>
</div>
<div class="grid g3">
  <div class="card"><h3>Bookings by status (30 days)</h3><?= $bySt ? hbars($bySt) : '<p class="muted">No bookings yet.</p>' ?></div>
  <div class="card"><h3>Trip types (30 days)</h3><?= $byType ? hbars($byType) : '<p class="muted">No bookings yet.</p>' ?></div>
  <div class="card"><h3>Documents expiring soon</h3><?php if (!$docs) echo '<p class="muted">Nothing expires in the next 30 days.</p>'; foreach (array_slice($docs, 0, 6) as $x): ?><div class="docwarn"><span><b><?= e($x['doc']) ?></b><br><span class="muted small"><?= e($x['owner']) ?></span></span><?= doc_badge($x['exp']) ?></div><?php endforeach; ?></div>
</div>
<div class="card mt"><h3>Recent activity</h3><?php foreach ($act as $a): ?><div class="docwarn"><span><b><?= e($a['action']) ?></b> <span class="muted"><?= e($a['detail']) ?></span></span><span class="muted small"><?= e($a['name'] ?? 'System') ?> | <?= ago($a['created_at']) ?></span></div><?php endforeach; ?></div>
<?php page_end();
