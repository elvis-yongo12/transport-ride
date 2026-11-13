<?php
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('customer');
if (is_post()) {
    post_guard();
    if (inp('action') === 'delete') { q('DELETE FROM saved_places WHERE id=? AND user_id=?', [(int)inp('id'), $u['id']]); flash('success', 'Place removed.'); }
    elseif (mb_strlen(inp('label')) < 2 || !(int)inp('location')) flash('error', 'Give the place a name and choose where it is.');
    elseif ((int)val('SELECT COUNT(*) FROM saved_places WHERE user_id=?', [$u['id']]) >= 8) flash('error', 'You can save up to 8 places. Remove one first.');
    else { q('INSERT INTO saved_places (user_id, label, location_id, detail) VALUES (?,?,?,?)', [$u['id'], inp('label'), (int)inp('location'), inp('detail')]); flash('success', 'Place saved.'); }
    redirect('customer/saved_places.php');
}
$list = rows('SELECT s.*, l.name FROM saved_places s JOIN locations l ON l.id=s.location_id WHERE user_id=? ORDER BY s.id', [$u['id']]);
page_start('Saved places', 'customer/saved_places.php', '', 'Save the places you ride from often, like Home, Work or church.'); ?>
<div class="grid g-side">
  <form class="card" method="post"><h3>Add a place</h3><?= csrf_field() ?>
    <div class="field"><label for="label">Name</label><input id="label" name="label" maxlength="40" placeholder="Home" required></div>
    <div class="field"><label for="location">Area</label><select id="location" name="location" required><?= loc_options() ?></select></div>
    <div class="field"><label for="detail">Details</label><input id="detail" name="detail" maxlength="160" placeholder="Gate, house number or landmark"></div><button class="btn">Save place</button></form>
  <div class="card"><h3>Your places</h3>
  <?php if (!$list): ?><div class="empty"><b>Nothing saved yet</b>Add Home or Work to book in two taps.</div><?php endif; ?>
  <?php foreach ($list as $s): ?><div class="docwarn"><span><b><?= e($s['label']) ?></b><br><span class="muted small"><?= e($s['name']) ?><?= $s['detail'] ? ', ' . e($s['detail']) : '' ?></span></span>
    <span class="flex"><a class="btn sm line" href="<?= url('customer/book.php?pickup=' . $s['location_id'] . '&pickup_detail=' . urlencode($s['detail'])) ?>">Ride from here</a>
    <form method="post" data-confirm="Remove this place?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $s['id'] ?>"><button class="btn sm bad">Remove</button></form></span></div><?php endforeach; ?></div>
</div>
<?php page_end();
