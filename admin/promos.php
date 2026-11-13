<?php
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('admin');
if (is_post()) {
    post_guard(); $id = (int)inp('id'); $act = inp('action');
    if ($act === 'toggle') q('UPDATE promo_codes SET active=1-active WHERE id=?', [$id]);
    elseif ($act === 'delete') { if (val('SELECT used_count FROM promo_codes WHERE id=?', [$id]) > 0) q('UPDATE promo_codes SET active=0 WHERE id=?', [$id]); else q('DELETE FROM promo_codes WHERE id=?', [$id]); }
    else {
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', inp('code'))); $type = inp('type') === 'fixed' ? 'fixed' : 'percent'; $val = (int)inp('value');
        if (strlen($code) < 4 || $val < 1 || ($type === 'percent' && $val > 80)) flash('error', 'Use a code of at least 4 letters or numbers, and a sensible value (percent discounts go up to 80).');
        elseif (val('SELECT id FROM promo_codes WHERE code=?', [$code])) flash('error', 'That code already exists.');
        else { q('INSERT INTO promo_codes (code, type, value, min_fare, expires_on, max_uses) VALUES (?,?,?,?,?,?)', [$code, $type, $val, (int)inp('min_fare'), strtotime(inp('expires')) ? inp('expires') : null, (int)inp('max_uses')]); audit('promo.create', $code); flash('success', 'Promo code created.'); }
    }
    redirect('admin/promos.php');
}
$list = rows('SELECT * FROM promo_codes ORDER BY active DESC, id DESC');
page_start('Promo codes', 'admin/promos.php', '', 'Discounts customers can apply when they book.'); ?>
<div class="grid g-side">
  <form class="card" method="post"><h3>New promo code</h3><?= csrf_field() ?>
    <div class="field"><label for="code">Code</label><input id="code" name="code" required placeholder="LAKE20" style="text-transform:uppercase"></div>
    <div class="row"><div class="field"><label for="type">Discount type</label><select id="type" name="type"><option value="percent">Percent off</option><option value="fixed">Fixed KES off</option></select></div><div class="field"><label for="value">Value</label><input id="value" type="number" name="value" min="1" required></div></div>
    <div class="row"><div class="field"><label for="min_fare">Minimum fare (KES)</label><input id="min_fare" type="number" name="min_fare" min="0" value="0"></div><div class="field"><label for="max_uses">Max uses (0 = unlimited)</label><input id="max_uses" type="number" name="max_uses" min="0" value="0"></div></div>
    <div class="field"><label for="expires">Expires on (optional)</label><input id="expires" type="date" name="expires"></div><button class="btn">Create code</button></form>
  <div class="card"><div class="tbl-wrap"><table class="tbl"><thead><tr><th>Code</th><th>Discount</th><th>Minimum fare</th><th>Used</th><th>Expires</th><th>Status</th><th></th></tr></thead><tbody>
  <?php foreach ($list as $p): ?><tr><td><b><?= e($p['code']) ?></b></td><td><?= $p['type'] === 'percent' ? (int)$p['value'] . '%' : money($p['value']) ?></td><td><?= $p['min_fare'] ? money($p['min_fare']) : 'None' ?></td><td><?= (int)$p['used_count'] ?><?= $p['max_uses'] ? ' / ' . $p['max_uses'] : '' ?></td><td><?= $p['expires_on'] ? dt($p['expires_on'], 'd M Y') : 'Never' ?></td>
  <td><?= $p['active'] ? '<span class="badge b-ok">Active</span>' : '<span class="badge b-muted">Off</span>' ?></td><td class="nowrap"><form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $p['id'] ?>"><input type="hidden" name="action" value="toggle"><button class="btn sm line"><?= $p['active'] ? 'Switch off' : 'Switch on' ?></button></form>
  <form method="post" style="display:inline" data-confirm="Delete this promo code?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $p['id'] ?>"><input type="hidden" name="action" value="delete"><button class="btn sm bad">Delete</button></form></td></tr><?php endforeach; ?></tbody></table></div></div>
</div>
<?php page_end();
