<?php
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('admin');
$types = rows('SELECT * FROM vehicle_types ORDER BY multiplier');
if (is_post()) {
    post_guard(); $act = inp('action');
    if ($act === 'delete') { q('DELETE FROM fares WHERE id=?', [(int)inp('id')]); audit('fare.delete', '#' . inp('id')); flash('success', 'Route removed.'); }
    else {
        $a = (int)inp('from'); $b = (int)inp('to'); $amt = (int)inp('amount'); $min = max(1, (int)inp('minutes'));
        if (!$a || !$b || $a === $b || $amt < 50) flash('error', 'Choose two different places and a fare of at least KES 50.');
        else {
            $ex = row('SELECT id FROM fares WHERE (from_location_id=? AND to_location_id=?) OR (from_location_id=? AND to_location_id=?)', [$a, $b, $b, $a]);
            if ($ex) q('UPDATE fares SET amount=?, est_minutes=? WHERE id=?', [$amt, $min, $ex['id']]); else q('INSERT INTO fares (from_location_id, to_location_id, amount, est_minutes) VALUES (?,?,?,?)', [$a, $b, $amt, $min]);
            audit('fare.save', $a . '-' . $b . ' ' . $amt); flash('success', $ex ? 'Fare updated.' : 'Route added.');
        }
    }
    redirect('admin/fares.php');
}
$edit = (int)($_GET['edit'] ?? 0); $f = $edit ? row('SELECT * FROM fares WHERE id=?', [$edit]) : null;
$list = rows('SELECT f.*, a.name an, b.name bn FROM fares f JOIN locations a ON a.id=f.from_location_id JOIN locations b ON b.id=f.to_location_id ORDER BY f.amount');
page_start('Routes and fares', 'admin/fares.php', '', 'Fixed fares for a sedan. SUV and van prices are worked out from the multiplier.'); ?>
<div class="grid g-side">
  <form class="card" method="post"><h3><?= $f ? 'Edit route' : 'Add or update a route' ?></h3><?= csrf_field() ?>
    <div class="field"><label for="from">From</label><select id="from" name="from"><?= loc_options($f['from_location_id'] ?? null) ?></select></div><div class="field"><label for="to">To</label><select id="to" name="to"><?= loc_options($f['to_location_id'] ?? null) ?></select></div>
    <div class="row"><div class="field"><label for="amount">Sedan fare (KES)</label><input id="amount" type="number" name="amount" min="50" required value="<?= e($f['amount'] ?? '') ?>"></div><div class="field"><label for="minutes">Minutes</label><input id="minutes" type="number" name="minutes" min="1" value="<?= e($f['est_minutes'] ?? 15) ?>"></div></div>
    <button class="btn">Save route</button><p class="help mt">Fares work in both directions. If a route is missing, the system adds up the two legs through the town stage.</p></form>
  <div class="card"><div class="toolbar"><input data-filter="#fr" placeholder="Search routes"></div><div class="tbl-wrap"><table class="tbl" id="fr"><thead><tr><th>Route</th><?php foreach ($types as $t): ?><th class="num"><?= e($t['name']) ?></th><?php endforeach; ?><th class="num">Time</th><th></th></tr></thead><tbody>
  <?php foreach ($list as $r): ?><tr><td><b><?= e($r['an']) ?></b> to <b><?= e($r['bn']) ?></b></td><?php foreach ($types as $t): ?><td class="num"><?= number_format(round($r['amount'] * $t['multiplier'] / 10) * 10) ?></td><?php endforeach; ?><td class="num"><?= (int)$r['est_minutes'] ?> min</td>
  <td class="nowrap"><a class="btn sm line" href="?edit=<?= $r['id'] ?>">Edit</a> <form method="post" style="display:inline" data-confirm="Remove this route?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="btn sm bad">Remove</button></form></td></tr><?php endforeach; ?></tbody></table></div></div>
</div>
<?php page_end();
