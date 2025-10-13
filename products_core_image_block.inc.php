<?php
require_once __DIR__ . '/products_variations_addon.inc.php';
$pid = isset($row['id']) ? (int)$row['id'] : (isset($_GET['id'])?(int)$_GET['id']:0);
$img = isset($row['image']) ? $row['image'] : '';
?>
<div class="card mt">
  <h3>Ürün Görseli</h3>
  <div class="row" style="align-items:center; gap:1rem;">
    <div><?php if(!empty($img)): ?><img src="<?= h(thumb_path($img,'300x300')) ?>" style="width:120px;height:120px;object-fit:cover;border:1px solid #ddd;border-radius:8px"><?php else: ?><span class="muted">Görsel yok</span><?php endif; ?></div>
    <div class="row" style="gap:.5rem;">
      <input type="file" name="image" accept="image/*">
      <span class="muted">Kaydettiğinizde güncellenir.</span>
    </div>
  </div>
</div>
