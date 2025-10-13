<?php
// customers_export.php
require_once __DIR__ . '/includes/helpers.php';
require_login();

$db = pdo();

// Get column list from customers table
$columns = [];
$stmt = $db->query("SHOW COLUMNS FROM customers");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $columns[] = $row['Field'];
}

if (empty($columns)) {
    http_response_code(500);
    echo "Müşteri tablosunda sütun bulunamadı.";
    exit;
}

// Prepare CSV output
$filename = 'customers_' . date('Y-m-d_H-i-s') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);
header('Pragma: no-cache');
header('Expires: 0');

// Output BOM for Excel UTF-8
echo "\xEF\xBB\xBF";

$fh = fopen('php://output', 'w');

// Write header
fputcsv($fh, $columns);

// Query data
$sql = "SELECT `" . implode("`,`", $columns) . "` FROM customers ORDER BY id ASC";
$q = $db->query($sql);

while ($row = $q->fetch(PDO::FETCH_ASSOC)) {
    // Ensure order matches $columns
    $line = [];
    foreach ($columns as $c) {
        $line[] = isset($row[$c]) ? $row[$c] : '';
    }
    fputcsv($fh, $line);
}

fclose($fh);
exit;
