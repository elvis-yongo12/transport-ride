<?php
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json');
$r = quote($_GET['pickup'] ?? 0, $_GET['drop'] ?? 0, $_GET['vtype'] ?? 0, $_GET['hours'] ?? 0, trim($_GET['promo'] ?? ''));
echo json_encode($r);
