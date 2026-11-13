<?php
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('admin'); $id = (int)($_GET['id'] ?? 0); $b = booking($id);
if (!$b) { flash('error', 'Booking not found.'); redirect('admin/bookings.php'); }
$me = (int)$u['id'];
if (is_post()) {
    post_guard(); $act = inp('action');
    if ($act === 'assign') { $err = assign_booking($b, (int)inp('driver'), (int)inp('vehicle'), $me); $err ? flash('error', $err) : flash('success', 'Driver assigned and notified.'); }
    elseif ($act === 'unassign' && in_array($b['status'], ['assigned', 'accepted'], true)) {
        if ($b['driver_id']) q("UPDATE drivers SET availability='available' WHERE user_id=? AND availability='busy'", [$b['driver_id']]);
        q("UPDATE bookings SET driver_id=NULL, vehicle_id=NULL, status='pending' WHERE id=?", [$id]); log_status($id, 'pending', 'Driver removed by admin', $me); audit('booking.unassign', $b['ref']); flash('success', 'Driver removed. Booking is pending again.');
    } elseif ($act === 'cancel' && !in_array($b['status'], ['completed', 'cancelled'], true)) {
        $why = inp('reason') ?: 'Cancelled by office'; q('UPDATE bookings SET cancel_reason=? WHERE id=?', [$why, $id]); set_status($b, 'cancelled', $why, $me); audit('booking.cancel', $b['ref']); flash('success', 'Booking cancelled.');
    } elseif ($act === 'complete' && in_array($b['status'], ['accepted', 'on_the_way', 'in_progress'], true)) { set_status($b, 'completed', 'Marked completed by office', $me); audit('booking.complete', $b['ref']); flash('success', 'Booking marked completed. Record the payment below.');
    } elseif ($act === 'pay' && $b['status'] === 'completed') {
        $m = inp('method') === 'mpesa' ? 'mpesa' : 'cash'; $code = strtoupper(inp('code'));
        if ($m === 'mpesa' && !preg_match('/^[A-Z0-9]{10}$/', $code)) flash('error', 'Enter the 10-character M-Pesa code.');
        else { q('INSERT INTO payments (booking_id, amount, method, mpesa_code, recorded_by) VALUES (?,?,?,?,?)', [$id, (int)inp('amount'), $m, $m === 'mpesa' ? $code : '', $me]); q("UPDATE bookings SET payment_status='paid', payment_method=? WHERE id=?", [$m, $id]); audit('payment.record', $b['ref']); flash('success', 'Payment recorded.'); }
    }
    redirect('admin/booking_view.php?id=' . $id);
}
$drivers = rows("SELECT u.id, u.name, d.availability, v.id vid, v.plate FROM users u JOIN drivers d ON d.user_id=u.id LEFT JOIN vehicles v ON v.driver_id=u.id AND v.status='active' WHERE u.status='active' AND u.role='driver' ORDER BY FIELD(d.availability,'available','busy','offline'), u.name");
$pays = rows('SELECT p.*, u.name rec FROM payments p LEFT JOIN users u ON u.id=p.recorded_by WHERE booking_id=? ORDER BY p.id', [$id]);
$open = !in_array($b['status'], ['completed', 'cancelled'], true);
page_start('Booking ' . $b['ref'], 'admin/bookings.php', $b['status'] === 'completed' ? '<a class="btn line" href="' . url('customer/receipt.php?id=' . $id) . '">' . icon('file') . 'Receipt</a>' : '', route_label($b)); ?>
<div class="grid g-main">
  <div class="grid" style="align-content:start">
    <div class="card"><div class="head"><h3>Trip details</h3><?= badge($b['status']) ?></div><?= progress_bar($b['status']) ?>
      <dl class="kv mt"><dt>Passenger</dt><dd><?= e($b['customer_name']) ?> <a href="tel:<?= e($b['customer_phone']) ?>"><?= e($b['customer_phone']) ?></a> <?= $b['customer_id'] ? '' : '<span class="badge b-muted">Walk-in</span>' ?></dd>
      <dt>Source</dt><dd><?= e(ucfirst($b['source'])) ?></dd><dt>Trip type</dt><dd><?= e($b['trip_type']) ?></dd><dt>Pickup</dt><dd><?= e($b['pickup_name']) ?><?= $b['pickup_detail'] ? ', ' . e($b['pickup_detail']) : '' ?></dd>
      <?php if ($b['drop_name']): ?><dt>Drop-off</dt><dd><?= e($b['drop_name']) ?><?= $b['dropoff_detail'] ? ', ' . e($b['dropoff_detail']) : '' ?></dd><?php else: ?><dt>Hire</dt><dd><?= (int)$b['hours'] ?> hours</dd><?php endif; ?>
      <dt>Pickup time</dt><dd><?= dt($b['scheduled_at']) ?></dd><dt>Passengers</dt><dd><?= (int)$b['passengers'] ?> in a <?= e($b['vtype']) ?></dd><?php if ($b['notes']): ?><dt>Notes</dt><dd><?= e($b['notes']) ?></dd><?php endif; ?>
      <dt>Fare</dt><dd><b><?= money($b['total_fare']) ?></b> <span class="muted">(base <?= number_format($b['base_fare']) ?>, discount <?= number_format($b['discount']) ?>)</span></dd><dt>Payment</dt><dd><?= $b['payment_method'] === 'mpesa' ? 'M-Pesa' : 'Cash' ?> <?= pay_badge($b['payment_status']) ?></dd>
      <?php if ($b['cancel_reason']): ?><dt>Cancelled because</dt><dd><?= e($b['cancel_reason']) ?></dd><?php endif; ?></dl></div>
    <div class="card"><h3>Status history</h3><?= timeline($id) ?></div>
    <?php if ($pays): ?><div class="card"><h3>Payments</h3><div class="tbl-wrap"><table class="tbl"><thead><tr><th>Recorded</th><th>Method</th><th>Code</th><th>Status</th><th class="num">Amount</th></tr></thead><tbody><?php foreach ($pays as $p): ?><tr><td><?= dt($p['recorded_at']) ?><span class="sub">by <?= e($p['rec']) ?></span></td><td><?= $p['method'] === 'mpesa' ? 'M-Pesa' : 'Cash' ?></td><td><?= e($p['mpesa_code']) ?></td><td><?= e(ucfirst($p['status'])) ?></td><td class="num"><?= number_format($p['amount']) ?></td></tr><?php endforeach; ?></tbody></table></div></div><?php endif; ?>
  </div>
  <div class="grid" style="align-content:start">
    <?php if (in_array($b['status'], ['pending', 'assigned', 'accepted'], true)): ?>
    <form class="card" method="post"><h3><?= $b['driver_id'] ? 'Change driver' : 'Assign a driver' ?></h3><?= csrf_field() ?><input type="hidden" name="action" value="assign">
      <div class="field"><label for="driver">Driver</label><select id="driver" name="driver" required onchange="var o=this.options[this.selectedIndex];this.form.vehicle.value=o.getAttribute('data-v')||''"><option value="">Choose a driver</option><?php foreach ($drivers as $d): ?><option value="<?= $d['id'] ?>" data-v="<?= $d['vid'] ?>" <?= (int)$b['driver_id'] === (int)$d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?> (<?= $d['availability'] ?>)<?= $d['plate'] ? ' ' . e($d['plate']) : '' ?></option><?php endforeach; ?></select></div>
      <div class="field"><label for="vehicle">Vehicle</label><select id="vehicle" name="vehicle" required><option value="">Choose a vehicle</option><?php foreach (rows("SELECT v.id, v.plate, t.name FROM vehicles v JOIN vehicle_types t ON t.id=v.vehicle_type_id WHERE v.status='active' ORDER BY (v.vehicle_type_id=" . (int)$b['vehicle_type_id'] . ") DESC, v.plate") as $v): ?><option value="<?= $v['id'] ?>" <?= (int)$b['vehicle_id'] === (int)$v['id'] ? 'selected' : '' ?>><?= e($v['plate']) ?> (<?= e($v['name']) ?>)</option><?php endforeach; ?></select><div class="help">Customer asked for a <?= e($b['vtype']) ?>. Expired documents block assignment.</div></div>
      <button class="btn block">Assign and notify driver</button></form>
    <?php endif; ?>
    <?php if ($b['driver_id']): ?><div class="card"><h3>Driver and vehicle</h3><div class="flex"><span class="avatar"><?= e(initials($b['driver_name'])) ?></span><div><b><?= e($b['driver_name']) ?></b><br><a href="tel:<?= e($b['driver_phone']) ?>"><?= e($b['driver_phone']) ?></a></div></div><dl class="kv mt"><dt>Vehicle</dt><dd><?= e($b['plate'] . ', ' . $b['colour'] . ' ' . $b['make'] . ' ' . $b['model']) ?></dd></dl>
      <?php if (in_array($b['status'], ['assigned', 'accepted'], true)): ?><form method="post" class="mt" data-confirm="Remove this driver?"><?= csrf_field() ?><input type="hidden" name="action" value="unassign"><button class="btn sm bad">Remove driver</button></form><?php endif; ?></div><?php endif; ?>
    <?php if ($b['status'] === 'completed' && $b['payment_status'] === 'unpaid'): ?>
    <form class="card" method="post"><h3>Record payment</h3><?= csrf_field() ?><input type="hidden" name="action" value="pay"><div class="field"><label for="amount">Amount (KES)</label><input id="amount" type="number" name="amount" value="<?= (int)$b['total_fare'] ?>" min="1" required></div>
      <div class="field"><label>Method</label><div class="choice"><label><input type="radio" name="method" value="cash" checked><b>Cash</b></label><label><input type="radio" name="method" value="mpesa"><b>M-Pesa</b></label></div></div><div class="field"><label for="code">M-Pesa code</label><input id="code" name="code" maxlength="10"></div><button class="btn block">Save payment</button></form><?php endif; ?>
    <?php if ($open): ?><div class="card"><h3>Other actions</h3>
      <?php if (in_array($b['status'], ['accepted', 'on_the_way', 'in_progress'], true)): ?><form method="post" class="mb" data-confirm="Mark this trip as completed?"><?= csrf_field() ?><input type="hidden" name="action" value="complete"><button class="btn line block">Mark trip completed</button></form><?php endif; ?>
      <form method="post" data-confirm="Cancel this booking?"><?= csrf_field() ?><input type="hidden" name="action" value="cancel"><div class="field"><label for="reason">Cancel reason</label><input id="reason" name="reason"></div><button class="btn bad block">Cancel booking</button></form></div><?php endif; ?>
  </div>
</div>
<?php page_end();
