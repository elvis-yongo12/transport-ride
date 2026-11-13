<?php
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('driver');
$tab = inp('tab', 'active'); $per = 10;
$w = ['active' => "b.status IN ('assigned','accepted','on_the_way','in_progress')", 'completed' => "b.status='completed'", 'cancelled' => "b.status='cancelled'", 'all' => '1=1'][$tab] ?? '1=1';
$from = " FROM bookings b LEFT JOIN locations pl ON pl.id=b.pickup_location_id LEFT JOIN locations dl ON dl.id=b.dropoff_location_id WHERE b.driver_id=? AND $w";
$total = (int)val('SELECT COUNT(*)' . $from, [$u['id']]); $off = (min(page_no(), max(1, (int)ceil($total / $per))) - 1) * $per;
$list = rows('SELECT b.*, pl.name pickup_name, dl.name drop_name' . $from . " ORDER BY b.scheduled_at DESC LIMIT $per OFFSET $off", [$u['id']]);
page_start('My trips', 'driver/jobs.php', '', 'Trips assigned to you, current and past.'); ?>
<div class="toolbar"><?php foreach (['active' => 'Active', 'completed' => 'Completed', 'cancelled' => 'Cancelled', 'all' => 'All'] as $k => $l): ?><a class="btn sm <?= $tab === $k ? 'dark' : 'line' ?>" href="?tab=<?= $k ?>"><?= $l ?></a><?php endforeach; ?></div>
<div class="card"><?php if (!$list): ?><div class="empty"><b>No trips here</b>Trips assigned to you will appear in this list.</div><?php else: ?>
<div class="tbl-wrap"><table class="tbl"><thead><tr><th>Reference</th><th>Route</th><th>Passenger</th><th>Pickup time</th><th>Status</th><th class="num">Fare (KES)</th></tr></thead><tbody>
<?php foreach ($list as $b): ?><tr><td><a href="<?= url('driver/job_view.php?id=' . $b['id']) ?>"><b><?= e($b['ref']) ?></b></a></td><td><?= e(route_label($b)) ?></td><td><?= e($b['customer_name']) ?></td><td class="nowrap"><?= dt($b['scheduled_at']) ?></td><td><?= badge($b['status']) ?></td><td class="num"><?= number_format($b['total_fare']) ?></td></tr><?php endforeach; ?></tbody></table></div><?= pager($total, $per) ?><?php endif; ?></div>
<?php page_end();
