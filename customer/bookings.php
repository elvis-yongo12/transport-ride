<?php
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('customer');
$st = inp('status'); $s = inp('s'); $per = 10; $w = 'b.customer_id=?'; $p = [$u['id']];
if (isset(STATUS[$st])) { $w .= ' AND b.status=?'; $p[] = $st; }
if ($s !== '') { $w .= ' AND (b.ref LIKE ? OR pl.name LIKE ? OR dl.name LIKE ?)'; array_push($p, "%$s%", "%$s%", "%$s%"); }
$from = ' FROM bookings b LEFT JOIN locations pl ON pl.id=b.pickup_location_id LEFT JOIN locations dl ON dl.id=b.dropoff_location_id WHERE ' . $w;
$total = (int)val('SELECT COUNT(*)' . $from, $p); $off = (min(page_no(), max(1, (int)ceil($total / $per))) - 1) * $per;
$list = rows('SELECT b.*, pl.name pickup_name, dl.name drop_name' . $from . " ORDER BY b.id DESC LIMIT $per OFFSET $off", $p);
page_start('My bookings', 'customer/bookings.php', '<a class="btn" href="' . url('customer/book.php') . '">' . icon('plus') . 'Book a ride</a>', 'Every ride you have requested, newest first.'); ?>
<form class="toolbar" method="get"><input name="s" placeholder="Search reference or place" value="<?= e($s) ?>"><select name="status"><option value="">All statuses</option><?php foreach (STATUS as $k => [$l]): ?><option value="<?= $k ?>" <?= $st === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select><button class="btn dark">Filter</button></form>
<div class="card">
<?php if (!$list): ?><div class="empty"><b>No bookings found</b>Try a different filter, or book a new ride.</div><?php else: ?>
<div class="tbl-wrap"><table class="tbl"><thead><tr><th>Reference</th><th>Route</th><th>Pickup time</th><th>Status</th><th>Payment</th><th class="num">Fare (KES)</th></tr></thead><tbody>
<?php foreach ($list as $b): ?><tr><td><a href="<?= url('customer/booking_view.php?id=' . $b['id']) ?>"><b><?= e($b['ref']) ?></b></a><span class="sub"><?= e($b['trip_type']) ?></span></td><td><?= e(route_label($b)) ?></td><td class="nowrap"><?= dt($b['scheduled_at']) ?></td><td><?= badge($b['status']) ?></td><td><?= $b['status'] === 'completed' ? pay_badge($b['payment_status']) : '<span class="muted">-</span>' ?></td><td class="num"><?= number_format($b['total_fare']) ?></td></tr><?php endforeach; ?></tbody></table></div><?= pager($total, $per) ?><?php endif; ?></div>
<?php page_end();
