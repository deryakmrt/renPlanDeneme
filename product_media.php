<?php
// product_media.php — standalone, non-destructive product image manager
require_once __DIR__ . '/pv_bootstrap.inc.php';
require_once __DIR__ . '/includes/header.php';
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { echo '<div class="alert danger">Geçersiz ürün.</div>'; require_once __DIR__ . '/includes/footer.php'; exit; }
$st = $db->prepare("SELECT id, name, image FROM products WHERE id=?"); $st->execute([$id]);
$row = $st->fetch(PDO::FETCH_ASSOC);
if (!$row) { echo '<div class="alert danger">Ürün bulunamadı.</div>'; require_once __DIR__ . '/includes/footer.php'; exit; }

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
  if (!pv_csrf_check('media')) { echo '<div class="alert danger">CSRF doğrulaması başarısız.</div>'; }
  else if (!empty($_FILES['image']['name']) && is_uploaded_file($_FILES['image']['tmp_name'])){
    $uploadDir = __DIR__ . '/uploads/products'; if (!is_dir($uploadDir)) @mkdir($uploadDir, 0775, true);
    $uploadUrl = 'uploads/products';
    $namef = $_FILES['image']['name']; $tmp = $_FILES['image']['tmp_name'];
    $ext = strtolower(pathinfo($namef, PATHINFO_EXTENSION)); if(!$ext) $ext='jpg';
    $rand = function_exists('random_bytes') ? bin2hex(random_bytes(2)) : (string)mt_rand(1000,9999);
    $fname = 'p_'.$id.'_'.date('Ymd_His').'_'.$rand.'.'.$ext;
    $dest = $uploadDir.'/'.$fname;
    if (move_uploaded_file($tmp, $dest)) {
      $rel = $uploadUrl.'/'.$fname;
      pv_ensure_thumbs_for($rel);
      $db->prepare("UPDATE products SET image=?, updated_at=NOW() WHERE id=?")->execute([$rel, $id]);
      $row['image'] = $rel;
      echo '<div class="alert success">Görsel güncellendi.</div>';
    } else {
      echo '<div class="alert danger">Görsel yüklenemedi.</div>';
    }
  } else {
    echo '<div class="alert danger">Dosya seçilmedi.</div>';
  }
}
?>
<div class="row" style="justify-content:space-between; align-items:center;">
  <h2>Ürün Görseli: <?= h($row['name']) ?> <span class="muted">#<?= (int)$row['id'] ?></span></h2>
  <a class="btn" href="products.php?a=edit&id=<?= (int)$row['id'] ?>">← Ürün Düzenle</a>
</div>
<div class="card">
  <div class="row" style="align-items:center; gap:1rem;">
    <div>
      <?php if (!empty($row['image'])): ?>
        <img src="<?= h($row['image']) ?>" style="width:140px;height:140px;object-fit:cover;border-radius:8px;border:1px solid #ddd">
      <?php else: ?>
        <div class="muted">Mevcut görsel yok.</div>
      <?php endif; ?>
    </div>
    <form method="post" enctype="multipart/form-data" class="row" style="gap:.5rem">
      <?php pv_csrf_field('media'); ?>
      <input type="file" name="image" accept="image/*" required>
      <button class="btn">Yükle / Değiştir</button>
    </form>
  </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
