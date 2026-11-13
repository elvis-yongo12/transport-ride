<?php
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('customer');
$vt = rows('SELECT * FROM vehicle_types ORDER BY multiplier');
$saved = rows('SELECT s.*, l.name FROM saved_places s JOIN locations l ON l.id=s.location_id WHERE user_id=?', [$u['id']]);
if (is_post()) {
    post_guard();
    $hourly = inp('mode') === 'hourly';
    [$id, $err] = create_booking([
        'customer_id' => $u['id'], 'name' => $u['name'], 'phone' => $u['phone'], 'pickup' => (int)inp('pickup'), 'drop' => (int)inp('drop'),
        'pickup_detail' => inp('pickup_detail'), 'drop_detail' => inp('drop_detail'), 'when' => inp('when'), 'passengers' => (int)inp('passengers', 1),
        'vtype' => (int)inp('vtype'), 'hours' => $hourly ? (int)inp('hours', 2) : 0, 'notes' => inp('notes'), 'promo' => inp('promo'), 'pay' => inp('pay') === 'mpesa' ? 'mpesa' : 'cash', 'source' => 'online',
    ], $u['id']);
    if ($err) flash('error', $err); else { audit('booking.create', '#' . $id); flash('success', 'Ride requested. We will assign a driver shortly.'); redirect('customer/booking_view.php?id=' . $id); }
}
$when = inp('when') ?: date('Y-m-d\TH:i', time() + 1200);
$mode = inp('mode', 'point'); $vsel = (int)inp('vtype', $vt[0]['id']); $pay = inp('pay', 'cash');
page_start('Book a ride', 'customer/book.php', '', 'Tell us where you are going. The meter shows your fare before you confirm.'); ?>
<form method="post" data-fare-form class="grid g-main" style="align-items:start"><?= csrf_field() ?>
  <div class="card">
    <div class="field"><label>Type of trip</label><div class="choice"><label><input type="radio" name="mode" value="point" <?= $mode !== 'hourly' ? 'checked' : '' ?>><b>Point to point</b><small>Pickup to a destination</small></label><label><input type="radio" name="mode" value="hourly" <?= $mode === 'hourly' ? 'checked' : '' ?>><b>Hourly hire</b><small>Keep the driver for hours</small></label></div></div>
    <?php if ($saved): ?><div class="field"><label for="savedPick">Use a saved place</label><select id="savedPick"><option value="">Choose...</option><?php foreach ($saved as $s): ?><option value="<?= $s['id'] ?>" data-loc="<?= $s['location_id'] ?>" data-detail="<?= e($s['detail']) ?>"><?= e($s['label']) ?>: <?= e($s['name']) ?></option><?php endforeach; ?></select></div><?php endif; ?>
    <div class="row"><div class="field"><label for="pickup">Pickup place</label><select id="pickup" name="pickup" required><?= loc_options(inp('pickup') ?: 1) ?></select></div>
      <div class="field"><label for="pickup_detail">Pickup details</label><input id="pickup_detail" name="pickup_detail" maxlength="160" placeholder="Gate, house or landmark" value="<?= e(inp('pickup_detail')) ?>"></div></div>
    <div class="row" data-point><div class="field"><label for="drop">Drop-off place</label><select id="drop" name="drop"><?= loc_options(inp('drop')) ?></select></div>
      <div class="field"><label for="drop_detail">Drop-off details</label><input id="drop_detail" name="drop_detail" maxlength="160" placeholder="Where exactly?" value="<?= e(inp('drop_detail')) ?>"></div></div>
    <div class="field" data-hourly style="display:none"><label for="hours">How many hours?</label><select id="hours" name="hours"><?php for ($h = (int)setting('min_hours', 2); $h <= 10; $h++): ?><option value="<?= $h ?>" <?= (int)inp('hours') === $h ? 'selected' : '' ?>><?= $h ?> hours</option><?php endfor; ?></select><div class="help">Charged at <?= money(setting('hourly_rate', 1200)) ?> per hour for a sedan.</div></div>
    <div class="row3"><div class="field"><label for="when">Pickup time</label><input id="when" type="datetime-local" name="when" required value="<?= e($when) ?>" min="<?= date('Y-m-d\TH:i') ?>"></div>
      <div class="field"><label for="passengers">Passengers</label><input id="passengers" type="number" name="passengers" min="1" max="8" value="<?= (int)inp('passengers', 1) ?>"></div>
      <div class="field"><label for="promo">Promo code</label><input id="promo" name="promo" placeholder="Optional" value="<?= e(inp('promo')) ?>"></div></div>
    <div class="field"><label>Vehicle</label><div class="choice"><?php foreach ($vt as $t): ?><label><input type="radio" name="vtype" value="<?= $t['id'] ?>" <?= $vsel === (int)$t['id'] ? 'checked' : '' ?>><b><?= e($t['name']) ?></b><small>Up to <?= $t['seats'] ?> passengers</small></label><?php endforeach; ?></div></div>
    <div class="field"><label>How will you pay?</label><div class="choice"><label><input type="radio" name="pay" value="cash" <?= $pay !== 'mpesa' ? 'checked' : '' ?>><b>Cash</b><small>Pay the driver</small></label><label><input type="radio" name="pay" value="mpesa" <?= $pay === 'mpesa' ? 'checked' : '' ?>><b>M-Pesa</b><small>Pay the driver by phone</small></label></div></div>
    <div class="field"><label for="notes">Notes for the driver</label><input id="notes" name="notes" maxlength="250" placeholder="Luggage, child seat, anything helpful" value="<?= e(inp('notes')) ?>"></div>
  </div>
  <div style="position:sticky;top:88px">
    <div class="meter" id="meter"><div class="tag">Your fare</div><div class="amt"><small>KES</small>--</div>
      <div class="ln mt"><span>Base fare</span><b data-base>-</b></div><div class="ln"><span>Discount</span><b data-disc>-</b></div><div class="ln"><span>Travel time</span><b data-min>-</b></div><div class="note">Choose your pickup and drop-off.</div></div>
    <button class="btn block mt" style="padding:14px">Request ride</button>
    <p class="help mt">You can cancel for free until the driver is on the way.</p>
  </div>
</form>
<?php page_end();
