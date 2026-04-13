<?php
require_once __DIR__ . '/includes/helpers.php';
require_login();
require_role(['admin', 'sistem_yoneticisi', 'muhasebe']);

require_once __DIR__ . '/app/Models/ProjectModel.php';

$model    = new ProjectModel(pdo());
$cu_role  = current_user()['role'] ?? '';
$can_edit = in_array($cu_role, ['admin', 'sistem_yoneticisi']);

// --- POST İŞLEMLERİ ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($can_edit && $action === 'create') {
        $name     = trim($_POST['name'] ?? '');
        $aciklama = trim($_POST['aciklama'] ?? '');
        if ($name) {
            $model->create($name, $aciklama);
            $_SESSION['flash_success'] = 'Proje oluşturuldu.';
        }
    }

    if ($can_edit && $action === 'delete') {
        $pid = (int)($_POST['project_id'] ?? 0);
        if ($pid) {
            $model->delete($pid);
            $_SESSION['flash_success'] = 'Proje silindi.';
        }
    }

    redirect('projeler.php');
}

// --- VERİ ---
$projects = $model->all();

// --- GÖRÜNÜM ---
include __DIR__ . '/includes/header.php';
include __DIR__ . '/app/Views/projects/projeler_view.php';
include __DIR__ . '/includes/footer.php';