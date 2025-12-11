<?php
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/audit_log.php';

// ==== AUDIT HELPERS (guarded) ====
if (!function_exists('AUD_normS')) {
  function AUD_normS($s){ $s=(string)$s; $s=str_replace(array("\r","\n","\t")," ",$s); $s=preg_replace('/\s+/u',' ',$s); return trim($s); }
}
if (!function_exists('AUD_normF')) {
  function AUD_normF($s){
    $s=(string)$s;
    if (strpos($s, ',') !== false && strpos($s, '.') !== false) { $s=str_replace('.','', $s); $s=str_replace(',', '.', $s); }
    else { $s=str_replace(',', '.', $s); }
    if ($s === '' || $s === '-') return '0';
    $n = (float)$s;
    $out = rtrim(rtrim(sprintf('%.8F', $n), '0'), '.');
    return ($out === '') ? '0' : $out;
  }
}
if (!function_exists('AUD_core')) {
  // Core identity: product_id|name|unit (ID-agnostic). This avoids false add/remove when row IDs change.
  function AUD_core($r){
    $pid = AUD_normS(isset($r['product_id']) ? $r['product_id'] : '');
    $nm  = AUD_normS(isset($r['name']) ? $r['name'] : '');
    $un  = AUD_normS(isset($r['unit']) ? $r['unit'] : '');
    return $pid.'|'.$nm.'|'.$un;
  }
}
if (!function_exists('AUD_full')) {
  function AUD_full($r){
    return AUD_core($r).'|Q='.AUD_normF(isset($r['qty'])?$r['qty']:'').'|P='.AUD_normF(isset($r['price'])?$r['price']:'');
  }
}
// ==== /AUDIT HELPERS ====

require_login();

$db = pdo();
$id = (int)($_GET['id'] ?? 0);
if (!$id) redirect('orders.php');

$st = $db->prepare("SELECT * FROM orders WHERE id=?");
$st->execute([$id]);
$order = $st->fetch();
if (!$order) redirect('orders.php');

if (method('POST')) {
  /*AUDIT_BEFORE*/
  $AUD_beforeOrder = null; $AUD_beforeItems = array();
  try {
    $AUD_stB1 = $db->prepare("SELECT * FROM orders WHERE id=?");
    $AUD_stB1->execute(array($id));
    $AUD_beforeOrder = $AUD_stB1->fetch(PDO::FETCH_ASSOC);

    $AUD_stB2 = $db->prepare("SELECT id, product_id, name, unit, qty, price, urun_ozeti, kullanim_alani FROM order_items WHERE order_id=? ORDER BY id ASC");
    $AUD_stB2->execute(array($id));
    $AUD_beforeItems = $AUD_stB2->fetchAll(PDO::FETCH_ASSOC);
  } catch (Exception $e) { $AUD_beforeOrder = null; $AUD_beforeItems = array(); }

  csrf_check();
  
    // Para birimi uyumluluk haritalama
    if (isset($_POST['odeme_para_birimi'])) {
        $__tmp_odeme = $_POST['odeme_para_birimi'];
        if ($__tmp_odeme === 'TL') { $_POST['currency'] = 'TRY'; }
        elseif ($__tmp_odeme === 'EUR') { $_POST['currency'] = 'EUR'; }
        elseif ($__tmp_odeme === 'USD') { $_POST['currency'] = 'USD'; }
    }
$fields = ['order_code','customer_id','status','currency','termin_tarihi','baslangic_tarihi','bitis_tarihi','teslim_tarihi','notes',
    'siparis_veren','siparisi_alan','siparisi_giren','siparis_tarihi','fatura_para_birimi','proje_adi','revizyon_no','nakliye_turu','odeme_kosulu','odeme_para_birimi'];
  $data = [];
  foreach ($fields as $f) { $data[$f] = $_POST[$f] ?? null; }
  $data['customer_id'] = (int)$data['customer_id'];

  $up = $db->prepare("UPDATE orders SET order_code=?, customer_id=?, status=?, currency=?, termin_tarihi=?, baslangic_tarihi=?, bitis_tarihi=?, teslim_tarihi=?, notes=?,
                       siparis_veren=?, siparisi_alan=?, siparisi_giren=?, siparis_tarihi=?, fatura_para_birimi=?, proje_adi=?, revizyon_no=?, nakliye_turu=?, odeme_kosulu=?, odeme_para_birimi=?
                      WHERE id=?");
  $up->execute([
    $data['order_code'],$data['customer_id'],$data['status'],$data['currency'],$data['termin_tarihi'],$data['baslangic_tarihi'],$data['bitis_tarihi'],$data['teslim_tarihi'],$data['notes'],
    $data['siparis_veren'],$data['siparisi_alan'],$data['siparisi_giren'],$data['siparis_tarihi'],$data['fatura_para_birimi'],$data['proje_adi'],$data['revizyon_no'],$data['nakliye_turu'],$data['odeme_kosulu'],$data['odeme_para_birimi'],
    $id
  ]);

  // Kalemleri yeniden yaz
  $db->prepare("DELETE FROM order_items WHERE order_id=?")->execute([$id]);

  // --- Robust items save (supports price[] or birim_fiyat[], associative indexes) ---
  function _tr_money_to_float($v) {
    if ($v === null || $v === '') return 0.0;
    $v = trim((string)$v);
    // If format like 1.234,56 -> remove thousands and use dot decimal
    if (preg_match('/^\\d{1,3}(\\.\\d{3})+(,\\d+)?$/', $v)) {
        $v = str_replace('.', '', $v);
        $v = str_replace(',', '.', $v);
    } else {
        // Otherwise: just convert comma to dot (keep existing dot!)
        $v = str_replace(',', '.', $v);
    }
    return (float)$v;
}


  $p_ids  = $_POST['product_id']     ?? [];
  $names  = $_POST['name']           ?? [];
  $units  = $_POST['unit']           ?? [];
  $qtys   = $_POST['qty']            ?? [];
  // Accept either price[] or birim_fiyat[]
  $prices = $_POST['price']          ?? ($_POST['birim_fiyat'] ?? []);
  $ozet   = $_POST['urun_ozeti']     ?? [];
  $kalan  = $_POST['kullanim_alani'] ?? [];

  // Determine all row keys (support associative indexes)
  $keys = array_unique(array_merge(
    array_keys((array)$p_ids),
    array_keys((array)$names),
    array_keys((array)$units),
    array_keys((array)$qtys),
    array_keys((array)$prices),
    array_keys((array)$ozet),
    array_keys((array)$kalan)
  ));

  // Keep key order stable
  sort($keys);

  $insIt = $db->prepare("INSERT INTO order_items (order_id, product_id, name, unit, qty, price, urun_ozeti, kullanim_alani)
                         VALUES (?,?,?,?,?,?,?,?)");

  foreach ($keys as $i) {
    $n = trim((string)($names[$i] ?? ''));
    $pid = (int)($p_ids[$i] ?? 0);
    $unit = trim((string)($units[$i] ?? ''));
    // Miktarı da virgüllü formattan (1,50) ondalıklı formata (1.50) çevir:
    $qty_raw = $qtys[$i] ?? 0;
    $qty = is_string($qty_raw) ? _tr_money_to_float($qty_raw) : (float)$qty_raw;

    $price_raw = $prices[$i] ?? 0;
    $price = is_string($price_raw) ? _tr_money_to_float($price_raw) : (float)$price_raw;
    $uo = trim((string)($ozet[$i] ?? ''));
    $ka = trim((string)($kalan[$i] ?? ''));

    // Skip completely empty rows
    if ($pid === 0 && $n === '' && $qty == 0 && $price == 0 && $uo === '' && $ka === '') continue;

    // If name is empty but product lookup exists in $products at render time, we still persist what we have.
    $insIt->execute([$id, $pid, $n, $unit, $qty, $price, $uo, $ka]);
  }

  
  /*AUDIT_AFTER*/
  try {
    $AUD_stA1 = $db->prepare("SELECT * FROM orders WHERE id=?");
    $AUD_stA1->execute(array($id));
    $AUD_afterOrder = $AUD_stA1->fetch(PDO::FETCH_ASSOC);

    $AUD_stA2 = $db->prepare("SELECT id, product_id, name, unit, qty, price, urun_ozeti, kullanim_alani FROM order_items WHERE order_id=? ORDER BY id ASC");
    $AUD_stA2->execute(array($id));
    $AUD_afterItems = $AUD_stA2->fetchAll(PDO::FETCH_ASSOC);
  } catch (Exception $e) { $AUD_afterOrder = null; $AUD_afterItems = array(); }

  // ORDER FIELD DIFFS (all except id/created_at)
  $AUD_orderFieldDiffs = array();
  if (is_array($AUD_beforeOrder) && is_array($AUD_afterOrder)) {
    $AUD_keys = array_unique(array_merge(array_keys($AUD_beforeOrder), array_keys($AUD_afterOrder)));
    foreach ($AUD_keys as $AUD_k) {
      if ($AUD_k === 'id' || $AUD_k === 'created_at') continue;
      $AUD_v1 = isset($AUD_beforeOrder[$AUD_k]) ? trim((string)$AUD_beforeOrder[$AUD_k]) : '';
      $AUD_v2 = isset($AUD_afterOrder[$AUD_k]) ? trim((string)$AUD_afterOrder[$AUD_k]) : '';
      if ($AUD_v1 !== $AUD_v2) { $AUD_orderFieldDiffs[$AUD_k] = array('from'=>$AUD_v1, 'to'=>$AUD_v2); }
    }
  }

  // ITEMS DIFF (ID-agnostic, exact-first, then core; multiset-aware)
  $AUD_B = array(); foreach ((array)$AUD_beforeItems as $AUD_r) { $k = AUD_core($AUD_r); if (!isset($AUD_B[$k])) $AUD_B[$k] = array(); $AUD_B[$k][] = $AUD_r; }
  $AUD_A = array(); foreach ((array)$AUD_afterItems as $AUD_r)  { $k = AUD_core($AUD_r); if (!isset($AUD_A[$k])) $AUD_A[$k] = array(); $AUD_A[$k][] = $AUD_r; }

  $AUD_added = array(); $AUD_removed = array(); $AUD_updated = array();
  $AUD_all = array_unique(array_merge(array_keys($AUD_B), array_keys($AUD_A)));
  foreach ($AUD_all as $AUD_k) {
    $AUD_bRows = isset($AUD_B[$AUD_k]) ? $AUD_B[$AUD_k] : array();
    $AUD_aRows = isset($AUD_A[$AUD_k]) ? $AUD_A[$AUD_k] : array();
    $AUD_used = array();

    foreach ($AUD_bRows as $AUD_br) {
      $AUD_exact = -1; $AUD_upd = -1;
      // exact match (including qty/price) -> unchanged
      foreach ($AUD_aRows as $AUD_i=>$AUD_ar) {
        if (isset($AUD_used[$AUD_i])) continue;
        if (AUD_full($AUD_ar) === AUD_full($AUD_br)) { $AUD_used[$AUD_i] = 1; $AUD_exact = $AUD_i; break; }
      }
      if ($AUD_exact !== -1) continue;

      // same core -> updated fields
      foreach ($AUD_aRows as $AUD_i=>$AUD_ar) {
        if (isset($AUD_used[$AUD_i])) continue;
        if (AUD_core($AUD_ar) === AUD_core($AUD_br)) {
          $AUD_used[$AUD_i] = 1; $AUD_upd = $AUD_i;
          $AUD_chg = array();
          $AUD_va = AUD_normF(isset($AUD_br['qty'])?$AUD_br['qty']:''); $AUD_vb = AUD_normF(isset($AUD_ar['qty'])?$AUD_ar['qty']:'');
          if ($AUD_va !== $AUD_vb) { $AUD_chg['qty'] = array('from'=>$AUD_va, 'to'=>$AUD_vb); }
          $AUD_va = AUD_normF(isset($AUD_br['price'])?$AUD_br['price']:''); $AUD_vb = AUD_normF(isset($AUD_ar['price'])?$AUD_ar['price']:'');
          if ($AUD_va !== $AUD_vb) { $AUD_chg['price'] = array('from'=>$AUD_va, 'to'=>$AUD_vb); }
          $AUD_va = AUD_normS(isset($AUD_br['urun_ozeti'])?$AUD_br['urun_ozeti']:''); $AUD_vb = AUD_normS(isset($AUD_ar['urun_ozeti'])?$AUD_ar['urun_ozeti']:'');
          if ($AUD_va !== $AUD_vb) { $AUD_chg['urun_ozeti'] = array('from'=>$AUD_va, 'to'=>$AUD_vb); }
          $AUD_va = AUD_normS(isset($AUD_br['kullanim_alani'])?$AUD_br['kullanim_alani']:''); $AUD_vb = AUD_normS(isset($AUD_ar['kullanim_alani'])?$AUD_ar['kullanim_alani']:'');
          if ($AUD_va !== $AUD_vb) { $AUD_chg['kullanim_alani'] = array('from'=>$AUD_va, 'to'=>$AUD_vb); }

          if (!empty($AUD_chg)) { $AUD_updated[] = array('name'=> AUD_normS(isset($AUD_ar['name'])?$AUD_ar['name']:''), 'changes'=>$AUD_chg); }
          break;
        }
      }

      if ($AUD_exact === -1 && $AUD_upd === -1) { $AUD_removed[] = $AUD_br; }
    }

    foreach ($AUD_aRows as $AUD_i=>$AUD_ar) { if (!isset($AUD_used[$AUD_i])) $AUD_added[] = $AUD_ar; }
  }

  if (function_exists('audit_log_action')) {
    $AUD_before = array('order'=>$AUD_beforeOrder, 'items'=>$AUD_beforeItems);
    $AUD_after  = array('order'=>$AUD_afterOrder,  'items'=>$AUD_afterItems);
    $AUD_extra  = array('source'=>'order_edit.php','order_field_diffs'=>$AUD_orderFieldDiffs,'item_diffs'=>array('added'=>$AUD_added,'removed'=>$AUD_removed,'updated'=>$AUD_updated));
    audit_log_action('update','orders',$id,$AUD_before,$AUD_after,$AUD_extra);
  }
redirect('orders.php');
}

// Dropdown verileri
$customers = $db->query("SELECT id,name FROM customers ORDER BY name ASC")->fetchAll();
$products  = $db->query("SELECT id,sku,name,unit,price,urun_ozeti,kullanim_alani,image FROM products ORDER BY name ASC")->fetchAll();
$it = $db->prepare("SELECT * FROM order_items WHERE order_id=? ORDER BY id ASC");
$it->execute([$id]);
$items = $it->fetchAll();


include __DIR__ . '/includes/header.php'; ?>
<?php $mode = 'edit';

include __DIR__ . '/includes/order_form.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function(){
  // Ek PDF butonu (para alanlarına dokunmaz)
  try {
    function addAfter(node, newNode){ node.parentNode.insertBefore(newNode, node.nextSibling); }
    var pdfButtons = Array.from(document.querySelectorAll('a.btn, a[class*=btn]')).filter(function(a){
      var t = (a.textContent || '').trim().toLowerCase();
      return t === 'görüntüle pdf' || t === 'goruntule pdf' || t === 'stf';
    });
    pdfButtons.forEach(function(pdf){
      var prod = document.createElement('a');
      prod.className = 'btn';
      prod.setAttribute('target','_blank');
      prod.setAttribute('rel','noopener');
      prod.setAttribute('href','http://renplan.ditetra.com/order_pdf_uretim.php?id=<?= (int)$id ?>');
      prod.setAttribute('style','background-color:#16a34a;border-color:#15803d;color:#fff');
      prod.textContent = 'Üretim Föyü';
      addAfter(pdf, prod);
    });
  } catch(e){}

  // Sunucudan gelen kalemler
  var _itemsFromPHP = <?php
    $___json_items = [];
    if (!empty($items)) {
      foreach ($items as $___it) {
        $___json_items[] = [
          'id'            => $___it['id']          ?? null,
          'product_id'    => $___it['product_id']  ?? null,
          'name'          => $___it['name']        ?? '',
          'urun_ozeti'    => $___it['urun_ozeti']  ?? '',
          'kullanim_alani'=> $___it['kullanim_alani'] ?? '',
          'price'         => isset($___it['price']) ? $___it['price'] : (isset($___it['birim_fiyat']) ? $___it['birim_fiyat'] : 0),
        ];
      }
    }
    echo json_encode($___json_items, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE);
  ?>;

  function toTR(n){ try{return Number(n).toLocaleString('tr-TR',{minimumFractionDigits:2,maximumFractionDigits:2});}catch(e){return String(n);} }
  function isZeroOrEmpty(s){ s=(s==null?'':String(s)).trim(); return (s===''||s==='0'||s==='0,00'||s==='0.00'); }
  function trToDotDecimal(str){ if(str==null) return ''; var s=String(str).trim(); if(!s) return ''; s=s.replace(/\s/g,'').replace(/\./g,'').replace(',', '.'); return s; }

  // Seçici grupları (Miktar alanlarını da ekledik)
  var selPrice = [
    'input[name="qty[]"]','input[name^="qty["]',
    'input[name="price[]"]','input[name^="price["]','input[name="price"]',
    'input[name="birim_fiyat[]"]','input[name^="birim_fiyat["]','input[name="birim_fiyat"]',
    'input[aria-label*="Birim Fiyat" i]','input[placeholder*="Birim Fiyat" i]'
  ];
  var selOzet  = ['input[name="urun_ozeti[]"]','input[name^="urun_ozeti["]','textarea[name="urun_ozeti[]"]','textarea[name^="urun_ozeti["]'];
  var selKA    = ['input[name="kullanim_alani[]"]','input[name^="kullanim_alani["]','textarea[name="kullanim_alani[]"]','textarea[name^="kullanim_alani["]'];

  function qAll(list){
    var out=[]; list.forEach(function(sel){ document.querySelectorAll(sel).forEach(function(el){ if(out.indexOf(el)<0) out.push(el); }); });
    return out;
  }

  // type=number ise virgül kabul etmeyebilir → text yap
  qAll(selPrice).forEach(function(el){
    try{ el.setAttribute('type','text'); el.setAttribute('inputmode','decimal'); el.removeAttribute('step'); el.removeAttribute('pattern'); }catch(e){}
  });

  // Sadece bir kez, boş/0 olan alanları doldur (yazarken asla dokunma)
  var inputsP = qAll(selPrice);
  var inputsO = qAll(selOzet);
  var inputsK = qAll(selKA);

  for (var i=0;i<_itemsFromPHP.length;i++){ 
    var it = _itemsFromPHP[i]||{};
    if (inputsP[i] && isZeroOrEmpty(inputsP[i].value)){ 
      var pv = (it.price ?? it.birim_fiyat ?? it.unit_price ?? 0);
      if (pv!==undefined && pv!==null) inputsP[i].value = toTR(pv);
    }
    if (inputsO[i] && isZeroOrEmpty(inputsO[i].value)){ 
      var ov = (it.urun_ozeti ?? '');
      inputsO[i].value = ov;
    }
    if (inputsK[i] && isZeroOrEmpty(inputsK[i].value)){ 
      var kv = (it.kullanim_alani ?? '');
      inputsK[i].value = kv;
    }
  }

  // Submit'te fiyatları noktalı-ondalık yap (ör: 235.99), özet/KA'ya dokunma
  document.querySelectorAll('form').forEach(function(form){
    form.addEventListener('submit', function(){
      qAll(selPrice).forEach(function(inp){
        var raw = trToDotDecimal(inp.value);
        var num = Number(raw);
        if (isFinite(num)) inp.value = num.toFixed(2);
      });
    }, true);
  });
});
</script>



<!-- PRICE STICKY v3 -->

<?php include __DIR__ . '/includes/footer.php';
