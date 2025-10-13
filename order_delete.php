<?php
/**
 * Admin-only HARD DELETE for a single order, without exposing a visible Delete button to non-admins.
 * Usage (ADMIN ONLY):
 *   /order_delete.php?id=123&confirm=EVET
 *
 * This script:
 *  - Bootstraps your app (helpers/app).
 *  - Verifies admin role.
 *  - Deletes child rows first, then the order (transaction).
 *  - Redirects back to referer (or orders.php) on success.
 */

// === Bootstrap (adjust if needed) ===
$bootstrap_paths = [
    __DIR__ . '/includes/helpers.php', // your project uses this
    __DIR__ . '/includes/app.php',
    __DIR__ . '/app.php',
    __DIR__ . '/config/app.php',
];
$bootstrapped = false;
foreach ($bootstrap_paths as $bp) {
    if (file_exists($bp)) {
        require_once $bp;
        $bootstrapped = true;
        break;
    }
}
if (!$bootstrapped) {
    http_response_code(500);
    echo "Bootstrap dosyası bulunamadı. Lütfen order_delete.php içindeki \$bootstrap_paths listesini projenize göre güncelleyin.";
    exit;
}

// RBAC: Only admin can use this
if (!function_exists('has_role') || !has_role('admin')) {
    http_response_code(403);
    echo "Bu işlem sadece admin içindir.";
    exit;
}

// PDO handle
$db = null;
if (function_exists('pdo')) {
    $db = pdo();
} elseif (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
    $db = $GLOBALS['pdo'];
}
if (!($db instanceof PDO)) {
    http_response_code(500);
    echo "PDO bağlantısı bulunamadı.";
    exit;
}

// Params & confirmation
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$confirm = $_GET['confirm'] ?? '';
if ($id <= 0 || $confirm !== 'EVET') {
    http_response_code(400);
    echo "Kullanım: /order_delete.php?id=ORDER_ID&confirm=EVET";
    exit;
}

// Order exists?
$exists = $db->prepare("SELECT id FROM orders WHERE id=? LIMIT 1");
$exists->execute([$id]);
if (!$exists->fetchColumn()) {
    http_response_code(404);
    echo "Sipariş bulunamadı (#$id).";
    exit;
}

// === HARD DELETE (transaction) ===
$db->beginTransaction();
try {
    // Delete children first (adjust list to your schema)
    $childDeletes = [
        "DELETE FROM order_items       WHERE order_id = ?",
        "DELETE FROM order_notes       WHERE order_id = ?",
        "DELETE FROM order_dates       WHERE order_id = ?",
        "DELETE FROM order_files       WHERE order_id = ?",
        "DELETE FROM order_history     WHERE order_id = ?",
        "DELETE FROM order_meta        WHERE order_id = ?",
    ];
    foreach ($childDeletes as $sql) {
        try {
            $st = $db->prepare($sql);
            $st->execute([$id]);
        } catch (Throwable $e) {
            // table may not exist; skip
        }
    }

    // Finally delete the order
    $st = $db->prepare("DELETE FROM orders WHERE id=?");
    $st->execute([$id]);

    $db->commit();

    // Redirect back
    $redirect = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'orders.php?deleted='.(int)$id;
    header("Location: " . $redirect);
    exit;
} catch (Throwable $e) {
    $db->rollBack();
    http_response_code(500);
    echo "Silme sırasında hata: " . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    exit;
}
