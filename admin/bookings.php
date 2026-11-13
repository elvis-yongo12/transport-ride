<?php
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('admin');
$st = inp('status'); $src = inp('source'); $s = inp('s'); $from = inp('from'); $to = inp('to'); $cust = (int)inp('customer'); $per = 12;
$w = ['1=1']; $p = [];
if (isset(STATUS[$st])) { $w[] = 'b.status=?'; $p[] = $st; }
if (in_array($src, ['online', 'phone', 'whatsapp', 'stage'], true)) { $w[] = 'b.source=?'; $p[] = $src; }
if ($cust) { $w[] = 'b.customer_id=?'; $p[] = $cust; }
if ($from && strtotime($from)) { $w[] = 'DATE(b.scheduled_at)>=?'; $p[] = $from; }
if ($to && strtotime($to)) { $w[] = 'DATE(b.scheduled_at)<=?'; $p[] = $to; }
if ($s !== '') { $w[] = '(b.ref LIKE ? OR b.customer_name LIKE ? OR b.customer_phone LIKE ? OR du.name LIKE ?)'; array_push($p, "%$s%", "%$s%", "%$s%", "%$s%"); }
$from_sql = ' FROM bookings b LEFT JOIN locations pl ON pl.id=b.pickup_location_id LEFT JOIN locations dl ON dl.id=b.dropoff_location_id LEFT JOIN users du ON du.id=b.driver_id WHERE ' . implode(' AND ', $w);
if (inp('export')) { $x = rows('SELECT b.*, pl.name pickup_name, dl.name drop_name, du.name driver_name' . $from_sql . ' ORDER BY b.id DESC', $p);
  csv_out('bookings', ['Reference', 'Customer', 'Phone', 'Route', 'Pickup time', 'Type', 'Status', 'Driver', 'Source', 'Fare', 'Payment'], array_map(fn($b) => [$b['ref'], $b['customer_name'], $b['customer_phone'], route_label($b), $b['scheduled_at'], $b['trip_type'], $b['status'], $b['driver_name'], $b['source'], $b['total_fare'], $b['payment_status']], $x)); }
$total = (int)val('SELECT COUNT(*)' . $from_sql, $p); $off = (min(page_no(), max(1, (int)ceil($total / $per))) - 1) * $per;
$list = rows('SELECT b.*, pl.name pickup_name, dl.name drop_name, du.name driver_name' . $from_sql . " ORDER BY b.id DESC LIMIT $per OFFSET $off", $p);
$qs = http_build_query(array_merge($_GET, ['export' => 1]));
page_start('All bookings', 'admin/bookings.php', '<a class="btn line" href="?' . e($qs) . '">Download CSV</a><a class="btn" href="' . url('admin/booking_new.php') . '">' . icon('plus') . 'New booking</a>', $total . ' bookings match your filters.'); ?>
<form class="toolbar" method="get"><input name="s" placeholder="Search reference, name, phone, driver" value="<?= e($s) ?>" style="min-width:260px">
  <select name="status"><option value="">Any status</option><?php foreach (STATUS as $k => [$l]): ?><option value="<?= $k ?>" <?= $st === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
  <select name="source"><option value="">Any source</option><?php foreach (['online', 'phone', 'whatsapp', 'stage'] as $x): ?><option <?= $src === $x ? 'selected' : '' ?>><?= $x ?></option><?php endforeach; ?></select>
  <input type="date" name="from" value="<?= e($from) ?>" aria-label="From date"><input type="date" name="to" value="<?= e($to) ?>" aria-label="To date"><button class="btn dark">Filter</button><a class="btn line" href="?">Clear</a></form>
<div class="card"><?php if (!$list): ?><div class="empty"><b>No bookings match</b>Try clearing some filters.</div><?php else: ?>
<div class="tbl-wrap"><table class="tbl"><thead><tr><th>Reference</th><th>Customer</th><th>Route</th><th>Pickup time</th><th>Driver</th><th>Status</th><th>Payment</th><th class="num">Fare</th></tr></thead><tbody>
<?php foreach ($list as $b): ?><tr><td><a href="<?= url('admin/booking_view.php?id=' . $b['id']) ?>"><b><?= e($b['ref']) ?></b></a><span class="sub"><?= e($b['source']) ?> | <?= e($b['trip_type']) ?></span></td><td><?= e($b['customer_name']) ?><span class="sub"><?= e($b['customer_phone']) ?></span></td><td><?= e(route_label($b)) ?></td><td class="nowrap"><?= dt($b['scheduled_at'], 'd M, H:i') ?></td><td><?= $b['driver_name'] ? e($b['driver_name']) : '<span class="muted">Not assigned</span>' ?></td><td><?= badge($b['status']) ?></td><td><?= $b['status'] === 'completed' ? pay_badge($b['payment_status']) : '-' ?></td><td class="num"><?= number_format($b['total_fare']) ?></td></tr><?php endforeach; ?></tbody></table></div><?= pager($total, $per) ?><?php endif; ?></div>
<?php page_end();
