<?php
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('customer', 'admin'); $b = booking((int)($_GET['id'] ?? 0));
if (!$b || ($u['role'] === 'customer' && (int)$b['customer_id'] !== (int)$u['id']) || $b['status'] !== 'completed') { flash('error', 'Receipt not available.'); redirect(home_for($u['role'])); }
$p = row('SELECT * FROM payments WHERE booking_id=? ORDER BY id DESC LIMIT 1', [$b['id']]);
page_start('Receipt', $u['role'] === 'admin' ? 'admin/bookings.php' : 'customer/bookings.php', '<button class="btn dark" data-print>Print receipt</button>'); ?>
<div class="receipt"><h2><?= APP_NAME ?></h2><p class="center muted small" style="text-align:center"><?= APP_TOWN ?>, Kenya | <?= APP_PHONE ?></p><hr>
  <div class="ln"><span>Receipt for</span><b><?= e($b['ref']) ?></b></div><div class="ln"><span>Date</span><span><?= dt($b['completed_at']) ?></span></div><div class="ln"><span>Passenger</span><span><?= e($b['customer_name']) ?></span></div>
  <div class="ln"><span>Route</span><span><?= e(route_label($b)) ?></span></div><div class="ln"><span>Driver</span><span><?= e($b['driver_name']) ?> (<?= e($b['plate']) ?>)</span></div><hr>
  <div class="ln"><span>Base fare</span><span><?= number_format($b['base_fare']) ?></span></div><div class="ln"><span>Discount</span><span>- <?= number_format($b['discount']) ?></span></div>
  <div class="ln tot"><span>Total paid (KES)</span><span><?= number_format($b['total_fare']) ?></span></div><hr>
  <div class="ln"><span>Payment</span><span><?= $p ? ($p['method'] === 'mpesa' ? 'M-Pesa ' . e($p['mpesa_code']) : 'Cash') : '-' ?></span></div><p style="text-align:center" class="muted small mt">Thank you for riding with TaxiRide.</p></div>
<?php page_end();
