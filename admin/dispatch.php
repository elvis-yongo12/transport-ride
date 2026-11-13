<?php
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('admin');
$base = "SELECT b.*, pl.name pickup_name, dl.name drop_name, du.name driver_name FROM bookings b LEFT JOIN locations pl ON pl.id=b.pickup_location_id LEFT JOIN locations dl ON dl.id=b.dropoff_location_id LEFT JOIN users du ON du.id=b.driver_id WHERE ";
$cols = ['Waiting for a driver' => rows($base . "b.status='pending' ORDER BY b.scheduled_at"), 'Assigned' => rows($base . "b.status IN ('assigned','accepted') ORDER BY b.scheduled_at"),
         'On the road' => rows($base . "b.status IN ('on_the_way','in_progress') ORDER BY b.scheduled_at"), 'Done today' => rows($base . "b.status='completed' AND DATE(b.completed_at)=CURDATE() ORDER BY b.completed_at DESC LIMIT 10")];
$drv = rows("SELECT u.name, d.availability FROM users u JOIN drivers d ON d.user_id=u.id WHERE u.status='active' ORDER BY FIELD(d.availability,'available','busy','offline'), u.name");
page_start('Dispatch board', 'admin/dispatch.php', '<a class="btn" href="' . url('admin/booking_new.php') . '">' . icon('plus') . 'New booking</a>', 'See every trip by stage. Open a card to assign or change a driver.'); ?>
<div class="card mb flex"><b>Drivers</b><?php foreach ($drv as $x): ?><span class="badge <?= ['available' => 'b-ok', 'busy' => 'b-primary', 'offline' => 'b-muted'][$x['availability']] ?>"><?= e($x['name']) ?>: <?= $x['availability'] ?></span><?php endforeach; ?></div>
<div class="board"><?php foreach ($cols as $title => $list): ?><div class="colm"><h4><?= e($title) ?> <span class="badge b-muted"><?= count($list) ?></span></h4>
  <?php if (!$list) echo '<p class="muted small" style="padding:6px 4px">Nothing here.</p>'; foreach ($list as $b): ?>
  <a class="job" href="<?= url('admin/booking_view.php?id=' . $b['id']) ?>"><div class="flex"><b><?= e($b['ref']) ?></b><span class="grow"></span><?= badge($b['status']) ?></div><div><?= e(route_label($b)) ?></div><div class="muted small"><?= dt($b['scheduled_at'], 'd M, H:i') ?> | <?= money($b['total_fare']) ?></div><?php if ($b['driver_name']): ?><div class="small mt"><?= icon('car', 14) ?> <?= e($b['driver_name']) ?></div><?php endif; ?></a>
  <?php endforeach; ?></div><?php endforeach; ?></div>
<?php page_end();
