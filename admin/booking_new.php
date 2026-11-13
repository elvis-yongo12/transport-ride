<?php
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('admin');
$vt = rows('SELECT * FROM vehicle_types ORDER BY multiplier');
$custs = rows("SELECT id, name, phone FROM users WHERE role='customer' AND status='active' ORDER BY name");
$drivers = rows("SELECT u.id, u.name, d.availability, v.id vid, v.plate FROM users u JOIN drivers d ON d.user_id=u.id LEFT JOIN vehicles v ON v.driver_id=u.id AND v.status='active' WHERE u.status='active' AND u.role='driver' ORDER BY FIELD(d.availability,'available','busy','offline'), u.name");
if (is_post()) {
    post_guard(); $cid = (int)inp('customer'); $name = inp('name'); $ph = norm_phone(inp('phone'));
    if ($cid) { $c = row("SELECT name, phone FROM users WHERE id=? AND role='customer'", [$cid]); $name = $c['name'] ?? $name; $ph = $c['phone'] ?? $ph; }
    $hourly = inp('mode') === 'hourly';
    if (mb_strlen($name) < 2 || !$ph) flash('error', 'Choose a registered customer, or enter the passenger name and a valid phone number.');
    else {
        [$id, $err] = create_booking(['customer_id' => $cid ?: null, 'name' => $name, 'phone' => $ph, 'pickup' => (int)inp('pickup'), 'drop' => (int)inp('drop'), 'pickup_detail' => inp('pickup_detail'), 'drop_detail' => inp('drop_detail'),
          'when' => inp('when'), 'passengers' => (int)inp('passengers', 1), 'vtype' => (int)inp('vtype'), 'hours' => $hourly ? (int)inp('hours', 2) : 0, 'notes' => inp('notes'), 'promo' => inp('promo'),
          'pay' => inp('pay') === 'mpesa' ? 'mpesa' : 'cash', 'source' => inp('source', 'phone')], (int)$u['id']);
        if ($err) flash('error', $err);
        else {
            audit('booking.create', '#' . $id . ' (' . inp('source') . ')');
            if ((int)inp('driver') && (int)inp('vehicle')) { $e = assign_booking(booking($id), (int)inp('driver'), (int)inp('vehicle'), (int)$u['id']); if ($e) flash('error', 'Booking saved but not assigned: ' . $e); }
            flash('success', 'Booking created.'); redirect('admin/booking_view.php?id=' . $id);
        }
    }
}
$when = inp('when') ?: date('Y-m-d\TH:i', time() + 900); $mode = inp('mode', 'point'); $vsel = (int)inp('vtype', $vt[0]['id']);
page_start('New booking', 'admin/booking_new.php', '', 'Enter a booking taken by phone, WhatsApp or at the stage.'); ?>
<form method="post" data-fare-form class="grid g-main" style="align-items:start"><?= csrf_field() ?>
  <div class="card">
    <div class="row"><div class="field"><label for="source">Where did it come from?</label><select id="source" name="source"><?php foreach (['phone' => 'Phone call', 'whatsapp' => 'WhatsApp', 'stage' => 'Walk-in at the stage', 'online' => 'Online'] as $k => $l): ?><option value="<?= $k ?>" <?= inp('source', 'phone') === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
    <div class="field"><label for="customer">Registered customer</label><select id="customer" name="customer"><option value="">Walk-in or new passenger</option><?php foreach ($custs as $c): ?><option value="<?= $c['id'] ?>" <?= (int)inp('customer') === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?> (<?= e($c['phone']) ?>)</option><?php endforeach; ?></select></div></div>
    <div class="row"><div class="field"><label for="name">Passenger name</label><input id="name" name="name" value="<?= e(inp('name')) ?>"></div><div class="field"><label for="phone">Phone</label><input id="phone" name="phone" placeholder="07XX XXX XXX" value="<?= e(inp('phone')) ?>"></div></div>
    <div class="field"><label>Type of trip</label><div class="choice"><label><input type="radio" name="mode" value="point" <?= $mode !== 'hourly' ? 'checked' : '' ?>><b>Point to point</b></label><label><input type="radio" name="mode" value="hourly" <?= $mode === 'hourly' ? 'checked' : '' ?>><b>Hourly hire</b></label></div></div>
    <div class="row"><div class="field"><label for="pickup">Pickup place</label><select id="pickup" name="pickup" required><?= loc_options(inp('pickup') ?: 1) ?></select></div><div class="field"><label for="pickup_detail">Pickup details</label><input id="pickup_detail" name="pickup_detail" value="<?= e(inp('pickup_detail')) ?>"></div></div>
    <div class="row" data-point><div class="field"><label for="drop">Drop-off place</label><select id="drop" name="drop"><?= loc_options(inp('drop')) ?></select></div><div class="field"><label for="drop_detail">Drop-off details</label><input id="drop_detail" name="drop_detail" value="<?= e(inp('drop_detail')) ?>"></div></div>
    <div class="field" data-hourly style="display:none"><label for="hours">Hours</label><select id="hours" name="hours"><?php for ($h = (int)setting('min_hours', 2); $h <= 10; $h++): ?><option value="<?= $h ?>"><?= $h ?> hours</option><?php endfor; ?></select></div>
    <div class="row3"><div class="field"><label for="when">Pickup time</label><input id="when" type="datetime-local" name="when" required value="<?= e($when) ?>"></div><div class="field"><label for="passengers">Passengers</label><input id="passengers" type="number" name="passengers" min="1" max="8" value="<?= (int)inp('passengers', 1) ?>"></div><div class="field"><label for="promo">Promo code</label><input id="promo" name="promo" value="<?= e(inp('promo')) ?>"></div></div>
    <div class="row"><div class="field"><label>Vehicle</label><div class="choice"><?php foreach ($vt as $t): ?><label><input type="radio" name="vtype" value="<?= $t['id'] ?>" <?= $vsel === (int)$t['id'] ? 'checked' : '' ?>><b><?= e($t['name']) ?></b><small><?= $t['seats'] ?> seats</small></label><?php endforeach; ?></div></div>
    <div class="field"><label>Payment</label><div class="choice"><label><input type="radio" name="pay" value="cash" checked><b>Cash</b></label><label><input type="radio" name="pay" value="mpesa"><b>M-Pesa</b></label></div></div></div>
    <div class="row"><div class="field"><label for="driver">Assign a driver now (optional)</label><select id="driver" name="driver" onchange="var o=this.options[this.selectedIndex];this.form.vehicle.value=o.getAttribute('data-v')||''"><option value="">Assign later</option><?php foreach ($drivers as $d): ?><option value="<?= $d['id'] ?>" data-v="<?= $d['vid'] ?>"><?= e($d['name']) ?> (<?= $d['availability'] ?>)</option><?php endforeach; ?></select></div>
    <div class="field"><label for="vehicle">Vehicle</label><select id="vehicle" name="vehicle"><option value="">None</option><?php foreach (rows("SELECT id, plate FROM vehicles WHERE status='active' ORDER BY plate") as $v): ?><option value="<?= $v['id'] ?>"><?= e($v['plate']) ?></option><?php endforeach; ?></select></div></div>
    <div class="field"><label for="notes">Notes</label><input id="notes" name="notes" maxlength="250" value="<?= e(inp('notes')) ?>"></div>
  </div>
  <div style="position:sticky;top:88px"><div class="meter" id="meter"><div class="tag">Fare quote</div><div class="amt"><small>KES</small>--</div><div class="ln mt"><span>Base fare</span><b data-base>-</b></div><div class="ln"><span>Discount</span><b data-disc>-</b></div><div class="ln"><span>Travel time</span><b data-min>-</b></div><div class="note">Choose pickup and drop-off.</div></div><button class="btn block mt" style="padding:14px">Create booking</button></div>
</form>
<?php page_end();
