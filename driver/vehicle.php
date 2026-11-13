<?php
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('driver'); $d = row('SELECT * FROM drivers WHERE user_id=?', [$u['id']]);
$v = row('SELECT v.*, t.name tname, t.seats FROM vehicles v JOIN vehicle_types t ON t.id=v.vehicle_type_id WHERE v.driver_id=? LIMIT 1', [$u['id']]);
page_start('My vehicle', 'driver/vehicle.php', '', 'Your car and the documents that keep you on the road.'); ?>
<div class="grid g2">
  <div class="card"><h3>Vehicle</h3><?php if (!$v): ?><div class="empty"><b>No vehicle assigned</b>Ask the TaxiRide office to assign you a car.</div><?php else: ?>
    <dl class="kv"><dt>Plate</dt><dd><b><?= e($v['plate']) ?></b></dd><dt>Car</dt><dd><?= e($v['colour'] . ' ' . $v['make'] . ' ' . $v['model'] . ' ' . $v['year']) ?></dd><dt>Type</dt><dd><?= e($v['tname']) ?>, <?= $v['seats'] ?> seats</dd><dt>Status</dt><dd><?= e(ucfirst($v['status'])) ?></dd></dl><?php endif; ?></div>
  <div class="card"><h3>Documents</h3>
    <div class="docwarn"><span>Driving licence <span class="muted small"><?= e($d['licence_no']) ?></span></span><?= doc_badge($d['licence_expiry']) ?></div>
    <div class="docwarn"><span>PSV badge <span class="muted small"><?= e($d['psv_badge']) ?></span></span><?= doc_badge($d['psv_expiry']) ?></div>
    <?php if ($v): ?><div class="docwarn"><span>Vehicle insurance</span><?= doc_badge($v['insurance_expiry']) ?></div><div class="docwarn"><span>Vehicle inspection</span><?= doc_badge($v['inspection_expiry']) ?></div><?php endif; ?>
    <p class="help mt">If a document is about to expire, tell the office early. You cannot go online with an expired document.</p></div>
</div>
<?php page_end();
