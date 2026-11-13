<?php
// TaxiRide - Admin payments: verify driver-recorded payments and see commission due. Save as taxiride/admin/payments.php
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('admin'); $me = (int)$u['id'];

/* ---------- actions ---------- */
if (is_post()) {
    post_guard(); $act = inp('action'); $id = (int)inp('id');
    $dupSql = "(SELECT COUNT(*) FROM payments p2 WHERE p2.mpesa_code=p.mpesa_code AND p2.mpesa_code<>'' AND p2.id<>p.id AND p2.status<>'rejected')";
    $doVerify = function (array $p) use ($me) {
        q("UPDATE payments SET status='verified', verified_by=?, verified_at=NOW() WHERE id=?", [$me, $p['id']]);
        q("UPDATE bookings SET payment_status='verified' WHERE id=?", [$p['booking_id']]);
        if ($p['recorded_by'] && (int)$p['recorded_by'] !== $me) notify((int)$p['recorded_by'], 'Payment verified', $p['ref'] . ': ' . money($p['amount']) . ' confirmed by the office.', 'driver/earnings.php');
    };
    if ($act === 'verify' || $act === 'reject') {
        $p = row("SELECT p.*, b.ref FROM payments p JOIN bookings b ON b.id=p.booking_id WHERE p.id=? AND p.status='recorded'", [$id]);
        if (!$p) flash('error', 'That payment has already been handled.');
        elseif ($act === 'verify') { $doVerify($p); audit('payment.verify', $p['ref'] . ' ' . $p['amount']); flash('success', 'Payment for ' . $p['ref'] . ' verified.'); }
        else {
            $why = inp('reason') ?: 'Details do not match';
            q("UPDATE payments SET status='rejected', verified_by=?, verified_at=NOW() WHERE id=?", [$me, $id]);
            q("UPDATE bookings SET payment_status='unpaid' WHERE id=? AND NOT EXISTS (SELECT 1 FROM payments WHERE booking_id=? AND status IN ('recorded','verified'))", [$p['booking_id'], $p['booking_id']]);
            if ($p['recorded_by'] && (int)$p['recorded_by'] !== $me) notify((int)$p['recorded_by'], 'Payment rejected', $p['ref'] . ': ' . $why . '. Please contact the office.', 'driver/jobs.php');
            audit('payment.reject', $p['ref'] . ': ' . $why); flash('success', 'Payment for ' . $p['ref'] . ' rejected.');
        }
    } elseif ($act === 'verify_all') {
        $n = 0;
        foreach (rows("SELECT p.*, b.ref FROM payments p JOIN bookings b ON b.id=p.booking_id WHERE p.status='recorded' AND p.amount=b.total_fare AND (p.method='cash' OR (p.mpesa_code<>'' AND $dupSql=0))") as $p) { $doVerify($p); $n++; }
        audit('payment.verify_all', $n . ' payments'); flash($n ? 'success' : 'info', $n ? $n . ' payments with matching amounts verified.' : 'No clean payments were waiting. Check the flagged ones one by one.');
    }
    redirect('admin/payments.php' . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''));
}

/* ---------- filters ---------- */
$st = inp('status'); $mt = inp('method'); $s = inp('s'); $per = 12;
$to = inp('to') ?: date('Y-m-d'); $from = inp('from') ?: date('Y-m-d', strtotime('-29 days'));
if (!strtotime($from) || !strtotime($to) || $from > $to) { $from = date('Y-m-d', strtotime('-29 days')); $to = date('Y-m-d'); }
$w = ['DATE(p.recorded_at) BETWEEN ? AND ?']; $par = [$from, $to];
if (in_array($st, ['recorded', 'verified', 'rejected'], true)) { $w[] = 'p.status=?'; $par[] = $st; }
if (in_array($mt, ['cash', 'mpesa'], true)) { $w[] = 'p.method=?'; $par[] = $mt; }
if ($s !== '') { $w[] = '(b.ref LIKE ? OR du.name LIKE ? OR p.mpesa_code LIKE ? OR b.customer_name LIKE ?)'; array_push($par, "%$s%", "%$s%", "%$s%", "%$s%"); }
$where = implode(' AND ', $w);
$base = "FROM payments p JOIN bookings b ON b.id=p.booking_id LEFT JOIN users du ON du.id=p.recorded_by WHERE $where";
$dup = "(SELECT COUNT(*) FROM payments p2 WHERE p2.mpesa_code=p.mpesa_code AND p2.mpesa_code<>'' AND p2.id<>p.id AND p2.status<>'rejected')";
$cols = "p.*, b.ref, b.total_fare, b.customer_name, du.name rec_name, $dup dup";
if (inp('export')) {
    $x = rows("SELECT $cols $base ORDER BY p.id DESC", $par);
    csv_out("taxiride-payments-$from-to-$to", ['Recorded', 'Booking', 'Customer', 'Recorded by', 'Method', 'M-Pesa code', 'Amount', 'Fare', 'Status'],
        array_map(fn($r) => [$r['recorded_at'], $r['ref'], $r['customer_name'], $r['rec_name'], $r['method'], $r['mpesa_code'], $r['amount'], $r['total_fare'], $r['status']], $x));
}
$total = (int)val("SELECT COUNT(*) $base", $par); $off = (min(page_no(), max(1, (int)ceil($total / $per))) - 1) * $per;
$list = rows("SELECT $cols $base ORDER BY FIELD(p.status,'recorded','verified','rejected'), p.id DESC LIMIT $per OFFSET $off", $par);

/* ---------- summary and commission (date range only) ---------- */
$sum = row("SELECT COALESCE(SUM(p.status='recorded'),0) pn, COALESCE(SUM(CASE WHEN p.status='recorded' THEN p.amount END),0) pa,
    COALESCE(SUM(CASE WHEN p.status='verified' THEN p.amount END),0) va, COALESCE(SUM(p.status='rejected'),0) rn,
    COALESCE(SUM(p.status='recorded' AND p.amount<>b.total_fare),0) mism, COALESCE(SUM(p.status='recorded' AND $dup>0),0) dups
    FROM payments p JOIN bookings b ON b.id=p.booking_id WHERE DATE(p.recorded_at) BETWEEN ? AND ?", [$from, $to]);
$pct = (float)setting('commission_percent', 15);
$rem = rows("SELECT u.name, COALESCE(SUM(CASE WHEN p.method='cash' THEN p.amount END),0) cash, COALESCE(SUM(CASE WHEN p.method='mpesa' THEN p.amount END),0) mpesa, COUNT(p.id) n
    FROM payments p JOIN users u ON u.id=p.recorded_by WHERE u.role='driver' AND p.status<>'rejected' AND DATE(p.recorded_at) BETWEEN ? AND ? GROUP BY u.id ORDER BY SUM(p.amount) DESC", [$from, $to]);
$qs = http_build_query(['from' => $from, 'to' => $to, 'status' => $st, 'method' => $mt, 's' => $s]);
$acts = '<a class="btn line" href="?' . e($qs) . '&export=1">Download CSV</a><form method="post" data-confirm="Verify every waiting payment whose amount matches the fare and has no duplicate code?">' . csrf_field() . '<input type="hidden" name="action" value="verify_all"><button class="btn">' . icon('check') . 'Verify all clean payments</button></form>';
page_start('Payments', 'admin/payments.php', $acts, 'Check what drivers recorded against the fare, then confirm or reject it.'); ?>
<div class="grid g4 mb">
  <div class="stat y"><span class="chip"><?= icon('clock', 22) ?></span><div><div class="v"><?= (int)$sum['pn'] ?></div><div class="l">Waiting for verification (<?= money($sum['pa']) ?>)</div></div></div>
  <div class="stat g"><span class="chip"><?= icon('check', 22) ?></span><div><div class="v"><?= number_format($sum['va']) ?></div><div class="l">KES verified in this period</div></div></div>
  <div class="stat r"><span class="chip"><?= icon('shield', 22) ?></span><div><div class="v"><?= (int)$sum['mism'] + (int)$sum['dups'] ?></div><div class="l">Flagged: <?= (int)$sum['mism'] ?> amount mismatch, <?= (int)$sum['dups'] ?> duplicate code</div></div></div>
  <div class="stat"><span class="chip"><?= icon('tag', 22) ?></span><div><div class="v"><?= (int)$sum['rn'] ?></div><div class="l">Payments rejected</div></div></div>
</div>
<form class="toolbar" method="get"><input name="s" placeholder="Search booking, driver, code" value="<?= e($s) ?>" style="min-width:240px">
  <select name="status"><option value="">Any status</option><?php foreach (['recorded' => 'Waiting', 'verified' => 'Verified', 'rejected' => 'Rejected'] as $k => $l): ?><option value="<?= $k ?>" <?= $st === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
  <select name="method"><option value="">Cash and M-Pesa</option><option value="cash" <?= $mt === 'cash' ? 'selected' : '' ?>>Cash</option><option value="mpesa" <?= $mt === 'mpesa' ? 'selected' : '' ?>>M-Pesa</option></select>
  <input type="date" name="from" value="<?= e($from) ?>" aria-label="From date"><input type="date" name="to" value="<?= e($to) ?>" aria-label="To date"><button class="btn dark">Filter</button><a class="btn line" href="?">Clear</a></form>
<div class="card mb"><?php if (!$list): ?><div class="empty"><b>No payments match</b>Try a wider date range or clear the filters.</div><?php else: ?>
<div class="tbl-wrap"><table class="tbl"><thead><tr><th>Recorded</th><th>Trip</th><th>Method</th><th class="num">Amount (KES)</th><th>Checks</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ($list as $p): ?><tr>
  <td class="nowrap"><?= dt($p['recorded_at'], 'd M, H:i') ?><span class="sub">by <?= e($p['rec_name'] ?? 'System') ?></span></td>
  <td><a href="<?= url('admin/booking_view.php?id=' . $p['booking_id']) ?>"><b><?= e($p['ref']) ?></b></a><span class="sub"><?= e($p['customer_name']) ?></span></td>
  <td><?= $p['method'] === 'mpesa' ? 'M-Pesa' : 'Cash' ?><?= $p['mpesa_code'] ? '<span class="sub">' . e($p['mpesa_code']) . '</span>' : '' ?></td>
  <td class="num"><b><?= number_format($p['amount']) ?></b><span class="sub">fare <?= number_format($p['total_fare']) ?></span></td>
  <td><?= (int)$p['amount'] === (int)$p['total_fare'] ? '<span class="badge b-ok">Amount matches</span>' : '<span class="badge b-bad">Amount differs</span>' ?><?= $p['dup'] ? ' <span class="badge b-bad">Code used twice</span>' : '' ?></td>
  <td><?= ['recorded' => '<span class="badge b-warn">Waiting</span>', 'verified' => '<span class="badge b-ok">Verified</span>', 'rejected' => '<span class="badge b-bad">Rejected</span>'][$p['status']] ?><?= $p['verified_at'] ? '<span class="sub">' . dt($p['verified_at'], 'd M, H:i') . '</span>' : '' ?></td>
  <td class="nowrap"><?php if ($p['status'] === 'recorded'): ?>
    <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="verify"><input type="hidden" name="id" value="<?= $p['id'] ?>"><button class="btn sm">Verify</button></form>
    <details style="display:inline-block;position:relative"><summary class="btn sm bad" style="list-style:none;cursor:pointer">Reject</summary><form method="post" class="card" style="position:absolute;right:0;z-index:5;width:250px;margin-top:6px"><?= csrf_field() ?><input type="hidden" name="action" value="reject"><input type="hidden" name="id" value="<?= $p['id'] ?>"><div class="field"><label for="r<?= $p['id'] ?>">Reason</label><input id="r<?= $p['id'] ?>" name="reason" placeholder="Wrong amount or code"></div><button class="btn sm bad">Reject payment</button></form></details>
  <?php else: ?><span class="muted small">Done</span><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div><?= pager($total, $per) ?><?php endif; ?></div>

<div class="card"><h3>Commission due from drivers</h3><p class="muted mb">Drivers collect the fares, so each one owes TaxiRide <?= $pct ?>% of what they collected between <?= dt($from, 'd M Y') ?> and <?= dt($to, 'd M Y') ?> (rejected payments are left out).</p>
<?php if (!$rem): ?><p class="muted">No payments in this period.</p><?php else: ?><div class="tbl-wrap"><table class="tbl"><thead><tr><th>Driver</th><th class="num">Trips paid</th><th class="num">Cash collected</th><th class="num">M-Pesa collected</th><th class="num">Total collected</th><th class="num">Commission due to TaxiRide</th><th class="num">Driver keeps</th></tr></thead><tbody>
<?php $tc = 0; foreach ($rem as $r): $tot = $r['cash'] + $r['mpesa']; $sp = earnings_split((float)$tot); $tc += $sp['fee']; ?><tr><td><b><?= e($r['name']) ?></b></td><td class="num"><?= (int)$r['n'] ?></td><td class="num"><?= number_format($r['cash']) ?></td><td class="num"><?= number_format($r['mpesa']) ?></td><td class="num"><?= number_format($tot) ?></td><td class="num"><b><?= number_format($sp['fee']) ?></b></td><td class="num"><?= number_format($sp['net']) ?></td></tr><?php endforeach; ?>
</tbody><tfoot><tr><td colspan="5"><b>Total commission due</b></td><td class="num"><b><?= number_format($tc) ?></b></td><td></td></tr></tfoot></table></div><?php endif; ?></div>
<?php page_end();