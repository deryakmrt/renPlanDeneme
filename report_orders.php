<?php
/**
 * report_orders.php — Sales Report (filters left + totals right + THREE pies)
 */

declare(strict_types=1);
ini_set('display_errors','1'); ini_set('display_startup_errors','1'); error_reporting(E_ALL);

require_once __DIR__ . '/includes/helpers.php';
require_login();
$db = pdo();

if (!function_exists('h')) { function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } }
if (!function_exists('fmt_tr_money')) { function fmt_tr_money($v){ if($v===null||$v==='')return ''; return number_format((float)$v,2,',','.'); }

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
  fputcsv($out,['Müşteri','Proje','Sipariş Kodu','Ürün','SKU','Miktar','Birim','Birim Fiyat','Para Birimi','Satır Toplam','Sipariş Tarihi']);
  foreach($rows as $r){ fputcsv($out,[$r['customer_name']??'',$r['project_name']??'',$r['order_code']??'',$r['product_name']??'',$r['sku']??'',$r['qty']??'',$r['unit_name']??'',$r['unit_price']??'',$r['currency']??'',$r['line_total']??'',$r['order_date']??'']); }
  fclose($out); exit;
}

$totalsByCurrency=[]; foreach($rows as $r){ $cur=normalize_currency($r['currency']??'—'); if(!isset($totalsByCurrency[$cur])) $totalsByCurrency[$cur]=0.0; $totalsByCurrency[$cur]+=(float)($r['line_total']??0); }

// Aggregations with dominant currency per label
$agg_customer = []; $agg_project = []; $agg_category = [];
$cur_customer = []; $cur_project = []; $cur_category = []; // per-label currency sums

foreach($rows as $r){
  $amt = (float)($r['line_total'] ?? 0);
  $cur = normalize_currency($r['currency'] ?? '—');
  $c = trim((string)($r['customer_name'] ?? 'Diğer')); if($c==='') $c='Diğer';
  $p = trim((string)($r['project_name'] ?? 'Diğer'));  if($p==='') $p='Diğer';
  $g = trim((string)($r['product_name'] ?? 'Diğer'));  if($g==='') $g='Diğer';

  // totals
  $agg_customer[$c] = ($agg_customer[$c] ?? 0) + $amt;
  $agg_project[$p]  = ($agg_project[$p]  ?? 0) + $amt;
  $agg_category[$g] = ($agg_category[$g] ?? 0) + $amt;

  // currency buckets
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

// sort totals desc
arsort($agg_customer); arsort($agg_project); arsort($agg_category);

// pick dominant currency by amount for each label
function dominant_currency($bucketMap){
  $out=[];
  foreach($bucketMap as $label=>$curMap){
    if(empty($curMap)){ $out[$label]='—'; continue; }
    arsort($curMap);
    $out[$label] = array_key_first($curMap);
  }
  return $out;
}
$dom_customer = dominant_currency($cur_customer);
$dom_project  = dominant_currency($cur_project);
$dom_category = dominant_currency($cur_category);

$chart_payload = [
  'customer' => ['totals'=>$agg_customer, 'currency'=>$dom_customer],
  'project'  => ['totals'=>$agg_project,  'currency'=>$dom_project],
  'category' => ['totals'=>$agg_category, 'currency'=>$dom_category],
];


include __DIR__ . '/includes/header.php';
?>
<style>
.container-card{background:#fff;border:1px solid #e8eef6;border-radius:14px;margin:16px;padding:16px;box-shadow:0 8px 20px rgba(0,0,0,.06)}
.topgrid{display:grid;grid-template-columns: minmax(520px, 1fr) 420px; gap:16px; align-items:start}
@media (max-width:1200px){.topgrid{grid-template-columns:1fr}}
.filters{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}
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
.triple-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}
@media (max-width:1200px){.triple-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media (max-width:820px){.triple-grid{grid-template-columns:1fr}}
.pie-card{border:1px solid #e8eef6;border-radius:12px;padding:12px}
.pie-card h4{margin:0 0 8px 0;font-size:13px;color:#0f172a}
.pie-canvas-wrap{width:100%;height:220px;display:flex;align-items:center;justify-content:center}
.pie-canvas-wrap canvas{width:240px;max-width:100%;height:100%}
.top5{margin-top:8px}
.top5 ul{list-style:none;margin:0;padding:0;display:grid;grid-template-columns:repeat(1,minmax(0,1fr));gap:6px}
.top5 li{display:flex;justify-content:space-between;gap:8px;font-size:12px;color:#475569}
.top5 li .name{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.top5 li .val{font-variant-numeric:tabular-nums}
.table-wrap{margin-top:14px;border:1px solid #e8eef6;border-radius:12px;overflow:hidden;background:#fff}
.table{width:100%;border-collapse:separate;border-spacing:0}
.table thead th{background:#eaf2ff;color:#0f172a;text-align:left;padding:12px 14px;font-weight:700;border-bottom:1px solid #e3ecfd}
.table tbody td{padding:12px 14px;border-bottom:1px solid #eef2f7;vertical-align:top}
.ta-right{text-align:right}.ta-center{text-align:center}
.badge{display:inline-block;padding:4px 8px;border-radius:999px;background:#eef2ff;font-weight:700}
</style>
<style>
/* Üretim Durumu çoklu seçim 5 sütun grid */
.prod-status-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:10px 12px;align-items:stretch}
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
  <h2 style="margin:0 0 14px 2px">Satış Raporları</h2>

  <?php if ($queryError): ?>
    <div class="alert alert-danger" style="margin:8px 0;background:#fff1f2;border:1px solid #fecdd3;padding:10px;border-radius:8px"><?= h($queryError) ?></div>
  <?php endif; ?>

  <div class="topgrid">
    <form method="get" id="reportFilters" class="filters">
<div>
        <label class="label">Tarih (Başlangıç)</label>
        <input type="date" name="date_from" value="<?=h($filters['date_from'])?>" class="input" placeholder="gg.aa.yyyy">
      </div>
<div>
        <label class="label">Tarih (Bitiş)</label>
        <input type="date" name="date_to" value="<?=h($filters['date_to'])?>" class="input" placeholder="gg.aa.yyyy">
      </div>
<div>
        <label class="label">Müşteri</label>
        <select name="customer_id" class="input">
          <option value="">— Hepsi —</option>
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
        <label class="label">Proje</label>
        <input type="text" name="project_query" placeholder="Proje adı" value="<?=h($filters['project_query'])?>" class="input">
      </div>
<div>
        <label class="label">Ürün (Ad/SKU)</label>
        <input type="text" name="product_query" placeholder="Örn: Wallwasher, 12345" value="<?=h($filters['product_query'])?>" class="input">
      </div>
<div>
        <label class="label">Para Birimi</label>
        <select name="currency" class="input">
          <option value="">— Hepsi —</option>
          <?php foreach (['TRY','USD','EUR'] as $cur): $sel = ($filters['currency'] && normalize_currency($filters['currency'])===$cur)?'selected':''; ?>
            <option value="<?=$cur?>" <?=$sel?>><?=$cur?></option>
          <?php endforeach; ?>
        </select>
      </div>
<div>
        <label class="label">Birim Fiyat (min)</label>
        <input type="text" name="min_unit" placeholder="Örn: 100,00" value="<?=h($filters['min_unit'])?>" class="input">
      </div>
<div>
        <label class="label">Birim Fiyat (max)</label>
        <input type="text" name="max_unit" placeholder="Örn: 5000,00" value="<?=h($filters['max_unit'])?>" class="input">
      </div>
<form method="get" id="reportFilters" class="filters">
      
      
      
      
      
      
      
      
      
      
      
      



      <div class="wide6">
  <label class="label">Üretim Durumu (çoklu)</label>
  <div class="prod-status-grid compact">
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
      <label class="status-chip">
        <input type="checkbox" name="prod_status[]" form="reportFilters" value="<?=h($val)?>"<?=$checked?>>
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
    
    
</div>
  </div>

  <div class="chart-panel">
    <div class="triple-grid">
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
          if (!isset($__orders[$__code])) {
            $__orders[$__code] = [
              'order_id'     => $r['order_id'] ?? null,
              'order_date'    => $r['order_date'] ?? '',
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
          <tr><td style="text-align:center;" colspan="7" class="ta-center muted">Kayıt bulunamadı.</td></tr>
        <?php else: foreach ($__orders as $__o): 
          $__kdv = $__o['subtotal'] * $__vatRate;
          $__genel = $__o['subtotal'] + $__kdv;
        ?>
          <tr data-order-id="<?= (int)($__o['order_id'] ?? 0) ?>" class="order-row">
            <td><?=fmt_tr_date($__o['order_date'] ?? '')?></td>
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

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>



<script>
(function(){
  const payload = <?php echo json_encode($chart_payload, JSON_UNESCAPED_UNICODE); ?>;

  function hashColor(str){
    let h=0; for(let i=0;i<str.length;i++){ h=(h*31+str.charCodeAt(i))>>>0; }
    const hue=h%360; return `hsl(${hue} 75% 55%)`;
  }
  function entriesFrom(group){
    const totals = (payload && payload[group] && payload[group].totals) || {};
    const curMap = (payload && payload[group] && payload[group].currency) || {};
    return Object.entries(totals)
      .map(([name,val])=>({name, val: Number(val)||0, cur: curMap[name]||''}))
      .sort((a,b)=> b.val - a.val);
  }
  function symbol(cur){ return cur==='TRY'?'₺':(cur==='USD'?'$':(cur==='EUR'?'€':'')); }

  function renderPie(canvasId, listId, entries){
    const labels = entries.map(e=>e.name);
    const data   = entries.map(e=>e.val);
    const colors = labels.map(lb=>hashColor(lb));

    const ctx = document.getElementById(canvasId)?.getContext('2d');
    if (ctx && labels.length){
      new Chart(ctx, {
        type: 'doughnut',
        data: { labels, datasets: [{ data, backgroundColor: colors }] },
        options: {
          responsive: true, maintainAspectRatio: false, cutout: '60%',
          plugins: { legend: { display:false }, tooltip: { titleFont:{size:11}, bodyFont:{size:11} } },
          layout: { padding: 0 }
        }
      });
    }

    const ul = document.getElementById(listId);
    if (ul){
      if (!entries.length){
        ul.innerHTML = '<li><span class="name">Veri yok</span><span class="val">—</span></li>';
      } else {
        const top5 = entries.slice(0,5);
        ul.innerHTML = top5.map(it => (
          `<li><span class="name">${it.name}</span><span class="val">${it.val.toLocaleString('tr-TR',{minimumFractionDigits:2, maximumFractionDigits:2})} ${symbol(it.cur)}</span></li>`
        )).join('');
      }
    }
  }

  renderPie('pieCustomer', 'top5Customer', entriesFrom('customer'));
  renderPie('pieProject',  'top5Project',  entriesFrom('project'));
  renderPie('pieCategory', 'top5Category', entriesFrom('category'));
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
  if(!window.Chart) return;
  Chart.overrides = Chart.overrides || {};
  Chart.overrides.doughnut = Chart.overrides.doughnut || {};
  Chart.overrides.doughnut.animation = {
    animateRotate: true, animateScale: true, duration: 900, easing: 'easeOutQuart',
    delay: (ctx) => (ctx.dataIndex||0) * 90
  };
  Chart.overrides.doughnut.hoverOffset = 8;
  Chart.overrides.doughnut.cutout = '65%';
  Chart.defaults.transitions.active = {animation: {duration: 300, easing:'easeOutCubic'}};
  function sweepExisting(){
    const inst = Chart.instances || Chart._instances || { };
    const list = Array.isArray(inst) ? inst : Object.values(inst);
    list.forEach(function(ch){
      try{
        if(ch && ch.config && ch.config.type === 'doughnut' && ch.data && ch.data.datasets && ch.data.datasets[0]){
          const ds = ch.data.datasets[0];
          if(!Array.isArray(ds.data)) return;
          const orig = ds.data.slice();
          ds.data = orig.map(()=>0.0001);
          ch.update(0);
          setTimeout(function(){ ds.data = orig; ch.update({duration:900, easing:'easeOutQuart'}); }, 60);
        }
      }catch(e){}
    });
  }
  setTimeout(sweepExisting, 0);
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