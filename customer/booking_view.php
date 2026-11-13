<?php
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('customer'); $id = (int)($_GET['id'] ?? 0);
$b = booking($id);
if (!$b || (int)$b['customer_id'] !== (int)$u['id']) { flash('error', 'Booking not found.'); redirect('customer/bookings.php'); }
if (is_post()) {
    post_guard();
    if (inp('action') === 'cancel' && in_array($b['status'], ['pending', 'assigned', 'accepted'], true)) {
        $why = inp('reason') ?: 'No reason given';
        q('UPDATE bookings SET cancel_reason=? WHERE id=?', [$why, $id]); set_status($b, 'cancelled', 'Cancelled by passenger: ' . $why, (int)$u['id']);
        audit('booking.cancel', $b['ref']); flash('success', 'Booking cancelled.');
    } elseif (inp('action') === 'rate' && $b['status'] === 'completed' && $b['driver_id'] && !val('SELECT id FROM ratings WHERE booking_id=?', [$id])) {
        $stars = max(1, min(5, (int)inp('stars')));
        q('INSERT INTO ratings (booking_id, customer_id, driver_id, stars, comment) VALUES (?,?,?,?,?)', [$id, $u['id'], $b['driver_id'], $stars, inp('comment')]);
        notify((int)$b['driver_id'], 'New rating', $u['name'] . ' rated your trip ' . $stars . ' out of 5.', 'driver/ratings.php'); flash('success', 'Thanks for rating your trip.');
    }
    redirect('customer/booking_view.php?id=' . $id);
}
$rated = row('SELECT * FROM ratings WHERE booking_id=?', [$id]);
$acts = $b['status'] === 'completed' ? '<a class="btn line" href="' . url('customer/receipt.php?id=' . $id) . '">' . icon('file') . 'Receipt</a>' : '';
page_start('Booking ' . $b['ref'], 'customer/bookings.php', $acts, route_label($b)); ?>
<div class="grid g-main">
  <div class="grid" style="align-content:start">
    <div class="card"><div class="head"><h3>Trip details</h3><?= badge($b['status']) ?></div><?= progress_bar($b['status']) ?>
      <dl class="kv mt"><dt>Trip type</dt><dd><?= e($b['trip_type']) ?></dd><dt>Pickup</dt><dd><?= e($b['pickup_name']) ?><?= $b['pickup_detail'] ? '<br><span class="muted">' . e($b['pickup_detail']) . '</span>' : '' ?></dd>
      <?php if ($b['drop_name']): ?><dt>Drop-off</dt><dd><?= e($b['drop_name']) ?><?= $b['dropoff_detail'] ? '<br><span class="muted">' . e($b['dropoff_detail']) . '</span>' : '' ?></dd><?php else: ?><dt>Hire</dt><dd><?= (int)$b['hours'] ?> hours</dd><?php endif; ?>
      <dt>Pickup time</dt><dd><?= dt($b['scheduled_at']) ?></dd><dt>Passengers</dt><dd><?= (int)$b['passengers'] ?> in a <?= e($b['vtype']) ?></dd><?php if ($b['notes']): ?><dt>Notes</dt><dd><?= e($b['notes']) ?></dd><?php endif; ?>
      <?php if ($b['cancel_reason']): ?><dt>Cancelled because</dt><dd><?= e($b['cancel_reason']) ?></dd><?php endif; ?></dl></div>
    <div class="card"><h3>Status history</h3><?= timeline($id) ?></div>
  </div>
  <div class="grid" style="align-content:start">
    <div class="card"><h3>Your driver</h3>
    <?php if ($b['driver_id']): $r = driver_rating((int)$b['driver_id']); ?>
      <div class="flex"><span class="avatar"><?= e(initials($b['driver_name'])) ?></span><div><b><?= e($b['driver_name']) ?></b><br><?= $r['n'] ? stars($r['avg']) . ' <span class="muted small">' . $r['avg'] . ' (' . $r['n'] . ')</span>' : '<span class="muted small">New driver</span>' ?></div></div>
      <dl class="kv mt"><dt>Car</dt><dd><?= e($b['colour'] . ' ' . $b['make'] . ' ' . $b['model']) ?></dd><dt>Plate</dt><dd><b><?= e($b['plate']) ?></b></dd></dl>
      <?php if (!in_array($b['status'], ['completed', 'cancelled'], true)): ?><a class="btn lake block mt" href="tel:<?= e($b['driver_phone']) ?>"><?= icon('phone') ?>Call <?= e($b['driver_phone']) ?></a><?php endif; ?>
    <?php else: ?><p class="muted"><?= $b['status'] === 'cancelled' ? 'No driver was assigned.' : 'The office is finding you a driver. You will get a notification as soon as one is assigned.' ?></p><?php endif; ?></div>
    <div class="card"><h3>Fare</h3>
      <dl class="kv"><dt>Base fare</dt><dd><?= money($b['base_fare']) ?></dd><dt>Discount</dt><dd><?= $b['discount'] ? '- ' . money($b['discount']) : 'None' ?></dd><dt>Total</dt><dd><b style="font-size:20px"><?= money($b['total_fare']) ?></b></dd><dt>Pay by</dt><dd><?= $b['payment_method'] === 'mpesa' ? 'M-Pesa' : 'Cash' ?> <?= $b['status'] === 'completed' ? pay_badge($b['payment_status']) : '' ?></dd></dl></div>
    <?php if (in_array($b['status'], ['pending', 'assigned', 'accepted'], true)): ?><form class="card" method="post" data-confirm="Cancel this booking?"><h3>Need to cancel?</h3><?= csrf_field() ?><input type="hidden" name="action" value="cancel"><div class="field"><label for="reason">Reason</label><input id="reason" name="reason" placeholder="Plans changed"></div><button class="btn bad">Cancel booking</button></form><?php endif; ?>
    <?php if ($b['status'] === 'completed' && $b['driver_id']): ?>
      <?php if ($rated): ?><div class="card"><h3>Your rating</h3><?= stars($rated['stars']) ?><p class="muted mt"><?= e($rated['comment']) ?></p></div>
      <?php else: ?><form class="card" method="post"><h3>How was your ride?</h3><?= csrf_field() ?><input type="hidden" name="action" value="rate">
        <div class="choice mb"><?php foreach ([5 => 'Excellent', 4 => 'Good', 3 => 'Fair', 2 => 'Poor', 1 => 'Bad'] as $n => $l): ?><label><input type="radio" name="stars" value="<?= $n ?>" <?= $n === 5 ? 'checked' : '' ?>><b><?= $n ?> stars</b><small><?= $l ?></small></label><?php endforeach; ?></div>
        <div class="field"><label for="comment">Comment (optional)</label><input id="comment" name="comment" maxlength="250"></div><button class="btn">Submit rating</button></form><?php endif; ?>
    <?php endif; ?>
    <a class="btn line" href="<?= url('support.php') ?>"><?= icon('life') ?>Report a problem with this trip</a>
  </div>
</div>
<?php page_end();
