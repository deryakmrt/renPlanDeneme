<?php
// products_tools_links.php — convenience links (no site changes)
require_once __DIR__ . '/pv_bootstrap.inc.php';
require_once __DIR__ . '/includes/header.php';
$id = (int)($_GET['id'] ?? 0);
?>
<div class="card">
  <h2>Ürün Araçları</h2>
  <form method="get">
    <label>Ürün ID <input type="number" name="id" value="<?= $id>0?(int)$id:'' ?>" required></label>
    <button class="btn">Git</button>
  </form>
  <?php if ($id>0): ?>
  <ul style="margin-top:12px">
    <li><a class="btn" href="product_media.php?id=<?= (int)$id ?>">Ürün Görseli Yönetimi</a></li>
    <li><a class="btn" href="product_attributes_variations.php?id=<?= (int)$id ?>">Öznitelik & Varyasyon Yönetimi</a></li>
  </ul>
  <?php endif; ?>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
