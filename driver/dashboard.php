<?php
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('driver');
$d = row('SELECT * FROM drivers WHERE user_id=?', [$u['id']]);
$veh = row('SELECT v.*, t.name tname FROM vehicles v JOIN vehicle_types t ON t.id=v.vehicle_type_id WHERE v.driver_id=? LIMIT 1', [$u['id']]);
if (is_post()) {
    post_guard(); $to = inp('availability');
    if ($d['availability'] === 'busy') flash('error', 'You cannot change availability during a trip.');
    elseif (!in_array($to, ['available', 'offline'], true)) flash('error', 'Invalid choice.');
    elseif ($to === 'available' && (!$veh || vehicle_problem($veh) || $d['licence_expiry'] < date('Y-m-d'))) flash('error', 'You cannot go online until your vehicle and licence documents are valid. Contact the office.');
    else { q('UPDATE drivers SET availability=? WHERE user_id=?', [$to, $u['id']]); audit('driver.availability', $to); flash('success', $to === 'available' ? 'You are online and can receive trips.' : 'You are offline.'); }
    redirect('driver/dashboard.php');
}
$td = row("SELECT COUNT(*) n, COALESCE(SUM(total_fare),0) g FROM bookings WHERE driver_id=? AND status='completed' AND DATE(completed_at)=CURDATE()", [$u['id']]);
$all = (int)val("SELECT COUNT(*) FROM bookings WHERE driver_id=? AND status='completed'", [$u['id']]);
$rate = driver_rating((int)$u['id']); $split = earnings_split((float)$td['g']);
$cur = row("SELECT id FROM bookings WHERE driver_id=? AND status IN ('accepted','on_the_way','in_progress') ORDER BY scheduled_at LIMIT 1", [$u['id']]);
$cur = $cur ? booking((int)$cur['id']) : null;
$reqs = rows("SELECT b.*, pl.name pickup_name, dl.name drop_name FROM bookings b LEFT JOIN locations pl ON pl.id=b.pickup_location_id LEFT JOIN locations dl ON dl.id=b.dropoff_location_id WHERE driver_id=? AND status='assigned' ORDER BY scheduled_at", [$u['id']]);
$days = []; for ($i = 6; $i >= 0; $i--) $days[date('Y-m-d', strtotime("-$i day"))] = 0;
foreach (rows("SELECT DATE(completed_at) d, SUM(total_fare) g FROM bookings WHERE driver_id=? AND status='completed' AND completed_at>=DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY DATE(completed_at)", [$u['id']]) as $r) $days[$r['d']] = round($r['g'] * (1 - (float)setting('commission_percent', 15) / 100));
$chart = []; foreach ($days as $k => $v) $chart[date('D', strtotime($k))] = $v;
$warn = array_filter(expiring_docs(30), fn($x) => $x['owner'] === $u['name'] || ($veh && $x['owner'] === $veh['plate']));
$tog = '<form method="post" class="toggle" aria-label="Availability">' . csrf_field()
     . '<button name="availability" value="available" class="' . ($d['availability'] !== 'offline' ? 'on' : '') . '">' . ($d['availability'] === 'busy' ? 'On a trip' : 'Online') . '</button><button name="availability" value="offline" class="' . ($d['availability'] === 'offline' ? 'on' : '') . '">Offline</button></form>';
page_start(greeting() . ', ' . explode(' ', $u['name'])[0], 'driver/dashboard.php', $tog, 'Your trips, earnings and documents at a glance.'); ?>
<div class="grid g4 mb">
  <div class="stat"><span class="chip"><?= icon('car', 22) ?></span><div><div class="v"><?= (int)$td['n'] ?></div><div class="l">Trips completed today</div></div></div>
  <div class="stat g"><span class="chip"><?= icon('wallet', 22) ?></span><div><div class="v"><?= number_format($split['net']) ?></div><div class="l">KES earned today (after <?= $split['pct'] ?>% commission)</div></div></div>
  <div class="stat y"><span class="chip"><?= icon('star', 22) ?></span><div><div class="v"><?= $rate['n'] ? $rate['avg'] : '-' ?></div><div class="l"><?= $rate['n'] ?> ratings</div></div></div>
  <div class="stat"><span class="chip"><?= icon('check', 22) ?></span><div><div class="v"><?= $all ?></div><div class="l">Trips completed in total</div></div></div>
</div>
<div class="grid g-main">
  <div class="grid" style="align-content:start">
    <?php if ($reqs): ?><div class="card" style="border-color:var(--taxi)"><h3>Trips waiting for your answer</h3>
      <?php foreach ($reqs as $r): ?><a class="job mb" href="<?= url('driver/job_view.php?id=' . $r['id']) ?>"><div class="flex"><b><?= e(route_label($r)) ?></b><span class="grow"></span><b><?= money($r['total_fare']) ?></b></div><span class="muted small"><?= dt($r['scheduled_at']) ?> | <?= (int)$r['passengers'] ?> passengers | tap to accept or decline</span></a><?php endforeach; ?></div><?php endif; ?>
    <div class="card"><div class="head"><h3>Current trip</h3><?php if ($cur): ?><?= badge($cur['status']) ?><?php endif; ?></div>
    <?php if ($cur): ?><p><b><?= e(route_label($cur)) ?></b><br><span class="muted"><?= e($cur['customer_name']) ?> | <?= dt($cur['scheduled_at']) ?> | <?= money($cur['total_fare']) ?></span></p><?= progress_bar($cur['status']) ?><a class="btn dark sm mt" href="<?= url('driver/job_view.php?id=' . $cur['id']) ?>">Continue trip</a>
    <?php else: ?><div class="empty"><b><?= $d['availability'] === 'offline' ? 'You are offline' : 'Waiting for the next trip' ?></b><?= $d['availability'] === 'offline' ? 'Go online to be assigned trips.' : 'The office will assign you a trip. You will get a notification.' ?></div><?php endif; ?></div>
    <div class="card"><h3>Your last 7 days (KES, after commission)</h3><?= col_chart($chart, true) ?></div>
  </div>
  <div class="grid" style="align-content:start">
    <div class="card"><h3>Your vehicle</h3>
    <?php if ($veh): ?><p><b><?= e($veh['plate']) ?></b> <?= $veh['status'] !== 'active' ? '<span class="badge b-warn">' . e($veh['status']) . '</span>' : '' ?><br><span class="muted"><?= e($veh['colour'] . ' ' . $veh['make'] . ' ' . $veh['model']) ?> (<?= e($veh['tname']) ?>)</span></p><a class="small" href="<?= url('driver/vehicle.php') ?>">See documents</a>
    <?php else: ?><p class="muted">No vehicle is assigned to you yet. Ask the office.</p><?php endif; ?></div>
    <div class="card"><h3>Documents to renew</h3><?php if (!$warn): ?><p class="muted">Everything is valid for at least 30 days.</p><?php endif; ?>
      <?php foreach ($warn as $w): ?><div class="docwarn"><span><?= e($w['doc']) ?></span><?= doc_badge($w['exp']) ?></div><?php endforeach; ?></div>
  </div>
</div>
<?php page_end();
