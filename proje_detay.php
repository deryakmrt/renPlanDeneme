<?php
require_once __DIR__ . '/includes/helpers.php';
require_login();
require_role(['admin', 'sistem_yoneticisi', 'muhasebe']);

require_once __DIR__ . '/app/Models/ProjectModel.php';

$model    = new ProjectModel(pdo());
$cu_role  = current_user()['role'] ?? '';
$can_edit = in_array($cu_role, ['admin', 'sistem_yoneticisi']);

$pid = (int)($_GET['id'] ?? 0);
if (!$pid) redirect('projeler.php');

$proje = $model->find($pid);
if (!$proje) { http_response_code(404); die('Proje bulunamadı.'); }

// --- POST İŞLEMLERİ ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($can_edit && $action === 'attach') {
        $order_ids = array_filter(array_map('intval', (array)($_POST['order_ids'] ?? [])));
        if ($order_ids) {
            $model->attachOrders($pid, $order_ids);
            $_SESSION['flash_success'] = count($order_ids) . ' sipariş projeye bağlandı.';
        }
    }

    if ($can_edit && $action === 'detach') {
        $oid = (int)($_POST['order_id'] ?? 0);
        if ($oid) {
            $model->detachOrder($pid, $oid);
            $_SESSION['flash_success'] = 'Sipariş projeden çıkarıldı.';
        }
    }

    if ($can_edit && $action === 'update') {
        $name     = trim($_POST['name'] ?? '');
        $aciklama = trim($_POST['aciklama'] ?? '');
        if ($name) {
            $model->update($pid, $name, $aciklama);
            $_SESSION['flash_success'] = 'Proje güncellendi.';
        }
    }

    redirect('proje_detay.php?id=' . $pid);
}

// --- VERİ ---
$sq             = trim($_GET['sq'] ?? '');
$bound_orders   = $model->boundOrders($pid);
$unbound_orders = $model->unboundOrders($sq);
$grand_total    = array_sum(array_column($bound_orders, 'order_total'));

// --- GÖRÜNÜM ---
include __DIR__ . '/includes/header.php';
include __DIR__ . '/app/Views/projects/proje_detay_view.php';
include __DIR__ . '/includes/footer.php';