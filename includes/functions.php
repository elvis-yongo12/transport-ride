<?php
// Fallbacks so the project also runs on PHP builds without the mbstring extension.
if (!function_exists('mb_strlen')) { function mb_strlen($s) { return (int)preg_match_all('/./us', (string)$s); } }
if (!function_exists('mb_substr')) { function mb_substr($s, $a, $l = null) { return $l === null ? substr($s, $a) : substr($s, $a, $l); } }
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

/* ---------- basics ---------- */
function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function url(string $p = ''): string { return BASE_URL . '/' . ltrim($p, '/'); }
function redirect(string $p) { header('Location: ' . url($p)); exit; }
function is_post(): bool { return $_SERVER['REQUEST_METHOD'] === 'POST'; }
function flash(string $type, string $msg): void { $_SESSION['flash'][] = [$type, $msg]; }
function csrf(): string { if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="' . csrf() . '">'; }
function post_guard(): void {
    if (is_post() && !hash_equals($_SESSION['csrf'] ?? '', $_POST['_csrf'] ?? '')) {
        http_response_code(419);
        exit('Your session expired. Go back, refresh the page and try again.');
    }
}
function inp(string $k, $d = ''): string { return trim((string)($_POST[$k] ?? $_GET[$k] ?? $d)); }
function money($n): string { return 'KES ' . number_format((float)$n); }
function dt($s, string $f = 'd M Y, H:i'): string { return $s ? date($f, strtotime($s)) : '-'; }
function ago($ts): string {
    $d = time() - strtotime($ts);
    if ($d < 60) return 'just now';
    if ($d < 3600) return floor($d / 60) . ' min ago';
    if ($d < 86400) return floor($d / 3600) . ' hr ago';
    return floor($d / 86400) . ' d ago';
}
function initials(string $n): string { $p = preg_split('/\s+/', trim($n)); return strtoupper(substr($p[0], 0, 1) . (isset($p[1]) ? substr($p[1], 0, 1) : '')); }
function stars($n): string { $n = (int)round((float)$n); return '<span class="stars" aria-label="' . $n . ' out of 5">' . str_repeat('&#9733;', $n) . '<i>' . str_repeat('&#9733;', 5 - $n) . '</i></span>'; }
function greeting(): string { $h = (int)date('G'); return $h < 12 ? 'Good morning' : ($h < 17 ? 'Good afternoon' : 'Good evening'); }
function norm_phone(string $p): ?string {
    $p = preg_replace('/[\s\-()]/', '', $p);
    if (preg_match('/^(?:\+?254)([17]\d{8})$/', $p, $m)) return '0' . $m[1];
    if (preg_match('/^0[17]\d{8}$/', $p)) return $p;
    return null;
}
function setting(string $k, $d = '') {
    static $c = null;
    if ($c === null) { $c = []; foreach (rows('SELECT k, v FROM settings') as $r) $c[$r['k']] = $r['v']; }
    return $c[$k] ?? $d;
}

/* ---------- auth ---------- */
function user(): ?array {
    static $u = false;
    if ($u === false) {
        $u = null;
        if (!empty($_SESSION['uid'])) {
            $u = row("SELECT * FROM users WHERE id=? AND status='active'", [$_SESSION['uid']]);
            if (!$u) unset($_SESSION['uid']);
        }
    }
    return $u;
}
function home_for(string $role): string { return ['admin' => 'admin/dashboard.php', 'driver' => 'driver/dashboard.php', 'customer' => 'customer/dashboard.php'][$role] ?? 'login.php'; }
function require_role(string ...$roles): array {
    $u = user();
    if (!$u) { flash('info', 'Please log in to continue.'); redirect('login.php'); }
    if ($roles && !in_array($u['role'], $roles, true)) { flash('error', 'You do not have access to that page.'); redirect(home_for($u['role'])); }
    return $u;
}
function audit(string $action, string $detail = ''): void {
    $u = user();
    q('INSERT INTO audit_log (user_id, action, detail, ip) VALUES (?,?,?,?)', [$u['id'] ?? null, $action, mb_substr($detail, 0, 250), $_SERVER['REMOTE_ADDR'] ?? '']);
}
function notify(int $uid, string $title, string $msg, string $link = ''): void {
    q('INSERT INTO notifications (user_id, title, message, link) VALUES (?,?,?,?)', [$uid, $title, mb_substr($msg, 0, 290), $link]);
}
function notify_role(string $role, string $title, string $msg, string $link = ''): void {
    foreach (rows("SELECT id FROM users WHERE role=? AND status='active'", [$role]) as $r) notify((int)$r['id'], $title, $msg, $link);
}

/* ---------- booking domain ---------- */
const STATUS = [
    'pending' => ['Pending', 'warn'], 'assigned' => ['Assigned', 'info'], 'accepted' => ['Accepted', 'info'],
    'on_the_way' => ['On the way', 'info'], 'in_progress' => ['In progress', 'primary'],
    'completed' => ['Completed', 'ok'], 'cancelled' => ['Cancelled', 'bad'],
];
function badge(string $s): string { [$l, $c] = STATUS[$s] ?? [$s, 'muted']; return '<span class="badge b-' . $c . '">' . e($l) . '</span>'; }
function pay_badge(string $s): string { $m = ['unpaid' => ['Unpaid', 'warn'], 'paid' => ['Paid', 'info'], 'verified' => ['Verified', 'ok']]; [$l, $c] = $m[$s] ?? [$s, 'muted']; return '<span class="badge b-' . $c . '">' . $l . '</span>'; }
function loc_options($sel = null, bool $blank = true): string {
    $h = $blank ? '<option value="">Select a place</option>' : '';
    $cur = '';
    foreach (rows("SELECT id, name, area FROM locations WHERE active=1 ORDER BY FIELD(area,'Town','Intertown','Intercounty'), name") as $l) {
        if ($l['area'] !== $cur) { if ($cur) $h .= '</optgroup>'; $cur = $l['area']; $h .= '<optgroup label="' . e($cur === 'Town' ? 'In Homa Bay town' : ($cur === 'Intertown' ? 'Nearby towns' : 'Other counties')) . '">'; }
        $h .= '<option value="' . $l['id'] . '"' . ((string)$sel === (string)$l['id'] ? ' selected' : '') . '>' . e($l['name']) . '</option>';
    }
    return $h . ($cur ? '</optgroup>' : '');
}
function booking(int $id): ?array {
    return row("SELECT b.*, pl.name AS pickup_name, dl.name AS drop_name, vt.name AS vtype, vt.seats,
        du.name AS driver_name, du.phone AS driver_phone, v.plate, v.make, v.model, v.colour,
        cu.name AS acc_name, cu.email AS acc_email
        FROM bookings b
        LEFT JOIN locations pl ON pl.id=b.pickup_location_id LEFT JOIN locations dl ON dl.id=b.dropoff_location_id
        LEFT JOIN vehicle_types vt ON vt.id=b.vehicle_type_id LEFT JOIN users du ON du.id=b.driver_id
        LEFT JOIN vehicles v ON v.id=b.vehicle_id LEFT JOIN users cu ON cu.id=b.customer_id WHERE b.id=?", [$id]);
}
function route_label(array $b): string {
    if ($b['trip_type'] === 'Hourly hire') return ($b['pickup_name'] ?? 'Homa Bay') . ' (' . (int)$b['hours'] . ' hr hire)';
    return ($b['pickup_name'] ?? '?') . ' to ' . ($b['drop_name'] ?? '?');
}
function log_status(int $bid, string $status, string $note = '', ?int $by = null): void {
    q('INSERT INTO booking_logs (booking_id, status, note, user_id) VALUES (?,?,?,?)', [$bid, $status, mb_substr($note, 0, 250), $by]);
}
function gen_ref(): string {
    do { $r = 'TR' . date('ymd') . strtoupper(substr(bin2hex(random_bytes(2)), 0, 3)); } while (val('SELECT id FROM bookings WHERE ref=?', [$r]));
    return $r;
}
function fare_between(int $a, int $b): ?array {
    return row('SELECT amount, est_minutes FROM fares WHERE (from_location_id=? AND to_location_id=?) OR (from_location_id=? AND to_location_id=?) LIMIT 1', [$a, $b, $b, $a]);
}
function quote($pickup, $drop, $vtype, $hours = 0, string $promo = ''): array {
    $fail = fn($m) => ['ok' => false, 'msg' => $m];
    $vt = row('SELECT * FROM vehicle_types WHERE id=?', [(int)$vtype]);
    if (!$vt) return $fail('Choose a vehicle type.');
    $pickup = (int)$pickup; $drop = (int)$drop; $hours = (int)$hours;
    $a = $pickup ? row('SELECT * FROM locations WHERE id=?', [$pickup]) : null;
    if (!$a) return $fail('Choose a pickup place.');
    if ($hours > 0) {
        $h = max((int)setting('min_hours', 2), $hours);
        $base = $h * (int)setting('hourly_rate', 1200); $minutes = $h * 60; $type = 'Hourly hire';
    } else {
        $b = $drop ? row('SELECT * FROM locations WHERE id=?', [$drop]) : null;
        if (!$b) return $fail('Choose a drop-off place.');
        if ($pickup === $drop) return $fail('Pickup and drop-off must be different places.');
        $f = fare_between($pickup, $drop);
        if ($f) { $base = (int)$f['amount']; $minutes = (int)$f['est_minutes']; }
        elseif ($a['area'] === 'Town' && $b['area'] === 'Town') { $base = (int)setting('town_default_fare', 200); $minutes = 12; }
        else {
            $hub = (int)setting('hub_location_id', 1);
            $f1 = $pickup === $hub ? ['amount' => 0, 'est_minutes' => 0] : fare_between($hub, $pickup);
            $f2 = $drop === $hub ? ['amount' => 0, 'est_minutes' => 0] : fare_between($hub, $drop);
            if (!$f1 || !$f2) return $fail('We have no fixed fare for this route yet. Call us on ' . APP_PHONE . ' for a quote.');
            $base = (int)$f1['amount'] + (int)$f2['amount']; $minutes = (int)$f1['est_minutes'] + (int)$f2['est_minutes'];
        }
        $rank = ['Town' => 0, 'Intertown' => 1, 'Intercounty' => 2];
        $type = ['Town ride', 'Intertown', 'Intercounty'][max($rank[$a['area']], $rank[$b['area']])];
    }
    $base = (int)(round($base * (float)$vt['multiplier'] / 10) * 10);
    $disc = 0; $pid = null; $pmsg = '';
    if ($promo !== '') {
        $pr = row('SELECT * FROM promo_codes WHERE code=? AND active=1', [strtoupper($promo)]);
        if (!$pr) $pmsg = 'That promo code is not valid.';
        elseif ($pr['expires_on'] && $pr['expires_on'] < date('Y-m-d')) $pmsg = 'This promo code has expired.';
        elseif ($pr['max_uses'] > 0 && $pr['used_count'] >= $pr['max_uses']) $pmsg = 'This promo code has been fully used.';
        elseif ($base < $pr['min_fare']) $pmsg = 'This promo needs a fare of at least ' . money($pr['min_fare']) . '.';
        else {
            $disc = $pr['type'] === 'percent' ? (int)(round($base * $pr['value'] / 100 / 10) * 10) : (int)$pr['value'];
            $disc = min($disc, $base); $pid = (int)$pr['id']; $pmsg = 'Promo applied: ' . money($disc) . ' off.';
        }
    }
    return ['ok' => true, 'type' => $type, 'base' => $base, 'discount' => $disc, 'total' => $base - $disc,
            'minutes' => $minutes, 'promo_id' => $pid, 'promo_msg' => $pmsg, 'seats' => (int)$vt['seats']];
}
function create_booking(array $d, ?int $by = null): array {
    $qt = quote($d['pickup'], $d['drop'] ?? 0, $d['vtype'], $d['hours'] ?? 0, $d['promo'] ?? '');
    if (!$qt['ok']) return [null, $qt['msg']];
    if ((int)$d['passengers'] < 1 || (int)$d['passengers'] > $qt['seats']) return [null, 'This vehicle seats ' . $qt['seats'] . ' passengers. Choose a larger vehicle or fewer passengers.'];
    $ts = strtotime($d['when']);
    if (!$ts || $ts < time() - 600) return [null, 'Pick a pickup time that is not in the past.'];
    if ($ts > time() + 90 * 86400) return [null, 'Bookings can be made up to 90 days ahead.'];
    $hourly = ($d['hours'] ?? 0) > 0;
    $ref = gen_ref();
    q('INSERT INTO bookings (ref, customer_id, customer_name, customer_phone, trip_type, pickup_location_id, dropoff_location_id, pickup_detail, dropoff_detail,
        scheduled_at, passengers, vehicle_type_id, hours, notes, base_fare, discount, total_fare, promo_id, source, payment_method, created_by)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
      [$ref, $d['customer_id'] ?: null, $d['name'], $d['phone'], $qt['type'], (int)$d['pickup'], $hourly ? null : (int)$d['drop'],
       $d['pickup_detail'] ?? '', $d['drop_detail'] ?? '', date('Y-m-d H:i:s', $ts), (int)$d['passengers'], (int)$d['vtype'],
       $hourly ? max((int)setting('min_hours', 2), (int)$d['hours']) : null,
       $d['notes'] ?? '', $qt['base'], $qt['discount'], $qt['total'], $qt['promo_id'], $d['source'] ?? 'online', $d['pay'] ?? 'cash', $by]);
    $id = (int)db()->lastInsertId();
    if ($qt['promo_id']) q('UPDATE promo_codes SET used_count=used_count+1 WHERE id=?', [$qt['promo_id']]);
    log_status($id, 'pending', 'Booking created (' . ($d['source'] ?? 'online') . ')', $by);
    notify_role('admin', 'New booking ' . $ref, $d['name'] . ': ' . $qt['type'] . ', ' . money($qt['total']), 'admin/booking_view.php?id=' . $id);
    return [$id, null];
}
function vehicle_problem(array $v): ?string {
    if ($v['status'] !== 'active') return 'The vehicle ' . $v['plate'] . ' is marked ' . $v['status'] . '.';
    if ($v['insurance_expiry'] < date('Y-m-d')) return 'Insurance for ' . $v['plate'] . ' has expired.';
    if ($v['inspection_expiry'] < date('Y-m-d')) return 'Inspection for ' . $v['plate'] . ' has expired.';
    return null;
}
function assign_booking(array $b, int $driver, int $vehicle, ?int $by): ?string {
    $dr = row("SELECT d.*, u.name FROM drivers d JOIN users u ON u.id=d.user_id WHERE d.user_id=? AND u.status='active'", [$driver]);
    $v = row('SELECT * FROM vehicles WHERE id=?', [$vehicle]);
    if (!$dr || !$v) return 'Choose a driver and a vehicle.';
    if ($dr['licence_expiry'] < date('Y-m-d')) return $dr['name'] . "'s driving licence has expired.";
    if ($p = vehicle_problem($v)) return $p;
    if (!in_array($b['status'], ['pending', 'assigned', 'accepted'], true)) return 'This booking can no longer be reassigned.';
    if ($b['driver_id'] && (int)$b['driver_id'] !== $driver) q("UPDATE drivers SET availability='available' WHERE user_id=? AND availability='busy'", [$b['driver_id']]);
    q("UPDATE bookings SET driver_id=?, vehicle_id=?, status='assigned' WHERE id=?", [$driver, $vehicle, $b['id']]);
    log_status((int)$b['id'], 'assigned', 'Assigned to ' . $dr['name'] . ' in ' . $v['plate'], $by);
    notify($driver, 'New trip assigned', $b['ref'] . ': ' . route_label($b) . ' at ' . dt($b['scheduled_at']), 'driver/job_view.php?id=' . $b['id']);
    if ($b['customer_id']) notify((int)$b['customer_id'], 'Driver assigned', $dr['name'] . ' will pick you up in ' . $v['plate'] . '.', 'customer/booking_view.php?id=' . $b['id']);
    audit('booking.assign', $b['ref'] . ' to ' . $dr['name']);
    return null;
}
function set_status(array $b, string $new, string $note = '', ?int $by = null): void {
    q('UPDATE bookings SET status=?' . ($new === 'completed' ? ', completed_at=NOW()' : '') . ' WHERE id=?', [$new, $b['id']]);
    log_status((int)$b['id'], $new, $note, $by);
    $drv = $b['driver_id'] ?: null;
    if ($drv) {
        if (in_array($new, ['accepted', 'on_the_way', 'in_progress'], true)) q("UPDATE drivers SET availability='busy' WHERE user_id=?", [$drv]);
        elseif (in_array($new, ['completed', 'cancelled'], true)) q("UPDATE drivers SET availability='available' WHERE user_id=? AND availability='busy'", [$drv]);
    }
    $txt = ['accepted' => 'Your driver accepted the trip.', 'on_the_way' => 'Your driver is on the way to the pickup point.', 'in_progress' => 'Your trip has started.',
            'completed' => 'Trip completed. Please rate your driver.', 'cancelled' => 'Your booking was cancelled.'];
    if ($b['customer_id'] && isset($txt[$new])) notify((int)$b['customer_id'], 'Booking ' . $b['ref'], $txt[$new], 'customer/booking_view.php?id=' . $b['id']);
    if ($new === 'cancelled' && $drv && $by !== (int)$drv) notify((int)$drv, 'Trip cancelled', $b['ref'] . ' was cancelled.', 'driver/job_view.php?id=' . $b['id']);
    $me = user();
    if (in_array($new, ['accepted', 'completed', 'cancelled'], true) && $me && $me['role'] !== 'admin') notify_role('admin', 'Booking ' . $b['ref'] . ' ' . strtolower(STATUS[$new][0]), route_label($b), 'admin/booking_view.php?id=' . $b['id']);
}
function timeline(int $bid): string {
    $h = '<ol class="timeline">';
    foreach (rows('SELECT l.*, u.name FROM booking_logs l LEFT JOIN users u ON u.id=l.user_id WHERE booking_id=? ORDER BY l.id', [$bid]) as $l) {
        $lab = (STATUS[$l['status']] ?? [$l['status']])[0];
        $h .= '<li class="tl-' . e($l['status']) . '"><b>' . e($lab) . '</b><span>' . e($l['note']) . ($l['name'] ? ' by ' . e($l['name']) : '') . '</span><time>' . dt($l['created_at'], 'd M, H:i') . '</time></li>';
    }
    return $h . '</ol>';
}
function driver_rating(int $uid): array {
    $r = row('SELECT AVG(stars) a, COUNT(*) n FROM ratings WHERE driver_id=?', [$uid]);
    return ['avg' => $r['n'] ? round($r['a'], 1) : 0, 'n' => (int)$r['n']];
}
function earnings_split(float $gross): array {
    $c = (float)setting('commission_percent', 15); $fee = round($gross * $c / 100);
    return ['gross' => $gross, 'fee' => $fee, 'net' => $gross - $fee, 'pct' => $c];
}
function expiring_docs(int $days = 30): array {
    $d = date('Y-m-d', strtotime("+$days days"));
    return rows("SELECT 'Driving licence' AS doc, u.name AS owner, dr.licence_expiry AS exp FROM drivers dr JOIN users u ON u.id=dr.user_id WHERE dr.licence_expiry<=?
      UNION ALL SELECT 'PSV badge', u.name, dr.psv_expiry FROM drivers dr JOIN users u ON u.id=dr.user_id WHERE dr.psv_expiry IS NOT NULL AND dr.psv_expiry<=?
      UNION ALL SELECT 'Insurance', v.plate, v.insurance_expiry FROM vehicles v WHERE v.status<>'retired' AND v.insurance_expiry<=?
      UNION ALL SELECT 'Inspection', v.plate, v.inspection_expiry FROM vehicles v WHERE v.status<>'retired' AND v.inspection_expiry<=? ORDER BY exp", [$d, $d, $d, $d]);
}

/* ---------- small ui helpers ---------- */
function page_no(): int { return max(1, (int)($_GET['p'] ?? 1)); }
function pager(int $total, int $per): string {
    $pages = max(1, (int)ceil($total / $per)); $cur = min(page_no(), $pages);
    if ($pages < 2) return '';
    $qs = $_GET; $h = '<nav class="pager" aria-label="Pages">';
    for ($i = 1; $i <= $pages; $i++) { if ($pages > 9 && abs($i - $cur) > 3 && $i != 1 && $i != $pages) continue; $qs['p'] = $i; $h .= '<a class="' . ($i == $cur ? 'on' : '') . '" href="?' . e(http_build_query($qs)) . '">' . $i . '</a>'; }
    return $h . '</nav>';
}
function col_chart(array $data, bool $money = false): string {
    $max = max(1, ...array_values($data ?: [1])); $h = '<div class="cols">';
    foreach ($data as $l => $v) $h .= '<div class="col"><span class="cv">' . ($money ? number_format($v) : $v) . '</span><div class="cb"><i style="height:' . round($v / $max * 100) . '%"></i></div><span class="cl">' . e($l) . '</span></div>';
    return $h . '</div>';
}
function hbars(array $data, bool $money = false): string {
    $max = max(1, ...array_values($data ?: [1])); $h = '<div class="hbars">';
    foreach ($data as $l => $v) $h .= '<div class="hb"><span>' . e($l) . '</span><div><i style="width:' . round($v / $max * 100) . '%"></i></div><b>' . ($money ? number_format($v) : $v) . '</b></div>';
    return $h . '</div>';
}
function csv_out(string $name, array $head, array $rows): void {
    header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="' . $name . '.csv"');
    $o = fopen('php://output', 'w'); fputcsv($o, $head); foreach ($rows as $r) fputcsv($o, $r); exit;
}
function tbadge(string $s): string { $m = ['open' => ['Open', 'warn'], 'in_progress' => ['In progress', 'info'], 'resolved' => ['Resolved', 'ok']]; [$l, $c] = $m[$s] ?? [$s, 'muted']; return '<span class="badge b-' . $c . '">' . $l . '</span>'; }
function progress_bar(string $status): string {
    $idx = ['pending' => 1, 'assigned' => 2, 'accepted' => 3, 'on_the_way' => 4, 'in_progress' => 5, 'completed' => 6, 'cancelled' => 0][$status] ?? 0;
    $h = '<div class="progress" aria-hidden="true">'; for ($i = 1; $i <= 6; $i++) $h .= '<i class="' . ($i <= $idx ? 'on' : '') . '"></i>'; return $h . '</div>';
}
function doc_badge(?string $date): string {
    if (!$date) return '<span class="badge b-muted">Not set</span>';
    $d = (strtotime($date) - strtotime(date('Y-m-d'))) / 86400;
    if ($d < 0) return '<span class="badge b-bad">Expired ' . dt($date, 'd M Y') . '</span>';
    if ($d <= 30) return '<span class="badge b-warn">Expires ' . dt($date, 'd M Y') . '</span>';
    return '<span class="badge b-ok">Valid to ' . dt($date, 'd M Y') . '</span>';
}
