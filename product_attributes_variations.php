<?php
// product_attributes_variations.php — standalone manager (no changes to products.php)
require_once __DIR__ . '/pv_bootstrap.inc.php';
require_once __DIR__ . '/includes/header.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { echo '<div class="alert danger">Geçersiz ürün.</div>'; require_once __DIR__ . '/includes/footer.php'; exit; }
$st = $db->prepare("SELECT id, name FROM products WHERE id=?"); $st->execute([$id]);
$product = $st->fetch(PDO::FETCH_ASSOC);
if (!$product) { echo '<div class="alert danger">Ürün bulunamadı.</div>'; require_once __DIR__ . '/includes/footer.php'; exit; }

function pv_attributes_with_terms($db){
  try { $a = $db->query("SELECT id,name FROM product_attributes ORDER BY name")->fetchAll(PDO::FETCH_ASSOC); }
  catch (Throwable $e) { return []; }
  foreach ($a as &$x){
    $st=$db->prepare("SELECT id,name FROM product_attribute_terms WHERE attribute_id=? ORDER BY name");
    $st->execute([$x['id']]);
    $x['terms'] = $st->fetchAll(PDO::FETCH_ASSOC);
  }
  return $a;
}
function pv_product_term_map($db,$pid){
  $m = [];
  $st = $db->prepare("SELECT attribute_id, term_id FROM product_attribute_values WHERE product_id=?");
  $st->execute([$pid]);
  foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r){
    $aid=(int)$r['attribute_id']; $tid=(int)$r['term_id'];
    if (!isset($m[$aid])) $m[$aid]=[];
    $m[$aid][$tid]=true;
  }
  return $m;
}
function pv_set_product_terms($db,$pid,$selected){
  foreach($selected as $aid=>$tids){
    $db->prepare("DELETE FROM product_attribute_values WHERE product_id=? AND attribute_id=?")->execute([$pid,(int)$aid]);
    foreach($tids as $tid){
      $db->prepare("INSERT INTO product_attribute_values (product_id,attribute_id,term_id) VALUES (?,?,?)")->execute([$pid,(int)$aid,(int)$tid]);
    }
  }
}
function pv_load_variations($db,$pid){
  $st=$db->prepare("SELECT * FROM product_variations WHERE product_id=? ORDER BY id ASC"); $st->execute([$pid]);
  $vars=$st->fetchAll(PDO::FETCH_ASSOC); if(!$vars) return [];
  $ids = array_map(fn($r)=>(int)$r['id'], $vars);
  $in = implode(',', array_fill(0,count($ids),'?'));
  $sql = "SELECT vo.variation_id, vo.attribute_id, vo.term_id, a.name AS attr_name, t.name AS term_name
          FROM product_variation_options vo
          JOIN product_attributes a ON a.id=vo.attribute_id
          JOIN product_attribute_terms t ON t.id=vo.term_id
          WHERE vo.variation_id IN ($in)
          ORDER BY a.name, t.name";
  $st2=$db->prepare($sql); $st2->execute($ids);
  $opt=[]; foreach($st2->fetchAll(PDO::FETCH_ASSOC) as $r){ $vid=(int)$r['variation_id']; if(!isset($opt[$vid])) $opt[$vid]=[]; $opt[$vid][]=$r; }
  foreach($vars as &$v){ $v['options']=$opt[(int)$v['id']] ?? []; }
  return $vars;
}
function pv_combo_exists($db,$pid,$combo){
  $vars = pv_load_variations($db,$pid);
  foreach($vars as $v){
    $vmap=[]; foreach($v['options'] as $o){ $vmap[(int)$o['attribute_id']]=(int)$o['term_id']; }
    if(count($vmap)!==count($combo)) continue;
    $ok=true; foreach($combo as $aid=>$tid){ if(!isset($vmap[$aid])||$vmap[$aid]!=$tid){$ok=false;break;} }
    if($ok) return (int)$v['id'];
  }
  return 0;
}
function pv_generate_variations($db,$pid){
  $st=$db->prepare("SELECT attribute_id,term_id FROM product_attribute_values WHERE product_id=? ORDER BY attribute_id");
  $st->execute([$pid]); $rows=$st->fetchAll(PDO::FETCH_ASSOC); if(!$rows) return 0;
  $by=[]; foreach($rows as $r){ $by[(int)$r['attribute_id']][]=(int)$r['term_id']; }
  if(!$by) return 0; $attrs=array_keys($by); sort($attrs);
  $combos=[[]]; foreach($attrs as $aid){ $next=[]; foreach($combos as $c){ foreach($by[$aid] as $tid){ $c2=$c; $c2[$aid]=$tid; $next[]=$c2; } } $combos=$next; }
  $created=0; foreach($combos as $combo){ if(pv_combo_exists($db,$pid,$combo)) continue;
    $db->prepare("INSERT INTO product_variations (product_id) VALUES (?)")->execute([$pid]);
    $vid=(int)$db->lastInsertId();
    foreach($combo as $aid=>$tid){ $db->prepare("INSERT INTO product_variation_options (variation_id,attribute_id,term_id) VALUES (?,?,?)")->execute([$vid,(int)$aid,(int)$tid]); }
    $created++;
  }
  return $created;
}

// Actions
$act = $_POST['act'] ?? '';
if ($act === 'attrs_save') {
  if (!pv_csrf_check('attrs')) { echo '<div class="alert danger">CSRF doğrulaması başarısız.</div>'; }
  else {
    $selected=[]; foreach(($_POST['attr']??[]) as $aid=>$tids){ if(!is_array($tids)) continue; $vals=[]; foreach($tids as $tid){ $tid=(int)$tid; if($tid>0)$vals[]=$tid; } $vals=array_values(array_unique($vals)); if($vals) $selected[(int)$aid]=$vals; }
    pv_set_product_terms($db,$id,$selected);
    echo '<div class="alert success">Öznitelikler kaydedildi.</div>';
  }
}
if ($act === 'vars_generate') {
  if (!pv_csrf_check('vars_gen')) { echo '<div class="alert danger">CSRF doğrulaması başarısız.</div>'; }
  else {
    $n = pv_generate_variations($db,$id);
    echo '<div class="alert success">'.(int)$n.' varyasyon oluşturuldu.</div>';
  }
}
if ($act === 'vars_bulk') {
  if (!pv_csrf_check('vars_bulk')) { echo '<div class="alert danger">CSRF doğrulaması başarısız.</div>'; }
  else {
    foreach(($_POST['var']??[]) as $vid=>$data){
      $vid=(int)$vid;
      $sku = trim($data['sku'] ?? '');
      $price = $data['price'] !== '' ? (float)$data['price'] : null;
      $sale_price = $data['sale_price'] !== '' ? (float)$data['sale_price'] : null;
      $stock = $data['stock_qty'] !== '' ? (int)$data['stock_qty'] : null;
      $is_active = isset($data['is_active']) ? 1 : 0;
      if($sku!==''){
        $st=$db->prepare("SELECT id FROM product_variations WHERE sku=? AND id<>? LIMIT 1");
        $st->execute([$sku,$vid]);
        if($st->fetchColumn()){ echo '<div class="alert danger">SKU çakışması: '.h($sku).'</div>'; continue; }
      } else { $sku=null; }
      $st=$db->prepare("UPDATE product_variations SET sku=?, price=?, sale_price=?, stock_qty=?, is_active=? WHERE id=?");
      $st->execute([$sku,$price,$sale_price,$stock,$is_active,$vid]);
      if (!empty($_FILES['var_image']['name'][$vid]) && is_uploaded_file($_FILES['var_image']['tmp_name'][$vid])){
        $uploadDir = __DIR__ . '/uploads/products'; if (!is_dir($uploadDir)) @mkdir($uploadDir,0775,true);
        $uploadUrl = 'uploads/products';
        $namef = $_FILES['var_image']['name'][$vid];
        $tmp   = $_FILES['var_image']['tmp_name'][$vid];
        $ext = strtolower(pathinfo($namef, PATHINFO_EXTENSION)); if(!$ext) $ext='jpg';
        $fname = 'v_'.$vid.'_'.date('Ymd_His').'_'.(function_exists('random_bytes')?bin2hex(random_bytes(2)):(string)mt_rand(1000,9999)).'.'.$ext;
        $dest = $uploadDir.'/'.$fname;
        if (move_uploaded_file($tmp, $dest)) {
          $rel = $uploadUrl.'/'.$fname;
          pv_ensure_thumbs_for($rel);
          $db->prepare("UPDATE product_variations SET image=? WHERE id=?")->execute([$rel,$vid]);
        }
      }
    }
    echo '<div class="alert success">Varyasyonlar güncellendi.</div>';
  }
}
if ($act === 'var_delete') {
  if (!pv_csrf_check('var_del')) { echo '<div class="alert danger">CSRF doğrulaması başarısız.</div>'; }
  else {
    $vid=(int)=$_POST['variation_id']??0;
  }
}
if (($act ?? '') === 'var_delete') {
  if (pv_csrf_check('var_del')) {
    $vid=(int)($_POST['variation_id']??0);
    if($vid>0){
      $st=$db->prepare("SELECT image FROM product_variations WHERE id=?"); $st->execute([$vid]);
      $img=$st->fetchColumn();
      if($img){
        $base=__DIR__ . '/' . ltrim($img,'/');
        $t300=__DIR__ . '/' . ltrim(pv_thumb_path($img,'300x300'),'/');
        $t96 =__DIR__ . '/' . ltrim(pv_thumb_path($img,'96x96'),'/');
        if(is_file($base)) @unlink($base); if(is_file($t300)) @unlink($t300); if(is_file($t96)) @unlink($t96);
      }
      $db->prepare("DELETE FROM product_variations WHERE id=?")->execute([$vid]);
      echo '<div class="alert success">Varyasyon silindi.</div>';
    }
  }
}

// Data for forms
$attrs = pv_attributes_with_terms($db);
$sel   = pv_product_term_map($db, $id);
$vars  = pv_load_variations($db, $id);
?>
<div class="row" style="justify-content:space-between; align-items:center;">
  <h2>Öznitelikler & Varyasyonlar: <?= h($product['name']) ?> <span class="muted">#<?= (int)$product['id'] ?></span></h2>
  <a class="btn" href="products.php?a=edit&id=<?= (int)$product['id'] ?>">← Ürün Düzenle</a>
</div>

<div class="card mt">
  <h3>Öznitelikler &amp; Terimler</h3>
  <form method="post" enctype="multipart/form-data">
    <?php pv_csrf_field('attrs'); ?>
    <input type="hidden" name="act" value="attrs_save">
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
            <?php endforeach; if (!$a['terms']): ?>
              <span class="muted">Terim yok. <a href="attributes.php?a=terms&attr_id=<?= $aid ?>">Terim ekle</a></span>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; if(!$attrs): ?>
        <div class="muted">Öznitelik yok. <a class="btn" href="attributes.php?a=new">Yeni Öznitelik</a></div>
      <?php endif; ?>
    </div>
    <div class="row mt" style="justify-content:flex-end; gap:.5rem;">
      <button class="btn">Kaydet</button>
    </div>
  </form>
</div>

<div class="row mt" style="justify-content:flex-end;">
  <form method="post">
    <?php pv_csrf_field('vars_gen'); ?>
    <input type="hidden" name="act" value="vars_generate">
    <button class="btn">Varyasyonları Oluştur / Eksikleri Tamamla</button>
  </form>
</div>

<div class="card mt">
  <h3>Varyasyonlar</h3>
  <form method="post" enctype="multipart/form-data">
    <?php pv_csrf_field('vars_bulk'); ?>
    <input type="hidden" name="act" value="vars_bulk">
    <table class="table">
      <thead><tr><th>Seçenekler</th><th>SKU</th><th>Fiyat</th><th>İnd.Fiyat</th><th>Stok</th><th>Aktif</th><th>Görsel</th><th></th></tr></thead>
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
            <?php if(!empty($v['image'])): $t=pv_thumb_path($v['image'],'96x96'); ?><img src="<?= h($t) ?>" style="width:48px;height:48px;object-fit:cover;border-radius:6px;display:block;margin-bottom:4px;"><?php endif; ?>
            <input type="file" name="var_image[<?= $vid ?>]" accept="image/*" style="width:150px">
          </td>
          <td class="t-right">
            <form method="post" onsubmit="return confirm('Varyasyon silinsin mi?')" style="display:inline;">
              <?php pv_csrf_field('var_del'); ?>
              <input type="hidden" name="act" value="var_delete">
              <input type="hidden" name="variation_id" value="<?= $vid ?>">
              <button class="btn danger">Sil</button>
            </form>
          </td>
        </tr>
      <?php endforeach; if(!$vars): ?><tr><td colspan="8" class="t-center muted">Henüz varyasyon yok.</td></tr><?php endif; ?>
      </tbody>
    </table>
    <div class="row mt" style="justify-content:flex-end; gap:.5rem;"><button class="btn primary">Varyasyonları Kaydet</button></div>
  </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
