<?php
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('driver'); $r = driver_rating((int)$u['id']);
$dist = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0]; foreach (rows('SELECT stars, COUNT(*) n FROM ratings WHERE driver_id=? GROUP BY stars', [$u['id']]) as $x) $dist[$x['stars']] = (int)$x['n'];
$list = rows('SELECT r.*, c.name, b.ref FROM ratings r JOIN users c ON c.id=r.customer_id JOIN bookings b ON b.id=r.booking_id WHERE r.driver_id=? ORDER BY r.id DESC LIMIT 20', [$u['id']]);
page_start('My ratings', 'driver/ratings.php', '', 'What passengers say about your driving.'); ?>
<div class="grid g-side">
  <div class="card"><div class="stat" style="box-shadow:none;border:0;padding:0"><div><div class="v" style="font-size:64px"><?= $r['n'] ? $r['avg'] : '-' ?></div><?= $r['n'] ? stars($r['avg']) : '' ?><div class="l"><?= $r['n'] ?> ratings</div></div></div>
    <div class="mt"><?php $lab = []; foreach ($dist as $k => $v) $lab[$k . ' stars'] = $v; echo hbars($lab); ?></div></div>
  <div class="card"><h3>Latest comments</h3><?php if (!$list): ?><div class="empty"><b>No ratings yet</b>Passengers can rate you after each completed trip.</div><?php endif; ?>
    <?php foreach ($list as $x): ?><div class="docwarn"><span><?= stars($x['stars']) ?> <b><?= e($x['name']) ?></b><br><span class="muted"><?= $x['comment'] ? e($x['comment']) : 'No comment' ?></span></span><span class="muted small nowrap"><?= dt($x['created_at'], 'd M') ?></span></div><?php endforeach; ?></div>
</div>
<?php page_end();
