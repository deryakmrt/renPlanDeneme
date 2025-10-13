<?php
@ini_set('display_errors',1); @error_reporting(E_ALL);
header('Content-Type: text/plain; charset=utf-8');
echo "PV selftest start\n";
require_once __DIR__ . '/includes/helpers.php';
echo "helpers ok\n";
$db = pdo();
echo "pdo ok\n";
require_once __DIR__ . '/products_variations_addon.inc.php';
echo "addon include ok\n";
$c = (int)$db->query("SELECT COUNT(*) FROM products")->fetchColumn();
echo "products count: $c\n";
echo "PV selftest done\n";
