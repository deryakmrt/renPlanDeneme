<?php
require_once __DIR__ . '/includes/helpers.php';

// === Variations & Attributes Addon START ===
// CSRF guards: use helpers if available; otherwise no-op to avoid redeclare
if (!function_exists('csrf_field')) { function csrf_field($action='global'){ /* no-op */ } }
if (!function_exists('csrf_check')) { function csrf_check($action='global', $redir=null){ return true; } }

if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

if (!function_exists('h')) { function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); } }
if (!function_exists('method')) { function method($m){ return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === strtoupper($m); } }
if (!function_exists('redirect')) { function redirect($to){ header('Location: '.$to); exit; } }

// Ensure DB handle early if not set yet
if (!isset($db) && function_exists('pdo')) { $db = pdo(); }

// Thumb helpers (guarded)
if (!function_exists('thumb_path')) {
  function thumb_path($imagePath, $size){
    $dot = strrpos($imagePath, '.');
    if ($dot === false) return $imagePath . '-' . $size;
    return substr($imagePath, 0, $dot) . '-' . $size . substr($imagePath, $dot);
  }
}
if (!function_exists('ensure_thumbs_for')) {
  function ensure_thumbs_for($imageUrlRel){
    if (!$imageUrlRel || !extension_loaded('gd')) return;
    $sizes = ['300x300' => [300,300], '96x96' => [96,96]];
    foreach ($sizes as $key => $wh) {
        $tRel = thumb_path($imageUrlRel, $key);
        $srcAbs = __DIR__ . '/' . ltrim($imageUrlRel,'/');
        $tAbs   = __DIR__ . '/' . ltrim($tRel,'/');
        if (!is_file($tAbs) && is_file($srcAbs)) {
            $info = @getimagesize($srcAbs); if(!$info) continue;
            $w = $info[0]; $h = $info[1];
            $src = null;
            $mime = strtolower($info['mime'] ?? '');
            if ($mime === 'image/jpeg' || $mime === 'image/jpg') { $src=@imagecreatefromjpeg($srcAbs); }
            elseif ($mime === 'image/png') { $src=@imagecreatefrompng($srcAbs); if($src) imagesavealpha($src,true); }
            elseif ($mime === 'image/gif') { $src=@imagecreatefromgif($srcAbs); if($src) imagesavealpha($src,true); }
            elseif ($mime === 'image/webp' && function_exists('imagecreatefromwebp')) { $src=@imagecreatefromwebp($srcAbs); if($src && function_exists('imagepalettetotruecolor')) @imagepalettetotruecolor($src); }
            if($src){
              $tw=$wh[0]; $th=$wh[1];
              $scale = max($tw/max(1,$w), $th/max(1,$h));
              $nw = (int)ceil($w*$scale); $nh=(int)ceil($h*$scale);
              $tmp=imagecreatetruecolor($nw,$nh); imagealphablending($tmp,false); imagesavealpha($tmp,true);
              imagecopyresampled($tmp,$src,0,0,0,0,$nw,$nh,$w,$h);
              $x=max(0,(int)floor(($nw-$tw)/2)); $y=max(0,(int)floor(($nh-$th)/2));
              $dst=imagecreatetruecolor($tw,$th); imagealphablending($dst,false); imagesavealpha($dst,true);
              imagecopy($dst,$tmp,0,0,$x,$y,$tw,$th);
              if ($mime === 'image/png' || $mime === 'image/gif') { @imagepng($dst,$tAbs,6); }
              else { @imagejpeg($dst,$tAbs,85); }
              @imagedestroy($src); @imagedestroy($tmp); @imagedestroy($dst);
            }
        }
    }
  }
}

// Attribute helpers
if (!function_exists('attributes_with_terms')) {
  function attributes_with_terms($db){
    try { $attrs = $db->query("SELECT id, name FROM product_attributes ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC); }
    catch (Throwable $e) { return []; }
    foreach ($attrs as &$a){
      $st = $db->prepare("SELECT id, name FROM product_attribute_terms WHERE attribute_id=? ORDER BY name ASC");
      $st->execute([$a['id']]);
      $a['terms'] = $st->fetchAll(PDO::FETCH_ASSOC);
    }
    return $attrs;
  }
}
if (!function_exists('product_term_map')) {
  function product_term_map($db,$pid){
    $map = [];
    try{
      $st=$db->prepare("SELECT attribute_id, term_id FROM product_attribute_values WHERE product_id=?");
      $st->execute([$pid]);
      foreach($st->fetchAll(PDO::FETCH_ASSOC) as $r){
        $aid=(int)$r['attribute_id']; $tid=(int)$r['term_id'];
        if(!isset($map[$aid])) $map[$aid]=[];
        $map[$aid][$tid]=true;
      }
    }catch(Throwable $e){}
    return $map;
  }
}
if (!function_exists('set_product_terms')) {
  function set_product_terms($db,$pid,$selected){
    foreach($selected as $aid=>$tids){
      $db->prepare("DELETE FROM product_attribute_values WHERE product_id=? AND attribute_id=?")->execute([$pid, (int)$aid]);
      foreach($tids as $tid){
        $db->prepare("INSERT INTO product_attribute_values (product_id, attribute_id, term_id) VALUES (?,?,?)")->execute([$pid, (int)$aid, (int)$tid]);
      }
    }
  }
}

// Variation helpers
if (!function_exists('load_variations')) {
  function load_variations($db,$pid){
    $st=$db->prepare("SELECT * FROM product_variations WHERE product_id=? ORDER BY id ASC");
    $st->execute([$pid]);
    $vars=$st->fetchAll(PDO::FETCH_ASSOC);
    if(!$vars) return [];
    $ids = array_map(function($r){return (int)$r['id'];}, $vars);
    $in = implode(',', array_fill(0,count($ids),'?'));
    $sql = "SELECT vo.variation_id, vo.attribute_id, vo.term_id, a.name AS attr_name, t.name AS term_name
            FROM product_variation_options vo
            JOIN product_attributes a ON a.id = vo.attribute_id
            JOIN product_attribute_terms t ON t.id = vo.term_id
            WHERE vo.variation_id IN ($in)
            ORDER BY a.name, t.name";
    $st2=$db->prepare($sql); $st2->execute($ids);
    $opt=[];
    foreach($st2->fetchAll(PDO::FETCH_ASSOC) as $r){
      $vid=(int)$r['variation_id'];
      if(!isset($opt[$vid])) $opt[$vid]=[];
      $opt[$vid][]=$r;
    }
    foreach($vars as &$v){ $v['options']=$opt[(int)$v['id']] ?? []; }
    return $vars;
  }
}
if (!function_exists('combo_exists')) {
  function combo_exists($db,$pid,$combo){
    $vars = load_variations($db,$pid);
    foreach($vars as $v){
      $vmap = [];
      foreach($v['options'] as $o){ $vmap[(int)$o['attribute_id']] = (int)$o['term_id']; }
      if(count($vmap) !== count($combo)) continue;
      $ok = true;
      foreach($combo as $aid=>$tid){
        if(!isset($vmap[$aid]) || $vmap[$aid] != $tid){ $ok=false; break; }
      }
      if($ok) return (int)$v['id'];
    }
    return 0;
  }
}
if (!function_exists('generate_variations')) {
  function generate_variations($db,$pid){
    $st=$db->prepare("SELECT attribute_id, term_id FROM product_attribute_values WHERE product_id=? ORDER BY attribute_id");
    $st->execute([$pid]);
    $rows=$st->fetchAll(PDO::FETCH_ASSOC);
    if(!$rows) return 0;
    $byAttr=[];
    foreach($rows as $r){
      $aid=(int)$r['attribute_id']; $tid=(int)$r['term_id'];
      if(!isset($byAttr[$aid])) $byAttr[$aid]=[];
      $byAttr[$aid][]=$tid;
    }
    if(!$byAttr) return 0;
    $attrs = array_keys($byAttr); sort($attrs);
    // Build cartesian product of selected term IDs
    $combos = [[]];
    foreach($attrs as $aid){
      $next = [];
      foreach($combos as $c){
        foreach($byAttr[$aid] as $tid){
          $c2 = $c; $c2[$aid] = $tid; $next[] = $c2;
        }
      }
      $combos = $next;
    }
    $created=0;
    foreach($combos as $combo){
      if(combo_exists($db,$pid,$combo)) continue;
      $db->prepare("INSERT INTO product_variations (product_id) VALUES (?)")->execute([$pid]);
      $vid = (int)$db->lastInsertId();
      foreach($combo as $aid=>$tid){
        $db->prepare("INSERT INTO product_variation_options (variation_id, attribute_id, term_id) VALUES (?,?,?)")->execute([$vid,(int)$aid,(int)$tid]);
      }
      $created++;
    }
    return $created;
  }
}

// Action handlers (only if DB is ready)
if (isset($db)) {
  $__a = $_GET['a'] ?? null;
  if ($__a === 'attrs_save' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $pid = (int)($_POST['product_id'] ?? 0);
    csrf_check('attrs_save', 'products.php?a=edit&id='.$pid);
    if($pid>0){
      $selected = [];
      foreach(($_POST['attr'] ?? []) as $aid => $termIds){
        $aid = (int)$aid; if(!is_array($termIds)) continue;
        $vals=[]; foreach($termIds as $tid){ $tid=(int)$tid; if($tid>0) $vals[]=$tid; }
        $vals = array_values(array_unique($vals));
        if($vals) $selected[$aid]=$vals;
      }
      try{ set_product_terms($db,$pid,$selected); $_SESSION['flash_success']='Öznitelik/terimler kaydedildi.'; }
      catch(Throwable $e){ $_SESSION['flash_error']='Öznitelik kaydında hata: '.$e->getMessage(); }
    }
    redirect('products.php?a=edit&id='.$pid);
  }
  if ($__a === 'vars_generate' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $pid=(int)($_POST['product_id'] ?? 0);
    csrf_check('vars_generate','products.php?a=edit&id='.$pid);
    if($pid>0){
      try{ $n=generate_variations($db,$pid); $_SESSION['flash_success']=$n.' varyasyon oluşturuldu.'; }
      catch(Throwable $e){ $_SESSION['flash_error']='Varyasyon üretim hatası: '.$e->getMessage(); }
    }
    redirect('products.php?a=edit&id='.$pid);
  }
  if ($__a === 'vars_bulk_update' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $pid=(int)($_POST['product_id'] ?? 0);
    csrf_check('vars_bulk_update','products.php?a=edit&id='.$pid);
    foreach(($_POST['var'] ?? []) as $vid=>$data){
      $vid=(int)$vid;
      $sku = trim($data['sku'] ?? '');
      $price = $data['price'] !== '' ? (float)$data['price'] : null;
      $sale_price = $data['sale_price'] !== '' ? (float)$data['sale_price'] : null;
      $stock = $data['stock_qty'] !== '' ? (int)$data['stock_qty'] : null;
      $is_active = isset($data['is_active']) ? 1 : 0;
      if($sku!==''){
        $st=$db->prepare("SELECT id FROM product_variations WHERE sku=? AND id<>? LIMIT 1");
        $st->execute([$sku,$vid]);
        if($st->fetchColumn()){ $_SESSION['flash_error']='Varyasyon SKU çakışması: '.$sku; continue; }
      } else { $sku=null; }
      try{
        $st=$db->prepare("UPDATE product_variations SET sku=?, price=?, sale_price=?, stock_qty=?, is_active=? WHERE id=?");
        $st->execute([$sku,$price,$sale_price,$stock,$is_active,$vid]);
      }catch(Throwable $e){
        $_SESSION['flash_error']='Varyasyon güncelleme hatası: '.$e->getMessage();
      }
      if (!empty($_FILES['var_image']['name'][$vid]) && is_uploaded_file($_FILES['var_image']['tmp_name'][$vid])){
        $uploadDir = __DIR__ . '/uploads/products'; if (!is_dir($uploadDir)) @mkdir($uploadDir,0775,True);
        $uploadUrl = 'uploads/products';
        $name = $_FILES['var_image']['name'][$vid];
        $tmp  = $_FILES['var_image']['tmp_name'][$vid];
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION)); if(!$ext) $ext='jpg';
        $rand = function_exists('random_bytes') ? bin2hex(random_bytes(2)) : (string)mt_rand(1000,9999);
        $fname = 'v_'.$vid.'_'.date('Ymd_His').'_'.$rand.'.'.$ext;
        $dest = $uploadDir.'/'.$fname;
        if (move_uploaded_file($tmp, $dest)) {
          $rel = $uploadUrl.'/'.$fname;
          if (function_exists('ensure_thumbs_for')) ensure_thumbs_for($rel);
          $db->prepare("UPDATE product_variations SET image=? WHERE id=?")->execute([$rel,$vid]);
        }
      }
    }
    if(empty($_SESSION['flash_error'])) $_SESSION['flash_success']='Varyasyonlar güncellendi.';
    redirect('products.php?a=edit&id='.$pid);
  }
  if ($__a === 'var_delete' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $pid=(int)($_POST['product_id'] ?? 0);
    $vid=(int)($_POST['variation_id'] ?? 0);
    csrf_check('var_delete','products.php?a=edit&id='.$pid);
    if($vid>0){
      try{
        $st=$db->prepare("SELECT image FROM product_variations WHERE id=?"); $st->execute([$vid]);
        $img=$st->fetchColumn();
        if($img){
          $base = __DIR__ . '/' . ltrim($img,'/');
          $t300 = __DIR__ . '/' . ltrim(thumb_path($img,'300x300'),'/');
          $t96  = __DIR__ . '/' . ltrim(thumb_path($img,'96x96'),'/');
          if(is_file($base)) { @unlink($base); }
          if(is_file($t300)) { @unlink($t300); }
          if(is_file($t96)) { @unlink($t96); }
        }
        $db->prepare("DELETE FROM product_variations WHERE id=?")->execute([$vid]);
        $_SESSION['flash_success']='Varyasyon silindi.';
      }catch(Throwable $e){
        $_SESSION['flash_error']='Varyasyon silme hatası: '.$e->getMessage();
      }
    }
    redirect('products.php?a=edit&id='.$pid);
  }
}
// === Variations & Attributes Addon END ===

// CSRF fonksiyonları helpers.php'de varsa onları kullan; yoksa no-op fallback tanımla (redeclare korumalı)
if (!function_exists('csrf_field')) { function csrf_field($action='global'){ /* no-op */ } }
if (!function_exists('csrf_check')) { function csrf_check($action='global', $redir=null){ return true; } }

require_login();

// Debug (local.php ile açılabilir)
$__local = __DIR__ . '/includes/local.php';
if (file_exists($__local)) { require_once $__local; }
if (!defined('APP_DEBUG')) { define('APP_DEBUG', false); }
if (APP_DEBUG) { @ini_set('display_errors', 1); @error_reporting(E_ALL); }

// ===== Minimal fallbacks =====
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
if (!function_exists('h')) { function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); } }
if (!function_exists('method')) { function method($m){ return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === strtoupper($m); } }
if (!function_exists('redirect')) { function redirect($to){ header('Location: '.$to); exit; } }
if (!function_exists('_pp_random_bytes')) { function _pp_random_bytes($len){ if(function_exists('random_bytes')) return random_bytes($len); if(function_exists('openssl_random_pseudo_bytes')) return openssl_random_pseudo_bytes($len); $b=''; for($i=0;$i<$len;$i++){$b.=chr(mt_rand(0,255));} return $b; } }

if (!function_exists('pdo')) {
  http_response_code(500);
  echo "<h2>Hata: pdo() fonksiyonu bulunamadı</h2><p><code>includes/helpers.php</code> içinde pdo() tanımlı olmalı.</p>";
  exit;
}
$db = pdo();

$action = $_GET['a'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

/** ===== Upload config ===== */
$uploadDir = __DIR__ . '/uploads/products';
$uploadUrl = 'uploads/products';

/** ===== Utils ===== */
function ensure_upload_dir($dir){
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
}
function find_or_create_taxonomy($db,$table,$name){
    if(!$name) return null; $name=trim($name); if($name==='') return null;
    $stmt=$db->prepare("SELECT id FROM {$table} WHERE name=? LIMIT 1"); $stmt->execute([$name]);
    $row=$stmt->fetch(PDO::FETCH_ASSOC); if($row) return (int)$row['id'];
    $ins=$db->prepare("INSERT INTO {$table}(name) VALUES(?)"); $ins->execute([$name]); return (int)$db->lastInsertId();
}
function load_taxonomy($db,$table){ try{ $s=$db->query("SELECT id,name FROM {$table} ORDER BY name ASC"); return $s->fetchAll(PDO::FETCH_ASSOC);}catch(Throwable $e){ return []; } }
function product_find($db,$id){ $s=$db->prepare("SELECT * FROM products WHERE id=? LIMIT 1"); $s->execute([$id]); return $s->fetch(PDO::FETCH_ASSOC); }
function sku_exists($db, $sku, $excludeId = 0){
    if ($sku === null) return false;
    $sku = trim($sku);
    if ($sku === '') return false;
    $sql = "SELECT id FROM products WHERE sku = ?";
    $args = [$sku];
    if ($excludeId) { $sql .= " AND id <> ?"; $args[] = (int)$excludeId; }
    $sql .= " LIMIT 1"; $st = $db->prepare($sql); $st->execute($args);
    return (bool)$st->fetchColumn();
}
function product_save($db,$data,$id=0){
    if($id){
        $sql="UPDATE products SET name=?,description=?,sku=?,unit=?,image=?,category_id=?,brand_id=? WHERE id=?";
        $s=$db->prepare($sql);
        $s->execute([$data['name'],$data['description'],$data['sku'],$data['unit'],$data['image'],$data['category_id'],$data['brand_id'],$id]);
        return $id;
    }else{
        $sql="INSERT INTO products (name,description,sku,unit,image,category_id,brand_id) VALUES (?,?,?,?,?,?,?)";
        $s=$db->prepare($sql);
        $s->execute([$data['name'],$data['description'],$data['sku'],$data['unit'],$data['image'],$data['category_id'],$data['brand_id']]);
        return (int)$db->lastInsertId();
    }
}

/** ===== Image helpers (thumb generation) ===== */
function _img_load($path, $mime){
    if (!extension_loaded('gd')) return null;
    switch (strtolower($mime)) {
        case 'image/jpeg':
        case 'image/jpg':
            $im = @imagecreatefromjpeg($path);
            if (function_exists('exif_read_data')) {
                $exif = @exif_read_data($path);
                if (!empty($exif['Orientation'])) {
                    switch ($exif['Orientation']) {
                        case 3: $im = imagerotate($im, 180, 0); break;
                        case 6: $im = imagerotate($im, -90, 0); break;
                        case 8: $im = imagerotate($im, 90, 0); break;
                    }
                }
            }
            return $im;
        case 'image/png':
            $im = @imagecreatefrompng($path);
            if ($im) { imagesavealpha($im, true); }
            return $im;
        case 'image/gif':
            $im = @imagecreatefromgif($path);
            if ($im) { imagesavealpha($im, true); }
            return $im;
        case 'image/webp':
            if (function_exists('imagecreatefromwebp')) {
                $im = @imagecreatefromwebp($path);
                if ($im) {
                    if (function_exists('imagepalettetotruecolor')) { @imagepalettetotruecolor($im); }
                    imagesavealpha($im, true);
                }
                return $im;
            }
            return null;
        default:
            return null;
    }
}
function _img_save($im, $path, $mime, $quality=85){
    if (!extension_loaded('gd')) return false;
    switch (strtolower($mime)) {
        case 'image/jpeg':
        case 'image/jpg':
            return @imagejpeg($im, $path, $quality);
        case 'image/png':
            imagealphablending($im, false);
            imagesavealpha($im, true);
            return @imagepng($im, $path, 6);
        case 'image/gif':
            $path = preg_replace('/\.gif$/i', '.png', $path);
            imagealphablending($im, false);
            imagesavealpha($im, true);
            return @imagepng($im, $path, 6);
        case 'image/webp':
            if (function_exists('imagewebp')) { return @imagewebp($im, $path, $quality); }
            $path = preg_replace('/\.(webp)$/i', '.jpg', $path);
            return @imagejpeg($im, $path, $quality);
        default:
            return false;
    }
}
function create_thumb_cover($srcPath, $destPath, $targetW, $targetH){
    if (!extension_loaded('gd')) return false;
    $info = @getimagesize($srcPath);
    if (!$info) return false;
    list($w, $h) = $info;
    $mime = $info['mime'] ?? 'image/jpeg';
    $src = _img_load($srcPath, $mime);
    if (!$src) return false;

    $scale = max($targetW / max(1,$w), $targetH / max(1,$h));
    $newW = (int)ceil($w * $scale);
    $newH = (int)ceil($h * $scale);

    $tmp = imagecreatetruecolor($newW, $newH);
    imagealphablending($tmp, false);
    imagesavealpha($tmp, true);
    imagecopyresampled($tmp, $src, 0, 0, 0, 0, $newW, $newH, $w, $h);

    $x = max(0, (int)floor(($newW - $targetW) / 2));
    $y = max(0, (int)floor(($newH - $targetH) / 2));
    $dst = imagecreatetruecolor($targetW, $targetH);
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    imagecopy($dst, $tmp, 0, 0, $x, $y, $targetW, $targetH);

    $ok = _img_save($dst, $destPath, $mime, 85);
    @imagedestroy($src); @imagedestroy($tmp); @imagedestroy($dst);
    return $ok;
}
function thumb_path($imagePath, $size){
    $dot = strrpos($imagePath, '.');
    if ($dot === false) return $imagePath . '-' . $size;
    return substr($imagePath, 0, $dot) . '-' . $size . substr($imagePath, $dot);
}
function ensure_thumbs_for($imageUrlRel){
    if (!$imageUrlRel || !extension_loaded('gd')) return;
    $sizes = ['300x300' => [300,300], '96x96' => [96,96]];
    foreach ($sizes as $key => $wh) {
        $tRel = thumb_path($imageUrlRel, $key);
        $srcAbs = __DIR__ . '/' . ltrim($imageUrlRel,'/');
        $tAbs   = __DIR__ . '/' . ltrim($tRel,'/');
        if (!is_file($tAbs) && is_file($srcAbs)) {
            @create_thumb_cover($srcAbs, $tAbs, $wh[0], $wh[1]);
        }
    }
}

/** ===== Delete ===== */
if($action==='delete' && method('POST')){
    csrf_check('product_delete', 'products.php');
    $id=(int)($_POST['id'] ?? 0);
    if($id){
        $row=product_find($db,$id);
        if($row && !empty($row['image'])){
            $base = __DIR__ . '/' . ltrim($row['image'],'/');
            $t300 = __DIR__ . '/' . ltrim(thumb_path($row['image'],'300x300'),'/');
            $t96  = __DIR__ . '/' . ltrim(thumb_path($row['image'],'96x96'),'/');
            if(is_file($base)) { @unlink($base); }
            if(is_file($t300)) { @unlink($t300); }
            if(is_file($t96)) { @unlink($t96); }
        }
        $s=$db->prepare("DELETE FROM products WHERE id=?"); $s->execute([$id]);
    }
    redirect('products.php'); exit;
}

/** ===== Save ===== */
if($action==='save' && method('POST')){
    $id=(int)($_POST['id'] ?? 0);
    csrf_check('product_save', $id ? ('products.php?a=edit&id='.$id) : 'products.php?a=new');

    $name=trim($_POST['name'] ?? '');
    $description=trim($_POST['description'] ?? '');
    $sku=trim($_POST['sku'] ?? '');
    $unit=trim($_POST['unit'] ?? '');
    $category_id = isset($_POST['category_id']) && $_POST['category_id']!=='' ? (int)$_POST['category_id'] : null;
    $brand_id    = isset($_POST['brand_id']) && $_POST['brand_id']!=='' ? (int)$_POST['brand_id'] : null;

    $pending_new_category = trim($_POST['new_category'] ?? '');
    $pending_new_brand    = trim($_POST['new_brand'] ?? '');

    $errors=[];
    if($name==='') $errors[]='Ürün adı zorunlu.';
    if($sku !== '' && sku_exists($db, $sku, $id)){
        $errors[] = 'Bu SKU zaten kullanılıyor. Lütfen farklı bir SKU girin.';
    }
    if($errors){
        $_SESSION['flash_error']=implode('\n',$errors);
        $_SESSION['form_data']=[
            'id'=>$id,'name'=>$name,'description'=>$description,'sku'=>$sku,'unit'=>$unit,
            'category_id'=>$category_id,'brand_id'=>$brand_id,
            'new_category'=>$pending_new_category,'new_brand'=>$pending_new_brand
        ];
        if($id) redirect('products.php?a=edit&id='.$id); else redirect('products.php?a=new'); exit;
    }

    if($pending_new_category!==''){ $category_id = find_or_create_taxonomy($db,'product_categories',$pending_new_category); }
    if($pending_new_brand!==''){ $brand_id = find_or_create_taxonomy($db,'product_brands',$pending_new_brand); }

    $imagePath=null; ensure_upload_dir($uploadDir);
    if(!empty($_FILES['image']['name']) && is_uploaded_file($_FILES['image']['tmp_name'])){
        $ext=strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $ext = $ext ?: 'jpg';
        $fname='p_'.date('Ymd_His').'_' . bin2hex(_pp_random_bytes(2)) . '.' . $ext;
        $dest=$uploadDir.'/'.$fname;
        if(move_uploaded_file($_FILES['image']['tmp_name'],$dest)){
            $imagePath=$uploadUrl.'/'.$fname;
            ensure_thumbs_for($imagePath);
        }
    }
    if($id && !$imagePath){
        $row=product_find($db,$id);
        $imagePath=$row?($row['image'] ?? null):null;
        if ($imagePath) ensure_thumbs_for($imagePath);
    }

    $payload=['name'=>$name,'description'=>$description,'sku'=>$sku?:null,'unit'=>$unit?:null,'image'=>$imagePath,'category_id'=>$category_id,'brand_id'=>$brand_id];

    try {
        $savedId=product_save($db,$payload,$id);
        $_SESSION['flash_success']=$id?'Ürün güncellendi.':'Ürün eklendi.';
        redirect('products.php'); exit;
    } catch (Throwable $e) {
        $msg = $e->getMessage();
        if (stripos($msg, 'duplicate') !== false && stripos($msg, 'sku') !== false) {
            $_SESSION['flash_error'] = 'Bu SKU zaten kullanılıyor (veritabanı). Lütfen farklı bir SKU girin.';
        } else {
            $_SESSION['flash_error'] = 'Kaydetme sırasında hata oluştu: ' . $msg;
        }
        $_SESSION['form_data']=[
            'id'=>$id,'name'=>$name,'description'=>$description,'sku'=>$sku,'unit'=>$unit,
            'category_id'=>$category_id,'brand_id'=>$brand_id,
            'new_category'=>$pending_new_category,'new_brand'=>$pending_new_brand
        ];
        if($id) redirect('products.php?a=edit&id='.$id); else redirect('products.php?a=new'); exit;
    }
}

/** ===== Taxonomies ===== */
$categories=load_taxonomy($db,'product_categories');
$brands=load_taxonomy($db,'product_brands');

/** ===== List with pagination & sorting ===== */
if($action==='list'){
    $q  = trim($_GET['q'] ?? '');
    $fc = isset($_GET['category']) && $_GET['category']!=='' ? (int)$_GET['category'] : null;
    $fb = isset($_GET['brand']) && $_GET['brand']!=='' ? (int)$_GET['brand'] : null;

    $per = max(1, (int)($_GET['per'] ?? 20));
    $page = max(1, (int)($_GET['page'] ?? 1));
    $sort = $_GET['sort'] ?? 'id';
    $dir  = strtolower($_GET['dir'] ?? 'desc');
    $allowed = ['id'=>'p.id','name'=>'p.name','sku'=>'p.sku'];
    if(!isset($allowed[$sort])) $sort='id';
    $dir = ($dir==='asc') ? 'ASC' : 'DESC';
    $offset = ($page-1)*$per;

    $where=[]; $args=[]; $countArgs=[];
    if($q!==''){ $where[]="(p.name LIKE ? OR p.sku LIKE ?)"; $args[]='%'.$q+'%'; $args[]='%'.$q+'%'; $countArgs=$args; }
    if($fc){ $where[]="p.category_id = ?"; $args[]=$fc; $countArgs[]=$fc; }
    if($fb){ $where[]="p.brand_id = ?"; $args[]=$fb; $countArgs[]=$fb; }

    $whereSql = $where ? (" WHERE ".implode(" AND ", $where)) : "";

    try {
        $countSql = "SELECT COUNT(*) FROM products p" . $whereSql;
        $sc = $db->prepare($countSql); $sc->execute($countArgs); $total = (int)$sc->fetchColumn();
    } catch (Throwable $e) {
        $total = 0;
        $_SESSION['flash_error'] = 'Liste yüklenirken hata: '.$e->getMessage();
    }
    $pages = max(1, (int)ceil(max(0,$total) / $per));

    $sql = "SELECT p.*, c.name AS category_name, b.name AS brand_name
            FROM products p
            LEFT JOIN product_categories c ON c.id = p.category_id
            LEFT JOIN product_brands b ON b.id = p.brand_id" . $whereSql .
            " ORDER BY {$allowed[$sort]} {$dir} LIMIT " . (int)$per . " OFFSET " . (int)$offset;

    try {
        $st = $db->prepare($sql); $st->execute($args); $rows=$st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $rows=[];
        $_SESSION['flash_error'] = 'Kayıtlar çekilirken hata: '.$e->getMessage();
    }

    function qbuild($params){
        $base = $_GET; foreach($params as $k=>$v){ if($v===null){ unset($base[$k]); } else { $base[$k]=$v; } }
        return 'products.php?' . http_build_query($base);
    }
    ?>
    <?php include __DIR__ . '/includes/header.php'; ?>
    <div class="row mb">
      <a class="btn primary" href="products.php?a=new">Yeni Ürün</a>
      <form class="row" method="get" style="gap:.5rem;">
        <input type="hidden" name="a" value="list">
        <input name="q" placeholder="Ad/SKU ara…" value="<?= h($q) ?>">
        <select name="category">
          <option value="">Kategori (tümü)</option>
          <?php foreach ($categories as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= $fc===(int)$c['id']?'selected':'' ?>><?= h($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <select name="brand">
          <option value="">Marka (tümü)</option>
          <?php foreach ($brands as $b): ?>
            <option value="<?= (int)$b['id'] ?>" <?= $fb===(int)$b['id']?'selected':'' ?>><?= h($b['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <select name="per" title="Sayfa başına">
          <?php foreach ([10,20,50,100] as $opt): ?>
            <option value="<?= $opt ?>" <?= $per===$opt?'selected':'' ?>><?= $opt ?>/sayfa</option>
          <?php endforeach; ?>
        </select>
        <button class="btn">Ara</button>
      </form>
    </div>

    <?php if (!empty($_SESSION['flash_success'])): ?>
      <div class="alert success"><?= h($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?></div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['flash_error'])): ?>
      <div class="alert danger"><?= nl2br(h($_SESSION['flash_error'])); unset($_SESSION['flash_error']); ?></div>
    <?php endif; ?>

    <div class="card">
      <table class="table">
        <thead>
          <tr>
            <th>Görsel</th>
            <th><a href="<?= h(qbuild(['sort'=>'name','dir'=>$sort==='name' && $dir==='ASC' ? 'desc':'asc','page'=>1])) ?>">Ürün</a></th>
            <th><a href="<?= h(qbuild(['sort'=>'sku','dir'=>$sort==='sku' && $dir==='ASC' ? 'desc':'asc','page'=>1])) ?>">SKU</a></th>
            <th>Birim</th>
            <th>Kategori</th>
            <th>Marka</th>
            <th><a href="<?= h(qbuild(['sort'=>'id','dir'=>$sort==='id' && $dir==='ASC' ? 'desc':'asc','page'=>1])) ?>">#</a></th>
            <th style="width:160px"></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r): $thumb = $r['image'] ? thumb_path($r['image'], '96x96') : null; ?>
          <tr>
            <td><?php if (!empty($r['image'])): ?><img src="<?= h($thumb) ?>" alt="" style="width:48px;height:48px;object-fit:cover;border-radius:6px;"><?php endif; ?></td>
            <td><?= h($r['name']) ?></td>
            <td><?= h($r['sku']) ?></td>
            <td><?= h($r['unit']) ?></td>
            <td><?= h($r['category_name']) ?></td>
            <td><?= h($r['brand_name']) ?></td>
            <td><?= (int)$r['id'] ?></td>
            <td class="t-right">
              <a class="btn" href="products.php?a=edit&id=<?= (int)$r['id'] ?>">Düzenle</a>
              <form method="post" action="products.php?a=delete" style="display:inline" onsubmit="return confirm('Silinsin mi?')">
                <?php csrf_field('product_delete'); ?>
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <button class="btn danger">Sil</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="8" class="t-center muted">Kayıt bulunamadı.</td></tr><?php endif; ?>
        </tbody>
      </table>

      <div class="row" style="justify-content:space-between; align-items:center; padding: .5rem 1rem;">
        <div class="muted">Toplam: <?= (int)$total ?> | Sayfa <?= (int)$page ?>/<?= (int)$pages ?></div>
        <div class="row" style="gap:.25rem;">
          <?php if($page>1): ?><a class="btn" href="<?= h(qbuild(['page'=>1])) ?>">&laquo; İlk</a><a class="btn" href="<?= h(qbuild(['page'=>$page-1])) ?>">&lsaquo; Önceki</a><?php endif; ?>
          <?php for($p=max(1,$page-3); $p<=min($pages,$page+3); $p++): ?>
            <a class="btn <?= $p===$page?'primary':'' ?>" href="<?= h(qbuild(['page'=>$p])) ?>"><?= (int)$p ?></a>
          <?php endfor; ?>
          <?php if($page<$pages): ?><a class="btn" href="<?= h(qbuild(['page'=>$page+1])) ?>">Sonraki &rsaquo;</a><a class="btn" href="<?= h(qbuild(['page'=>$pages])) ?>">Son &raquo;</a><?php endif; ?>
        </div>
      </div>
    </div>
    <?php include __DIR__ . '/includes/footer.php'; ?>
    <?php exit; } // list end ?>

<?php
/** ===== Form (new/edit) ===== */
$row=['id'=>0,'name'=>'','description'=>'','sku'=>'','unit'=>'','image'=>'','category_id'=>null,'brand_id'=>null];
if($action==='edit' && $id){ $f=product_find($db,$id); if($f) { $row=$f; if(!empty($row['image'])) ensure_thumbs_for($row['image']); } }

if (!empty($_SESSION['form_data'])) {
    $row = array_merge($row, $_SESSION['form_data']);
    unset($_SESSION['form_data']);
}

include __DIR__ . '/includes/header.php';
?>
<?php if (!empty($_SESSION['flash_error'])): ?>
  <div class="alert danger"><?= nl2br(h($_SESSION['flash_error'])); unset($_SESSION['flash_error']); ?></div>
<?php endif; ?>
<?php if (!empty($_SESSION['flash_success'])): ?>
  <div class="alert success"><?= h($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?></div>
<?php endif; ?>

<div class="card">
  <form class="form" method="post" action="products.php?a=save" enctype="multipart/form-data">
    <?php csrf_field('product_save'); ?>
    <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
    <div class="grid" style="grid-template-columns: 1fr 1fr; gap: 1rem;">
      <div><label>Ürün Adı *</label><input name="name" value="<?= h($row['name']) ?>" required></div>
      <div><label>SKU (Stok Kodu)</label><input name="sku" value="<?= h($row['sku']) ?>" placeholder="Örn: ABC-123"></div>
      <div style="grid-column: 1 / -1;"><label>Açıklama</label><textarea name="description" rows="5"><?= h($row['description']) ?></textarea></div>
      <div><label>Birim</label><input name="unit" value="<?= h($row['unit']) ?>" placeholder="adet, paket, metre..."></div>
      <div>
        <label>Görsel</label><input type="file" name="image" accept="image/*">
        <?php if(!empty($row['image'])): $mid=thumb_path($row['image'],'300x300'); ?>
          <div class="mt"><img src="<?= h($mid) ?>" alt="" style="width:150px;height:150px;object-fit:cover;border-radius:8px;"></div>
        <?php endif; ?>
      </div>
      <div>
        <label>Kategori</label>
        <select name="category_id">
          <option value="">— Seç —</option>
          <?php foreach ($categories as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= ((int)$row['category_id']===(int)$c['id'])?'selected':'' ?>><?= h($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <div class="row mt" style="gap:.5rem;"><input name="new_category" value="<?= h($row['new_category'] ?? '') ?>" placeholder="Yeni kategori adı"><small class="muted">Doldurursan yukarıdaki seçimin yerine eklenir.</small></div>
      </div>
      <div>
        <label>Marka</label>
        <select name="brand_id">
          <option value="">— Seç —</option>
          <?php foreach ($brands as $b): ?>
            <option value="<?= (int)$b['id'] ?>" <?= ((int)$row['brand_id']===(int)$b['id'])?'selected':'' ?>><?= h($b['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <div class="row mt" style="gap:.5rem;"><input name="new_brand" value="<?= h($row['new_brand'] ?? '') ?>" placeholder="Yeni marka adı"><small class="muted">Doldurursan yukarıdaki seçimin yerine eklenir.</small></div>
      </div>
    </div>
    <div class="row mt" style="justify-content:flex-end; gap:.5rem;">
      <a class="btn" href="products.php">İptal</a>
      <button class="btn primary"><?= $row['id'] ? 'Güncelle' : 'Kaydet' ?></button>
    </div>
  </form>
</div>
<?php <?php /* === Variations UI START === */ ?>
<?php if (isset($row) && is_array($row) && !empty($row['id'])): ?>
  <div class="card mt">
    <h3>Öznitelikler &amp; Terimler</h3>
    <form class="form" method="post" action="products.php?a=attrs_save">
      <?php csrf_field('attrs_save'); ?>
      <input type="hidden" name="product_id" value="<?= (int)$row['id'] ?>">
      <?php $attrs = attributes_with_terms($db); $sel = product_term_map($db, (int)$row['id']); ?>
      <?php if (!$attrs): ?>
        <div class="muted">Öznitelik yok. <a class="btn" href="attributes.php?a=new">Yeni Öznitelik</a></div>
      <?php else: ?>
        <div class="grid" style="grid-template-columns: 1fr 1fr; gap: 1rem;">
          <?php foreach ($attrs as $a): $aid=(int)$a['id']; ?>
            <div class="card" style="padding:12px;">
              <strong><?= h($a['name']) ?></strong>
              <div class="row" style="flex-wrap:wrap; gap:.5rem; margin-top:.5rem;">
                <?php foreach ($a['terms'] as $t): $tid=(int)$t['id']; $checked = isset($sel[$aid][$tid]); ?>
                  <label style="display:inline-flex; align-items:center; gap:.35rem;">
                    <input type="checkbox" name="attr[<?= $aid ?>][]" value="<?= $tid ?>" <?= $checked?'checked':'' ?>>
                    <span><?= h($t['name']) ?></span>
                  </label>
                <?php endforeach; ?>
                <?php if (!$a['terms']): ?>
                  <span class="muted">Terim yok. <a href="attributes.php?a=terms&attr_id=<?= $aid ?>">Terim ekle</a></span>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="row mt" style="justify-content:flex-end; gap:.5rem;">
          <button class="btn">Kaydet</button>
        </div>
      <?php endif; ?>
    </form>
  </div>

  <div class="row mt" style="justify-content:flex-end;">
    <form method="post" action="products.php?a=vars_generate">
      <?php csrf_field('vars_generate'); ?>
      <input type="hidden" name="product_id" value="<?= (int)$row['id'] ?>">
      <button class="btn">Varyasyonları Oluştur / Eksikleri Tamamla</button>
    </form>
  </div>

  <?php $vars = load_variations($db,(int)$row['id']); ?>
  <div class="card mt">
    <h3>Varyasyonlar</h3>
    <form method="post" action="products.php?a=vars_bulk_update" enctype="multipart/form-data">
      <?php csrf_field('vars_bulk_update'); ?>
      <input type="hidden" name="product_id" value="<?= (int)$row['id'] ?>">
      <table class="table">
        <thead>
          <tr>
            <th>Seçenekler</th>
            <th>SKU</th>
            <th>Fiyat</th>
            <th>İnd. Fiyat</th>
            <th>Stok</th>
            <th>Aktif</th>
            <th>Görsel</th>
            <th style="width:140px"></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($vars as $v): $vid=(int)$v['id']; $labelParts=[]; foreach(($v['options'] ?? []) as $o){ $labelParts[] = h($o['attr_name']).': '.h($o['term_name']); } $label = $labelParts ? implode(' / ', $labelParts) : '—'; ?>
            <tr>
              <td><?= $label ?></td>
              <td><input name="var[<?= $vid ?>][sku]" value="<?= h($v['sku']) ?>" placeholder="VAR-SKU"></td>
              <td><input name="var[<?= $vid ?>][price]" value="<?= h($v['price']) ?>" style="width:90px"></td>
              <td><input name="var[<?= $vid ?>][sale_price]" value="<?= h($v['sale_price']) ?>" style="width:90px"></td>
              <td><input name="var[<?= $vid ?>][stock_qty]" value="<?= h($v['stock_qty']) ?>" style="width:70px"></td>
              <td><input type="checkbox" name="var[<?= $vid ?>][is_active]" <?= !empty($v['is_active'])?'checked':'' ?>></td>
              <td>
                <?php if(!empty($v['image'])): $t=thumb_path($v['image'],'96x96'); ?><img src="<?= h($t) ?>" style="width:48px;height:48px;object-fit:cover;border-radius:6px;display:block;margin-bottom:4px;"><?php endif; ?>
                <input type="file" name="var_image[<?= $vid ?>]" accept="image/*" style="width:150px">
              </td>
              <td class="t-right">
                <form method="post" action="products.php?a=var_delete" onsubmit="return confirm('Varyasyon silinsin mi?')" style="display:inline;">
                  <?php csrf_field('var_delete'); ?>
                  <input type="hidden" name="product_id" value="<?= (int)$row['id'] ?>">
                  <input type="hidden" name="variation_id" value="<?= $vid ?>">
                  <button class="btn danger">Sil</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$vars): ?><tr><td colspan="8" class="t-center muted">Henüz varyasyon yok.</td></tr><?php endif; ?>
        </tbody>
      </table>
      <div class="row mt" style="justify-content:flex-end; gap:.5rem;">
        <button class="btn primary">Varyasyonları Kaydet</button>
      </div>
    </form>
  </div>
<?php endif; ?>
<?php /* === Variations UI END === */ ?>

include __DIR__ . '/includes/footer.php'; ?>
