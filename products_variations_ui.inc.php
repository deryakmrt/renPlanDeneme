<?php
if (!function_exists('h')) { function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); } }
require_once __DIR__ . '/products_variations_addon.inc.php';
$db = isset($db) ? $db : (function_exists('pdo') ? pdo() : null);
if (!$db) { echo '<div class="alert danger">DB yok.</div>'; return; }
$pid = isset($row['id']) ? (int)$row['id'] : (isset($_GET['id'])?(int)$_GET['id']:0);
if ($pid<=0) { echo '<div class="alert danger">Ürün ID yok.</div>'; return; }
function pv_attrs($db){ try { $a = $db->query("SELECT id,name FROM product_attributes ORDER BY name")->fetchAll(PDO::FETCH_ASSOC); } catch(Throwable $e){ return []; } foreach($a as &$x){ $st=$db->prepare("SELECT id,name FROM product_attribute_terms WHERE attribute_id=? ORDER BY name"); $st->execute([$x['id']]); $x['terms']=$st->fetchAll(PDO::FETCH_ASSOC);} return $a; }
function pv_selected($db,$pid){ $m=[]; $st=$db->prepare("SELECT attribute_id, term_id FROM product_attribute_values WHERE product_id=?"); $st->execute([$pid]); foreach($st->fetchAll(PDO::FETCH_ASSOC) as $r){ $aid=(int)$r['attribute_id']; $tid=(int)$r['term_id']; if(!isset($m[$aid]))$m[$aid]=[]; $m[$aid][$tid]=true; } return $m; }
function pv_load_vars($db,$pid){ $st=$db->prepare("SELECT * FROM product_variations WHERE product_id=? ORDER BY id ASC"); $st->execute([$pid]); $vars=$st->fetchAll(PDO::FETCH_ASSOC); if(!$vars) return []; $ids=array_map(fn($r)=>(int)$r['id'],$vars); if(!$ids) return []; $in=implode(',', array_fill(0,count($ids),'?')); $sql="SELECT vo.variation_id,vo.attribute_id,vo.term_id,a.name attr_name,t.name term_name FROM product_variation_options vo JOIN product_attributes a ON a.id=vo.attribute_id JOIN product_attribute_terms t ON t.id=vo.term_id WHERE vo.variation_id IN ($in) ORDER BY a.name,t.name"; $st2=$db->prepare($sql); $st2->execute($ids); $opt=[]; foreach($st2->fetchAll(PDO::FETCH_ASSOC) as $r){ $vid=(int)$r['variation_id']; if(!isset($opt[$vid]))$opt[$vid]=[]; $opt[$vid][]=$r; } foreach($vars as &$v){ $v['options']=$opt[(int)$v['id']] ?? []; } return $vars; }
function pv_generate($db,$pid){ $st=$db->prepare("SELECT attribute_id,term_id FROM product_attribute_values WHERE product_id=? ORDER BY attribute_id"); $st->execute([$pid]); $rows=$st->fetchAll(PDO::FETCH_ASSOC); if(!$rows) return 0; $by=[]; foreach($rows as $r){ $by[(int)$r['attribute_id']][]=(int)$r['term_id']; } if(!$by) return 0; $attrs=array_keys($by); sort($attrs); $combos=[[]]; foreach($attrs as $aid){ $next=[]; foreach($combos as $c){ foreach($by[$aid] as $tid){ $c2=$c; $c2[$aid]=$tid; $next[]=$c2; } } $combos=$next; } $created=0; foreach($combos as $combo){ $st=$db->prepare("SELECT id FROM product_variations WHERE product_id=?"); $st->execute([$pid]); $all=$st->fetchAll(PDO::FETCH_ASSOC); $exists=false; foreach($all as $v){ $vid=(int)$v['id']; $ok=true; foreach($combo as $aid=>$tid){ $q=$db->prepare("SELECT 1 FROM product_variation_options WHERE variation_id=? AND attribute_id=? AND term_id=?"); $q->execute([$vid,(int)$aid,(int)$tid]); if(!$q->fetchColumn()){ $ok=false; break; } } if($ok){ $exists=true; break; } } if($exists) continue; $db->prepare("INSERT INTO product_variations (product_id) VALUES (?)")->execute([$pid]); $vid=(int)$db->lastInsertId(); foreach($combo as $aid=>$tid){ $db->prepare("INSERT INTO product_variation_options (variation_id,attribute_id,term_id) VALUES (?,?,?)")->execute([$vid,(int)$aid,(int)$tid]); } $created++; } return $created; }
$act = $_POST['pv_act'] ?? ''; if ($act==='attrs_save' && $_SERVER['REQUEST_METHOD']==='POST'){ if (!csrf_check_both('attrs')) echo '<div class="alert danger">CSRF doğrulaması başarısız.</div>'; else { $sel=[]; foreach(($_POST['attr']??[]) as $aid=>$tids){ if(!is_array($tids)) continue; $vals=[]; foreach($tids as $tid){ $tid=(int)$tid; if($tid>0)$vals[]=$tid; } $vals=array_values(array_unique($vals)); if($vals) $sel[(int)$aid]=$vals; } foreach($sel as $aid=>$tids){ $db->prepare("DELETE FROM product_attribute_values WHERE product_id=? AND attribute_id=?")->execute([$pid,(int)$aid]); foreach($tids as $tid){ $db->prepare("INSERT INTO product_attribute_values (product_id,attribute_id,term_id) VALUES (?,?,?)")->execute([$pid,(int)$aid,(int)$tid]); } } echo '<div class="alert success">Öznitelikler kaydedildi.</div>'; } }
if ($act==='vars_generate' && $_SERVER['REQUEST_METHOD']==='POST'){ if (!csrf_check_both('vars_gen')) echo '<div class="alert danger">CSRF doğrulaması başarısız.</div>'; else { $n=pv_generate($db,$pid); echo '<div class="alert success">'.(int)$n.' varyasyon oluşturuldu.</div>'; } }
if ($act==='vars_bulk' && $_SERVER['REQUEST_METHOD']==='POST'){ if (!csrf_check_both('vars_bulk')) echo '<div class="alert danger">CSRF doğrulaması başarısız.</div>'; else { foreach(($_POST['var']??[]) as $vid=>$data){ $vid=(int)$vid; $sku=trim($data['sku']??''); $price=$data['price']!==''?(float)$data['price']:null; $sale=$data['sale_price']!==''?(float)$data['sale_price']:null; $stock=$data['stock_qty']!==''?(int)$data['stock_qty']:null; $active=isset($data['is_active'])?1:0; if($sku!==''){ $st=$db->prepare("SELECT id FROM product_variations WHERE sku=? AND id<>? LIMIT 1"); $st->execute([$sku,$vid]); if($st->fetchColumn()){ echo '<div class="alert danger">SKU çakışması: '.h($sku).'</div>'; continue; } } else { $sku=null; } $db->prepare("UPDATE product_variations SET sku=?, price=?, sale_price=?, stock_qty=?, is_active=? WHERE id=?")->execute([$sku,$price,$sale,$stock,$active,$vid]); if (!empty($_FILES['var_image']['name'][$vid]) && is_uploaded_file($_FILES['var_image']['tmp_name'][$vid])){ $uploadDir = __DIR__.'/uploads/products'; if(!is_dir($uploadDir)) @mkdir($uploadDir,0775,true); $uploadUrl = 'uploads/products'; $namef=$_FILES['var_image']['name'][$vid]; $tmp=$_FILES['var_image']['tmp_name'][$vid]; $ext=strtolower(pathinfo($namef,PATHINFO_EXTENSION)); if(!$ext)$ext='jpg'; $fname='v_'.$vid.'_'.date('Ymd_His').'_' . (function_exists('random_bytes')?bin2hex(random_bytes(2)):(string)mt_rand(1000,9999)) .'.'.$ext; $dest=$uploadDir.'/'.$fname; if (move_uploaded_file($tmp, $dest)){ $rel=$uploadUrl.'/'.$fname; ensure_thumbs_for($rel); $db->prepare("UPDATE product_variations SET image=? WHERE id=?")->execute([$rel,$vid]); } } } echo '<div class="alert success">Varyasyonlar güncellendi.</div>'; } }
$attrs = pv_attrs($db); $sel   = pv_selected($db,$pid); $vars  = pv_load_vars($db,$pid);
?> 
<div class="card mt">
  <h3>Öznitelikler &amp; Terimler</h3>
  <form method="post">
    <?php csrf_field_both('attrs'); ?><input type="hidden" name="pv_act" value="attrs_save">
    <div class="grid" style="grid-template-columns: 1fr 1fr; gap:1rem;">
      <?php foreach ($attrs as $a): $aid=(int)$a['id']; ?>
        <div class="card" style="padding:12px;"><strong><?= h($a['name']) ?></strong>
          <div class="row" style="flex-wrap:wrap; gap:.5rem; margin-top:.5rem;">
            <?php foreach ($a['terms'] as $t): $tid=(int)$t['id']; $checked = isset($sel[$aid][$tid]); ?>
              <label style="display:inline-flex; align-items:center; gap:.35rem;">
                <input type="checkbox" name="attr[<?= $aid ?>][]" value="<?= $tid ?>" <?= $checked?'checked':'' ?>>
                <span><?= h($t['name']) ?></span>
              </label>
            <?php endforeach; if (!$a['terms']): ?><span class="muted">Terim yok.</span><?php endif; ?>
          </div>
        </div>
      <?php endforeach; if(!$attrs): ?><div class="muted">Öznitelik yok.</div><?php endif; ?>
    </div>
    <div class="row mt" style="justify-content:flex-end; gap:.5rem;"><button class="btn">Kaydet</button></div>
  </form>
</div>
<div class="row mt" style="justify-content:flex-end;">
  <form method="post"><?php csrf_field_both('vars_gen'); ?><input type="hidden" name="pv_act" value="vars_generate"><button class="btn">Varyasyonları Oluştur / Eksikleri Tamamla</button></form>
</div>
<div class="card mt">
  <h3>Varyasyonlar</h3>
  <form method="post" enctype="multipart/form-data">
    <?php csrf_field_both('vars_bulk'); ?><input type="hidden" name="pv_act" value="vars_bulk">
    <table class="table"><thead><tr><th>Seçenekler</th><th>SKU</th><th>Fiyat</th><th>İnd. Fiyat</th><th>Stok</th><th>Aktif</th><th>Görsel</th></tr></thead><tbody>
      <?php foreach ($vars as $v): $vid=(int)$v['id']; $labelParts=[]; foreach(($v['options'] ?? []) as $o){ $labelParts[] = h($o['attr_name']).': '.h($o['term_name']); } $label = $labelParts ? implode(' / ', $labelParts) : '—'; ?>
      <tr><td><?= $label ?></td>
        <td><input name="var[<?= $vid ?>][sku]" value="<?= h($v['sku']) ?>" style="width:120px"></td>
        <td><input name="var[<?= $vid ?>][price]" value="<?= h($v['price']) ?>" style="width:90px"></td>
        <td><input name="var[<?= $vid ?>][sale_price]" value="<?= h($v['sale_price']) ?>" style="width:90px"></td>
        <td><input name="var[<?= $vid ?>][stock_qty]" value="<?= h($v['stock_qty']) ?>" style="width:70px"></td>
        <td><input type="checkbox" name="var[<?= $vid ?>][is_active]" <?= !empty($v['is_active'])?'checked':'' ?>></td>
        <td><?php if(!empty($v['image'])): $t=thumb_path($v['image'],'96x96'); ?><img src="<?= h($t) ?>" style="width:48px;height:48px;object-fit:cover;border:1px solid #ddd;border-radius:6px;display:block;margin-bottom:4px;"><?php endif; ?><input type="file" name="var_image[<?= $vid ?>]" accept="image/*" style="width:150px"></td>
      </tr>
      <?php endforeach; if(!$vars): ?><tr><td colspan="7" class="t-center muted">Henüz varyasyon yok.</td></tr><?php endif; ?>
    </tbody></table>
    <div class="row mt" style="justify-content:flex-end; gap:.5rem;"><button class="btn primary">Varyasyonları Kaydet</button></div>
  </form>
</div>
