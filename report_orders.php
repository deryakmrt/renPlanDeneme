<?php
/**
 * report_orders.php — Sales Report (filters left + totals right + THREE pies)
 */

declare(strict_types=1);
ini_set('display_errors','1'); ini_set('display_startup_errors','1'); error_reporting(E_ALL);

require_once __DIR__ . '/includes/helpers.php';
require_login();

// --- 🔒 SADECE ADMİN YETKİ KONTROLÜ ---
$__role = current_user()['role'] ?? ''; 
if ($__role !== 'admin') {
    die('<div style="margin:50px auto; max-width:500px; padding:30px; background:#fff1f2; border:2px solid #fda4af; border-radius:12px; color:#e11d48; font-family:sans-serif; text-align:center; box-shadow:0 10px 25px rgba(225,29,72,0.1);">
        <h2 style="margin-top:0; font-size:24px;">⛔ YETKİSİZ ERİŞİM</h2>
        <p style="font-size:15px; line-height:1.5;">Bu finansal raporları ve grafikleri yalnızca <b>Yönetici (Admin)</b> yetkisine sahip kullanıcılar görüntüleyebilir.</p>
        <a href="index.php" style="display:inline-block; margin-top:15px; padding:10px 20px; background:#e11d48; color:#fff; text-decoration:none; border-radius:8px; font-weight:bold;">Ana Sayfaya Dön</a>
    </div>');
}
// --------------------------------------

$db = pdo();
// =========================================================================================
// [YENİ] CANLI ÜRETİM YÜKÜ GRAFİK MOTORU (V2 - GENİŞLETİLMİŞ DURUM KONTROLÜ)
// =========================================================================================

// 1. Verileri Çek (Hem boşluklu 'sac lazer' hem de alt çizgili 'sac_lazer' versiyonlarını kapsar)
$sqlStats = "SELECT o.status, oi.name, p.sku, SUM(oi.qty) as total_qty
             FROM order_items oi
             JOIN orders o ON oi.order_id = o.id
             LEFT JOIN products p ON oi.product_id = p.id
             WHERE o.status IN (
                'tedarik', 
                'sac_lazer', 'sac lazer', 
                'boru_lazer', 'boru lazer', 
                'kaynak', 
                'boya', 
                'elektrik_montaj', 'elektrik montaj', 
                'test', 
                'paketleme'
             )
             GROUP BY o.status, p.sku, oi.name";

$stS = $db->query($sqlStats);
$rowsS = $stS->fetchAll(PDO::FETCH_ASSOC);

// 2. Verileri İşle ve Grupla
$data_tedarik = []; // Üretime Girecekler
$data_uretim  = []; // Sahada Olanlar

$total_tedarik_qty = 0;
$total_uretim_qty  = 0;

// Üretim aşamaları (Tüm varyasyonlarıyla)
$production_steps = [
    'sac_lazer', 'sac lazer', 
    'boru_lazer', 'boru lazer', 
    'kaynak', 
    'boya', 
    'elektrik_montaj', 'elektrik montaj', 
    'test', 
    'paketleme'
];

foreach ($rowsS as $r) {
    $status = $r['status'];
    $qty    = (float)$r['total_qty'];
    
    // --- AİLE GRUBU BELİRLEME ---
    $raw_sku  = trim($r['sku'] ?? '');
    $raw_name = trim($r['name'] ?? '');
    
    if (empty($raw_sku) && strpos($raw_name, 'RN') === 0) {
        $parts = explode(' ', $raw_name);
        $raw_sku = $parts[0];
    }
    
    $family_code = 'DİĞER'; 
    
    if (!empty($raw_sku)) {
        if (strpos($raw_sku, 'RN-MLS-RAY') === 0) {
            if (strpos($raw_sku, 'TR') !== false) $family_code = 'RN-MLS-RAY (TR)';
            elseif (strpos($raw_sku, 'SR') !== false) $family_code = 'RN-MLS-RAY (SR)';
            elseif (strpos($raw_sku, 'SU') !== false) $family_code = 'RN-MLS-RAY (SU)';
            elseif (strpos($raw_sku, 'SA') !== false) $family_code = 'RN-MLS-RAY (SA)';
            else $family_code = 'RN-MLS-RAY';
        } 
        else {
            $parts = explode('-', $raw_sku);
            if (count($parts) >= 2) {
                $family_code = $parts[0] . '-' . $parts[1];
            } else {
                $family_code = $raw_sku;
            }
        }
    }

    // KATEGORİLEME MANTIĞI
    if ($status === 'tedarik') {
        if (!isset($data_tedarik[$family_code])) $data_tedarik[$family_code] = 0;
        $data_tedarik[$family_code] += $qty;
        $total_tedarik_qty += $qty;
    } 
    elseif (in_array($status, $production_steps)) {
        if (!isset($data_uretim[$family_code])) $data_uretim[$family_code] = 0;
        $data_uretim[$family_code] += $qty;
        $total_uretim_qty += $qty;
    }
}

// 3. JSON Hazırla (Büyükten küçüğe)
arsort($data_tedarik);
arsort($data_uretim);

function limit_chart_data($arr) {
    if (count($arr) <= 12) return $arr;
    $top = array_slice($arr, 0, 12, true);
    $others = array_slice($arr, 12, null, true);
    $top['DİĞER'] = array_sum($others);
    return $top;
}

$data_tedarik = limit_chart_data($data_tedarik);
$data_uretim  = limit_chart_data($data_uretim);

$json_tedarik = json_encode(['labels'=>array_keys($data_tedarik), 'data'=>array_values($data_tedarik)], JSON_UNESCAPED_UNICODE);
$json_uretim  = json_encode(['labels'=>array_keys($data_uretim),  'data'=>array_values($data_uretim)], JSON_UNESCAPED_UNICODE);
// =========================================================================================

if (!function_exists('h')) { function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } }
if (!function_exists('fmt_tr_money')) { function fmt_tr_money($v){ if($v===null||$v==='')return ''; return number_format((float)$v,4,',','.'); }

function fmt_tr_date($s){
  if ($s===null || $s==='') return '';
  $s = (string)$s;
  if (preg_match('~^\d{2}[\-/]\d{2}[\-/]\d{4}$~', $s)) {
    return str_replace('/', '-', $s);
  }
  try {
    $dt = new DateTime($s);
    return $dt->format('d-m-Y');
  } catch (Throwable $e) {
    if (preg_match('~^(\d{4})-(\d{2})-(\d{2})~', $s, $m)) {
      return $m[3].'-'.$m[2].'-'.$m[1];
    }
    return $s;
  }
}

 }
if (!function_exists('tr_to_float')) { function tr_to_float($s){ $s=str_replace(['.',','],['','.' ],$s); return is_numeric($s)?(float)$s:null; } }
if (!function_exists('normalize_currency')) {
  function normalize_currency($cur){
    $cur = strtoupper(trim((string)$cur));
    if ($cur === '' || $cur === '—') return '—';
    if ($cur === 'TL' || $cur === '₺' || $cur === 'TRL') return 'TRY';
    if ($cur === 'US$' || $cur === '$') return 'USD';
    if ($cur === '€' || $cur === 'EURO') return 'EUR';
    return $cur;
  }
}
function inparam($k,$d=null){ return (isset($_GET[$k]) && $_GET[$k] !== '') ? trim($_GET[$k]) : $d; }
function inparam_arr($k){
  if (!isset($_GET[$k])) return [];
  $v = $_GET[$k];
  if (is_array($v)) {
    $out=[]; foreach($v as $x){ $x=trim((string)$x); if($x!=='') $out[]=$x; }
    return $out;
  }
  $v = trim((string)$v);
  if ($v==='') return [];
  return array_map('trim', explode(',', $v));
}


$filters=[
  'date_from'     => inparam('date_from'),
  'date_to'       => inparam('date_to'),
  'customer_id'   => inparam('customer_id'),
  'product_query' => inparam('product_query'),
  'project_query' => inparam('project_query'),
  'currency'      => inparam('currency'),
  'min_unit'      => inparam('min_unit'),
  'max_unit'      => inparam('max_unit'),
  'prod_status'  => inparam_arr('prod_status'),
];

$where=[]; $args=[];
$dateCol      = 'orders.order_date';
$projectCol   = 'orders.proje_adi';
$currencyCol  = 'orders.currency';
$orderCodeCol = 'orders.order_code';
$custNameCol  = 'customers.name';
$prodNameCol  = 'products.name';
$prodSkuCol   = 'products.sku';
$itemsTable   = 'order_items';
$qtyCol       = 'quantity';
$unitCol      = 'unit';
$unitPriceCol = 'unit_price';
// Detect item status column if present
$itemStatusCol = null;
try { $db->query("SELECT status FROM `$itemsTable` LIMIT 0"); $itemStatusCol="`$itemsTable`.`status`"; }
catch(Throwable $e){ $itemStatusCol=null; }
$prodStatusCol = 'orders.status';

// --- YENİ: Siparişi alan kolon kontrolü ---
// Veritabanında olduğundan emin olduğumuz için kontrolü atlıyor ve direkt okuyoruz
$siparisiAlanCol = 'orders.siparisi_alan';

try { $db->query("SELECT orders.order_date FROM orders LIMIT 0"); }
catch(Throwable $e){ try{ $db->query("SELECT orders.siparis_tarihi FROM orders LIMIT 0"); $dateCol='orders.siparis_tarihi'; }catch(Throwable $e2){ try{ $db->query("SELECT orders.created_at FROM orders LIMIT 0"); $dateCol='orders.created_at'; }catch(Throwable $e3){ $dateCol='orders.id'; } } }
try { $db->query("SELECT orders.proje_adi FROM orders LIMIT 0"); }
catch(Throwable $e){ try{ $db->query("SELECT orders.project_name FROM orders LIMIT 0"); $projectCol='orders.project_name'; }catch(Throwable $e2){ $projectCol=null; } }
try { $db->query("SELECT orders.currency FROM orders LIMIT 0"); }
catch(Throwable $e){ try{ $db->query("SELECT orders.odeme_para_birimi FROM orders LIMIT 0"); $currencyCol='orders.odeme_para_birimi'; }catch(Throwable $e2){ $currencyCol=null; } }
try { $db->query("SELECT orders.order_code FROM orders LIMIT 0"); }
catch(Throwable $e){ try{ $db->query("SELECT orders.code FROM orders LIMIT 0"); $orderCodeCol='orders.code'; }catch(Throwable $e2){ $orderCodeCol='orders.id'; } }
try { $db->query("SELECT customers.name FROM customers LIMIT 0"); }
catch(Throwable $e){ try{ $db->query("SELECT customers.customer_name FROM customers LIMIT 0"); $custNameCol='customers.customer_name'; }catch(Throwable $e2){ $custNameCol='customers.id'; } }
try { $db->query("SELECT products.name FROM products LIMIT 0"); }
catch(Throwable $e){ try{ $db->query("SELECT products.product_name FROM products LIMIT 0"); $prodNameCol='products.product_name'; }catch(Throwable $e2){ $prodNameCol='products.id'; } }
try { $db->query("SELECT products.sku FROM products LIMIT 0"); }
catch(Throwable $e){ $prodSkuCol=null; }
foreach(['order_items','order_lines','order_products'] as $cand){ try{ $db->query("SELECT * FROM `$cand` LIMIT 0"); $itemsTable=$cand; break; }catch(Throwable $e){} }
try { $db->query("SELECT quantity FROM `$itemsTable` LIMIT 0"); } catch(Throwable $e){ if(@$db->query("SELECT qty FROM `$itemsTable` LIMIT 0")) $qtyCol='qty'; else if(@$db->query("SELECT miktar FROM `$itemsTable` LIMIT 0")) $qtyCol='miktar'; }
try { $db->query("SELECT unit FROM `$itemsTable` LIMIT 0"); }     catch(Throwable $e){ if(@$db->query("SELECT birim FROM `$itemsTable` LIMIT 0")) $unitCol='birim'; else if(@$db->query("SELECT unit_name FROM `$itemsTable` LIMIT 0")) $unitCol='unit_name'; }
try { $db->query("SELECT unit_price FROM `$itemsTable` LIMIT 0"); }catch(Throwable $e){ if(@$db->query("SELECT price FROM `$itemsTable` LIMIT 0")) $unitPriceCol='price'; else if(@$db->query("SELECT birim_fiyat FROM `$itemsTable` LIMIT 0")) $unitPriceCol='birim_fiyat'; }
$productIdCol='product_id'; try{ $db->query("SELECT product_id FROM `$itemsTable` LIMIT 0"); }
catch(Throwable $e){ if(@$db->query("SELECT product FROM `$itemsTable` LIMIT 0")) $productIdCol='product'; else if(@$db->query("SELECT productId FROM `$itemsTable` LIMIT 0")) $productIdCol='productId'; }

if ($filters['date_from']) { $where[]="$dateCol >= ?"; $args[]=$filters['date_from']; }
if ($filters['date_to'])   { $where[]="$dateCol <= ?"; $args[]=$filters['date_to']; }
if ($filters['customer_id']) { $where[]="orders.customer_id = ?"; $args[]=$filters['customer_id']; }
if ($currencyCol && $filters['currency']) {
  $sel = strtoupper(trim($filters['currency']));
  $vals = [$sel];
  if ($sel === 'TRY') { $vals = ['TRY','TL','₺','TRL']; }
  elseif ($sel === 'USD') { $vals = ['USD','$','US$']; }
  elseif ($sel === 'EUR') { $vals = ['EUR','€','EURO']; }
  $place = implode(',', array_fill(0, count($vals), '?'));
  $where[] = "$currencyCol IN ($place)";
  $args = array_merge($args, $vals);
}
if ($projectCol && $filters['project_query']) { $where[]="$projectCol LIKE ?"; $args[]='%'.$filters['project_query'].'%'; }
if ($filters['product_query']) {
  $or=["$prodNameCol LIKE ?"]; $oargs=['%'.$filters['product_query'].'%'];
  if ($prodSkuCol) { $or[]="$prodSkuCol LIKE ?"; $oargs[]='%'.$filters['product_query'].'%'; }
  $where[]='('.implode(' OR ',$or).')'; $args=array_merge($args,$oargs);
}
if ($filters['min_unit']) { $v=tr_to_float($filters['min_unit']); if($v!==null){ $where[]="`$itemsTable`.`$unitPriceCol` >= ?"; $args[]=$v; } }
if ($filters['max_unit']) { $v=tr_to_float($filters['max_unit']); if($v!==null){ $where[]="`$itemsTable`.`$unitPriceCol` <= ?"; $args[]=$v; } }


// Production status multi-select (fixed 10 options)
if (!empty($filters['prod_status']) && !empty($prodStatusCol)){
  $placeholders = implode(',', array_fill(0, count($filters['prod_status']), '?'));
  $where[] = "$prodStatusCol IN ($placeholders)";
  foreach($filters['prod_status'] as $ps){ $args[] = $ps; }
}

$whereSql = $where ? ('WHERE '.implode(' AND ',$where)) : '';

$sel=[
  "orders.id AS order_id",
  "$siparisiAlanCol AS siparisi_alan",
  "$custNameCol AS customer_name",
  "$orderCodeCol AS order_code",
  ($projectCol ? "$projectCol AS project_name" : "NULL AS project_name"),
  "$prodNameCol AS product_name",
  ($prodSkuCol ? "$prodSkuCol AS sku" : "NULL AS sku"),
  "`$itemsTable`.`$qtyCol` AS qty",
  "`$itemsTable`.`$unitCol` AS unit_name",
  "`$itemsTable`.`$unitPriceCol` AS unit_price",
  ($currencyCol ? "$currencyCol AS currency" : "NULL AS currency"),
  "(`$itemsTable`.`$qtyCol`*`$itemsTable`.`$unitPriceCol`) AS line_total",
  "$dateCol AS order_date"
];
$joins=[
  "JOIN orders   ON orders.id = `$itemsTable`.order_id",
  "JOIN products ON products.id = `$itemsTable`.`$productIdCol`",
  "JOIN customers ON customers.id = orders.customer_id"
];
$sql = "SELECT
  ".implode(",
  ",$sel)."
" . "FROM `".$itemsTable."`
" . implode("
",$joins) . "
" . $whereSql . "
" . "ORDER BY ".$dateCol." DESC, orders.id DESC, `".$itemsTable."`.id ASC";

$rows=[]; $queryError=null;
try{ $st=$db->prepare($sql); $st->execute($args); $rows=$st->fetchAll(PDO::FETCH_ASSOC); }
catch(Throwable $e){ $queryError=$e->getMessage(); $rows=[]; }

if (isset($_GET['export']) && $_GET['export']==='csv') {
  $filename='satis_raporu_'.date('Ymd_His').'.csv';
  header('Content-Type: text/csv; charset=UTF-8');
  header('Content-Disposition: attachment; filename="'.$filename.'"');
  echo "\xEF\xBB\xBF";
  $out=fopen('php://output','w');
  fputcsv($out,['Siparişi Alan','Müşteri','Proje','Sipariş Kodu','Ürün','SKU','Miktar','Birim','Birim Fiyat','Para Birimi','Satır Toplam','Sipariş Tarihi']);
  foreach($rows as $r){ 
      $export_sp = trim((string)($r['siparisi_alan'] ?? ''));
      $export_sp = $export_sp === '' ? 'Belirtilmemiş' : mb_convert_case(mb_strtolower(str_replace(['I','İ'], ['ı','i'], $export_sp), 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
      fputcsv($out,[$export_sp, $r['customer_name']??'',$r['project_name']??'',$r['order_code']??'',$r['product_name']??'',$r['sku']??'',$r['qty']??'',$r['unit_name']??'',$r['unit_price']??'',$r['currency']??'',$r['line_total']??'',$r['order_date']??'']); 
  }
  fclose($out); exit;
}

$totalsByCurrency=[]; foreach($rows as $r){ $cur=normalize_currency($r['currency']??'—'); if(!isset($totalsByCurrency[$cur])) $totalsByCurrency[$cur]=0.0; $totalsByCurrency[$cur]+=(float)($r['line_total']??0); }

// --- TCMB GÜNCEL KUR ÇEKİMİ ---
$usd_rate = 1.0;
$eur_rate = 1.0;
try {
    $ctx = stream_context_create(['http' => ['timeout' => 2]]); // Sayfa yavaşlamasın diye 2 sn sınır
    $xml_data = @file_get_contents('https://www.tcmb.gov.tr/kurlar/today.xml', false, $ctx); //🔴 Güncel kur buradan çekiliyor. 
    if ($xml_data) {
        $tcmb = @simplexml_load_string($xml_data);
        if ($tcmb) {
            foreach ($tcmb->Currency as $c) {
                if ((string)$c['CurrencyCode'] === 'USD') $usd_rate = (float)$c->ForexBuying;
                if ((string)$c['CurrencyCode'] === 'EUR') $eur_rate = (float)$c->ForexBuying;
            }
        }
    }
} catch(Throwable $e){}

// Hata olursa veya haftasonu API kapanırsa fallback (varsayılan) kurlar
if ($usd_rate <= 1.0) $usd_rate = 36.50; 
if ($eur_rate <= 1.0) $eur_rate = 38.00;
// -----------------------------

// Grafikler için TL Bazlı Toplamlar ve Ekranda Gösterilecek Ham Toplamlar
$agg_customer_try = []; $agg_project_try = []; $agg_category_try = [];
$cur_customer = []; $cur_project = []; $cur_category = [];

// [YENİ] Satış temsilcisi (siparisi_alan) verileri
$salesperson_orders = [];
$processed_orders_for_sp = []; 

foreach($rows as $r){
  $amt = (float)($r['line_total'] ?? 0);
  $cur = normalize_currency($r['currency'] ?? '—');
  
  $rate = 1.0;
  if ($cur === 'USD') $rate = $usd_rate;
  elseif ($cur === 'EUR') $rate = $eur_rate;
  
  $amt_try = $amt * $rate; // Grafik ve sıralama için TL karşılığı

  $c = trim((string)($r['customer_name'] ?? 'Diğer')); if($c==='') $c='Diğer';
  $p = trim((string)($r['project_name'] ?? 'Diğer'));  if($p==='') $p='Diğer';
  $g = trim((string)($r['product_name'] ?? 'Diğer'));  if($g==='') $g='Diğer';
  
  // --- [YENİ] Siparişi Alan (Büyük/Küçük Harf ve Boşluk Gruplama Zekası) ---
  $raw_sp = trim((string)($r['siparisi_alan'] ?? ''));
  if ($raw_sp === '') {
      $sp = 'Belirtilmemiş'; // Boş siparişler koca bir "Diğer" yerine buraya düşsün
  } else {
      // Türkçe I/İ harf sorunlarını çöz, hepsini küçük harfe çevir, sonra Baş Harflerini Büyüt
      $lower_sp = mb_strtolower(str_replace(['I','İ'], ['ı','i'], $raw_sp), 'UTF-8');
      $sp = mb_convert_case($lower_sp, MB_CASE_TITLE, 'UTF-8');
  }
  
  $oid = $r['order_id'];

  // Siparişi alan kişinin sipariş sayısını hesaplama (Aynı sipariş no 1 kez sayılır)
  if(!isset($processed_orders_for_sp[$oid])) {
      $processed_orders_for_sp[$oid] = true;
      $salesperson_orders[$sp] = ($salesperson_orders[$sp] ?? 0) + 1;
  }

  // TL Üzerinden Grafik ve Sıralama Toplamları
  $agg_customer_try[$c] = ($agg_customer_try[$c] ?? 0) + $amt_try;
  $agg_project_try[$p]  = ($agg_project_try[$p]  ?? 0) + $amt_try;
  $agg_category_try[$g] = ($agg_category_try[$g] ?? 0) + $amt_try;

  // Ekrana basmak için ham döviz tutarlarını koru
  if(!isset($cur_customer[$c])) $cur_customer[$c]=[];
  if(!isset($cur_customer[$c][$cur])) $cur_customer[$c][$cur]=0.0;
  $cur_customer[$c][$cur]+=$amt;

  if(!isset($cur_project[$p])) $cur_project[$p]=[];
  if(!isset($cur_project[$p][$cur])) $cur_project[$p][$cur]=0.0;
  $cur_project[$p][$cur]+=$amt;

  if(!isset($cur_category[$g])) $cur_category[$g]=[];
  if(!isset($cur_category[$g][$cur])) $cur_category[$g][$cur]=0.0;
  $cur_category[$g][$cur]+=$amt;
}

// TL'ye çevrilmiş değerlere göre büyükten küçüğe sırala! (Zekice Kısım)
arsort($agg_customer_try); arsort($agg_project_try); arsort($agg_category_try);
arsort($salesperson_orders); // Satış Temsilcisini en çok satandan küçüğe sırala

function get_dominant_info($tryTotals, $bucketMap) {
  $out=[];
  foreach($tryTotals as $label=>$tryVal){
    $curMap = $bucketMap[$label] ?? [];
    if(empty($curMap)){ 
        $out[$label] = ['cur'=>'TRY', 'val'=>$tryVal, 'try_val'=>$tryVal]; 
        continue; 
    }
    arsort($curMap);
    $dom_cur = array_key_first($curMap);
    $out[$label] = [
        'cur' => $dom_cur,                 // Orijinal Simge (Örn: USD)
        'val' => $curMap[$dom_cur],        // Orijinal Miktar (Örn: 500)
        'try_val' => $tryVal               // Grafiğin oranı için TL hali
    ];
  }
  return $out;
}

// Satış temsilcilerini grafiğin anlayacağı formata çevir
$sp_formatted = [];
foreach($salesperson_orders as $name => $count) {
    $sp_formatted[$name] = [
        'cur' => 'Adet',
        'val' => $count,
        'try_val' => $count
    ];
}

$chart_payload = [
  'customer'    => get_dominant_info($agg_customer_try, $cur_customer),
  'project'     => get_dominant_info($agg_project_try, $cur_project),
  'category'    => get_dominant_info($agg_category_try, $cur_category),
  'salesperson' => $sp_formatted,
];


include __DIR__ . '/includes/header.php';
?>
<style>
.container-card{background:#fff;border:1px solid #e8eef6;border-radius:14px;margin:16px;padding:16px;box-shadow:0 8px 20px rgba(0,0,0,.06)}
.topgrid{display:grid;grid-template-columns: minmax(520px, 1fr) 420px; gap:16px; align-items:start}
@media (max-width:1200px){.topgrid{grid-template-columns:1fr}}
/* Filtre Ana Kutusu */
.filters {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 16px 20px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}
.filters label.label {
    font-size: 12px;
    color: #475569;
    font-weight: 600;
    margin-bottom: 6px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.filters .input {
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    padding: 8px 12px;
    transition: all 0.2s;
    font-size: 13px;
}
.filters .input:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
}
@media (max-width:1100px){.filters{grid-template-columns:repeat(3,minmax(0,1fr))}}
@media (max-width:720px){.filters{grid-template-columns:repeat(1,minmax(0,1fr))}}
.label{color:#102a43;font-size:12px;margin-bottom:6px;display:block}
.input{width:100%;padding:12px 14px;border:1px solid #e8eef6;border-radius:10px;background:#fff}
.actions{display:flex;gap:10px;align-items:center;margin-top:8px}
.btn{display:inline-flex;align-items:center;gap:8px;padding:10px 14px;border-radius:10px;border:1px solid #e8eef6;background:#fff;cursor:pointer;text-decoration:none;font-weight:600}
.btn:hover{background:#f5f7fb}
.btn-primary{background:#1e293b;color:#fff;border-color:#1e293b}
.btn-primary:hover{background:#0f172a}
.small{font-size:12px;color:#334155}
.stat-col{display:grid;grid-template-columns:1fr;gap:12px}
.stat-card{background:#fff;border:1px solid #e8eef6;border-radius:12px;padding:16px;box-shadow:0 6px 16px rgba(0,0,0,.06)}
.stat-card h4{margin:0 0 6px 0;font-size:12px;color:#6b7a90;font-weight:600}
.stat-card .val{font-size:22px;font-weight:800}

.chart-panel{margin-top:14px; padding:12px; border:1px solid #e8eef6; border-radius:12px; background:#fff}
.quad-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}
@media (max-width:1400px){.quad-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media (max-width:820px){.quad-grid{grid-template-columns:1fr}}
.pie-card{border:1px solid #e8eef6;border-radius:12px;padding:12px}
.pie-card h4{margin:0 0 8px 0;font-size:13px;color:#0f172a}
.pie-canvas-wrap{width:100%;height:220px;display:flex;align-items:center;justify-content:center}
.pie-canvas-wrap canvas{width:240px;max-width:100%;height:100%}
.top5{margin-top:8px}
.top5 ul{list-style:none;margin:0;padding:0;display:grid;grid-template-columns:repeat(1,minmax(0,1fr));gap:6px}
.top5 li{display:flex;justify-content:space-between;gap:8px;font-size:12px;color:#475569;align-items:center;}
.top5 li .name{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:1;min-width:0;}
.top5 li .val{font-variant-numeric:tabular-nums;white-space:nowrap;flex-shrink:0;font-weight:600;color:#0f172a;}
.table-wrap{margin-top:14px;border:1px solid #e8eef6;border-radius:12px;overflow:hidden;background:#fff}
.table{width:100%;border-collapse:separate;border-spacing:0}
.table thead th{background:#eaf2ff;color:#0f172a;text-align:left;padding:12px 14px;font-weight:700;border-bottom:1px solid #e3ecfd}
.table tbody td{padding:12px 14px;border-bottom:1px solid #eef2f7;vertical-align:top}
.ta-right{text-align:right}.ta-center{text-align:center}
.badge{display:inline-block;padding:4px 8px;border-radius:999px;background:#eef2ff;font-weight:700}
</style>
<style>
/* Üretim Durumu çoklu seçim */
.prod-status-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}
.prod-status-grid .status-chip {
    padding: 6px 12px;
    min-height: auto;
    font-size: 12px;
    border-radius: 20px;
}
@media (max-width:1200px){.prod-status-grid{grid-template-columns:repeat(4,minmax(0,1fr))}}
@media (max-width:900px){.prod-status-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}
@media (max-width:640px){.prod-status-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
.status-chip{display:flex;align-items:center;justify-content:flex-start;gap:8px;min-height:56px;padding:10px 12px;border:1px solid #e8eef6;border-radius:10px;background:#fff;cursor:pointer;user-select:none;line-height:1.2}
.status-chip input{flex:0 0 auto}
.status-chip span{display:block;overflow:hidden;text-overflow:ellipsis;color:#0f172a;font-size:13px;font-weight:600;white-space:normal}
.status-chip:hover{background:#f8fafc}

/* Filter item should span wide area */
.topgrid .wide-row{grid-column:1 / -1}
@media (max-width:1100px){.topgrid .wide-row{grid-column:1 / -1}}
@media (max-width:720px){.topgrid .wide-row{grid-column:1 / -1}}

/* Selected state emphasis */
.status-chip input:checked + span{font-weight:800;text-decoration:underline}

/* compact variation */
.prod-status-grid.compact .status-chip{min-height:66px;padding:8px 10px}

/* place Üretim grid inside left column's filter area */
.filters .wide6{grid-column:1 / -1}
/* ensure text visible */
.status-chip span{color:#0f172a !important}

/* vertical layout: checkbox on top, label under it */
.status-chip{flex-direction:column;align-items:center;justify-content:center;text-align:center}
.status-chip input{margin:0}
.status-chip span{margin-top:6px;font-size:12px;line-height:1.1;color:#0f172a!important;white-space:normal}
</style>


<div class="container-card">
  <div class="container-card" style="margin-bottom: 20px; background: #f0fdf4; border-color: #bbf7d0;">
    <h3 style="margin: 0 0 10px 0; font-size: 16px; color: #166534;">📅 Günlük Faaliyet Raporları</h3>
    <div style="display: flex; gap: 20px; align-items: center; flex-wrap: wrap;">
        
        <div style="display: flex; gap: 10px;">
            <?php 
            for ($i = 0; $i < 5; $i++) {
                $d = date('Y-m-d', strtotime("-$i days"));
                $label = ($i === 0) ? 'Bugün' : (($i === 1) ? 'Dün' : date('d.m', strtotime($d)));
                echo '<a href="report_daily_print.php?date='.$d.'" target="_blank" class="btn" style="background:#fff; border-color:#86efac; color:#14532d;">';
                echo '📄 ' . $label . ' <span style="font-size:10px; color:#999;">('.date('d.m.Y', strtotime($d)).')</span>';
                echo '</a>';
            }
            ?>
        </div>

        <div style="border-left: 1px solid #bbf7d0; padding-left: 20px; display: flex; align-items: center; gap: 10px;">
            <label style="font-size: 13px; font-weight: 600; color: #166534;">Başka bir gün:</label>
            <form action="report_daily_print.php" method="get" target="_blank" style="display:flex; gap:5px;">
                <input type="date" name="date" class="input" style="padding: 6px; width: 140px;" required>
                <button type="submit" class="btn btn-primary" style="padding: 6px 12px;">Raporla</button>
            </form>
        </div>
    </div>
</div>
<div style="margin-bottom: 30px; border-bottom: 2px dashed #e2e8f0; padding-bottom: 20px;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
        <h3 style="margin:0; color:#312e81; font-size:18px; display:flex; align-items:center; gap:10px;">
            🏭 Canlı Üretim Sahası
            <span style="background:#e0e7ff; color:#3730a3; padding:4px 8px; border-radius:6px; font-size:11px; font-weight:normal;">Anlık Adet Yükü</span>
        </h3>
        <div style="font-size:12px; color:#999;">
            <span style="display:inline-block; width:8px; height:8px; background:#22c55e; border-radius:50%; margin-right:5px;"></span>
            Canlı Veri
        </div>
    </div>
    
    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
        
        <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:12px; padding:20px; position:relative; overflow:hidden;">
            <div style="position:absolute; top:-10px; right:-10px; font-size:80px; opacity:0.05; color:#15803d;">⏳</div>
            <div style="text-align:center; margin-bottom:15px; position:relative; z-index:2;">
                <h4 style="margin:0; color:#166534; font-size:15px; text-transform:uppercase; letter-spacing:0.5px;">Üretime Girecekler</h4>
                <div style="font-size:13px; color:#15803d; opacity:0.8;">(Tedarik Aşaması)</div>
                <div style="font-size:28px; font-weight:800; color:#15803d; margin-top:5px; text-shadow:0 2px 4px rgba(0,0,0,0.05);">
                    <?=number_format($total_tedarik_qty,0,',','.')?> <span style="font-size:14px;font-weight:600;">Adet</span>
                </div>
            </div>
            <div style="height:250px; position:relative; z-index:2;">
                <canvas id="chartTedarik"></canvas>
            </div>
        </div>

        <div style="background:#fff7ed; border:1px solid #ffedd5; border-radius:12px; padding:20px; position:relative; overflow:hidden;">
            <div style="position:absolute; top:-10px; right:-10px; font-size:80px; opacity:0.05; color:#c2410c;">⚙️</div>
            <div style="text-align:center; margin-bottom:15px; position:relative; z-index:2;">
                <h4 style="margin:0; color:#9a3412; font-size:15px; text-transform:uppercase; letter-spacing:0.5px;">Üretimde Olanlar</h4>
                <div style="font-size:13px; color:#c2410c; opacity:0.8;">(Lazer'den Paketlemeye)</div>
                <div style="font-size:28px; font-weight:800; color:#c2410c; margin-top:5px; text-shadow:0 2px 4px rgba(0,0,0,0.05);">
                    <?=number_format($total_uretim_qty,0,',','.')?> <span style="font-size:14px;font-weight:600;">Adet</span>
                </div>
            </div>
            <div style="height:250px; position:relative; z-index:2;">
                <canvas id="chartUretim"></canvas>
            </div>
        </div>

    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
    const dTedarik = <?=$json_tedarik?>;
    const dUretim  = <?=$json_uretim?>;

    // Otomatik Renk Üretici (Canlı Pastel Tonlar)
    function generateColors(count, hueStart) {
        let colors = [];
        for(let i=0; i<count; i++) {
            // Renkleri birbirinden uzaklaştırarak üret
            let hue = (hueStart + (i * 45)) % 360; 
            colors.push(`hsl(${hue}, 70%, 55%)`);
        }
        return colors;
    }

    function renderChart(id, dataObj, startHue) {
        const canvas = document.getElementById(id);
        const container = canvas.parentNode;
        
        if(dataObj.data.length === 0) {
            container.innerHTML = "<div style='display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;color:#94a3b8;font-style:italic;'><div style='font-size:24px;margin-bottom:5px;'>∅</div>Veri Yok</div>";
            return;
        }

        const ctx = canvas.getContext('2d');
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: dataObj.labels,
                datasets: [{
                    data: dataObj.data,
                    backgroundColor: generateColors(dataObj.data.length, startHue),
                    borderWidth: 2,
                    borderColor: '#ffffff',
                    hoverOffset: 15
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { 
                        position: 'right', 
                        labels: { boxWidth: 12, font: {size: 11}, padding: 15 },
                        // [GÜNCELLENMİŞ] TIKLAMA OLAYI
                        onClick: function(e, legendItem, legend) {
                            const index = legendItem.index;
                            const ci = legend.chart;
                            
                            // 1. Gizle/Göster işlemini yap
                            ci.toggleDataVisibility(index);
                            ci.update();

                            // 2. Görünür Olanları Yeniden Topla
                            let visibleTotal = 0;
                            ci.data.datasets[0].data.forEach((val, i) => {
                                // getDataVisibility(i) false değilse (true veya undefined) görünür demektir.
                                if (ci.getDataVisibility(i) !== false) {
                                    visibleTotal += parseFloat(val);
                                }
                            });

                            // 3. Ekrandaki Sayıyı Güncelle
                            // DOM Yolu: Canvas -> Parent(Chart Div) -> Kardeş(Başlık Divi) -> Son Çocuk(Sayı Divi)
                            const infoDiv = ci.canvas.parentElement.previousElementSibling;
                            if(infoDiv && infoDiv.lastElementChild) {
                                infoDiv.lastElementChild.innerHTML = visibleTotal.toLocaleString('tr-TR') + ' <span style="font-size:14px;font-weight:600;">Adet</span>';
                            }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(255, 255, 255, 0.9)',
                        titleColor: '#1e293b',
                        bodyColor: '#1e293b',
                        borderColor: '#e2e8f0',
                        borderWidth: 1,
                        padding: 10,
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                let value = context.parsed;
                                return ' ' + label + ': ' + value + ' Adet';
                            }
                        }
                    }
                },
                layout: { padding: 10 }
            }
        });
    }

    renderChart('chartTedarik', dTedarik, 150); // 150: Yeşil tonlarından başla
    renderChart('chartUretim', dUretim, 25);    // 25: Turuncu tonlarından başla
});
</script>
  <h2 style="margin:0 0 14px 2px">Satış Raporları</h2>

  <?php if ($queryError): ?>
    <div class="alert alert-danger" style="margin:8px 0;background:#fff1f2;border:1px solid #fecdd3;padding:10px;border-radius:8px"><?= h($queryError) ?></div>
  <?php endif; ?>

  <div class="topgrid">
    <form method="get" id="reportFilters" class="filters">
      
      <div>
        <label class="label">🗓️ Başlangıç Tarihi</label>
        <input type="date" name="date_from" value="<?=h($filters['date_from'])?>" class="input">
      </div>
      <div>
        <label class="label">🗓️ Bitiş Tarihi</label>
        <input type="date" name="date_to" value="<?=h($filters['date_to'])?>" class="input">
      </div>
      
      <div>
        <label class="label">👤 Müşteri</label>
        <select name="customer_id" class="input">
          <option value="">— Tüm Müşteriler —</option>
          <?php
          try { $cs=$db->query("SELECT id, name FROM customers ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC); }
          catch (Throwable $e) { try { $cs=$db->query("SELECT id, customer_name AS name FROM customers ORDER BY customer_name ASC")->fetchAll(PDO::FETCH_ASSOC); } catch(Throwable $e2){ $cs=[]; } }
          foreach ($cs as $c):
            $sel = ($filters['customer_id'] == $c['id']) ? 'selected' : '';
          ?>
            <option value="<?=$c['id']?>" <?=$sel?>><?=h($c['name'])?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="label">📁 Proje Adı</label>
        <input type="text" name="project_query" placeholder="Proje adı ile ara..." value="<?=h($filters['project_query'])?>" class="input">
      </div>

      <div>
        <label class="label">📦 Ürün (Ad veya SKU)</label>
        <input type="text" name="product_query" placeholder="Örn: Wallwasher, 12345" value="<?=h($filters['product_query'])?>" class="input">
      </div>
      <div>
        <label class="label">💱 Para Birimi</label>
        <select name="currency" class="input">
          <option value="">— Tümü —</option>
          <?php foreach (['TRY','USD','EUR'] as $cur): $sel = ($filters['currency'] && normalize_currency($filters['currency'])===$cur)?'selected':''; ?>
            <option value="<?=$cur?>" <?=$sel?>><?=$cur?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="label">💰 Min Fiyat</label>
        <input type="text" name="min_unit" placeholder="Alt limit (Örn: 100)" value="<?=h($filters['min_unit'])?>" class="input">
      </div>
      <div>
        <label class="label">💰 Max Fiyat</label>
        <input type="text" name="max_unit" placeholder="Üst limit (Örn: 5000)" value="<?=h($filters['max_unit'])?>" class="input">
      </div>

      <div style="grid-column: 1 / -1; padding-top: 10px; border-top: 1px dashed #cbd5e1; margin-top: 5px;">
        <label class="label" style="display:block; margin-bottom:10px;">⚙️ Üretim Aşamaları (Çoklu Seçim)</label>
        <div class="prod-status-grid">
        <?php
          $statusFixed = [
            'tedarik'         => 'Tedarik',
            'sac lazer'       => 'Sac Lazer',
            'boru lazer'      => 'Boru Lazer',
            'kaynak'          => 'Kaynak',
            'boya'            => 'Boya',
            'elektrik montaj' => 'Elektrik Montaj',
            'test'            => 'Test',
            'paketleme'       => 'Paketleme',
            'sevkiyat'        => 'Sevkiyat',
            'teslim edildi'   => 'Teslim Edildi',
          ];
          foreach($statusFixed as $val => $label){
            $checked = in_array($val, $filters['prod_status']??[]) ? ' checked' : '';
            ?>
            <label class="status-chip" style="cursor:pointer;">
              <input type="checkbox" name="prod_status[]" value="<?=h($val)?>"<?=$checked?>>
              <span><?=h($label)?></span>
            </label>
            <?php
          }
        ?>
        </div>
      </div>
<div style="grid-column:1 / -1" class="actions">
        <button class="btn" type="submit">Filtrele</button>
        <a class="btn" href="<?=h(basename(__FILE__))?>">Sıfırla</a>
        <?php $q=$_GET; $q['export']='csv'; $exportUrl=basename(__FILE__).'?'.http_build_query($q); ?>
        <a class="btn btn-primary" href="<?=$exportUrl?>">Excel (CSV) Dışa Aktar</a>
        <span class="small"><?=count($rows)?> satır bulundu.</span>
      </div>
    </form>

    <div class="stat-col">
      <?php foreach (['TRY','USD','EUR'] as $cur): if(isset($totalsByCurrency[$cur])): ?>
        <div class="stat-card">
          <h4>Toplam (<?= h($cur) ?>)</h4>
          <div class="val"><?= fmt_tr_money($totalsByCurrency[$cur]) ?> <?= h($cur) ?></div>
        </div>
      <?php endif; endforeach; ?>
      
      <div class="stat-card" style="background: #d3f5d588; border-color:#cbd5e1; display:flex; flex-direction:column; justify-content:center; gap:8px; margin-top:5px;">
        <h4 style="display:flex; justify-content:space-between; color:#334155; margin:0;">
            <span>💱 Güncel Kur (TCMB Döviz Alış)</span>
        </h4>
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <div>
                <div style="font-size:11px; color: #64748b;">USD / TRY</div>
                <div style="font-size:16px; font-weight:800; color:#0f172a;">₺<?=number_format($usd_rate, 4, ',', '.')?></div>
            </div>
            <div style="text-align:right;">
                <div style="font-size:11px; color: #64748b;">EUR / TRY</div>
                <div style="font-size:16px; font-weight:800; color:#0f172a;">₺<?=number_format($eur_rate, 4, ',', '.')?></div>
            </div>
        </div>
        <div style="font-size:10px; color:#94a3b8; margin-top:4px;">*Grafik sıralamaları bu kur baz alınarak TL'ye çevrilir.</div>
      </div>

    </div>
  </div>

  <div class="chart-panel">
    <div class="quad-grid">
      <div class="pie-card">
        <h4>Satış Temsilcisi Dağılımı</h4>
        <div class="pie-canvas-wrap"><canvas id="pieSalesperson"></canvas></div>
        <div class="top5"><ul id="top5Salesperson"></ul></div>
      </div>
      <div class="pie-card">
        <h4>Müşterilere Göre Dağılım</h4>
        <div class="pie-canvas-wrap"><canvas id="pieCustomer"></canvas></div>
        <div class="top5"><ul id="top5Customer"></ul></div>
      </div>
      <div class="pie-card">
        <h4>Projelere Göre Dağılım</h4>
        <div class="pie-canvas-wrap"><canvas id="pieProject"></canvas></div>
        <div class="top5"><ul id="top5Project"></ul></div>
      </div>
      <div class="pie-card">
        <h4>Ürün Gruplarına Göre Dağılım</h4>
        <div class="pie-canvas-wrap"><canvas id="pieCategory"></canvas></div>
        <div class="top5"><ul id="top5Category"></ul></div>
      </div>
    </div>
  </div>

  <div class="table-wrap">
    <style>#reportTable th:nth-child(4),#reportTable td:nth-child(4),#reportTable th:nth-child(5),#reportTable td:nth-child(5),#reportTable th:nth-child(6),#reportTable td:nth-child(6),#reportTable th:nth-child(7),#reportTable td:nth-child(7){text-align:center!important;}</style><table class="table" id="reportTable">
      <thead>
        <tr>
          <th>Sipariş Tarihi</th>
          <th>Siparişi Alan</th>
          <th>Müşteri</th>
          <th>Proje Adı</th>
          <th class="ta-center" style="text-align:center;">Sipariş Kodu</th>
          <th class="ta-center" style="text-align:center;">Toplam Tutar</th>
          <th class="ta-center" style="text-align:center;">KDV</th>
          <th class="ta-center" style="text-align:center;">Genel Toplam</th>
        </tr>
<tbody>
        <?php
        // Tek satır/sipariş: tablo içinde, sadece görünümde grupluyoruz (SQL'e dokunmadan)
        $__vatRate = 0.20; // KDV %20 sabit
        $__orders = [];
        foreach (($rows ?? []) as $r) {
          $__code = (string)($r['order_code'] ?? '');
          if ($__code === '') continue;
          
          // Satici ismini formatla
          $raw_sp2 = trim((string)($r['siparisi_alan'] ?? ''));
          $formatted_sp = $raw_sp2 === '' ? 'Belirtilmemiş' : mb_convert_case(mb_strtolower(str_replace(['I','İ'], ['ı','i'], $raw_sp2), 'UTF-8'), MB_CASE_TITLE, 'UTF-8');

          if (!isset($__orders[$__code])) {
            $__orders[$__code] = [
              'order_id'      => $r['order_id'] ?? null,
              'order_date'    => $r['order_date'] ?? '',
              'siparisi_alan' => $formatted_sp,
              'customer_name' => $r['customer_name'] ?? '',
              'project_name'  => $r['project_name'] ?? '',
              'order_code'    => $__code,
              'currency'      => $r['currency'] ?? '',
              'subtotal'      => 0.0,
            ];
          }
          $__orders[$__code]['subtotal'] += (float)($r['line_total'] ?? 0);
        }
        // Yazdır
        if (empty($__orders)):
        ?>
          <tr><td style="text-align:center;" colspan="8" class="ta-center muted">Kayıt bulunamadı.</td></tr>
        <?php else: foreach ($__orders as $__o): 
          $__kdv = $__o['subtotal'] * $__vatRate;
          $__genel = $__o['subtotal'] + $__kdv;
          
          // Satici ismine gore renk ver (Bos ise kirmizi olsun)
          $sp_color = $__o['siparisi_alan'] === 'Belirtilmemiş' ? 'color:#ef4444; font-style:italic;' : 'color:#0f172a; font-weight:600;';
        ?>
          <tr data-order-id="<?= (int)($__o['order_id'] ?? 0) ?>" class="order-row">
            <td><?=fmt_tr_date($__o['order_date'] ?? '')?></td>
            <td style="<?= $sp_color ?>"><?=h($__o['siparisi_alan'])?></td>
            <td><?=h($__o['customer_name'])?></td>
            <td><?=h($__o['project_name'])?></td>
            <td style="text-align:center;" class="ta-center"><a href="order_view.php?id=<?= (int)($__o['order_id'] ?? 0) ?>" class="badge"><?=h($__o['order_code'])?></a></td>
            <td class="ta-center"><?=fmt_tr_money($__o['subtotal'])?> <?=h($__o['currency'])?></td>
            <td class="ta-center"><?=fmt_tr_money($__kdv)?> <?=h($__o['currency'])?></td>
            <td class="ta-center"><?=fmt_tr_money($__genel)?> <?=h($__o['currency'])?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
      </tbody>
    </table>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>

<script>
(function(){
  const payload = <?php echo json_encode($chart_payload, JSON_UNESCAPED_UNICODE); ?>;

  // 1. CANLI ÜRETİM SAHASINDAKİ MATEMATİKSEL RENK ALGORİTMASI
  // Yan yana gelen dilimler asla birbirine benzemez (+45 derece atlar)
  function generateColors(count, hueStart) {
      let colors = [];
      for(let i=0; i<count; i++) {
          let hue = (hueStart + (i * 45)) % 360; 
          colors.push(`hsl(${hue}, 70%, 55%)`);
      }
      return colors;
  }

  function entriesFrom(group){
    const items = (payload && payload[group]) || {};
    return Object.entries(items)
      .map(([name, info]) => ({
         name: name, 
         val: Number(info.try_val)||0,        // Grafik için TL
         disp_val: Number(info.val)||0,       // Liste için Orijinal Döviz
         cur: info.cur||''
      }))
      .sort((a,b)=> b.val - a.val);
  }
  
  function symbol(cur){ return cur==='TRY'?'₺':(cur==='USD'?'$':(cur==='EUR'?'€':'')); }

  function renderPie(canvasId, listId, entries, startHue, isCount = false){
    const labels = entries.map(e=>e.name);
    const data   = entries.map(e=>e.val); 
    
    const colors = generateColors(labels.length, startHue); 

    const ctx = document.getElementById(canvasId)?.getContext('2d');
    if (ctx && labels.length){
      new Chart(ctx, {
        type: 'doughnut',
        data: { 
            labels: labels, 
            datasets: [{ 
                data: data, 
                backgroundColor: colors,
                borderWidth: 2,           
                borderColor: '#ffffff',
                hoverOffset: 15           
            }] 
        },
        options: {
          responsive: true, 
          maintainAspectRatio: false, 
          plugins: { 
              legend: { display:false }, 
              tooltip: { 
                  backgroundColor: 'rgba(255, 255, 255, 0.95)',
                  titleColor: '#1e293b',
                  bodyColor: '#1e293b',
                  borderColor: '#e2e8f0',
                  borderWidth: 1,
                  padding: 12,
                  callbacks: {
                      label: function(context) {
                          let label = context.label || '';
                          let value = context.parsed;
                          
                          let maxLength = 30; 
                          if (label.length > maxLength) {
                              label = label.substring(0, maxLength) + '...';
                          }
                          
                          if (isCount) {
                              return ' ' + label + ': ' + value + ' Sipariş';
                          } else {
                              return ' ' + label + ': ₺' + value.toLocaleString('tr-TR', {minimumFractionDigits:4, maximumFractionDigits:4});
                          }
                      }
                  }
              } 
          },
          layout: { padding: 15 }
        }
      });
    }

    const ul = document.getElementById(listId);
    if (ul){
      if (!entries.length){
        ul.innerHTML = '<li><span class="name">Veri yok</span><span class="val">—</span></li>';
      } else {
        const top5 = entries.slice(0,5);
        ul.innerHTML = top5.map(it => {
          if (isCount) {
              return `<li><span class="name">${it.name}</span><span class="val" style="color:#10b981;">${it.disp_val} Adet</span></li>`;
          } else {
              return `<li><span class="name">${it.name}</span><span class="val">${it.disp_val.toLocaleString('tr-TR',{minimumFractionDigits:2, maximumFractionDigits:2})} ${symbol(it.cur)}</span></li>`;
          }
        }).join('');
      }
    }
  }

  // 2. HER GRAFİĞE FARKLI BİR "TEMA/BAŞLANGIÇ" RENGİ VERİYORUZ
  renderPie('pieSalesperson', 'top5Salesperson', entriesFrom('salesperson'), 50, true); // Sarı/Yeşil (Sipariş Sayısı)
  renderPie('pieCustomer', 'top5Customer', entriesFrom('customer'), 200, false); // Mavi tonlarından başlar
  renderPie('pieProject',  'top5Project',  entriesFrom('project'), 280, false);  // Mor tonlarından başlar
  renderPie('pieCategory', 'top5Category', entriesFrom('category'), 340, false); // Kırmızı/Pembe tonları
})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function(){
  var table = document.getElementById('reportTable');
  if (!table) return;
  table.querySelectorAll('tbody tr.order-row').forEach(function(tr){
    var oid = tr.getAttribute('data-order-id');
    if (!oid || oid === '0') return;
    tr.style.cursor = 'pointer';
    tr.addEventListener('click', function(ev){
      var tag = ev.target.tagName.toLowerCase();
      if (['a','button','input','select','textarea','label'].includes(tag)) return;
      window.location.href = 'order_view.php?id=' + encodeURIComponent(oid);
    });
  });
});
</script>
<!-- ANIM_START:renplan -->
<style>
.will-animate { opacity:0; transform:translateY(6px); transition:opacity .4s ease, transform .4s ease; }
.will-animate.appear { opacity:1; transform:none; }
</style>

<script>
(function(){
  function trParse(s){ return parseFloat(String(s).replace(/\./g,'').replace(',', '.')); }
  function trFmt(n){ return n.toLocaleString('tr-TR', {minimumFractionDigits:2, maximumFractionDigits:2}); }
  window.__renplan_trParse = trParse; window.__renplan_trFmt = trFmt;

  function countUp(el, to, ms){
    const curTxt = (el.dataset.cur || '').trim();
    const from = el.dataset.prev ? trParse(el.dataset.prev) : 0;
    const start = performance.now();
    function step(t){
      const p = Math.min((t - start) / ms, 1);
      const e = 1 - Math.pow(1 - p, 3);
      const v = from + (to - from) * e;
      el.textContent = trFmt(v) + (curTxt ? (' ' + curTxt) : '');
      if(p<1) requestAnimationFrame(step); else el.dataset.prev = trFmt(to);
    }
    requestAnimationFrame(step);
  }

  document.addEventListener('DOMContentLoaded', function(){
    document.querySelectorAll('.pie-card, .stat-card').forEach(function(el){ el.classList.add('will-animate'); });
    document.querySelectorAll('.stat-card .val').forEach(function(el){
      const parts = el.textContent.trim().split(/\s+/);
      const cur = parts.pop(); el.dataset.cur = cur;
      const to  = trParse(parts.join(' '));
      if(!isNaN(to)) countUp(el, to, 900);
    });
    const io = new IntersectionObserver(function(entries){
      entries.forEach(function(e){ if(e.isIntersecting){ e.target.classList.add('appear'); io.unobserve(e.target); } });
    }, {threshold:.15});
    document.querySelectorAll('.will-animate').forEach(function(n){ io.observe(n); });
  });
})();
</script>

<script>
(function(){
  const io = new IntersectionObserver(function(entries){
    entries.forEach(function(e){ if(e.isIntersecting){ e.target.classList.add('appear'); io.unobserve(e.target); } });
  }, {threshold:.15});
  document.addEventListener('DOMContentLoaded', function(){
    document.querySelectorAll('.will-animate').forEach(function(n){ io.observe(n); });
  });
})();
</script>
<!-- ANIM_END:renplan -->