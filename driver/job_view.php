<?php
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('driver'); $id = (int)($_GET['id'] ?? 0); $b = booking($id);
if (!$b || (int)$b['driver_id'] !== (int)$u['id']) { flash('error', 'Trip not found.'); redirect('driver/jobs.php'); }
if (is_post()) {
    post_guard(); $act = inp('action'); $by = (int)$u['id'];
    if ($act === 'accept' && $b['status'] === 'assigned') { set_status($b, 'accepted', 'Driver accepted', $by); flash('success', 'Trip accepted.'); }
    elseif ($act === 'decline' && $b['status'] === 'assigned') {
        $why = inp('reason') ?: 'No reason given';
        q("UPDATE bookings SET driver_id=NULL, vehicle_id=NULL, status='pending' WHERE id=?", [$id]); log_status($id, 'pending', 'Declined by ' . $u['name'] . ': ' . $why, $by);
        notify_role('admin', 'Trip declined', $b['ref'] . ' declined by ' . $u['name'] . ': ' . $why, 'admin/booking_view.php?id=' . $id); flash('info', 'Trip declined. The office will reassign it.'); redirect('driver/jobs.php');
    } elseif ($act === 'start_pickup' && $b['status'] === 'accepted') { set_status($b, 'on_the_way', 'Heading to pickup point', $by); flash('success', 'The passenger has been told you are on the way.'); }
    elseif ($act === 'start_trip' && $b['status'] === 'on_the_way') { set_status($b, 'in_progress', 'Trip started', $by); flash('success', 'Trip started. Drive safely.'); }
    elseif ($act === 'complete' && $b['status'] === 'in_progress') {
        $m = inp('method') === 'mpesa' ? 'mpesa' : 'cash'; $amt = (int)inp('amount'); $code = strtoupper(inp('code'));
        if ($amt < 1) flash('error', 'Enter the amount you collected.');
        elseif ($m === 'mpesa' && !preg_match('/^[A-Z0-9]{10}$/', $code)) flash('error', 'Enter the 10-character M-Pesa code, for example SFH3K9X2LM.');
        else {
            set_status($b, 'completed', 'Trip completed. Collected ' . money($amt) . ' by ' . $m, $by);
            q('INSERT INTO payments (booking_id, amount, method, mpesa_code, recorded_by) VALUES (?,?,?,?,?)', [$id, $amt, $m, $m === 'mpesa' ? $code : '', $by]);
            q("UPDATE bookings SET payment_status='paid', payment_method=? WHERE id=?", [$m, $id]);
            notify_role('admin', 'Payment recorded', $b['ref'] . ': ' . money($amt) . ' by ' . $m, 'admin/payments.php'); flash('success', 'Trip completed and payment recorded. Well done!');
        }
    }
    redirect('driver/job_view.php?id=' . $id);
}
$act = [];
$next = ['accepted' => ['start_pickup', 'I am on my way to the pickup'], 'on_the_way' => ['start_trip', 'Passenger on board: start trip']][$b['status']] ?? null;
page_start('Trip ' . $b['ref'], 'driver/jobs.php', '<a class="btn line" href="' . url('driver/jobs.php') . '">Back to trips</a>', route_label($b)); ?>
<div class="grid g-main">
  <div class="grid" style="align-content:start">
    <div class="card"><div class="head"><h3>Trip details</h3><?= badge($b['status']) ?></div><?= progress_bar($b['status']) ?>
      <dl class="kv mt"><dt>Pickup</dt><dd><?= e($b['pickup_name']) ?><?= $b['pickup_detail'] ? '<br><span class="muted">' . e($b['pickup_detail']) . '</span>' : '' ?></dd>
      <?php if ($b['drop_name']): ?><dt>Drop-off</dt><dd><?= e($b['drop_name']) ?><?= $b['dropoff_detail'] ? '<br><span class="muted">' . e($b['dropoff_detail']) . '</span>' : '' ?></dd><?php else: ?><dt>Hire</dt><dd><?= (int)$b['hours'] ?> hours</dd><?php endif; ?>
      <dt>Pickup time</dt><dd><?= dt($b['scheduled_at']) ?></dd><dt>Passengers</dt><dd><?= (int)$b['passengers'] ?></dd><?php if ($b['notes']): ?><dt>Notes</dt><dd><?= e($b['notes']) ?></dd><?php endif; ?>
      <dt>Passenger</dt><dd><?= e($b['customer_name']) ?> <a class="btn sm lake" href="tel:<?= e($b['customer_phone']) ?>"><?= icon('phone', 15) ?>Call <?= e($b['customer_phone']) ?></a></dd>
      <dt>Fare</dt><dd><b><?= money($b['total_fare']) ?></b> by <?= $b['payment_method'] === 'mpesa' ? 'M-Pesa' : 'cash' ?></dd></dl></div>
    <div class="card"><h3>Status history</h3><?= timeline($id) ?></div>
  </div>
  <div class="grid" style="align-content:start">
    <?php if ($b['status'] === 'assigned'): ?>
      <form class="card" method="post"><h3>Accept this trip?</h3><?= csrf_field() ?><input type="hidden" name="action" value="accept"><p class="muted mb">The passenger is waiting to hear from you.</p><button class="btn block">Accept trip</button></form>
      <form class="card" method="post" data-confirm="Decline this trip?"><h3>Cannot take it?</h3><?= csrf_field() ?><input type="hidden" name="action" value="decline"><div class="field"><label for="reason">Reason</label><input id="reason" name="reason" placeholder="Vehicle problem, too far, etc."></div><button class="btn bad block">Decline trip</button></form>
    <?php elseif ($next): ?>
      <form class="card" method="post"><h3>Next step</h3><?= csrf_field() ?><input type="hidden" name="action" value="<?= $next[0] ?>"><button class="btn block" style="padding:14px"><?= e($next[1]) ?></button></form>
    <?php elseif ($b['status'] === 'in_progress'): ?>
      <form class="card" method="post"><h3>Complete trip</h3><?= csrf_field() ?><input type="hidden" name="action" value="complete">
        <div class="field"><label for="amount">Amount collected (KES)</label><input id="amount" type="number" name="amount" min="1" required value="<?= (int)$b['total_fare'] ?>"></div>
        <div class="field"><label>Payment method</label><div class="choice"><label><input type="radio" name="method" value="cash" <?= $b['payment_method'] !== 'mpesa' ? 'checked' : '' ?>><b>Cash</b></label><label><input type="radio" name="method" value="mpesa" <?= $b['payment_method'] === 'mpesa' ? 'checked' : '' ?>><b>M-Pesa</b></label></div></div>
        <div class="field"><label for="code">M-Pesa code (only for M-Pesa)</label><input id="code" name="code" maxlength="10" placeholder="SFH3K9X2LM" style="text-transform:uppercase"></div><button class="btn block">Complete and record payment</button></form>
    <?php else: ?><div class="card"><h3>Trip closed</h3><p class="muted"><?= $b['status'] === 'completed' ? 'Payment status: ' . pay_badge($b['payment_status']) : 'This trip was cancelled.' ?></p></div><?php endif; ?>
  </div>
</div>
<?php page_end();
