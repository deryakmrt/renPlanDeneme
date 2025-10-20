<?php
// users_admin.php — DEBUG BUILD (no duplicate functions)
// This version avoids redeclaring any helpers that might exist in includes/helpers.php

// ---- DEBUG ----
@ini_set('display_errors', 1);
@ini_set('display_startup_errors', 1);
@ini_set('log_errors', 1);
@ini_set('error_log', __DIR__ . '/users_admin_debug.log');
error_reporting(E_ALL);
ob_start(); // catch warnings before headers

register_shutdown_function(function () {
  $e = error_get_last();
  if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
    echo "<pre style='background:#2b2b2b;color:#ffb4b4;padding:10px;border-radius:6px'>FATAL: "
       . htmlspecialchars($e['message']) . " in "
       . htmlspecialchars($e['file']) . ":" . (int)$e['line'] . "</pre>";
    error_log('[fatal] ' . $e['message'] . ' in ' . $e['file'] . ':' . $e['line']);
  }
});

require_once __DIR__ . '/includes/helpers.php';

// Ensure helper shims only if not defined there
if (!function_exists('go')) {
  function go($url){ if (function_exists('redirect')) redirect($url); else { header('Location: '.$url); } exit; }
}

// ---- DB ----
try { $db = pdo(); }
catch (Throwable $e) {
  echo "<pre style='background:#2b2b2b;color:#ffd479;padding:10px;border-radius:6px'>DB ERROR: "
     . htmlspecialchars($e->getMessage()) . "</pre>";
  error_log('[db] ' . $e->getMessage());
  exit;
}

// ---- Auth (after DB include to reuse helpers) ----
if (function_exists('require_login')) { require_login(); }
if (function_exists('require_admin')) { require_admin(); }

// ---- Roller (DB'den birebir) ----
function fetch_roles(PDO $db): array {
  try {
    $rs = $db->query("SELECT DISTINCT role FROM users WHERE role IS NOT NULL AND role<>'' ORDER BY role");
    $out = [];
    if ($rs) {
      foreach ($rs->fetchAll(PDO::FETCH_ASSOC) as $r) { $out[] = (string)$r['role']; }
    }
    // Ensure required roles are present regardless of DB contents
    $defaults = ['admin','sistem_yoneticisi','musteri','plasiyer','uretim'];
    $out = array_values(array_unique(array_merge($defaults, $out)));
    return $out;
  } catch (Throwable $e) {
    echo "<pre style='background:#2b2b2b;color:#ffd479;padding:10px;border-radius:6px'>ROLES ERROR: "
       . htmlspecialchars($e->getMessage()) . "</pre>";
    // Fallback to defaults if DB fails
    return ['admin','sistem_yoneticisi','musteri','plasiyer','uretim'];
  }
}

$action = $_GET['a'] ?? 'list';
$id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// ---------------- NEW (FORM) ----------------
if ($action==='new') {
  $u = ['username'=>'','email'=>'','role'=>''];
  $roles = fetch_roles($db);
  include __DIR__ . '/includes/header.php'; ?>
  <div class="container py-4" style="max-width:900px;">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h3 class="m-0">Yeni Kullanıcı</h3>
      <a class="btn btn-sm btn-outline-secondary" href="users_admin.php?a=list">Listeye Dön</a>
    </div>
    <?php if (function_exists('flash')) { flash(); } ?>
    <form method="post" action="users_admin.php?a=create" class="vstack gap-3">
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label">Kullanıcı Adı</label>
          <input type="text" name="username" class="form-control" required>
        </div>
        <div class="col-md-4">
          <label class="form-label">E-posta</label>
          <input type="email" name="email" class="form-control" placeholder="opsiyonel">
        </div>
        <div class="col-md-4">
          <label class="form-label">Rol</label>
          <select name="role" class="form-select">
            <?php foreach ($roles as $opt): ?>
              <option value="<?= h($opt) ?>"><?= h($opt) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label">Şifre</label>
          <input type="password" name="password" class="form-control" required>
        </div>
        <div class="col-md-4">
          <label class="form-label">Şifre (Tekrar)</label>
          <input type="password" name="password2" class="form-control" required>
        </div>
      </div>
      <div class="d-flex gap-2">
        <button class="btn btn-success" type="submit">Kaydet</button>
        <a class="btn btn-outline-secondary" href="users_admin.php?a=list">İptal</a>
      </div>
    </form>
  </div>
<?php
  ob_end_flush();
  exit;
}

// ---------------- DELETE ----------------
// ---------------- CREATE (INSERT) ----------------
if ($_SERVER['REQUEST_METHOD']==='POST' && $action==='create') {
  $username = trim((string)($_POST['username'] ?? ''));
  $email    = trim((string)($_POST['email'] ?? ''));
  $role     = trim((string)($_POST['role'] ?? ''));
  $pass1    = (string)($_POST['password'] ?? '');
  $pass2    = (string)($_POST['password2'] ?? '');

  try {
    if ($username==='') throw new Exception('Kullanıcı adı boş olamaz.');
    if ($email!=='' && !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Exception('Geçerli bir e-posta girin.');
    if ($pass1==='' || $pass2==='') throw new Exception('Şifre giriniz.');
    if ($pass1!==$pass2) throw new Exception('Şifreler uyuşmuyor.');

    $hash = password_hash($pass1, PASSWORD_BCRYPT);
    $st = $db->prepare("INSERT INTO users (username, email, role, password_hash) VALUES (?,?,?,?)");
    $st->execute([$username, ($email!==''?$email:null), $role, $hash]);

    $_SESSION['flash_success'] = 'Kullanıcı oluşturuldu.';
    go('users_admin.php?a=list');
  } catch (Throwable $e) {
    echo "<pre style='background:#2b2b2b;color:#ffb4b4;padding:10px;border-radius:6px'>CREATE ERROR: "
       . htmlspecialchars($e->getMessage()) . "</pre>";
    $_SESSION['flash_error'] = 'Hata: '.$e->getMessage();
    go('users_admin.php?a=new');
  }
}

if ($_SERVER['REQUEST_METHOD']==='POST' && ($action==='delete')) {
  $del_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
  try {
    $st = $db->prepare("DELETE FROM users WHERE id=?");
    $st->execute([$del_id]);
    $_SESSION['flash_success'] = 'Kullanıcı silindi.';
  } catch (Throwable $e) {
    echo "<pre style='background:#2b2b2b;color:#ffb4b4;padding:10px;border-radius:6px'>DELETE ERROR: "
       . htmlspecialchars($e->getMessage()) . "</pre>";
  }
  go('users_admin.php?a=list');
}

// ---------------- SAVE (EDIT) ----------------
if ($_SERVER['REQUEST_METHOD']==='POST' && $action==='edit' && $id>0) {
  $username = trim((string)($_POST['username'] ?? ''));
  $email    = trim((string)($_POST['email'] ?? ''));
  $role     = trim((string)($_POST['role'] ?? ''));
  $pass1    = (string)($_POST['password'] ?? '');
  $pass2    = (string)($_POST['password2'] ?? '');

  try {
    if ($username==='') throw new Exception('Kullanıcı adı boş olamaz.');
    if ($email!=='' && !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Exception('Geçerli bir e-posta girin.');

    if ($pass1!=='' || $pass2!=='') {
      if ($pass1!==$pass2) throw new Exception('Şifreler uyuşmuyor.');
      $hash = password_hash($pass1, PASSWORD_BCRYPT);
      $st = $db->prepare("UPDATE users SET username=?, email=?, role=?, password_hash=? WHERE id=?");
      $st->execute([$username, ($email!==''?$email:null), $role, $hash, $id]);
    } else {
      $st = $db->prepare("UPDATE users SET username=?, email=?, role=? WHERE id=?");
      $st->execute([$username, ($email!==''?$email:null), $role, $id]);
    }
    $_SESSION['flash_success'] = 'Kullanıcı güncellendi.';
    go('users_admin.php?a=edit&id='.$id);
  } catch (Throwable $e) {
    echo "<pre style='background:#2b2b2b;color:#ffb4b4;padding:10px;border-radius:6px'>SAVE ERROR: "
       . htmlspecialchars($e->getMessage()) . "</pre>";
    $_SESSION['flash_error'] = 'Hata: '.$e->getMessage();
  }
}

// ---------------- EDIT SCREEN ----------------
if ($action==='edit' && $id>0) {
  try {
    $st = $db->prepare("SELECT id, username, email, role FROM users WHERE id=?");
    $st->execute([$id]);
    $u = $st->fetch(PDO::FETCH_ASSOC);
  } catch (Throwable $e) {
    echo "<pre style='background:#2b2b2b;color:#ffb4b4;padding:10px;border-radius:6px'>READ ERROR: "
       . htmlspecialchars($e->getMessage()) . "</pre>";
    exit;
  }
  if (!$u) { $_SESSION['flash_error'] = 'Kullanıcı bulunamadı.'; go('users_admin.php?a=list'); }
  $roles = fetch_roles($db);

  include __DIR__ . '/includes/header.php'; ?>
  <div class="container py-4" style="max-width:900px;">
    <h3>Kullanıcı Düzenle</h3>
    <?php if (function_exists('flash')) { flash(); } ?>
    <form method="post" action="users_admin.php?a=edit&id=<?= (int)$u['id'] ?>">
      <div class="card"><div class="card-body">
        <div class="mb-3">
          <label class="form-label">Kullanıcı Adı</label>
          <input type="text" name="username" class="form-control" required value="<?= h($u['username']) ?>">
        </div>
        <div class="mb-3">
          <label class="form-label">Rol</label>
          <select name="role" class="form-select">
            <?php $current = (string)($u['role'] ?? '');
            if ($current!==''): ?>
              <option value="<?= h($current) ?>" selected><?= h($current) ?></option>
            <?php endif;
            foreach ($roles as $opt) { if ($opt===$current) continue; ?>
              <option value="<?= h($opt) ?>"><?= h($opt) ?></option>
            <?php } ?>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">E-posta</label>
          <input type="email" name="email" class="form-control" value="<?= h($u['email'] ?? '') ?>" placeholder="kullanici@firma.com">
        </div>
        <div class="mb-3">
          <label class="form-label">Şifre (opsiyonel)</label>
          <input type="password" name="password" class="form-control" placeholder="Değiştirmek istemiyorsanız boş bırakın">
        </div>
        <div class="mb-3">
          <label class="form-label">Şifre (Tekrar)</label>
          <input type="password" name="password2" class="form-control">
        </div>
        <div class="d-flex gap-2">
          <button class="btn btn-primary" type="submit">Güncelle</button>
          <a class="btn btn-secondary" href="users_admin.php?a=list">Vazgeç</a>
        </div>
      </div></div>
    </form>
  </div>
<?php
  include __DIR__ . '/includes/footer.php';
  exit;
}

// ---------------- LIST ----------------
$search = trim((string)($_GET['q'] ?? ''));
$where  = ''; $args = [];
if ($search!=='') { $where="WHERE username LIKE ? OR email LIKE ? OR role LIKE ?"; $args=["%$search%","%$search%","%$search%"]; }

$st = $db->prepare("SELECT id, username, email, role, created_at FROM users $where ORDER BY id DESC");
$st->execute($args);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/includes/header.php'; ?>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
  <h3 class="m-0">Kullanıcılar</h3>
  <a class="btn btn-primary btn-sm" href="users_admin.php?a=new">+ Yeni Kullanıcı</a>
</div>
    <form method="get" class="d-flex" action="users_admin.php">
      <input type="hidden" name="a" value="list">
      <input type="text" class="form-control me-2" name="q" value="<?= h($search) ?>" placeholder="Kullanıcı adı veya e-posta ara">
      <button class="btn btn-outline-secondary">Ara</button>
    </form>
  </div>
  <?php if (function_exists('flash')) { flash(); } ?>
  <div class="table-responsive">
    <table class="table table-striped align-middle">
      <thead>
        <tr>
          <th style="width:80px;">ID</th>
          <th>Kullanıcı Adı</th>
          <th>E-posta</th>
          <th>Rol</th>
          <th style="width:200px;">İşlem</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= (int)$r['id'] ?></td>
            <td><?= h($r['username']) ?></td>
            <td><?= h($r['email'] ?? '') ?></td>
            <td><?= h($r['role']) ?></td>
            <td class="d-flex gap-2">
              <a class="btn btn-sm btn-secondary" href="users_admin.php?a=edit&id=<?= (int)$r['id'] ?>">Düzenle</a>
              <form method="post" action="users_admin.php?a=delete" onsubmit="return confirm('Bu kullanıcı silinsin mi?');" style="display:inline">
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <button class="btn btn-sm btn-danger" type="submit">Sil</button>
              </form>
            </td>
          </tr>
        <?php endforeach; if (!$rows): ?>
          <tr><td colspan="5" class="text-muted">Kayıt bulunamadı.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php
// DEBUG: flush buffer to reveal any header warnings
ob_end_flush();
