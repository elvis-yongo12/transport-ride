<?php
// TaxiRide - Admin reports. Save as taxiride/admin/reports.php
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('admin');

$to = inp('to') ?: date('Y-m-d');
$from = inp('from') ?: date('Y-m-d', strtotime('-29 days'));
if (!strtotime($from) || !strtotime($to) || $from > $to) { $from = date('Y-m-d', strtotime('-29 days')); $to = date('Y-m-d'); }
$p = [$from, $to];
$range = "DATE(b.scheduled_at) BETWEEN ? AND ?";

/* ---------- CSV exports ---------- */
if (inp('export') === 'bookings') {
    $x = rows("SELECT b.ref, b.customer_name, b.scheduled_at, b.trip_type, b.status, b.source, b.payment_method, b.payment_status, b.base_fare, b.discount, b.total_fare,
        pl.name pickup, dl.name dropoff, du.name driver FROM bookings b LEFT JOIN locations pl ON pl.id=b.pickup_location_id LEFT JOIN locations dl ON dl.id=b.dropoff_location_id
        LEFT JOIN users du ON du.id=b.driver_id WHERE $range ORDER BY b.scheduled_at", $p);
    csv_out("taxiride-bookings-$from-to-$to", ['Reference', 'Customer', 'Pickup time', 'Type', 'Status', 'Source', 'Pay method', 'Pay status', 'Base fare', 'Discount', 'Total fare', 'From', 'To', 'Driver'],
        array_map(fn($r) => [$r['ref'], $r['customer_name'], $r['scheduled_at'], $r['trip_type'], $r['status'], $r['source'], $r['payment_method'], $r['payment_status'], $r['base_fare'], $r['discount'], $r['total_fare'], $r['pickup'], $r['dropoff'], $r['driver']], $x));
}

/* ---------- summary numbers ---------- */
$k = row("SELECT COUNT(*) total, COALESCE(SUM(status='completed'),0) done, COALESCE(SUM(status='cancelled'),0) cancelled,
    COALESCE(SUM(CASE WHEN status='completed' THEN total_fare END),0) revenue, COALESCE(SUM(CASE WHEN status='completed' THEN discount END),0) discounts
    FROM bookings b WHERE $range", $p);
$done = (int)$k['done']; $total = (int)$k['total']; $rev = (float)$k['revenue'];
$split = earnings_split($rev); $avg = $done ? $rev / $done : 0; $cancelRate = $total ? round($k['cancelled'] / $total * 100, 1) : 0;

/* ---------- revenue over time ---------- */
$span = (strtotime($to) - strtotime($from)) / 86400; $series = [];
if ($span <= 31) {
    for ($t = strtotime($from); $t <= strtotime($to); $t += 86400) $series[date('d M', $t)] = 0;
    foreach (rows("SELECT DATE(b.scheduled_at) d, SUM(b.total_fare) g FROM bookings b WHERE b.status='completed' AND $range GROUP BY d", $p) as $r) $series[date('d M', strtotime($r['d']))] = (int)$r['g'];
    $chartTitle = 'Revenue per day (KES)';
} else {
    for ($t = strtotime(date('Y-m-01', strtotime($from))); $t <= strtotime($to); $t = strtotime('+1 month', $t)) $series[date('M y', $t)] = 0;
    foreach (rows("SELECT DATE_FORMAT(b.scheduled_at,'%Y-%m-01') m, SUM(b.total_fare) g FROM bookings b WHERE b.status='completed' AND $range GROUP BY m", $p) as $r) $series[date('M y', strtotime($r['m']))] = (int)$r['g'];
    $chartTitle = 'Revenue per month (KES)';
}

/* ---------- breakdowns ---------- */
$byType = []; foreach (rows("SELECT b.trip_type t, COUNT(*) n FROM bookings b WHERE b.status<>'cancelled' AND $range GROUP BY b.trip_type ORDER BY n DESC", $p) as $r) $byType[$r['t']] = (int)$r['n'];
$bySource = []; foreach (rows("SELECT b.source s, COUNT(*) n FROM bookings b WHERE $range GROUP BY b.source ORDER BY n DESC", $p) as $r) $bySource[ucfirst($r['s'])] = (int)$r['n'];
$byPay = ['Cash' => 0, 'M-Pesa' => 0]; foreach (rows("SELECT b.payment_method m, SUM(b.total_fare) g FROM bookings b WHERE b.status='completed' AND $range GROUP BY b.payment_method", $p) as $r) $byPay[$r['m'] === 'mpesa' ? 'M-Pesa' : 'Cash'] = (int)$r['g'];
$hours = []; for ($h = 5; $h <= 22; $h++) $hours[sprintf('%02d', $h)] = 0;
foreach (rows("SELECT HOUR(b.scheduled_at) h, COUNT(*) n FROM bookings b WHERE b.status<>'cancelled' AND $range GROUP BY h", $p) as $r) if (isset($hours[sprintf('%02d', $r['h'])])) $hours[sprintf('%02d', $r['h'])] = (int)$r['n'];
$routes = rows("SELECT pl.name a, dl.name b, COUNT(*) n, SUM(b.total_fare) g FROM bookings b JOIN locations pl ON pl.id=b.pickup_location_id JOIN locations dl ON dl.id=b.dropoff_location_id
    WHERE b.status='completed' AND $range GROUP BY b.pickup_location_id, b.dropoff_location_id ORDER BY n DESC, g DESC LIMIT 8", $p);

/* ---------- driver and vehicle performance ---------- */
$drivers = rows("SELECT u.id, u.name,
    (SELECT COUNT(*) FROM bookings b WHERE b.driver_id=u.id AND b.status='completed' AND $range) trips,
    (SELECT COALESCE(SUM(b.total_fare),0) FROM bookings b WHERE b.driver_id=u.id AND b.status='completed' AND $range) revenue,
    (SELECT AVG(r.stars) FROM ratings r JOIN bookings b ON b.id=r.booking_id WHERE r.driver_id=u.id AND $range) rating,
    (SELECT COUNT(*) FROM booking_logs l WHERE l.note LIKE CONCAT('Declined by ', u.name, '%') AND DATE(l.created_at) BETWEEN ? AND ?) declines
    FROM users u WHERE u.role='driver' ORDER BY revenue DESC", array_merge($p, $p, $p, $p));
if (inp('export') === 'drivers') csv_out("taxiride-drivers-$from-to-$to", ['Driver', 'Completed trips', 'Fares collected', 'Commission', 'Driver earnings', 'Average rating', 'Declined trips'],
    array_map(fn($d) => [$d['name'], $d['trips'], $d['revenue'], earnings_split((float)$d['revenue'])['fee'], earnings_split((float)$d['revenue'])['net'], $d['rating'] ? round($d['rating'], 1) : '', $d['declines']], $drivers));
$vehicles = rows("SELECT v.plate, t.name tname, COUNT(b.id) trips, COALESCE(SUM(b.total_fare),0) revenue FROM vehicles v JOIN vehicle_types t ON t.id=v.vehicle_type_id
    LEFT JOIN bookings b ON b.vehicle_id=v.id AND b.status='completed' AND $range WHERE v.status<>'retired' GROUP BY v.id ORDER BY trips DESC, v.plate", $p);
$vbars = []; foreach ($vehicles as $v) $vbars[$v['plate']] = (int)$v['trips'];

$qs = 'from=' . urlencode($from) . '&to=' . urlencode($to);
$acts = '<a class="btn line" href="?' . $qs . '&export=bookings">Bookings CSV</a><a class="btn line" href="?' . $qs . '&export=drivers">Drivers CSV</a><button class="btn dark" data-print>Print report</button>';
page_start('Reports', 'admin/reports.php', $acts, 'Operations and money summary for ' . dt($from, 'd M Y') . ' to ' . dt($to, 'd M Y') . '. Dates refer to the trip pickup date.'); ?>
<form class="toolbar no-print" method="get"><label for="from" class="small" style="margin:0">From</label><input id="from" type="date" name="from" value="<?= e($from) ?>"><label for="to" class="small" style="margin:0">to</label><input id="to" type="date" name="to" value="<?= e($to) ?>"><button class="btn dark">Show report</button>
  <a class="btn line" href="?from=<?= date('Y-m-d', strtotime('-6 days')) ?>&to=<?= date('Y-m-d') ?>">Last 7 days</a><a class="btn line" href="?from=<?= date('Y-m-01') ?>&to=<?= date('Y-m-d') ?>">This month</a><a class="btn line" href="?from=<?= date('Y-m-d', strtotime('-89 days')) ?>&to=<?= date('Y-m-d') ?>">Last 90 days</a></form>

<div class="grid g4 mb">
  <div class="stat"><span class="chip"><?= icon('list', 22) ?></span><div><div class="v"><?= $total ?></div><div class="l">Bookings in period</div></div></div>
  <div class="stat g"><span class="chip"><?= icon('check', 22) ?></span><div><div class="v"><?= $done ?></div><div class="l">Trips completed</div></div></div>
  <div class="stat r"><span class="chip"><?= icon('clock', 22) ?></span><div><div class="v"><?= $cancelRate ?>%</div><div class="l">Cancellation rate (<?= (int)$k['cancelled'] ?> trips)</div></div></div>
  <div class="stat y"><span class="chip"><?= icon('wallet', 22) ?></span><div><div class="v"><?= number_format($rev) ?></div><div class="l">Revenue from completed trips (KES)</div></div></div>
  <div class="stat"><span class="chip"><?= icon('chart', 22) ?></span><div><div class="v"><?= number_format($avg) ?></div><div class="l">Average fare (KES)</div></div></div>
  <div class="stat"><span class="chip"><?= icon('tag', 22) ?></span><div><div class="v"><?= number_format($k['discounts']) ?></div><div class="l">Discounts given (KES)</div></div></div>
  <div class="stat g"><span class="chip"><?= icon('shield', 22) ?></span><div><div class="v"><?= number_format($split['fee']) ?></div><div class="l">TaxiRide commission (<?= $split['pct'] ?>%)</div></div></div>
  <div class="stat g"><span class="chip"><?= icon('users', 22) ?></span><div><div class="v"><?= number_format($split['net']) ?></div><div class="l">Driver earnings (KES)</div></div></div>
</div>

<div class="grid g-main mb">
  <div class="card"><h3><?= e($chartTitle) ?></h3><?= col_chart($series, true) ?></div>
  <div class="card"><h3>Revenue by payment method (KES)</h3><?= hbars($byPay, true) ?><h3 class="mt">Where bookings come from</h3><?= $bySource ? hbars($bySource) : '<p class="muted">No bookings in this period.</p>' ?></div>
</div>
<div class="grid g3 mb">
  <div class="card"><h3>Trip types</h3><?= $byType ? hbars($byType) : '<p class="muted">No trips in this period.</p>' ?></div>
  <div class="card"><h3>Busiest hours of the day</h3><?= col_chart($hours) ?></div>
  <div class="card"><h3>Vehicle use (completed trips)</h3><?= $vbars ? hbars($vbars) : '<p class="muted">No vehicles.</p>' ?></div>
</div>

<div class="card mb"><h3>Driver performance</h3><div class="tbl-wrap"><table class="tbl"><thead><tr><th>Driver</th><th class="num">Trips</th><th class="num">Fares collected</th><th class="num">Commission</th><th class="num">Driver earnings</th><th>Rating</th><th class="num">Declined</th></tr></thead><tbody>
<?php foreach ($drivers as $d): $s = earnings_split((float)$d['revenue']); ?><tr><td><b><?= e($d['name']) ?></b></td><td class="num"><?= (int)$d['trips'] ?></td><td class="num"><?= number_format($d['revenue']) ?></td><td class="num"><?= number_format($s['fee']) ?></td><td class="num"><b><?= number_format($s['net']) ?></b></td><td><?= $d['rating'] ? stars($d['rating']) . ' ' . round($d['rating'], 1) : '<span class="muted">No ratings</span>' ?></td><td class="num"><?= (int)$d['declines'] ?></td></tr><?php endforeach; ?></tbody></table></div></div>

<div class="grid g2">
  <div class="card"><h3>Most popular routes</h3><?php if (!$routes): ?><p class="muted">No completed trips in this period.</p><?php else: ?><div class="tbl-wrap"><table class="tbl"><thead><tr><th>Route</th><th class="num">Trips</th><th class="num">Revenue (KES)</th></tr></thead><tbody>
    <?php foreach ($routes as $r): ?><tr><td><?= e($r['a']) ?> to <?= e($r['b']) ?></td><td class="num"><?= (int)$r['n'] ?></td><td class="num"><?= number_format($r['g']) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div>
  <div class="card"><h3>Vehicle revenue</h3><div class="tbl-wrap"><table class="tbl"><thead><tr><th>Vehicle</th><th class="num">Trips</th><th class="num">Revenue (KES)</th></tr></thead><tbody>
    <?php foreach ($vehicles as $v): ?><tr><td><b><?= e($v['plate']) ?></b><span class="sub"><?= e($v['tname']) ?></span></td><td class="num"><?= (int)$v['trips'] ?></td><td class="num"><?= number_format($v['revenue']) ?></td></tr><?php endforeach; ?></tbody></table></div></div>
</div>
<p class="muted small mt">Report generated <?= date('d M Y, H:i') ?> by <?= e($u['name']) ?>. Commission is calculated at the rate set in Settings (default 15%).</p>
<?php page_end();