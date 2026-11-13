<?php
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('driver');
$to = inp('to') ?: date('Y-m-d'); $from = inp('from') ?: date('Y-m-d', strtotime('-29 days'));
if (!strtotime($from) || !strtotime($to) || $from > $to) { $from = date('Y-m-d', strtotime('-29 days')); $to = date('Y-m-d'); }
$trips = rows("SELECT b.*, pl.name pickup_name, dl.name drop_name FROM bookings b LEFT JOIN locations pl ON pl.id=b.pickup_location_id LEFT JOIN locations dl ON dl.id=b.dropoff_location_id WHERE b.driver_id=? AND b.status='completed' AND DATE(b.completed_at) BETWEEN ? AND ? ORDER BY b.completed_at DESC", [$u['id'], $from, $to]);
$gross = array_sum(array_column($trips, 'total_fare')); $sp = earnings_split((float)$gross);
$cash = 0; $mp = 0; foreach ($trips as $t) { if ($t['payment_method'] === 'mpesa') $mp += $t['total_fare']; else $cash += $t['total_fare']; }
if (inp('export')) csv_out('earnings-' . $from . '-to-' . $to, ['Reference', 'Completed', 'Route', 'Method', 'Fare', 'Commission', 'Net'], array_map(fn($t) => [$t['ref'], $t['completed_at'], route_label($t), $t['payment_method'], $t['total_fare'], earnings_split((float)$t['total_fare'])['fee'], earnings_split((float)$t['total_fare'])['net']], $trips));
$days = []; $a = strtotime($from); $z = strtotime($to);
if (($z - $a) / 86400 <= 31) for ($t = $a; $t <= $z; $t += 86400) $days[date('d M', $t)] = 0;
foreach ($trips as $t) { $k = date('d M', strtotime($t['completed_at'])); if (isset($days[$k])) $days[$k] += round($t['total_fare'] * (1 - $sp['pct'] / 100)); }
page_start('Earnings', 'driver/earnings.php', '<a class="btn line" href="?from=' . e($from) . '&to=' . e($to) . '&export=1">Download CSV</a>', 'Money earned from completed trips. TaxiRide keeps ' . $sp['pct'] . '% commission.'); ?>
<form class="toolbar" method="get"><label for="from" class="small" style="margin:0">From</label><input id="from" type="date" name="from" value="<?= e($from) ?>"><label for="to" class="small" style="margin:0">to</label><input id="to" type="date" name="to" value="<?= e($to) ?>"><button class="btn dark">Show</button></form>
<div class="grid g4 mb">
  <div class="stat"><span class="chip"><?= icon('car', 22) ?></span><div><div class="v"><?= count($trips) ?></div><div class="l">Completed trips</div></div></div>
  <div class="stat y"><span class="chip"><?= icon('wallet', 22) ?></span><div><div class="v"><?= number_format($gross) ?></div><div class="l">Fares collected (KES)</div></div></div>
  <div class="stat r"><span class="chip"><?= icon('tag', 22) ?></span><div><div class="v"><?= number_format($sp['fee']) ?></div><div class="l">Commission to TaxiRide</div></div></div>
  <div class="stat g"><span class="chip"><?= icon('check', 22) ?></span><div><div class="v"><?= number_format($sp['net']) ?></div><div class="l">Your earnings (KES)</div></div></div>
</div>
<div class="grid g-main">
  <div class="card"><h3>Trips in this period</h3><?php if (!$trips): ?><div class="empty"><b>No completed trips</b>Try a wider date range.</div><?php else: ?>
    <div class="tbl-wrap"><table class="tbl"><thead><tr><th>Completed</th><th>Route</th><th>Method</th><th class="num">Fare</th><th class="num">You earn</th></tr></thead><tbody>
    <?php foreach ($trips as $t): ?><tr><td class="nowrap"><?= dt($t['completed_at'], 'd M, H:i') ?><span class="sub"><?= e($t['ref']) ?></span></td><td><?= e(route_label($t)) ?></td><td><?= $t['payment_method'] === 'mpesa' ? 'M-Pesa' : 'Cash' ?></td><td class="num"><?= number_format($t['total_fare']) ?></td><td class="num"><b><?= number_format(earnings_split((float)$t['total_fare'])['net']) ?></b></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div>
  <div class="grid" style="align-content:start"><?php if ($days): ?><div class="card"><h3>Earnings per day</h3><?= col_chart(count($days) > 14 ? array_slice($days, -14, null, true) : $days, true) ?></div><?php endif; ?>
    <div class="card"><h3>How you were paid</h3><?= hbars(['Cash' => (int)$cash, 'M-Pesa' => (int)$mp], true) ?></div></div>
</div>
<?php page_end();
