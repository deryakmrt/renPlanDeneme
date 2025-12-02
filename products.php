<?php

ini_set('display_errors', 1);

error_reporting(E_ALL);



require_once __DIR__ . '/includes/helpers.php';

require_once __DIR__ . '/includes/image_upload.php';



require_once __DIR__ . '/products_variations_addon.inc.php';

require_login();



$db = pdo();

// -- Taxonomy columns bootstrap (category_id, brand_id)

try {

    $colExists = function($table,$col) use ($db){

        try { $st=$db->prepare("SHOW COLUMNS FROM `$table` LIKE ?"); $st->execute([$col]); return (bool)$st->fetchColumn(); }

        catch (Exception $e) { return false; }

    };

    if (!$colExists('products','category_id')) { @ $db->exec("ALTER TABLE products ADD COLUMN category_id INT UNSIGNED NULL"); }

    if (!$colExists('products','brand_id'))    { @ $db->exec("ALTER TABLE products ADD COLUMN brand_id INT UNSIGNED NULL"); }

} catch (Exception $e) { /* ignore */ }



// Load taxonomies for form selects (graceful if tables missing)

$__cats = $__brands = [];

try { $__cats = $db->query("SELECT id,name FROM product_categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC); } catch (Exception $e) { $__cats = []; }

try { $__brands = $db->query("SELECT id,name FROM product_brands ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC); } catch (Exception $e) { $__brands = []; }



$action = $_GET['a'] ?? 'list';



// Silme (POST)

if ($action === 'delete' && method('POST')) {

    csrf_check();

    $id = (int)($_POST['id'] ?? 0);

    if ($id) {

        $stmt = $db->prepare("DELETE FROM products WHERE id=?");

        $stmt->execute([$id]);

    }



    // (Silme isteğinde dosya gelmeyeceği için aşağıdakiler koşullu, zararsız)

    // Silme işleminde resim işleme gerekmez

    if (!empty($_FILES['image']['name']) && is_uploaded_file($_FILES['image']['tmp_name'])) {

        if ($id > 0) {

            $old = (string)$db->query("SELECT image FROM products WHERE id=".$id)->fetchColumn();

            $rel = product_image_store($id, $_FILES, 'image', $old ?: null);

        } else {

            $rel = null;

        }

        if ($rel) {

            $st_img = $db->prepare("UPDATE products SET image=?, updated_at=NOW() WHERE id=?");

            $st_img->execute([$rel, $id]);

        }

    }

    redirect('products.php');

}



// Kayıt ekle/düzenle (POST)

if (($action === 'new' || $action === 'edit') && method('POST')) {

    csrf_check();

    $id   = (int)($_POST['id'] ?? 0);

    $sku  = trim($_POST['sku'] ?? '');

    $name = trim($_POST['name'] ?? '');

    $unit = trim($_POST['unit'] ?? 'adet');

    $price = (float)($_POST['price'] ?? 0);

    $urun_ozeti = trim($_POST['urun_ozeti'] ?? '');

    $kullanim_alani = trim($_POST['kullanim_alani'] ?? '');



    $category_id = isset($_POST['category_id']) && $_POST['category_id']!=='' ? (int)$_POST['category_id'] : null;

    $brand_id = isset($_POST['brand_id']) && $_POST['brand_id']!=='' ? (int)$_POST['brand_id'] : null;



    if ($name === '') {

        $error = 'Ürün adı zorunlu';

    } else {
        // SKU benzersizlik kontrolü
        if ($sku !== '') {
            $checkSku = $db->prepare("SELECT id FROM products WHERE sku=? AND id<>?");
            $checkSku->execute([$sku, $id]);
            if ($checkSku->fetchColumn()) {
                $error = 'Bu SKU zaten kullanılıyor';
            }
        }
    }
    
    if (empty($error)) {

        if ($id > 0) {

            $stmt = $db->prepare("UPDATE products SET sku=?, name=?, unit=?, price=?, urun_ozeti=?, kullanim_alani=?, category_id=?, brand_id=? WHERE id=?");

            $stmt->execute([$sku,$name,$unit,$price,$urun_ozeti,$kullanim_alani,$category_id,$brand_id,$id]);



            if (!empty($_FILES['image']['name']) && is_uploaded_file($_FILES['image']['tmp_name'])) {

                $old = (string)$db->query("SELECT image FROM products WHERE id=".$id)->fetchColumn();

                $rel = product_image_store($id, $_FILES, 'image', $old ?: null);

                if ($rel) {

                    $st_img = $db->prepare("UPDATE products SET image=?, updated_at=NOW() WHERE id=?");

                    $st_img->execute([$rel, $id]);

                }

            }

        } else {

            $stmt = $db->prepare("INSERT INTO products (sku,name,unit,price,urun_ozeti,kullanim_alani,category_id,brand_id) VALUES (?,?,?,?,?,?,?,?)");

            $stmt->execute([$sku,$name,$unit,$price,$urun_ozeti,$kullanim_alani,$category_id,$brand_id]);

            $id = (int)$db->lastInsertId();



            if (!empty($_FILES['image']['name']) && is_uploaded_file($_FILES['image']['tmp_name'])) {

                $rel = product_image_store($id, $_FILES, 'image', null);

                if ($rel) {

                    $st_img = $db->prepare("UPDATE products SET image=?, updated_at=NOW() WHERE id=?");

                    $st_img->execute([$rel, $id]);

                }

            }

        }

        if (empty($error)) {
            redirect('products.php');
        }
    }

}



include __DIR__ . '/includes/header.php';



// Form (yeni/düzenle)

if ($action === 'new' || $action === 'edit') {

    $id = (int)($_GET['id'] ?? 0);

    $row = ['id'=>0,'sku'=>'','name'=>'','unit'=>'adet','price'=>'0.00','urun_ozeti'=>'','kullanim_alani'=>'','category_id'=>null,'brand_id'=>null];

    if ($action === 'edit' && $id) {

        $stmt = $db->prepare("SELECT * FROM products WHERE id=?");

        $stmt->execute([$id]);

        $row = $stmt->fetch() ?: $row;

    }

    ?>

    <div class="card">

      <h2><?= $row['id'] ? 'Ürün Düzenle' : 'Yeni Ürün' ?></h2>

      <?php if (!empty($error)): ?><div class="alert mb"><?= h($error) ?></div><?php endif; ?>

      <form method="post" enctype="multipart/form-data">

        <?php csrf_input(); ?>

        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">

        <label>SKU</label>

        <input name="sku" value="<?= h($row['sku']) ?>" placeholder="Opsiyonel">

        <label class="mt">Ad</label>

        <input name="name" value="<?= h($row['name']) ?>" required>

        <div class="row mt" style="gap:12px">

          <div style="flex:1">

            <label>Birim</label>

            <input name="unit" value="<?= h($row['unit']) ?>">

          </div>

          <div style="flex:1">

            <label>Fiyat</label>

            <input name="price" type="number" step="0.01" value="<?= h($row['price']) ?>">

          </div>

        </div>



        <div class="row mt" style="gap:12px">

          <div style="flex:1">

            <label>Kategori</label>

            <select name="category_id">

              <option value="">— Seçiniz —</option>

              <?php foreach($__cats as $c): $sel = ((int)($row['category_id'] ?? 0) === (int)$c['id']) ? 'selected' : ''; ?>

                <option value="<?= (int)$c['id'] ?>" <?= $sel ?>><?= h($c['name']) ?></option>

              <?php endforeach; ?>

            </select>

            <div class="muted"><a href="taxonomies.php?t=categories" target="_blank">Kategori yönet</a></div>

          </div>

          <div style="flex:1">

            <label>Marka</label>

            <select name="brand_id">

              <option value="">— Seçiniz —</option>

              <?php foreach($__brands as $b): $sel = ((int)($row['brand_id'] ?? 0) === (int)$b['id']) ? 'selected' : ''; ?>

                <option value="<?= (int)$b['id'] ?>" <?= $sel ?>><?= h($b['name']) ?></option>

              <?php endforeach; ?>

            </select>

            <div class="muted"><a href="taxonomies.php?t=brands" target="_blank">Marka yönet</a></div>

          </div>

        </div>



        <label class="mt">Ürün Özeti</label>

        <textarea name="urun_ozeti" rows="3"><?= h($row['urun_ozeti']) ?></textarea>

        <label class="mt">Kullanım Alanı</label>

        <textarea name="kullanim_alani" rows="3"><?= h($row['kullanim_alani']) ?></textarea>



        <div class="row mt">

          <button class="btn primary"><?= $row['id'] ? 'Güncelle' : 'Kaydet' ?></button>

          <a class="btn" href="products.php">Vazgeç</a>

        </div>



        <label class="mt">Ürün Görseli</label>

        <input type="file" name="image" accept="image/*">

        <?php if(!empty($row['image'])){ $img=(string)$row['image']; $src=(preg_match('~^https?://~',$img)||strpos($img,'/')===0)?$img:'/'.ltrim($img,'/'); echo '<div><img src="'.h($src).'" width="120" height="120" alt=""></div>'; } ?>

      </form>

    </div>

    <?php

    require __DIR__ . '/products_variations_ui.inc.php';

    include __DIR__ . '/includes/footer.php'; exit;

}



// Liste/Arama

$q = trim($_GET['q'] ?? '');

$perPage = 20;

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

if ($page < 1) $page = 1;

$offset = ($page - 1) * $perPage;



if ($q !== '') {

    $countStmt = $db->prepare("SELECT COUNT(*) FROM products WHERE name LIKE ? OR sku LIKE ?");

    $countStmt->execute(['%'.$q.'%','%'.$q.'%']);

    $total = (int)$countStmt->fetchColumn();



    $stmt = $db->prepare("SELECT * FROM products WHERE name LIKE ? OR sku LIKE ? ORDER BY id DESC LIMIT " . (int)$perPage . " OFFSET " . (int)$offset);

    $stmt->execute(['%'.$q.'%','%'.$q.'%']);

} else {

    $total = (int)$db->query("SELECT COUNT(*) FROM products")->fetchColumn();

    $stmt = $db->prepare("SELECT * FROM products ORDER BY id DESC LIMIT " . (int)$perPage . " OFFSET " . (int)$offset);

    $stmt->execute();

}

$totalPages = max(1, (int)ceil($total / $perPage));

if (!function_exists('__build_qs_page')){

  function __build_qs_page($page){

    $q = $_GET; $q['page'] = $page;

    return htmlspecialchars(http_build_query($q), ENT_QUOTES, 'UTF-8');

  }

}

$prev = max(1, $page-1);

$next = min($totalPages, $page+1);



?>

<div class="row mb">

  <a class="btn primary" href="products.php?a=new">Yeni Ürün</a>

  <form class="row" method="get">

    <input type="hidden" name="p" value="products">

    <input name="q" placeholder="Ad/sku ara..." value="<?= h($q) ?>">

    <button class="btn">Ara</button>

  </form>

</div>



<div class="card">



  <!-- ===== ÜST SAYFALAMA (KART İÇİ) ===== -->

<?php if (($totalPages ?? 1) > 1): 
  $qs = $_GET; unset($qs['page']); 
  $base = 'products.php';
  if (!empty($qs)) { $base .= '?' . http_build_query($qs); }
  if (!function_exists('__products_page_link')) {
    function __products_page_link($p,$base){ return $base . (strpos($base,'?')!==false ? '&' : '?') . 'page=' . (int)$p; }
  }
  $first_link = __products_page_link(1, $base);
  $prev_link  = __products_page_link(max(1,(int)$page-1), $base);
  $next_link  = __products_page_link(min((int)$totalPages,(int)$page+1), $base);
  $last_link  = __products_page_link((int)$totalPages, $base);
  $window = 2;
  $start = max(1, (int)$page - $window);
  $end   = min((int)$totalPages, (int)$page + $window);
?>
<div class="row" style="margin:12px 0; gap:6px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap;">
  <div class="row" style="gap:6px; flex-wrap:wrap;">
    <?php if ((int)$page > 1): ?>
      <a class="btn" href="<?= h($first_link) ?>">&laquo; İlk</a>
      <a class="btn" href="<?= h($prev_link) ?>">&lsaquo; Önceki</a>
    <?php else: ?>
      <span class="btn disabled">&laquo; İlk</span>
      <span class="btn disabled">&lsaquo; Önceki</span>
    <?php endif; ?>

    <?php for($i=$start; $i<=$end; $i++): $lnk = __products_page_link($i, $base); ?>
      <a class="btn <?= $i==(int)$page?'btn-primary':'' ?>" href="<?= h($lnk) ?>"><?= (int)$i ?></a>
    <?php endfor; ?>

    <?php if ((int)$page < (int)$totalPages): ?>
      <a class="btn" href="<?= h($next_link) ?>">Sonraki &rsaquo;</a>
      <a class="btn" href="<?= h($last_link) ?>">Son &raquo;</a>
    <?php else: ?>
      <span class="btn disabled">Sonraki &rsaquo;</span>
      <span class="btn disabled">Son &raquo;</span>
    <?php endif; ?>
  </div>

  <form method="get" class="row" style="gap:6px; align-items:center; flex:0 0 auto;">
    <label>Sayfa:</label>
    <input type="number" name="page" value="<?= (int)$page ?>" min="1" max="<?= (int)$totalPages ?>" style="width:72px">
    <?php foreach($qs as $k=>$v): ?>
      <input type="hidden" name="<?= h($k) ?>" value="<?= h($v) ?>">
    <?php endforeach; ?>
    <button class="btn">Git</button>
  </form>
</div>
<?php endif; ?>

  <!-- ===== /ÜST SAYFALAMA ===== -->



  <div class="table-responsive">

    <table>

      <tr>

        <th>ID</th>

        <th>Görsel</th>

        <th>SKU</th>

        <th>Ad</th>

        <th>Birim</th>

        <th>Fiyat</th>

        <th class="right">İşlem</th>

      </tr>

      <?php while($r = $stmt->fetch()): ?>

      <tr>

        <td><?= (int)$r['id'] ?></td>

        <td>

          <?php $img = (string)($r['image'] ?? ''); if ($img !== '') {

                $src = (preg_match('~^https?://~',$img) || strpos($img,'/')===0) ? $img : '/'.ltrim($img,'/'); ?>

            <img src="<?= h($src) ?>" width="64" height="64" alt="">

          <?php } ?>

        </td>

        <td><?= h($r['sku']) ?></td>

        <td><?= h($r['name']) ?></td>

        <td><?= h($r['unit']) ?></td>

        <td class="right"><?= number_format((float)$r['price'], 2, ',', '.') ?></td>

        <td class="right">

          <a class="btn" href="products.php?a=edit&id=<?= (int)$r['id'] ?>">Düzenle</a>

          <form method="post" action="products.php?a=delete" style="display:inline" onsubmit="return confirm('Silinsin mi?')">

            <?php csrf_input(); ?>

            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">

            <button class="btn" style="background:#450a0a;border-color:#7f1d1d">Sil</button>

          </form>

        </td>

      </tr>

      <?php endwhile; ?>

    </table>

  </div>



  <!-- ===== ALT SAYFALAMA (KART İÇİ) ===== -->

<?php if (($totalPages ?? 1) > 1): 
  $qs = $_GET; unset($qs['page']); 
  $base = 'products.php';
  if (!empty($qs)) { $base .= '?' . http_build_query($qs); }
  if (!function_exists('__products_page_link')) {
    function __products_page_link($p,$base){ return $base . (strpos($base,'?')!==false ? '&' : '?') . 'page=' . (int)$p; }
  }
  $first_link = __products_page_link(1, $base);
  $prev_link  = __products_page_link(max(1,(int)$page-1), $base);
  $next_link  = __products_page_link(min((int)$totalPages,(int)$page+1), $base);
  $last_link  = __products_page_link((int)$totalPages, $base);
  $window = 2;
  $start = max(1, (int)$page - $window);
  $end   = min((int)$totalPages, (int)$page + $window);
?>
<div class="row" style="margin:12px 0; gap:6px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap;">
  <div class="row" style="gap:6px; flex-wrap:wrap;">
    <?php if ((int)$page > 1): ?>
      <a class="btn" href="<?= h($first_link) ?>">&laquo; İlk</a>
      <a class="btn" href="<?= h($prev_link) ?>">&lsaquo; Önceki</a>
    <?php else: ?>
      <span class="btn disabled">&laquo; İlk</span>
      <span class="btn disabled">&lsaquo; Önceki</span>
    <?php endif; ?>

    <?php for($i=$start; $i<=$end; $i++): $lnk = __products_page_link($i, $base); ?>
      <a class="btn <?= $i==(int)$page?'btn-primary':'' ?>" href="<?= h($lnk) ?>"><?= (int)$i ?></a>
    <?php endfor; ?>

    <?php if ((int)$page < (int)$totalPages): ?>
      <a class="btn" href="<?= h($next_link) ?>">Sonraki &rsaquo;</a>
      <a class="btn" href="<?= h($last_link) ?>">Son &raquo;</a>
    <?php else: ?>
      <span class="btn disabled">Sonraki &rsaquo;</span>
      <span class="btn disabled">Son &raquo;</span>
    <?php endif; ?>
  </div>

  <form method="get" class="row" style="gap:6px; align-items:center; flex:0 0 auto;">
    <label>Sayfa:</label>
    <input type="number" name="page" value="<?= (int)$page ?>" min="1" max="<?= (int)$totalPages ?>" style="width:72px">
    <?php foreach($qs as $k=>$v): ?>
      <input type="hidden" name="<?= h($k) ?>" value="<?= h($v) ?>">
    <?php endforeach; ?>
    <button class="btn">Git</button>
  </form>
</div>
<?php endif; ?>

  <!-- ===== /ALT SAYFALAMA ===== -->



</div>



<?php include __DIR__ . '/includes/footer.php'; ?>