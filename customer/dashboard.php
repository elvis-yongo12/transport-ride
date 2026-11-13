<?php
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('customer');
$st = row("SELECT SUM(status='completed') done, SUM(status IN ('pending','assigned','accepted','on_the_way','in_progress')) active,
  COALESCE(SUM(CASE WHEN status='completed' THEN total_fare END),0) spent FROM bookings WHERE customer_id=?", [$u['id']]);
$month = (int)val("SELECT COUNT(*) FROM bookings WHERE customer_id=? AND status='completed' AND completed_at>=DATE_FORMAT(NOW(),'%Y-%m-01')", [$u['id']]);
$active = row("SELECT id FROM bookings WHERE customer_id=? AND status IN ('pending','assigned','accepted','on_the_way','in_progress') ORDER BY FIELD(status,'in_progress','on_the_way','accepted','assigned','pending'), scheduled_at LIMIT 1", [$u['id']]);
$a = $active ? booking((int)$active['id']) : null;
$recent = rows('SELECT b.*, pl.name pickup_name, dl.name drop_name FROM bookings b LEFT JOIN locations pl ON pl.id=b.pickup_location_id LEFT JOIN locations dl ON dl.id=b.dropoff_location_id WHERE customer_id=? ORDER BY b.id DESC LIMIT 5', [$u['id']]);
$saved = rows('SELECT s.*, l.name FROM saved_places s JOIN locations l ON l.id=s.location_id WHERE user_id=?', [$u['id']]);
$promos = rows("SELECT * FROM promo_codes WHERE active=1 AND (expires_on IS NULL OR expires_on>=CURDATE()) AND (max_uses=0 OR used_count<max_uses) ORDER BY id");
page_start(greeting() . ', ' . explode(' ', $u['name'])[0], 'customer/dashboard.php', '<a class="btn" href="' . url('customer/book.php') . '">' . icon('plus') . 'Book a ride</a>', 'Here is what is happening with your rides around Homa Bay.'); ?>
<div class="grid g4 mb">
  <div class="stat"><span class="chip"><?= icon('check', 22) ?></span><div><div class="v"><?= (int)$st['done'] ?></div><div class="l">Trips completed</div></div></div>
  <div class="stat y"><span class="chip"><?= icon('clock', 22) ?></span><div><div class="v"><?= (int)$st['active'] ?></div><div class="l">Trips in progress or upcoming</div></div></div>
  <div class="stat g"><span class="chip"><?= icon('wallet', 22) ?></span><div><div class="v"><?= number_format($st['spent']) ?></div><div class="l">KES spent on rides</div></div></div>
  <div class="stat"><span class="chip"><?= icon('car', 22) ?></span><div><div class="v"><?= $month ?></div><div class="l">Trips this month</div></div></div>
</div>
<div class="grid g-main">
  <div class="grid" style="align-content:start">
    <div class="card"><div class="head"><h3>Your current trip</h3><?php if ($a): ?><?= badge($a['status']) ?><?php endif; ?></div>
    <?php if ($a): ?>
      <p><b><?= e(route_label($a)) ?></b><br><span class="muted"><?= dt($a['scheduled_at']) ?> | <?= e($a['vtype']) ?> | <?= money($a['total_fare']) ?></span></p><?= progress_bar($a['status']) ?>
      <p class="muted small mb"><?= $a['driver_name'] ? 'Driver ' . e($a['driver_name']) . ' in ' . e($a['plate']) : 'Waiting for the office to assign a driver.' ?></p>
      <a class="btn dark sm" href="<?= url('customer/booking_view.php?id=' . $a['id']) ?>">Open trip</a>
    <?php else: ?><div class="empty"><b>No trip right now</b>Book a ride and it will show up here with live status updates.<br><br><a class="btn" href="<?= url('customer/book.php') ?>">Book a ride</a></div><?php endif; ?></div>
    <div class="card"><div class="head"><h3>Recent bookings</h3><a href="<?= url('customer/bookings.php') ?>" class="small">See all</a></div>
    <?php if (!$recent): ?><div class="empty"><b>No bookings yet</b>Your trips will be listed here.</div><?php else: ?>
    <div class="tbl-wrap"><table class="tbl"><thead><tr><th>Reference</th><th>Route</th><th>When</th><th>Status</th><th class="num">Fare</th></tr></thead><tbody>
    <?php foreach ($recent as $b): ?><tr><td><a href="<?= url('customer/booking_view.php?id=' . $b['id']) ?>"><?= e($b['ref']) ?></a></td><td><?= e(route_label($b)) ?></td><td class="muted small nowrap"><?= dt($b['scheduled_at'], 'd M, H:i') ?></td><td><?= badge($b['status']) ?></td><td class="num"><?= number_format($b['total_fare']) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div>
  </div>
  <div class="grid" style="align-content:start">
    <div class="card"><div class="head"><h3>Saved places</h3><a href="<?= url('customer/saved_places.php') ?>" class="small">Manage</a></div>
    <?php if (!$saved): ?><p class="muted">Save Home or Work to book faster.</p><?php endif; ?>
    <?php foreach ($saved as $s): ?><a class="docwarn" style="color:inherit" href="<?= url('customer/book.php?pickup=' . $s['location_id'] . '&pickup_detail=' . urlencode($s['detail'])) ?>"><span><b><?= e($s['label']) ?></b><br><span class="muted small"><?= e($s['name']) ?></span></span><span class="small">Ride from here</span></a><?php endforeach; ?></div>
    <div class="card"><h3>Promo codes you can use</h3>
    <?php foreach ($promos as $p): ?><div class="docwarn"><span><b><?= e($p['code']) ?></b><br><span class="muted small"><?= $p['type'] === 'percent' ? $p['value'] . '% off' : money($p['value']) . ' off' ?><?= $p['min_fare'] ? ', fares from ' . money($p['min_fare']) : '' ?></span></span><span class="muted small"><?= $p['expires_on'] ? 'Until ' . dt($p['expires_on'], 'd M') : 'No expiry' ?></span></div><?php endforeach; ?></div>
  </div>
</div>
<?php page_end();
