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
// --- TÜM ANA ÜRÜNLERİ ÇEK (Baba Adayları) ---
$__parents = [];
try {
    // Sadece kendisi bir varyasyon olmayan ürünleri getir
    $__parents = $db->query("SELECT id, name, sku FROM products WHERE parent_id IS NULL ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}



$action = $_GET['a'] ?? 'list';
// --- ARAMA SABİTLEME MANTIĞI (BAŞLANGIÇ) ---
$search_lock = $_SESSION['product_search_lock'] ?? false;

// 1. Kilit Açma/Kapama İsteği (Linkten gelen)
if (isset($_GET['toggle_lock'])) {
    $search_lock = !$search_lock;
    $_SESSION['product_search_lock'] = $search_lock;
    
    // Sayfayı temiz URL ile yenile (mevcut aramayı koruyarak)
    $redirQ = $_GET['q'] ?? ($_SESSION['product_last_q'] ?? '');
    redirect('products.php?q='.urlencode($redirQ));
}

// 2. Arama Terimini Belirle
$q_in_url = isset($_GET['q']); // URL'de q parametresi var mı?
$q = trim($_GET['q'] ?? '');

if ($q_in_url) {
    // Kullanıcı elle bir şey arattıysa (veya boş aratıp temizlediyse)
    $_SESSION['product_last_q'] = $q; // Hafızayı güncelle
} elseif ($search_lock && !empty($_SESSION['product_last_q'])) {
    // URL'de arama yok ama KİLİT AÇIK -> Hafızadan geri yükle
    $q = $_SESSION['product_last_q'];
}
// --- ARAMA SABİTLEME MANTIĞI (BİTİŞ) ---



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

            // --- HATA YAKALAMA VE MÜKERRER KAYIT KONTROLÜ ---
            try {
                $stmt->execute([$sku,$name,$unit,$price,$urun_ozeti,$kullanim_alani,$category_id,$brand_id,$id]);
            } catch (PDOException $e) {
                // Hata kodu 23000 (Integrity constraint violation) ise
                if ($e->getCode() == '23000') {
                    // Sayfa yapısını bozmadan şık bir uyarı bas
                    echo '<div style="font-family: sans-serif; display: flex; align-items: center; justify-content: center; height: 100vh; background: #fef2f2;">';
                    echo '  <div style="background: #fff; padding: 40px; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); text-align: center; max-width: 500px; border: 1px solid #fee2e2;">';
                    echo '      <div style="font-size: 50px; margin-bottom: 15px;">🛑</div>';
                    echo '      <h2 style="color: #b91c1c; margin-top: 0;">Bu Kod Zaten Var!</h2>';
                    echo '      <p style="color: #4b5563; line-height: 1.6;">Girmeye çalıştığınız <b>"'.h($sku).'"</b> SKU kodu (veya boş kod) sistemde başka bir ürüne ait.</p>';
                    echo '      <p style="color: #4b5563; font-size: 13px;">İpucu: Eğer varyasyon yapıyorsanız, her varyasyonun SKU kodu (veya sonuna eklenen kodu) benzersiz olmalıdır.</p>';
                    echo '      <button onclick="history.back()" style="background: #dc2626; color: #fff; border: none; padding: 12px 25px; border-radius: 6px; font-weight: bold; cursor: pointer; margin-top: 15px;">🔙 Geri Dön ve Düzelt</button>';
                    echo '  </div>';
                    echo '</div>';
                    exit; // İşlemi burada durdur
                } else {
                    throw $e; // Başka bir hataysa normal şekilde göster
                }
            }
            // --------------------------------------------------
            // --- ANA ÜRÜN BAĞLAMA (PARENT GÜNCELLEME) ---
            if (isset($_POST['parent_id'])) {
                $pid = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : NULL;
                if ($pid !== $id) { // Kendisini baba seçemez
                    $db->prepare("UPDATE products SET parent_id = ? WHERE id = ?")->execute([$pid, $id]);
                    
                    // --- HAFIZA ÖZELLİĞİ: Son seçileni hatırla ---
                    if ($pid) {
                        $_SESSION['last_selected_parent_id'] = $pid;
                    }
                }
            }
            // --- GÖRSEL SİLME KODU (BURADA OLMALI) ---
            if (isset($_POST['delete_image']) && $_POST['delete_image'] == '1') {
                $oldImg = (string)$db->query("SELECT image FROM products WHERE id=".$id)->fetchColumn();
                if ($oldImg) {
                    // 1. Yeni klasörden silmeyi dene
                    if (file_exists(__DIR__ . '/uploads/product_images/' . $oldImg)) {
                        @unlink(__DIR__ . '/uploads/product_images/' . $oldImg);
                    }
                    // 2. Eski klasörden silmeyi dene (ihtimal dahilinde)
                    if (file_exists(__DIR__ . '/' . ltrim($oldImg, '/'))) {
                        @unlink(__DIR__ . '/' . ltrim($oldImg, '/'));
                    }
                    
                    // 3. Veritabanını güncelle
                    $db->prepare("UPDATE products SET image = NULL WHERE id = ?")->execute([$id]);
                }
            }
            // ----------------------------------------



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
            <select name="unit" style="width:100%; height:40px; border:1px solid #ccc; border-radius:4px; padding:0 10px;">
                <option value="Adet" <?= (strtolower($row['unit'] ?? '') == 'adet') ? 'selected' : '' ?>>Adet</option>
                <option value="Metre" <?= (strtolower($row['unit'] ?? '') == 'metre') ? 'selected' : '' ?>>Metre</option>
            </select>
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
            <div style="background:#f0f9ff; padding:10px; border:1px solid #bae6fd; border-radius:5px; margin-bottom:15px;">
        <label style="color:#0369a1; font-weight:bold;">🔗 Ana Ürüne Bağla (Varyasyon Yap)</label>
        
        <input type="text" id="parentSearchBox" placeholder="🔍 Listede ara..." 
               style="width:100%; margin-top:5px; padding:6px; border:1px solid #bae6fd; border-radius:4px; font-size:13px;" 
               onkeyup="filterParentOptions()">

        <select name="parent_id" id="parentSelectBox" class="form-control" style="margin-top:5px; border:1px solid #0284c7; width:100%;">
            <option value="">-- Yok (Bu bir Ana Ürün) --</option>
            <?php 
                // ÖNCELİK MANTIĞI:
                // 1. Ürünün zaten bir veritabanı kaydı varsa onu kullan.
                // 2. Yoksa (veya boşsa) ve hafızada (session) bir seçim varsa onu kullan.
                $currentParentId = $row['parent_id'] ?? null;
                if (!$currentParentId && isset($_SESSION['last_selected_parent_id'])) {
                    $currentParentId = $_SESSION['last_selected_parent_id'];
                }
            ?>
            <?php foreach($__parents as $p): ?>
                <?php if($p['id'] == ($id ?? 0)) continue; // Kendisi listelenmesin ?>
                <option value="<?= $p['id'] ?>" <?= ($currentParentId == $p['id']) ? 'selected' : '' ?>>
                    <?= h($p['name']) ?> [Kod: <?= h($p['sku']) ?>]
                </option>
            <?php endforeach; ?>
        </select>
        
        <script>
        function filterParentOptions() {
            var input, filter, select, options, i, txtValue;
            input = document.getElementById("parentSearchBox");
            filter = input.value.toUpperCase();
            select = document.getElementById("parentSelectBox");
            options = select.getElementsByTagName("option");

            // Seçenekleri döngüye al
            for (i = 0; i < options.length; i++) {
                // İlk seçenek (--Yok--) her zaman görünsün
                if (i === 0) continue;
                
                txtValue = options[i].textContent || options[i].innerText;
                if (txtValue.toUpperCase().indexOf(filter) > -1) {
                    options[i].style.display = "";
                } else {
                    options[i].style.display = "none";
                }
            }
        }
        </script>
    </div>

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

        <?php if(!empty($row['image'])): 
    $img = (string)$row['image']; 
    // Yeni yol mu, eski yol mu kontrolü
    if (file_exists(__DIR__ . '/uploads/product_images/' . $img)) {
        $src = 'uploads/product_images/' . $img;
    } else {
        $src = (preg_match('~^https?://~',$img) || strpos($img,'/')===0) ? $img : '/'.ltrim($img,'/');
    }
?>
    <div style="margin-top:5px; background:#f8fafc; padding:10px; border:1px solid #e2e8f0; border-radius:6px; display:inline-block;">
        <img src="<?= h($src) ?>" width="100" height="100" style="object-fit:contain; background:#fff; border:1px solid #ddd; margin-bottom:5px; display:block;">
        <label style="color:#dc2626; font-size:13px; font-weight:bold; cursor:pointer;">
            <input type="checkbox" name="delete_image" value="1"> 🗑️ Resmi Sil
        </label>
    </div>
<?php endif; ?>

      </form>

    </div>

    <?php

    require __DIR__ . '/products_variations_ui.inc.php';

    include __DIR__ . '/includes/footer.php'; exit;

}



// Liste/Arama



$perPage = 20;

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

if ($page < 1) $page = 1;

$offset = ($page - 1) * $perPage;



// --- SIRALAMA MANTIĞI (YENİ) ---
$sort = $_GET['sort'] ?? 'id_desc';
$orderBy = "id DESC"; // Varsayılan: Son Eklenen

switch($sort) {
    case 'name_asc':  $orderBy = "name ASC"; break;  // A'dan Z'ye
    case 'name_desc': $orderBy = "name DESC"; break; // Z'den A'ya
    case 'id_asc':    $orderBy = "id ASC"; break;    // İlk Eklenen
    default:          $orderBy = "id DESC"; break;   // Son Eklenen
}

// --- VERİ ÇEKME ---
if ($q) {
    $countStmt = $db->prepare("SELECT COUNT(*) FROM products WHERE parent_id IS NULL AND (name LIKE ? OR sku LIKE ?)");
    $countStmt->execute(['%'.$q.'%','%'.$q.'%']);
    $total = (int)$countStmt->fetchColumn();

    // Sorguya $orderBy değişkenini ekledik
    $stmt = $db->prepare("SELECT * FROM products WHERE parent_id IS NULL AND (name LIKE ? OR sku LIKE ?) ORDER BY $orderBy LIMIT " . (int)$perPage . " OFFSET " . (int)$offset);
    $stmt->execute(['%'.$q.'%','%'.$q.'%']);
} else {
    $total = (int)$db->query("SELECT COUNT(*) FROM products WHERE parent_id IS NULL")->fetchColumn();
    
    // Sorguya $orderBy değişkenini ekledik
    $stmt = $db->prepare("SELECT * FROM products WHERE parent_id IS NULL ORDER BY $orderBy LIMIT " . (int)$perPage . " OFFSET " . (int)$offset);
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

  <a class="btn primary" href="products.php?a=new">➕Yeni Ürün</a>
  <a href="products_grouper.php" class="btn" style="background: #4bf63b94; color:#fff; margin-left:10px;">🧩 Ürünleri Grupla</a>

  <form class="row" method="get" style="align-items:center; gap:5px;">
    <input type="hidden" name="p" value="products">
    
    <select name="sort" onchange="this.form.submit()" style="padding:10px; border:1px solid #ccc; border-radius:4px; cursor:pointer; background:#fff;">
        <option value="id_desc" <?= ($sort??'')=='id_desc'?'selected':'' ?>>📅 Son Eklenen</option>
        <option value="name_asc" <?= ($sort??'')=='name_asc'?'selected':'' ?>>abc İsim (A-Z)</option>
        <option value="name_desc" <?= ($sort??'')=='name_desc'?'selected':'' ?>>zyx İsim (Z-A)</option>
    </select>

    <?php 
       $isLocked = $_SESSION['product_search_lock'] ?? false;
       $lockIcon = $isLocked ? '🔒' : '🔓';
       $lockStyle = $isLocked 
           ? 'background:#dcfce7; color:#166534; border:1px solid #86efac;' // Yeşil (Aktif)
           : 'background:#f1f5f9; color:#64748b; border:1px solid #cbd5e1;'; // Gri (Pasif)
       $lockTitle = $isLocked ? 'Arama Sabitlendi (Kaldırmak için tıkla)' : 'Aramayı Sabitle (Her girişte hatırla)';
    ?>
    <a href="products.php?toggle_lock=1&q=<?= urlencode($q) ?>" class="btn" title="<?= $lockTitle ?>" style="padding:10px; text-decoration:none; <?= $lockStyle ?>">
        <?= $lockIcon ?>
    </a>

    <input name="q" placeholder="Ad veya SKU ara..." value="<?= h($q) ?>" style="padding:10px; border:1px solid #ccc; border-radius:4px;">
    <button class="btn" style="padding:10px 20px;">Ara</button>
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

      <?php while($r = $stmt->fetch()): 
    // Bu ürünün varyasyonu var mı kontrol et
    $vCount = (int)$db->query("SELECT COUNT(*) FROM products WHERE parent_id = " . (int)$r['id'])->fetchColumn();
    $isMaster = ($vCount > 0);
    
    // Eğer varyasyonluysa bizim YENİ sayfaya gitsin, değilse eskiye
    $editLink = $isMaster ? 'product_master_edit.php?id=' . (int)$r['id'] : 'products.php?a=edit&id=' . (int)$r['id'];
?>
<tr>
    <td><?= (int)$r['id'] ?></td>
    <td>
        <?php 
        $img = (string)($r['image'] ?? ''); 
        if ($img !== '') {
            $src = '';
            
            // 1. Önce YENİ yükleme klasörüne bak (Sunucu tarafında kontrol et)
            // __DIR__ ile tam yolu garantiye alıyoruz
            if (file_exists(__DIR__ . '/uploads/product_images/' . $img)) {
                $src = 'uploads/product_images/' . $img;
            }
            // 2. Yeni yerde yoksa ESKİ mantığı olduğu gibi kullan (Eskiler geri gelir)
            else {
                $src = (preg_match('~^https?://~',$img) || strpos($img,'/')===0) ? $img : '/'.ltrim($img,'/');
            }
        ?>
        <img src="<?= h($src) ?>" style="width: 50px; height: 50px; object-fit: contain; background: #fff; border-radius: 4px; border: 1px solid #e2e8f0; padding: 2px;">
        <?php } ?>
    </td>
    <td>
        <?= h($r['sku']) ?>
        <?php if($isMaster): ?>
            <div style="font-size:11px; color:#2563eb; font-weight:bold; margin-top:2px; background:#eff6ff; display:inline-block; padding:2px 6px; border-radius:4px;">
                🧬 <?= $vCount ?> Varyasyon
            </div>
        <?php endif; ?>
    </td>
    <td>
        <a href="<?= $editLink ?>" style="text-decoration:none; color:#333; font-weight:500;">
            <?= h($r['name']) ?>
        </a>
    </td>
    <td><?= h($r['unit']) ?></td>
    <td class="right" style="font-family:monospace; font-size:14px;"><?= number_format((float)$r['price'], 2, ',', '.') ?></td>
    <td class="right">
        <a class="btn" href="<?= $editLink ?>" style="<?= $isMaster ? 'background:#dbeafe; color:#1e40af; border:1px solid #93c5fd;' : '' ?>">
            <?= $isMaster ? '✨ Yönet' : 'Düzenle' ?>
        </a>

        <?php if(!$isMaster): ?>
        <form method="post" action="products.php?a=delete" style="display:inline" onsubmit="return confirm('Silinsin mi?')">
            <?php csrf_input(); ?>
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <button class="btn" style="background:#fff1f2; color:#be123c; border-color:#fda4af;">Sil</button>
        </form>
        <?php endif; ?>
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