<?php
require_once __DIR__ . '/functions.php';

function icon(string $n, int $s = 18): string {
    static $p = [
        'home' => 'M3 11l9-8 9 8M5 10v10h14V10', 'grid' => 'M3 3h7v7H3zM14 3h7v7h-7zM14 14h7v7h-7zM3 14h7v7H3z',
        'list' => 'M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01', 'plus' => 'M12 5v14M5 12h14',
        'car' => 'M5 17h14M3 13l2-6h14l2 6v4H3zM7 17v2M17 17v2', 'users' => 'M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8M23 21v-2a4 4 0 00-3-3.9M16 3.1a4 4 0 010 7.8',
        'user' => 'M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2M12 11a4 4 0 100-8 4 4 0 000 8', 'pin' => 'M12 21s7-6 7-11a7 7 0 10-14 0c0 5 7 11 7 11zM12 12a2 2 0 100-4 2 2 0 000 4',
        'map' => 'M1 6v16l7-4 8 4 7-4V2l-7 4-8-4zM8 2v16M16 6v16', 'tag' => 'M20.6 13.4l-7.2 7.2a2 2 0 01-2.8 0L2 12V2h10l8.6 8.6a2 2 0 010 2.8zM7 7h.01',
        'wallet' => 'M2 7h20v12H2zM2 11h20M16 15h2', 'chart' => 'M3 3v18h18M7 14l4-4 3 3 5-6', 'star' => 'M12 2l3 7 7 .6-5.3 4.7L18.2 22 12 18l-6.2 4 1.5-7.7L2 9.6 9 9z',
        'life' => 'M12 22a10 10 0 100-20 10 10 0 000 20M12 16a4 4 0 100-8 4 4 0 000 8M4.9 4.9l4.3 4.3M14.8 14.8l4.3 4.3M14.8 9.2l4.3-4.3M4.9 19.1l4.3-4.3',
        'mega' => 'M3 11v2a1 1 0 001 1h2l5 4V6L6 10H4a1 1 0 00-1 1zM15 9a4 4 0 010 6', 'shield' => 'M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z',
        'gear' => 'M12 8a4 4 0 100 8 4 4 0 000-8M12 2v3M12 19v3M4.2 4.2l2.1 2.1M17.7 17.7l2.1 2.1M2 12h3M19 12h3M4.2 19.8l2.1-2.1M17.7 6.3l2.1-2.1',
        'bell' => 'M18 8a6 6 0 10-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 01-3.4 0', 'heart' => 'M20.8 4.6a5.5 5.5 0 00-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 00-7.8 7.8l1 1L12 21l7.8-7.8 1-1a5.5 5.5 0 000-7.8z',
        'file' => 'M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8zM14 2v6h6M8 13h8M8 17h8', 'out' => 'M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9',
        'menu' => 'M3 12h18M3 6h18M3 18h18', 'check' => 'M5 13l4 4L19 7', 'clock' => 'M12 22a10 10 0 100-20 10 10 0 000 20M12 6v6l4 2', 'phone' => 'M5 4h4l2 5-2.5 1.5a11 11 0 005 5L15 13l5 2v4a2 2 0 01-2 2A16 16 0 013 6a2 2 0 012-2z',
    ];
    return '<svg class="ic" width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="' . ($p[$n] ?? '') . '"/></svg>';
}

function nav_for(string $role): array {
    $help = ['Help and account', [['support.php', 'Support', 'life'], ['notifications.php', 'Notifications', 'bell'], ['profile.php', 'My profile', 'user']]];
    if ($role === 'admin') return [
        ['Operations', [['admin/dashboard.php', 'Dashboard', 'home'], ['admin/dispatch.php', 'Dispatch board', 'grid'], ['admin/bookings.php', 'All bookings', 'list'], ['admin/booking_new.php', 'New booking', 'plus']]],
        ['People and fleet', [['admin/drivers.php', 'Drivers', 'users'], ['admin/vehicles.php', 'Vehicles', 'car'], ['admin/customers.php', 'Customers', 'user']]],
        ['Pricing', [['admin/fares.php', 'Routes and fares', 'map'], ['admin/locations.php', 'Places', 'pin'], ['admin/promos.php', 'Promo codes', 'tag']]],
        ['Money and insight', [['admin/payments.php', 'Payments', 'wallet'], ['admin/reports.php', 'Reports', 'chart'], ['admin/ratings.php', 'Ratings', 'star']]],
        ['Communication', [['admin/tickets.php', 'Support tickets', 'life'], ['admin/announcements.php', 'Announcements', 'mega'], ['notifications.php', 'Notifications', 'bell']]],
        ['System', [['admin/audit.php', 'Audit log', 'shield'], ['admin/settings.php', 'Settings', 'gear'], ['profile.php', 'My profile', 'user']]],
    ];
    if ($role === 'driver') return [
        ['Driving', [['driver/dashboard.php', 'Overview', 'home'], ['driver/jobs.php', 'My trips', 'list'], ['driver/earnings.php', 'Earnings', 'wallet'], ['driver/ratings.php', 'My ratings', 'star'], ['driver/vehicle.php', 'My vehicle', 'car']]], $help];
    return [
        ['Rides', [['customer/dashboard.php', 'Dashboard', 'home'], ['customer/book.php', 'Book a ride', 'plus'], ['customer/bookings.php', 'My bookings', 'list'], ['customer/saved_places.php', 'Saved places', 'heart']]], $help];
}

function head_html(string $title): void { ?>
<!doctype html>
<html lang="en" data-base="<?= e(BASE_URL) ?>">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($title) ?> | <?= APP_NAME ?> <?= APP_TOWN ?></title>
<meta name="theme-color" content="#16202A">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Big+Shoulders+Display:wght@700;800&family=Figtree:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= url('assets/css/style.css') ?>?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
</head>
<?php }

function toasts(): void {
    if (empty($_SESSION['flash'])) return;
    echo '<div class="toasts" role="status">';
    foreach ($_SESSION['flash'] as [$t, $m]) echo '<div class="toast ' . e($t) . '">' . e($m) . '</div>';
    echo '</div>'; unset($_SESSION['flash']);
}

function page_start(string $title, string $active = '', string $actions = '', string $sub = ''): void {
    $u = user();
    $nav = nav_for($u['role'] ?? 'customer');
    $unread = $u ? (int)val('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0', [$u['id']]) : 0;
    $self = ltrim(substr(strtok($_SERVER['REQUEST_URI'], '?'), strlen(BASE_URL)), '/');
    $active = $active ?: $self;
    head_html($title); ?>
<body>
<div class="app">
  <aside class="side" aria-label="Main navigation">
    <a class="brand" href="<?= url(home_for($u['role'] ?? '')) ?>"><span class="dot"></span><span><?= APP_NAME ?><small><?= APP_TOWN ?>, Kenya</small></span></a>
    <span class="rolechip"><?= ['admin' => 'Administrator', 'driver' => 'Driver', 'customer' => 'Passenger'][$u['role'] ?? ''] ?? '' ?></span>
    <?php foreach ($nav as [$group, $links]): if (!array_filter($links, fn($l) => is_file(__DIR__ . '/../' . $l[0]))) continue; ?>
      <div class="navgroup"><h6><?= e($group) ?></h6>
      <?php foreach ($links as [$href, $label, $ic]): if (!is_file(__DIR__ . '/../' . $href)) continue; ?>
        <a class="navlink <?= $active === $href ? 'on' : '' ?>" href="<?= url($href) ?>"><?= icon($ic) ?><?= e($label) ?><?php if ($href === 'notifications.php' && $unread): ?><span class="count"><?= $unread ?></span><?php endif; ?></a>
      <?php endforeach; ?></div>
    <?php endforeach; ?>
    <div class="side-foot">Need help? Call <?= APP_PHONE ?></div>
  </aside>
  <div class="main">
    <header class="topbar">
      <button class="menu-btn" aria-label="Open menu"><?= icon('menu', 24) ?></button>
      <span class="muted small"><?= date('l, j F Y') ?></span><span class="sp"></span>
      <a class="iconbtn" href="<?= url('notifications.php') ?>" aria-label="Notifications"><?= icon('bell') ?><?php if ($unread): ?><b><?= $unread ?></b><?php endif; ?></a>
      <details class="me"><summary><span class="avatar"><?= e(initials($u['name'] ?? '?')) ?></span><span class="small"><b><?= e(explode(' ', $u['name'] ?? '')[0]) ?></b></span></summary>
        <div class="menu"><a href="<?= url('profile.php') ?>"><?= icon('user') ?>My profile</a>
          <form method="post" action="<?= url('logout.php') ?>"><?= csrf_field() ?><button><?= icon('out') ?>Log out</button></form></div></details>
    </header>
    <main class="content">
      <div class="page-head"><div><h1><?= e($title) ?></h1><?php if ($sub): ?><p><?= e($sub) ?></p><?php endif; ?></div><div class="flex no-print"><?= $actions ?></div></div>
<?php }

function page_end(): void { ?>
    </main>
  </div>
</div>
<?php toasts(); ?>
<script src="<?= url('assets/js/app.js') ?>?v=<?= filemtime(__DIR__ . '/../assets/js/app.js') ?>"></script>
</body></html>
<?php }

function scene_svg(): string {
    return '<svg class="scene" viewBox="0 0 900 250" role="img" aria-label="A taxi on the shore road beside Lake Victoria at sunset"><circle cx="690" cy="92" r="46" fill="#F2BE1A"/><circle cx="690" cy="92" r="64" fill="#F2BE1A" opacity=".18"/>'
      . '<path d="M0 142 Q150 128 300 142 T600 142 T900 142 V250 H0z" fill="#0B4F5C"/><path d="M0 160 H900" stroke="#2A93A0" stroke-width="2" stroke-dasharray="40 26" opacity=".6"/><path d="M60 178 H520" stroke="#2A93A0" stroke-width="2" stroke-dasharray="50 30" opacity=".5"/>'
      . '<rect x="0" y="206" width="900" height="44" fill="#16202A"/><line x1="0" y1="228" x2="900" y2="228" stroke="#F2BE1A" stroke-width="4" stroke-dasharray="46 34"/>'
      . '<g transform="translate(300,104) scale(.5)"><ellipse cx="300" cy="264" rx="250" ry="14" fill="#000" opacity=".25"/><path d="M40 215C40 185 60 170 100 165L135 112C145 96 165 86 192 86H408C435 86 455 96 465 113L500 165C540 170 560 185 560 215V232C560 242 552 248 540 248H40Z" fill="#F2BE1A"/><path d="M150 160L172 118C177 110 185 105 196 105H290V160zM310 105H404C415 105 423 110 428 118L450 160H310z" fill="#16202A"/><rect x="262" y="58" width="76" height="28" rx="5" fill="#fff" stroke="#16202A" stroke-width="3"/><text x="300" y="79" text-anchor="middle" font-family="Big Shoulders Display,sans-serif" font-size="22" font-weight="800" fill="#16202A">TAXI</text><rect x="40" y="190" width="520" height="22" fill="#16202A"/><line x1="40" y1="201" x2="560" y2="201" stroke="#F2BE1A" stroke-width="22" stroke-dasharray="22 22"/><circle cx="150" cy="248" r="38" fill="#0C141B"/><circle cx="150" cy="248" r="16" fill="#9FB2BE"/><circle cx="450" cy="248" r="38" fill="#0C141B"/><circle cx="450" cy="248" r="16" fill="#9FB2BE"/></g></svg>';
}

function auth_start(string $title, string $headline, string $blurb): void { head_html($title); ?>
<body>
<div class="auth">
  <section class="auth-art">
    <a class="brand" href="<?= url('index.php') ?>"><span class="dot"></span><span><?= APP_NAME ?><small><?= APP_TOWN ?>, Kenya</small></span></a>
    <div><h2><?= e($headline) ?></h2><p><?= e($blurb) ?></p></div>
    <?= scene_svg() ?>
  </section>
  <section class="auth-form"><div class="auth-box">
<?php }
function auth_end(): void { ?>
  </div></section>
</div>
<?php toasts(); ?>
</body></html>
<?php }

function public_start(string $title): void { head_html($title); ?>
<body>
<nav class="pub-nav">
  <a class="brand" href="<?= url('index.php') ?>"><span class="dot"></span><span><?= APP_NAME ?></span></a>
  <a href="<?= url('index.php#fares') ?>">Check a fare</a><a href="<?= url('index.php#coverage') ?>">Where we go</a><a href="<?= url('index.php#how') ?>">How it works</a>
  <span class="sp"></span>
  <?php if (user()): ?><a class="btn" href="<?= url(home_for(user()['role'])) ?>">Open my account</a>
  <?php else: ?><a href="<?= url('login.php') ?>">Log in</a><a class="btn" href="<?= url('register.php') ?>">Create account</a><?php endif; ?>
</nav>
<?php }
function public_end(): void { ?>
<footer class="foot"><span><?= APP_NAME ?> taxis, <?= APP_TOWN ?> County. Call <?= APP_PHONE ?> any time, day or night.</span><span><a style="color:#C9D6DE" href="https://wa.me/<?= APP_WHATSAPP ?>">WhatsApp us</a></span></footer>
<?php toasts(); ?>
<script src="<?= url('assets/js/app.js') ?>?v=<?= filemtime(__DIR__ . '/../assets/js/app.js') ?>"></script>
</body></html>
<?php }
