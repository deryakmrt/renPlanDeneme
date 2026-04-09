<?php

/**
 * report_orders.php — Sales Report (filters left + totals right + THREE pies)
 */

declare(strict_types=1);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/helpers.php';
require_login();

// --- 🔒 SADECE ADMİN YETKİ KONTROLÜ ---
$__role = current_user()['role'] ?? '';
if ($__role !== 'admin') {
  die('<div style="margin:50px auto; max-width:500px; padding:30px; background:#fff1f2; border:2px solid #fda4af; border-radius:12px; color:#e11d48; font-family:sans-serif; text-align:center; box-shadow:0 10px 25px rgba(225,29,72,0.1);">
        <h2 style="margin-top:0; font-size:24px;">⛔ YETKİSİZ ERİŞİM</h2>
        <p style="font-size:15px; line-height:1.5;">Bu finansal raporları ve grafikleri yalnızca <b>Yönetici (Admin)</b> yetkisine sahip kullanıcılar görüntüleyebilir.</p>
        <a href="../index.php" style="display:inline-block; margin-top:15px; padding:10px 20px; background:#e11d48; color:#fff; text-decoration:none; border-radius:8px; font-weight:bold;">Ana Sayfaya Dön</a>
    </div>');
}
// --------------------------------------

$db = pdo();

if (!function_exists('h')) {
  function h($s)
  {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
  }
}
if (!function_exists('fmt_tr_money')) {
  function fmt_tr_money($v)
  {
    if ($v === null || $v === '') return '';
    return number_format((float)$v, 4, ',', '.');
  }

  function fmt_tr_date($s)
  {
    if ($s === null || $s === '') return '';
    $s = (string)$s;
    if (preg_match('~^\d{2}[\-/]\d{2}[\-/]\d{4}$~', $s)) {
      return str_replace('/', '-', $s);
    }
    try {
      $dt = new DateTime($s);
      return $dt->format('d-m-Y');
    } catch (Throwable $e) {
      if (preg_match('~^(\d{4})-(\d{2})-(\d{2})~', $s, $m)) {
        return $m[3] . '-' . $m[2] . '-' . $m[1];
      }
      return $s;
    }
  }
}
if (!function_exists('tr_to_float')) {
  function tr_to_float($s)
  {
    $s = str_replace(['.', ','], ['', '.'], $s);
    return is_numeric($s) ? (float)$s : null;
  }
}
if (!function_exists('normalize_currency')) {
  function normalize_currency($cur)
  {
    $cur = strtoupper(trim((string)$cur));
    if ($cur === '' || $cur === '—') return '—';
    if ($cur === 'TL' || $cur === '₺' || $cur === 'TRL') return 'TRY';
    if ($cur === 'US$' || $cur === '$') return 'USD';
    if ($cur === '€' || $cur === 'EURO') return 'EUR';
    return $cur;
  }
}
function inparam($k, $d = null)
{
  return (isset($_GET[$k]) && $_GET[$k] !== '') ? trim($_GET[$k]) : $d;
}
function inparam_arr($k)
{
  if (!isset($_GET[$k])) return [];
  $v = $_GET[$k];
  if (is_array($v)) {
    $out = [];
    foreach ($v as $x) {
      $x = trim((string)$x);
      if ($x !== '') $out[] = $x;
    }
    return $out;
  }
  $v = trim((string)$v);
  if ($v === '') return [];
  return array_map('trim', explode(',', $v));
}


$filters = [
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

$where = [];
$args = [];
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
try {
  $db->query("SELECT status FROM `$itemsTable` LIMIT 0");
  $itemStatusCol = "`$itemsTable`.`status`";
} catch (Throwable $e) {
  $itemStatusCol = null;
}
$prodStatusCol = 'orders.status';

// --- YENİ: Siparişi alan kolon kontrolü ---
// Veritabanında olduğundan emin olduğumuz için kontrolü atlıyor ve direkt okuyoruz
$siparisiAlanCol = 'orders.siparisi_alan';

try {
  $db->query("SELECT orders.order_date FROM orders LIMIT 0");
} catch (Throwable $e) {
  try {
    $db->query("SELECT orders.siparis_tarihi FROM orders LIMIT 0");
    $dateCol = 'orders.siparis_tarihi';
  } catch (Throwable $e2) {
    try {
      $db->query("SELECT orders.created_at FROM orders LIMIT 0");
      $dateCol = 'orders.created_at';
    } catch (Throwable $e3) {
      $dateCol = 'orders.id';
    }
  }
}
try {
  $db->query("SELECT orders.proje_adi FROM orders LIMIT 0");
} catch (Throwable $e) {
  try {
    $db->query("SELECT orders.project_name FROM orders LIMIT 0");
    $projectCol = 'orders.project_name';
  } catch (Throwable $e2) {
    $projectCol = null;
  }
}
try {
  $db->query("SELECT orders.currency FROM orders LIMIT 0");
} catch (Throwable $e) {
  try {
    $db->query("SELECT orders.odeme_para_birimi FROM orders LIMIT 0");
    $currencyCol = 'orders.odeme_para_birimi';
  } catch (Throwable $e2) {
    $currencyCol = null;
  }
}
try {
  $db->query("SELECT orders.order_code FROM orders LIMIT 0");
} catch (Throwable $e) {
  try {
    $db->query("SELECT orders.code FROM orders LIMIT 0");
    $orderCodeCol = 'orders.code';
  } catch (Throwable $e2) {
    $orderCodeCol = 'orders.id';
  }
}
try {
  $db->query("SELECT customers.name FROM customers LIMIT 0");
} catch (Throwable $e) {
  try {
    $db->query("SELECT customers.customer_name FROM customers LIMIT 0");
    $custNameCol = 'customers.customer_name';
  } catch (Throwable $e2) {
    $custNameCol = 'customers.id';
  }
}
try {
  $db->query("SELECT products.name FROM products LIMIT 0");
} catch (Throwable $e) {
  try {
    $db->query("SELECT products.product_name FROM products LIMIT 0");
    $prodNameCol = 'products.product_name';
  } catch (Throwable $e2) {
    $prodNameCol = 'products.id';
  }
}
try {
  $db->query("SELECT products.sku FROM products LIMIT 0");
} catch (Throwable $e) {
  $prodSkuCol = null;
}
foreach (['order_items', 'order_lines', 'order_products'] as $cand) {
  try {
    $db->query("SELECT * FROM `$cand` LIMIT 0");
    $itemsTable = $cand;
    break;
  } catch (Throwable $e) {
  }
}
try {
  $db->query("SELECT quantity FROM `$itemsTable` LIMIT 0");
} catch (Throwable $e) {
  if (@$db->query("SELECT qty FROM `$itemsTable` LIMIT 0")) $qtyCol = 'qty';
  else if (@$db->query("SELECT miktar FROM `$itemsTable` LIMIT 0")) $qtyCol = 'miktar';
}
try {
  $db->query("SELECT unit FROM `$itemsTable` LIMIT 0");
} catch (Throwable $e) {
  if (@$db->query("SELECT birim FROM `$itemsTable` LIMIT 0")) $unitCol = 'birim';
  else if (@$db->query("SELECT unit_name FROM `$itemsTable` LIMIT 0")) $unitCol = 'unit_name';
}
try {
  $db->query("SELECT unit_price FROM `$itemsTable` LIMIT 0");
} catch (Throwable $e) {
  if (@$db->query("SELECT price FROM `$itemsTable` LIMIT 0")) $unitPriceCol = 'price';
  else if (@$db->query("SELECT birim_fiyat FROM `$itemsTable` LIMIT 0")) $unitPriceCol = 'birim_fiyat';
}
$productIdCol = 'product_id';
try {
  $db->query("SELECT product_id FROM `$itemsTable` LIMIT 0");
} catch (Throwable $e) {
  if (@$db->query("SELECT product FROM `$itemsTable` LIMIT 0")) $productIdCol = 'product';
  else if (@$db->query("SELECT productId FROM `$itemsTable` LIMIT 0")) $productIdCol = 'productId';
}

if ($filters['date_from']) {
  $where[] = "$dateCol >= ?";
  $args[] = $filters['date_from'];
}
if ($filters['date_to']) {
  $where[] = "$dateCol <= ?";
  $args[] = $filters['date_to'];
}
if ($filters['customer_id']) {
  $where[] = "orders.customer_id = ?";
  $args[] = $filters['customer_id'];
}
if ($currencyCol && $filters['currency']) {
  $sel = strtoupper(trim($filters['currency']));
  $vals = [$sel];
  if ($sel === 'TRY') {
    $vals = ['TRY', 'TL', '₺', 'TRL'];
  } elseif ($sel === 'USD') {
    $vals = ['USD', '$', 'US$'];
  } elseif ($sel === 'EUR') {
    $vals = ['EUR', '€', 'EURO'];
  }
  $place = implode(',', array_fill(0, count($vals), '?'));
  $where[] = "$currencyCol IN ($place)";
  $args = array_merge($args, $vals);
}
if ($projectCol && $filters['project_query']) {
  $where[] = "$projectCol LIKE ?";
  $args[] = '%' . $filters['project_query'] . '%';
}
if ($filters['product_query']) {
  $or = ["$prodNameCol LIKE ?"];
  $oargs = ['%' . $filters['product_query'] . '%'];
  if ($prodSkuCol) {
    $or[] = "$prodSkuCol LIKE ?";
    $oargs[] = '%' . $filters['product_query'] . '%';
  }
  $where[] = '(' . implode(' OR ', $or) . ')';
  $args = array_merge($args, $oargs);
}
if ($filters['min_unit']) {
  $v = tr_to_float($filters['min_unit']);
  if ($v !== null) {
    $where[] = "`$itemsTable`.`$unitPriceCol` >= ?";
    $args[] = $v;
  }
}
if ($filters['max_unit']) {
  $v = tr_to_float($filters['max_unit']);
  if ($v !== null) {
    $where[] = "`$itemsTable`.`$unitPriceCol` <= ?";
    $args[] = $v;
  }
}


// Production status multi-select (fixed 10 options)
if (!empty($filters['prod_status']) && !empty($prodStatusCol)) {
  $placeholders = implode(',', array_fill(0, count($filters['prod_status']), '?'));
  $where[] = "$prodStatusCol IN ($placeholders)";
  foreach ($filters['prod_status'] as $ps) {
    $args[] = $ps;
  }
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$sel = [
  "orders.id AS order_id",
  "orders.status AS order_status",
  "orders.kalem_para_birimi AS kalem_para_birimi",
  "orders.fatura_para_birimi AS fatura_para_birimi",
  "orders.kur_usd AS kur_usd",
  "orders.kur_eur AS kur_eur",
  "orders.fatura_toplam AS fatura_toplam",
  "orders.kdv_orani AS kdv_orani",
  "$siparisiAlanCol AS siparisi_alan",
  "$custNameCol AS customer_name",
  "$orderCodeCol AS order_code",
  ($projectCol ? "$projectCol AS project_name" : "NULL AS project_name"),
  "$prodNameCol AS product_name",
  ($prodSkuCol ? "$prodSkuCol AS sku" : "NULL AS sku"),
  "pc.name AS category_name",          // YENİ: Kategori Adı
  "pc.macro_category AS macro_cat",    // YENİ: İç/Dış Bilgisi
  "`$itemsTable`.`$qtyCol` AS qty",
  "`$itemsTable`.`$unitCol` AS unit_name",
  "`$itemsTable`.`$unitPriceCol` AS unit_price",
  ($currencyCol ? "$currencyCol AS currency" : "NULL AS currency"),
  "(`$itemsTable`.`$qtyCol`*`$itemsTable`.`$unitPriceCol`) AS line_total",
  "$dateCol AS order_date"
];
$joins = [
  "JOIN orders   ON orders.id = `$itemsTable`.order_id",
  "JOIN products ON products.id = `$itemsTable`.`$productIdCol`",
  "JOIN customers ON customers.id = orders.customer_id",
  "LEFT JOIN product_categories pc ON pc.id = products.category_id" // YENİ: Kategori Bağlantısı
];
$sql = "SELECT
  " . implode(",
  ", $sel) . "
" . "FROM `" . $itemsTable . "`
" . implode("
", $joins) . "
" . $whereSql . "
" . "ORDER BY " . $dateCol . " DESC, orders.id DESC, `" . $itemsTable . "`.id ASC";

$rows = [];
$queryError = null;
try {
  $st = $db->prepare($sql);
  $st->execute($args);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $queryError = $e->getMessage();
  $rows = [];
}

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
  $filename = 'satis_raporu_' . date('Ymd_His') . '.csv';
  header('Content-Type: text/csv; charset=UTF-8');
  header('Content-Disposition: attachment; filename="' . $filename . '"');
  echo "\xEF\xBB\xBF";
  $out = fopen('php://output', 'w');
  fputcsv($out, ['Siparişi Alan', 'Müşteri', 'Proje', 'Sipariş Kodu', 'Ürün', 'SKU', 'Miktar', 'Birim', 'Birim Fiyat', 'Para Birimi', 'Satır Toplam', 'Sipariş Tarihi']);
  $temsilciler_sabit = ['ALİ ALTUNAY', 'FATİH SERHAT ÇAÇIK', 'HASAN BÜYÜKOBA', 'HİKMET ŞAHİN', 'MUHAMMET YAZGAN', 'MURAT SEZER'];
  foreach ($rows as $r) {
    $raw_exp_sp = trim((string)($r['siparisi_alan'] ?? ''));

    if ($raw_exp_sp === '') {
      $export_sp = 'Belirtilmemiş';
    } else {
      $upper_exp_sp = mb_strtoupper(str_replace(['i', 'ı'], ['İ', 'I'], $raw_exp_sp), 'UTF-8');
      $lower_exp_sp = mb_strtolower(str_replace(['I', 'İ'], ['ı', 'i'], $raw_exp_sp), 'UTF-8');
      $title_exp_sp = mb_convert_case($lower_exp_sp, MB_CASE_TITLE, 'UTF-8');

      if (in_array($upper_exp_sp, $temsilciler_sabit)) {
        $export_sp = $title_exp_sp;
      } else {
        $export_sp = $title_exp_sp . ' (Diğer)';
      }
    }
    fputcsv($out, [$export_sp, $r['customer_name'] ?? '', $r['project_name'] ?? '', $r['order_code'] ?? '', $r['product_name'] ?? '', $r['sku'] ?? '', $r['qty'] ?? '', $r['unit_name'] ?? '', $r['unit_price'] ?? '', $r['currency'] ?? '', $r['line_total'] ?? '', $r['order_date'] ?? '']);
  }
  fclose($out);
  exit;
}

$totalsByCurrency = [];
foreach ($rows as $r) {
  $cur = normalize_currency($r['currency'] ?? '—');
  if (!isset($totalsByCurrency[$cur])) $totalsByCurrency[$cur] = 0.0;
  $totalsByCurrency[$cur] += (float)($r['line_total'] ?? 0);
}

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
        if ((string)$c['CurrencyCode'] === 'USD') $usd_rate = (float)$c->ForexSelling;
        if ((string)$c['CurrencyCode'] === 'EUR') $eur_rate = (float)$c->ForexSelling;
      }
    }
  }
} catch (Throwable $e) {
}

// Hata olursa veya haftasonu API kapanırsa fallback (varsayılan) kurlar
if ($usd_rate <= 1.0) $usd_rate = 36.50;
if ($eur_rate <= 1.0) $eur_rate = 38.00;
// -----------------------------

// --- YENİ: Siparişlerin toplam kalem maliyetlerini (KDV'li) önceden hesapla ki, 
// mühürlü fatura toplamını satırlara tam oranlayabilelim (kur_usd 0 olsa bile kusursuz çalışır) ---
$order_kalem_totals = [];
foreach ($rows as $r) {
    $oid = $r['order_id'];
    $raw = (float)($r['line_total'] ?? 0);
    $kdv = isset($r['kdv_orani']) ? (float)$r['kdv_orani'] : 20;
    $order_kalem_totals[$oid] = ($order_kalem_totals[$oid] ?? 0) + ($raw * (1 + ($kdv / 100)));
}

// Grafikler için TL Bazlı Toplamlar ve Ekranda Gösterilecek Ham Toplamlar
$agg_customer_try = [];
$agg_project_try = [];
$agg_category_try = [];
$cur_customer = [];
$cur_project = [];
$cur_category = [];

// [YENİ] Satış temsilcisi (siparisi_alan) verileri
$salesperson_orders = [];
$processed_orders_for_sp = [];
$salesperson_details = []; // ⭐ YENİ: Temsilci Detay Analizi İçin 
$sp_agg_proj = [];
$sp_cur_proj = []; // Dövizli yapı için
$sp_agg_grp = [];
$sp_cur_grp = [];   // Dövizli yapı için 

foreach ($rows as $r) {
  $raw_amt = (float)($r['line_total'] ?? 0);
  $kdv_orani = isset($r['kdv_orani']) ? (float)$r['kdv_orani'] : 20;
  $raw_amt_kdvli = $raw_amt * (1 + ($kdv_orani / 100)); // 🚀 GRAFİKLERE KDV DAHİL EDİLDİ!

  $is_invoiced = (mb_strtolower(trim((string)($r['order_status'] ?? '')), 'UTF-8') === 'fatura_edildi' || mb_strtolower(trim((string)($r['order_status'] ?? '')), 'UTF-8') === 'fatura edildi');
  $fatura_toplam_muhur = (float)($r['fatura_toplam'] ?? 0);

  if ($is_invoiced && $fatura_toplam_muhur > 0) {
    // 1. SİPARİŞ MÜHÜRLÜYSE: Kalemin faturadaki tam oranını (hissesini) bul ve mühürlü toplamdan al!
    $raw_cur = !empty($r['fatura_para_birimi']) ? $r['fatura_para_birimi'] : 'TL';
    $order_kalem_total = $order_kalem_totals[$oid] ?? 1;
    if ($order_kalem_total <= 0) $order_kalem_total = 1;
    
    $oran = $raw_amt_kdvli / $order_kalem_total;
    $amt = $fatura_toplam_muhur * $oran; // Mühürlü rakamdan payına düşen net ciro (KDV Dahil)
  } else {
    // 2. MÜHÜRSÜZ VEYA AÇIK SİPARİŞ (Kendi saf KDV'li fiyatını kullan)
    $raw_cur = !empty($r['kalem_para_birimi']) ? $r['kalem_para_birimi'] : ($r['currency'] ?? '—');
    $amt = $raw_amt_kdvli;
  }

  $cur = normalize_currency($raw_cur);

  $rate = 1.0;
  if ($cur === 'USD') $rate = $usd_rate;
  elseif ($cur === 'EUR') $rate = $eur_rate;

  $amt_try = $amt * $rate; // Grafik ve sıralama için TL karşılığı
  
  $c = trim((string)($r['customer_name'] ?? 'Diğer'));
  if ($c === '') $c = 'Diğer';
  $p = trim((string)($r['project_name'] ?? 'Diğer'));
  if ($p === '') $p = 'Diğer';

  // --- YENİ: KATEGORİ & SKU AKILLI FALLBACK ---
  $cat_name = trim((string)($r['category_name'] ?? ''));
  if ($cat_name !== '') {
    $best_group = $cat_name; // Gerçek kategori varsa onu kullan
  } else {
    // Kategori yoksa eski tahmin yöntemini (SKU Parçalama) kullan
    $raw_sku  = trim($r['sku'] ?? '');
    $raw_name = trim($r['product_name'] ?? '');
    $best_group = 'DİĞER';
    if (!empty($raw_sku)) {
      if (strpos($raw_sku, 'RN-MLS-RAY') === 0) {
        if (strpos($raw_sku, 'TR') !== false) $best_group = 'RN-MLS-RAY (TR)';
        elseif (strpos($raw_sku, 'SR') !== false) $best_group = 'RN-MLS-RAY (SR)';
        elseif (strpos($raw_sku, 'SU') !== false) $best_group = 'RN-MLS-RAY (SU)';
        elseif (strpos($raw_sku, 'SA') !== false) $best_group = 'RN-MLS-RAY (SA)';
        else $best_group = 'RN-MLS-RAY';
      } else {
        $parts = explode('-', $raw_sku);
        $best_group = (count($parts) >= 2) ? ($parts[0] . '-' . $parts[1]) : $parts[0];
      }
    } elseif (strpos($raw_name, 'RN') === 0) {
      $best_group = explode(' ', $raw_name)[0];
    }
  }
  $g = $best_group; // 1. Genel Ürün Grubu Pastası İçin

  // --- [YENİ] Siparişi Alan (Sabit Liste ve Diğer Gruplaması) ---
  $raw_sp = trim((string)($r['siparisi_alan'] ?? ''));
  $temsilciler_sabit = ['ALİ ALTUNAY', 'FATİH SERHAT ÇAÇIK', 'HASAN BÜYÜKOBA', 'HİKMET ŞAHİN', 'MUHAMMET YAZGAN', 'MURAT SEZER'];

  if ($raw_sp === '') {
    $sp = 'Belirtilmemiş';
  } else {
    $upper_sp = mb_strtoupper(str_replace(['i', 'ı'], ['İ', 'I'], $raw_sp), 'UTF-8');
    $lower_sp = mb_strtolower(str_replace(['I', 'İ'], ['ı', 'i'], $raw_sp), 'UTF-8');
    $title_sp = mb_convert_case($lower_sp, MB_CASE_TITLE, 'UTF-8');

    if (in_array($upper_sp, $temsilciler_sabit)) {
      $sp = $title_sp;
    } else {
      $sp = $title_sp . ' (Diğer)';
    }
  }

  $oid = $r['order_id'];

  // Siparişi alan kişinin sipariş sayısını hesaplama (Aynı sipariş no 1 kez sayılır)
  if (!isset($processed_orders_for_sp[$oid])) {
    $processed_orders_for_sp[$oid] = true;
    $salesperson_orders[$sp] = ($salesperson_orders[$sp] ?? 0) + 1;
  }

  // TL Üzerinden Grafik ve Sıralama Toplamları
  $agg_customer_try[$c] = ($agg_customer_try[$c] ?? 0) + $amt_try;
  $agg_project_try[$p]  = ($agg_project_try[$p]  ?? 0) + $amt_try;
  $agg_category_try[$g] = ($agg_category_try[$g] ?? 0) + $amt_try;

  // ⭐ YENİ: Satış Temsilcisi Detay Analizi Verisi (Döviz Korumalı)
  // 1. Projeden ne kadar kazanmış?
  $sp_agg_proj[$sp][$p] = ($sp_agg_proj[$sp][$p] ?? 0) + $amt_try;
  if (!isset($sp_cur_proj[$sp][$p][$cur])) $sp_cur_proj[$sp][$p][$cur] = 0;
  $sp_cur_proj[$sp][$p][$cur] += $amt;

  // 2. Hangi ürün grubundan ne kadar satmış? (Akıllı Kategori/SKU Fallback)
  $family = $best_group; // Yukarıda ürettiğimiz mükemmel veriyi kullanıyoruz

  // (Opsiyonel: Eğer kategori adının yanına İç/Dış etiketi de eklensin istersen:)
  // if (!empty($r['macro_cat'])) {
  //    $macro_label = $r['macro_cat'] == 'ic' ? 'İç' : ($r['macro_cat'] == 'dis' ? 'Dış' : 'Diğer');
  //    $family = $family . " [" . $macro_label . "]";
  // }

  $sp_agg_grp[$sp][$family] = ($sp_agg_grp[$sp][$family] ?? 0) + $amt_try;
  if (!isset($sp_cur_grp[$sp][$family][$cur])) $sp_cur_grp[$sp][$family][$cur] = 0;
  $sp_cur_grp[$sp][$family][$cur] += $amt;

  // Ekrana basmak için ham döviz tutarlarını koru
  if (!isset($cur_customer[$c])) $cur_customer[$c] = [];
  if (!isset($cur_customer[$c][$cur])) $cur_customer[$c][$cur] = 0.0;
  $cur_customer[$c][$cur] += $amt;

  if (!isset($cur_project[$p])) $cur_project[$p] = [];
  if (!isset($cur_project[$p][$cur])) $cur_project[$p][$cur] = 0.0;
  $cur_project[$p][$cur] += $amt;

  if (!isset($cur_category[$g])) $cur_category[$g] = [];
  if (!isset($cur_category[$g][$cur])) $cur_category[$g][$cur] = 0.0;
  $cur_category[$g][$cur] += $amt;
}

// TL'ye çevrilmiş değerlere göre büyükten küçüğe sırala! (Zekice Kısım)
arsort($agg_customer_try);
arsort($agg_project_try);
arsort($agg_category_try);
arsort($salesperson_orders); // Satış Temsilcisini en çok satandan küçüğe sırala

function get_dominant_info($tryTotals, $bucketMap)
{
  $out = [];
  foreach ($tryTotals as $label => $tryVal) {
    $curMap = $bucketMap[$label] ?? [];
    if (empty($curMap)) {
      $out[$label] = ['cur' => 'TRY', 'val' => $tryVal, 'try_val' => $tryVal];
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
foreach ($salesperson_orders as $name => $count) {
  $sp_formatted[$name] = [
    'cur' => 'Adet',
    'val' => $count,
    'try_val' => $count
  ];

  // ⭐ YENİ: Detay analizi verisini döviz bilgisiyle oluştur
  $salesperson_details[$name] = [
    'projects' => get_dominant_info($sp_agg_proj[$name] ?? [], $sp_cur_proj[$name] ?? []),
    'groups'   => get_dominant_info($sp_agg_grp[$name] ?? [], $sp_cur_grp[$name] ?? [])
  ];
}

$chart_payload = [
  'customer'    => get_dominant_info($agg_customer_try, $cur_customer),
  'project'     => get_dominant_info($agg_project_try, $cur_project),
  'category'    => get_dominant_info($agg_category_try, $cur_category),
  'salesperson' => $sp_formatted,
  'salesperson_details' => $salesperson_details, // ⭐ YENİ EKLENDİ
];

// ===============================================
// ⭐ GELİŞTİRİLMİŞ SATIŞ TEMSİLCİSİ VERİSİ
// ===============================================
$salesperson_enhanced = [];

// Eksik olan Döngü (foreach) Geri Geldi!
foreach ($rows as $row) {
  $raw_sp = trim($row['siparisi_alan'] ?? '');
  $temsilciler_sabit = ['ALİ ALTUNAY', 'FATİH SERHAT ÇAÇIK', 'HASAN BÜYÜKOBA', 'HİKMET ŞAHİN', 'MUHAMMET YAZGAN', 'MURAT SEZER'];

  if ($raw_sp === '') {
    $sp = 'Belirtilmemiş';
  } else {
    $upper_sp = mb_strtoupper(str_replace(['i', 'ı'], ['İ', 'I'], $raw_sp), 'UTF-8');
    $lower_sp = mb_strtolower(str_replace(['I', 'İ'], ['ı', 'i'], $raw_sp), 'UTF-8');
    $title_sp = mb_convert_case($lower_sp, MB_CASE_TITLE, 'UTF-8');

    if (in_array($upper_sp, $temsilciler_sabit)) {
      $sp = $title_sp;
    } else {
      $sp = $title_sp . ' (Diğer)';
    }
  }

  // Eksik olan Tanımlama (Initialization) Geri Geldi!
  if (!isset($salesperson_enhanced[$sp])) {
    $salesperson_enhanced[$sp] = [
      'order_count' => 0,
      'total_price_try' => 0,
      'product_groups' => [],
      'currency' => 'TRY',
      'original_price' => 0,
      'original_currency' => 'TRY',
      'processed_orders' => [] 
    ];
  }

  $oid = $row['order_id'];

  // 1. Sipariş sayısı (Her siparişi 1 kez say)
  if (!isset($salesperson_enhanced[$sp]['processed_orders'][$oid])) {
    $salesperson_enhanced[$sp]['processed_orders'][$oid] = true;
    $salesperson_enhanced[$sp]['order_count']++;
  }

  // 2. Toplam fiyat (KDV Dahil hesaplama)
  $raw_amt2 = (float)($row['line_total'] ?? 0);
  $kdv_orani2 = isset($row['kdv_orani']) ? (float)$row['kdv_orani'] : 20;
  $raw_amt_kdvli2 = $raw_amt2 * (1 + ($kdv_orani2 / 100));

  $is_invoiced2 = (mb_strtolower(trim((string)($row['order_status'] ?? '')), 'UTF-8') === 'fatura_edildi' || mb_strtolower(trim((string)($row['order_status'] ?? '')), 'UTF-8') === 'fatura edildi');
  $fatura_toplam_muhur2 = (float)($row['fatura_toplam'] ?? 0);

  if ($is_invoiced2 && $fatura_toplam_muhur2 > 0) {
    $raw_cur2 = !empty($row['fatura_para_birimi']) ? $row['fatura_para_birimi'] : 'TL';
    $order_kalem_total2 = $order_kalem_totals[$oid] ?? 1;
    if ($order_kalem_total2 <= 0) $order_kalem_total2 = 1;
    
    $oran2 = $raw_amt_kdvli2 / $order_kalem_total2;
    $subtotal = $fatura_toplam_muhur2 * $oran2; // Mühürlü orandan gelen net KDV'li rakam
    $cur = normalize_currency($raw_cur2);
  } else {
    $subtotal = $raw_amt_kdvli2;
    $cur = normalize_currency(!empty($row['kalem_para_birimi']) ? $row['kalem_para_birimi'] : ($row['currency'] ?? 'TRY'));
  }

  // Orijinal para biriminde toplam
  if ($salesperson_enhanced[$sp]['original_currency'] === $cur || $salesperson_enhanced[$sp]['original_price'] == 0) {
    $salesperson_enhanced[$sp]['original_currency'] = $cur;
    $salesperson_enhanced[$sp]['original_price'] += $subtotal;
  }

  // TL'ye çevir
  $rate = 1.0;
  if ($cur === 'USD') $rate = $usd_rate;
  elseif ($cur === 'EUR') $rate = $eur_rate;
  $salesperson_enhanced[$sp]['total_price_try'] += ($subtotal * $rate);

  // 3. Ürün grupları (Akıllı Kategori/SKU Fallback)
  $cat_name2 = trim((string)($row['category_name'] ?? ''));
  if ($cat_name2 !== '') {
    $group = $cat_name2;
  } else {
    $raw_sku2 = trim($row['sku'] ?? '');
    $raw_name2 = trim($row['product_name'] ?? '');
    $group = 'DİĞER';
    if (!empty($raw_sku2)) {
      if (strpos($raw_sku2, 'RN-MLS-RAY') === 0) {
        if (strpos($raw_sku2, 'TR') !== false) $group = 'RN-MLS-RAY (TR)';
        elseif (strpos($raw_sku2, 'SR') !== false) $group = 'RN-MLS-RAY (SR)';
        elseif (strpos($raw_sku2, 'SU') !== false) $group = 'RN-MLS-RAY (SU)';
        elseif (strpos($raw_sku2, 'SA') !== false) $group = 'RN-MLS-RAY (SA)';
        else $group = 'RN-MLS-RAY';
      } else {
        $parts = explode('-', $raw_sku2);
        $group = (count($parts) >= 2) ? ($parts[0] . '-' . $parts[1]) : $parts[0];
      }
    } elseif (strpos($raw_name2, 'RN') === 0) {
      $group = explode(' ', $raw_name2)[0];
    }
  }
  $salesperson_enhanced[$sp]['product_groups'][$group] = true;
}
// <-- Döngü Burada Kapanır

// Ürün grubu sayısını hesapla
foreach ($salesperson_enhanced as $sp => &$data) {
  $data['product_group_count'] = count($data['product_groups']);
  unset($data['product_groups']);
  unset($data['processed_orders']);
}
unset($data);

// Mevcut chart_payload'a ekle
$chart_payload['salesperson_enhanced'] = $salesperson_enhanced;

include __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="/assets/reports.css">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<h2 style="margin:0 0 14px 2px">Satış ve Finans İstatistikleri</h2>

<?php if ($queryError): ?>
  <div class="alert alert-danger" style="margin:8px 0;background:#fff1f2;border:1px solid #fecdd3;padding:10px;border-radius:8px"><?= h($queryError) ?></div>
<?php endif; ?>

<div class="stat-row">
  <?php foreach (['TRY', 'USD', 'EUR'] as $cur): if (isset($totalsByCurrency[$cur])): ?>
      <div class="stat-card" style="box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); border-radius: 12px;">
        <h4 style="color:#64748b; font-size:12px; font-weight:700; text-transform:uppercase; margin-bottom:8px;">Toplam (<?= h($cur) ?>)</h4>
        <div class="val" style="color:#0f172a; font-size:22px;"><?= fmt_tr_money($totalsByCurrency[$cur]) ?> <span style="font-size:13px; color:#94a3b8; font-weight:600;"><?= h($cur) ?></span></div>
      </div>
  <?php endif;
  endforeach; ?>

  <div class="stat-card" style="background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); border-color:#bbf7d0; border-radius: 12px; display:flex; flex-direction:column; justify-content:center; box-shadow: 0 4px 6px -1px rgba(22, 163, 74, 0.1);">
    <h4 style="color:#166534; font-size:12px; font-weight:700; text-transform:uppercase; margin-bottom:12px; display:flex; align-items:center; gap:5px;">
      <span>💱</span> Güncel Kur <span style="font-size:10px; opacity:0.8; margin-left:4px;">(TCMB Satış)</span>
    </h4>
    <div style="display:flex; justify-content:space-between; align-items:center; padding: 0 5px;">
      <div>
        <div style="font-size:10px; color: #15803d; font-weight:600; opacity:0.8;">USD / TRY</div>
        <div style="font-size:16px; font-weight:800; color:#14532d;">₺<?= number_format($usd_rate, 4, ',', '.') ?></div>
      </div>
      <div style="text-align:right;">
        <div style="font-size:10px; color: #15803d; font-weight:600; opacity:0.8;">EUR / TRY</div>
        <div style="font-size:16px; font-weight:800; color:#14532d;">₺<?= number_format($eur_rate, 4, ',', '.') ?></div>
      </div>
    </div>
  </div>
</div>

<form method="get" id="reportFilters" class="filter-bar">
  <div class="filter-group">
    <label class="label">🗓️ Başlangıç</label>
    <input type="date" name="date_from" value="<?= h($filters['date_from']) ?>" class="input">
  </div>
  <div class="filter-group">
    <label class="label">🗓️ Bitiş</label>
    <input type="date" name="date_to" value="<?= h($filters['date_to']) ?>" class="input">
  </div>
  <div class="filter-group">
    <label class="label">👤 Müşteri</label>
    <select name="customer_id" class="input">
      <option value="">— Tüm Müşteriler —</option>
      <?php
      try {
        $cs = $db->query("SELECT id, name FROM customers ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
      } catch (Throwable $e) {
        try {
          $cs = $db->query("SELECT id, customer_name AS name FROM customers ORDER BY customer_name ASC")->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e2) {
          $cs = [];
        }
      }
      foreach ($cs as $c): $sel = ($filters['customer_id'] == $c['id']) ? 'selected' : '';
      ?>
        <option value="<?= $c['id'] ?>" <?= $sel ?>><?= h($c['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="filter-group">
    <label class="label">📁 Proje</label>
    <select name="project_query" class="input">
      <option value="">— Tüm Projeler —</option>
      <?php
      try {
        // Sadece ismi dolu olan ve birbirinden farklı projeleri çekiyoruz
        $proje_adi_col = $projectCol ?? 'proje_adi';
        // Eğer $projectCol null veya yoksa diye ekstra güvenlik
        if ($proje_adi_col) {
          $projects = $db->query("SELECT DISTINCT $proje_adi_col as p_name FROM orders WHERE $proje_adi_col IS NOT NULL AND TRIM($proje_adi_col) != '' ORDER BY $proje_adi_col ASC")->fetchAll(PDO::FETCH_ASSOC);
          foreach ($projects as $p):
            $p_name = trim($p['p_name']);
            $sel = ($filters['project_query'] == $p_name) ? 'selected' : '';
      ?>
            <option value="<?= h($p_name) ?>" <?= $sel ?>><?= h($p_name) ?></option>
      <?php
          endforeach;
        }
      } catch (Throwable $e) {
      }
      ?>
    </select>
  </div>
  <div class="filter-group">
    <label class="label">💱 Para Birimi</label>
    <select name="currency" class="input">
      <option value="">— Tümü —</option>
      <?php foreach (['TRY', 'USD', 'EUR'] as $cur): $sel = ($filters['currency'] && normalize_currency($filters['currency']) === $cur) ? 'selected' : ''; ?>
        <option value="<?= $cur ?>" <?= $sel ?>><?= $cur ?></option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="actions">
    <div class="actions-left">
      <button class="btn btn-primary" type="submit" style="background:#3b82f6; border-color:#2563eb;">🔍 Filtrele</button>
      <a class="btn" href="<?= h($_SERVER['PHP_SELF']) ?>" style="background:#fff; color:#475569;">🧹 Sıfırla</a>
    </div>
    <div style="display:flex; align-items:center; gap:15px;">
      <span style="font-size:12px; color:#64748b; font-weight:600; padding-right:10px; border-right:1px solid #cbd5e1;">📋 <?= count($rows) ?> satır bulundu</span>
      <?php $q = $_GET;
      $q['export'] = 'csv';
      $exportUrl = $_SERVER['PHP_SELF'] . '?' . http_build_query($q); ?>
      <a class="btn" href="<?= $exportUrl ?>" style="background:#10b981; color:#fff; border-color:#059669; gap:5px;"><span>📥</span> Excel Dışa Aktar</a>
    </div>
  </div>
</form>

<div class="chart-panel">
  <div class="quad-grid">
    <div class="pie-card">
      <h4 style="margin-bottom: 5px;">Satış Temsilcisi Dağılımı</h4>
      <div class="chart-sort-controls" style="margin-bottom: 6px; padding: 4px; background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-radius: 6px; border: 1px solid #e2e8f0;">
        <div style="display: flex; gap: 6px; flex-wrap: wrap; justify-content: center;">
          <label class="sort-option">
            <input type="radio" name="salesperson_sort" value="order_count" checked>
            <span>📦 Adet</span>
          </label>
          <label class="sort-option">
            <input type="radio" name="salesperson_sort" value="total_price">
            <span>💰 Fiyat</span>
          </label>
        </div>
      </div>

      <div id="spPriceInfo" style="display: none; text-align: center; font-size: 10px; color: #94a3b8; font-style: italic; margin-bottom: 8px; padding: 0 10px; line-height: 1.3;">
        *Buradaki ciro, farklı döviz cinslerinden kesilen siparişlerin güncel TCMB kuru ile TL'ye çevrilip toplanmış halidir.
      </div>

      <div class="chart-box" style="transition: opacity 0.3s ease;">
        <div class="pie-canvas-wrap"><canvas id="pieSalesperson"></canvas></div>
      </div>
      <div class="top5">
        <ul id="top5Salesperson"></ul>
      </div>
    </div>
    <div class="pie-card">
      <h4>Müşterilere Göre Dağılım</h4>
      <div class="pie-canvas-wrap"><canvas id="pieCustomer"></canvas></div>
      <div class="top5">
        <ul id="top5Customer"></ul>
      </div>
    </div>
    <div class="pie-card">
      <h4>Projelere Göre Dağılım</h4>
      <div class="pie-canvas-wrap"><canvas id="pieProject"></canvas></div>
      <div class="top5">
        <ul id="top5Project"></ul>
      </div>
    </div>
    <div class="pie-card">
      <h4>Ürün Gruplarına Göre Dağılım</h4>
      <div class="pie-canvas-wrap"><canvas id="pieCategory"></canvas></div>
      <div class="top5">
        <ul id="top5Category"></ul>
      </div>
    </div>
  </div>

  <div style="margin-top: 20px; padding: 20px; border: 1px solid #e2e8f0; border-radius: 12px; background: linear-gradient(to right, #f8fafc, #ffffff);">
    <h3 style="margin-top: 0; color: #0f172a; font-size: 16px; margin-bottom: 15px; border-bottom: 2px dashed #cbd5e1; padding-bottom: 10px;">
      🔍 Satış Temsilcisi Performans Analizi
    </h3>
    <div style="display: flex; gap: 20px; flex-wrap: wrap;">
      <div style="flex: 1; min-width: 250px; background: #fff; padding: 15px; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); border: 1px solid #f1f5f9;">
        <label style="font-size: 13px; font-weight: 700; color: #475569; display:block; margin-bottom: 6px;">1. Temsilci Seçin:</label>
        <select id="spDetailSelect" style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px; margin-bottom: 20px; font-weight: 600; color: #0f172a; outline: none;"></select>

        <label style="font-size: 13px; font-weight: 700; color: #475569; display:block; margin-bottom: 8px;">2. Analiz Türü:</label>
        <div style="display: flex; flex-direction: column; gap: 10px;">
          <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; padding: 10px; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0; transition: 0.2s;">
            <input type="radio" name="sp_detail_type" value="projects" checked style="width: 16px; height: 16px; accent-color: #8b5cf6;">
            <span style="font-size: 13px; font-weight: 600; color: #334155;">📁 Projelere Göre Dağılım (Ciro)</span>
          </label>
          <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; padding: 10px; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0; transition: 0.2s;">
            <input type="radio" name="sp_detail_type" value="groups" style="width: 16px; height: 16px; accent-color: #ec4899;">
            <span style="font-size: 13px; font-weight: 600; color: #334155;">🏷️ Ürün Grubuna Göre (Ciro)</span>
          </label>
        </div>
      </div>

      <div style="flex: 2; min-width: 300px; display: flex; gap: 20px; align-items: center; background: #fff; padding: 15px; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); border: 1px solid #f1f5f9;">
        <div style="flex: 1; height: 250px; position: relative;">
          <canvas id="pieSpDetail"></canvas>
        </div>
        <div style="flex: 1; max-height: 250px; overflow-y: auto;">
          <h4 style="margin-top: 0; font-size: 13px; color: #64748b; border-bottom: 1px solid #e2e8f0; padding-bottom: 8px; margin-bottom: 10px;">🏆 En Yüksek İlk 5</h4>
          <ul id="top5SpDetail" style="list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 8px;"></ul>
        </div>
      </div>
    </div>
  </div>

</div>

<div class="table-wrap">
  <table class="table" id="reportTable">
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
        $temsilciler_sabit = ['ALİ ALTUNAY', 'FATİH SERHAT ÇAÇIK', 'HASAN BÜYÜKOBA', 'HİKMET ŞAHİN', 'MUHAMMET YAZGAN', 'MURAT SEZER'];

        if ($raw_sp2 === '') {
          $formatted_sp = 'Belirtilmemiş';
        } else {
          $upper_sp2 = mb_strtoupper(str_replace(['i', 'ı'], ['İ', 'I'], $raw_sp2), 'UTF-8');
          $lower_sp2 = mb_strtolower(str_replace(['I', 'İ'], ['ı', 'i'], $raw_sp2), 'UTF-8');
          $title_sp2 = mb_convert_case($lower_sp2, MB_CASE_TITLE, 'UTF-8');

          if (in_array($upper_sp2, $temsilciler_sabit)) {
            $formatted_sp = $title_sp2;
          } else {
            $formatted_sp = $title_sp2 . ' (Diğer)';
          }
        }

        if (!isset($__orders[$__code])) {
          $is_inv = (mb_strtolower(trim((string)($r['order_status'] ?? '')), 'UTF-8') === 'fatura_edildi' || mb_strtolower(trim((string)($r['order_status'] ?? '')), 'UTF-8') === 'fatura edildi');
          $f_toplam = (float)($r['fatura_toplam'] ?? 0);
          $kdv_rate = isset($r['kdv_orani']) ? (float)$r['kdv_orani'] : 20;

          if ($is_inv && $f_toplam > 0) {
            $row_cur = !empty($r['fatura_para_birimi']) ? $r['fatura_para_birimi'] : ($r['currency'] ?? '');
            $subtotal_val = 0.0;
            $genel_toplam_val = $f_toplam; // Mühürlü Genel Toplam
            $is_sealed = true;
          } else {
            $row_cur = !empty($r['kalem_para_birimi']) ? $r['kalem_para_birimi'] : ($r['currency'] ?? '');
            $subtotal_val = 0.0;
            $genel_toplam_val = 0.0;
            $is_sealed = false;
          }

          $__orders[$__code] = [
            'order_id'      => $r['order_id'] ?? null,
            'order_date'    => $r['order_date'] ?? '',
            'siparisi_alan' => $formatted_sp,
            'customer_name' => $r['customer_name'] ?? '',
            'project_name'  => $r['project_name'] ?? '',
            'order_code'    => $__code,
            'currency'      => $row_cur,
            'subtotal'      => $subtotal_val,
            'genel_toplam'  => $genel_toplam_val,
            'kdv_rate'      => $kdv_rate,
            'is_sealed'     => $is_sealed
          ];
        }

        // Eğer mühürlü değilse alt kalemleri toplayarak git (Sadece KDV'siz kısmı topla, yazdırırken kdv eklenecek)
        if (!$__orders[$__code]['is_sealed']) {
          $__orders[$__code]['subtotal'] += (float)($r['line_total'] ?? 0);
        }
      }
      // Yazdır
      if (empty($__orders)):
      ?>
        <tr>
          <td style="text-align:center;" colspan="8" class="ta-center muted">Kayıt bulunamadı.</td>
        </tr>
        <?php else: foreach ($__orders as $__o):
          $kdv_carpan = $__o['kdv_rate'] / 100;
          
          if ($__o['is_sealed']) {
              // Mühürlü sistemde KDV'yi TERSTEN hesapla
              $__genel = $__o['genel_toplam'];
              $__kdv = $__genel - ($__genel / (1 + $kdv_carpan));
              $__ara = $__genel - $__kdv;
          } else {
              // Mühürsüz sistemde (Normal akış)
              $__ara = $__o['subtotal'];
              $__kdv = $__ara * $kdv_carpan;
              $__genel = $__ara + $__kdv;
          }

          // Satici ismine gore renk ver (Bos ise kirmizi olsun)
          $sp_color = $__o['siparisi_alan'] === 'Belirtilmemiş' ? 'color:#ef4444; font-style:italic;' : 'color:#0f172a; font-weight:600;';
        ?>
          <tr data-order-id="<?= (int)($__o['order_id'] ?? 0) ?>" class="order-row">
            <td><?= fmt_tr_date($__o['order_date'] ?? '') ?></td>
            <td style="<?= $sp_color ?>"><?= h($__o['siparisi_alan']) ?></td>
            <td><?= h($__o['customer_name']) ?></td>
            <td><?= h($__o['project_name']) ?></td>
            <td style="text-align:center;" class="ta-center"><a href="order_view.php?id=<?= (int)($__o['order_id'] ?? 0) ?>" class="badge"><?= h($__o['order_code']) ?></a></td>
            <td class="ta-center"><?= fmt_tr_money($__ara) ?> <?= h($__o['currency']) ?></td>
            <td class="ta-center"><?= fmt_tr_money($__kdv) ?> <?= h($__o['currency']) ?></td>
            <td class="ta-center" style="font-weight:bold; color:#166534;"><?= fmt_tr_money($__genel) ?> <?= h($__o['currency']) ?></td>
          </tr>
      <?php endforeach;
      endif; ?>
    </tbody>
    </tbody>
  </table>
</div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>

<script>
  (function() {
    const payload = <?php echo json_encode($chart_payload, JSON_UNESCAPED_UNICODE); ?>;

    // 1. CANLI ÜRETİM SAHASINDAKİ MATEMATİKSEL RENK ALGORİTMASI
    // Yan yana gelen dilimler asla birbirine benzemez (+45 derece atlar)
    function generateColors(count, hueStart) {
      let colors = [];
      for (let i = 0; i < count; i++) {
        let hue = (hueStart + (i * 45)) % 360;
        colors.push(`hsl(${hue}, 70%, 55%)`);
      }
      return colors;
    }

    function entriesFrom(group) {
      const items = (payload && payload[group]) || {};
      return Object.entries(items)
        .map(([name, info]) => ({
          name: name,
          val: Number(info.try_val) || 0, // Grafik için TL
          disp_val: Number(info.val) || 0, // Liste için Orijinal Döviz
          cur: info.cur || ''
        }))
        .sort((a, b) => b.val - a.val);
    }

    function symbol(cur) {
      return cur === 'TRY' ? '₺' : (cur === 'USD' ? '$' : (cur === 'EUR' ? '€' : ''));
    }

    function renderPie(canvasId, listId, entries, startHue, isCount = false) {
      const labels = entries.map(e => e.name);
      const data = entries.map(e => e.val);

      const colors = generateColors(labels.length, startHue);

      const ctx = document.getElementById(canvasId)?.getContext('2d');
      if (ctx && labels.length) {
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
              legend: {
                display: false
              },
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
                      return ' ' + label + ': ₺' + value.toLocaleString('tr-TR', {
                        minimumFractionDigits: 4,
                        maximumFractionDigits: 4
                      });
                    }
                  }
                }
              }
            },
            layout: {
              padding: 15
            }
          }
        });
      }

      const ul = document.getElementById(listId);
      if (ul) {
        if (!entries.length) {
          ul.innerHTML = '<li><span class="name">Veri yok</span><span class="val">—</span></li>';
        } else {
          const top5 = entries.slice(0, 5);
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

    // ================================================
    // ⭐ SATIŞ TEMSİLCİSİ - DİNAMİK SIRALAMA
    // ================================================
    let salespersonChart = null;

    function renderSalespersonChart(sortBy) {
      const enhanced = payload.salesperson_enhanced || {};
      let entries = Object.entries(enhanced).map(([name, data]) => {
        let value = 0;
        let displayValue = 0;
        let suffix = '';

        if (sortBy === 'order_count') {
          value = data.order_count || 0;
          displayValue = value;
          suffix = ' Sipariş';
        } else if (sortBy === 'total_price') {
          value = data.total_price_try || 0;
          displayValue = value;
          suffix = '';
        }
        return {
          name: name,
          value: value,
          displayValue: displayValue,
          suffix: suffix,
          currency: data.original_currency || 'TRY'
        };
      });

      entries.sort((a, b) => b.value - a.value);
      const labels = entries.map(e => e.name);
      const data = entries.map(e => e.value);
      const colors = generateColors(labels.length, 50);

      if (salespersonChart) salespersonChart.destroy();

      const ctx = document.getElementById('pieSalesperson')?.getContext('2d');
      if (ctx && labels.length) {
        salespersonChart = new Chart(ctx, {
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
              legend: {
                display: false
              },
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
                    let entry = entries[context.dataIndex];
                    if (label.length > 30) label = label.substring(0, 30) + '...';
                    if (sortBy === 'total_price') return ' ' + label + ': ₺' + value.toLocaleString('tr-TR', {
                      minimumFractionDigits: 2,
                      maximumFractionDigits: 2
                    });
                    return ' ' + label + ': ' + value + entry.suffix;
                  }
                }
              }
            },
            layout: {
              padding: 15
            }
          }
        });
      }

      const ul = document.getElementById('top5Salesperson');
      if (ul) {
        if (!entries.length) {
          ul.innerHTML = '<li><span class="name">Veri yok</span><span class="val">—</span></li>';
        } else {
          const top5 = entries.slice(0, 5);
          ul.innerHTML = top5.map((it, idx) => {
            let displayText = sortBy === 'total_price' ?
              '₺' + it.displayValue.toLocaleString('tr-TR', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
              }) :
              it.displayValue.toLocaleString('tr-TR') + it.suffix;

            // 👑 Sadece birinciye isimden önce taç ekle (Yazısız/Sade)
            let crown = (idx === 0) ? '<span style="margin-right:6px; display:inline-block; transform:scale(1.2);">👑</span>' : '';
            let nameStyle = (idx === 0) ? 'font-weight:700; color:#1e293b;' : '';

            return `<li><span class="name" style="${nameStyle}">${crown}${it.name}</span><span class="val" style="color:#10b981; font-weight:600;">${displayText}</span></li>`;
          }).join('');
        }
      }
    }

    renderSalespersonChart('order_count');
    document.querySelectorAll('input[name="salesperson_sort"]').forEach(function(radio) {
      radio.addEventListener('change', function() {
        if (this.checked) {
          // ⭐ YENİ: Bilgi metnini sadece 'total_price' seçiliyse göster
          const infoText = document.getElementById('spPriceInfo');
          if (infoText) {
            infoText.style.display = this.value === 'total_price' ? 'block' : 'none';
          }

          const chartBox = document.querySelector('#pieSalesperson').closest('.chart-box');
          chartBox.style.opacity = '0.3';
          setTimeout(() => {
            renderSalespersonChart(this.value);
            chartBox.style.opacity = '1';
          }, 150);
        }
      });
    });
    // 2. HER GRAFİĞE FARKLI BİR "TEMA/BAŞLANGIÇ" RENGİ VERİYORUZ
    // renderPie('pieSalesperson'...) kodu buradan SİLİNDİ çünkü yukarıda dinamik çiziliyor.
    renderPie('pieCustomer', 'top5Customer', entriesFrom('customer'), 200, false); // Mavi tonlarından başlar
    renderPie('pieProject', 'top5Project', entriesFrom('project'), 280, false); // Mor tonlarından başlar
    renderPie('pieCategory', 'top5Category', entriesFrom('category'), 340, false); // Kırmızı/Pembe tonları

    // ================================================
    // ⭐ YENİ: SATIŞ TEMSİLCİSİ DETAY ANALİZİ
    // ================================================
    const spDetails = payload.salesperson_details || {};
    const spSelect = document.getElementById('spDetailSelect');

    // Select Kutusunu Doldur
    const spList = Object.keys(spDetails).sort();
    spList.forEach(sp => {
      let opt = document.createElement('option');
      opt.value = sp;
      opt.textContent = sp;
      spSelect.appendChild(opt);
    });

    // "Belirtilmemiş" harici ilk kişiyi seçili yap (DERYA gibi)
    const firstRealSp = spList.find(s => s !== 'Belirtilmemiş') || spList[0];
    if (firstRealSp) spSelect.value = firstRealSp;

    let spDetailChart = null;

    function renderSpDetailChart() {
      const selectedSp = spSelect.value;
      const selectedType = document.querySelector('input[name="sp_detail_type"]:checked').value;
      const ul = document.getElementById('top5SpDetail');
      const ctx = document.getElementById('pieSpDetail')?.getContext('2d');

      if (!selectedSp || !spDetails[selectedSp]) {
        if (spDetailChart) spDetailChart.destroy();
        ul.innerHTML = '<li style="font-size:12px; color:#94a3b8;">Temsilci seçilmedi</li>';
        return;
      }

      function getSymbol(c) {
        return c === 'TRY' ? '₺' : (c === 'USD' ? '$' : (c === 'EUR' ? '€' : ''));
      }

      const dataObj = spDetails[selectedSp][selectedType] || {};
      let entries = Object.entries(dataObj)
        .map(([name, info]) => ({
          name: name,
          val: Number(info.try_val) || 0, // Grafikte dilim büyüklüğü ve sıralama için TL
          disp_val: Number(info.val) || 0, // Ekranda göstermek için Orijinal Döviz
          cur: info.cur || 'TRY'
        }))
        .sort((a, b) => b.val - a.val);

      const labels = entries.map(e => e.name);
      const data = entries.map(e => e.val); // ChartJS oranlamak için TL kullanacak

      // Mor (Proje) veya Pembe (Grup) renk teması
      const colors = generateColors(labels.length, selectedType === 'projects' ? 270 : 330);

      if (spDetailChart) spDetailChart.destroy();

      if (labels.length > 0 && ctx) {
        spDetailChart = new Chart(ctx, {
          type: 'doughnut',
          data: {
            labels: labels,
            datasets: [{
              data: data,
              backgroundColor: colors,
              borderWidth: 2,
              borderColor: '#ffffff',
              hoverOffset: 10
            }]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
              legend: {
                display: false
              },
              tooltip: {
                backgroundColor: 'rgba(255, 255, 255, 0.95)',
                titleColor: '#1e293b',
                bodyColor: '#1e293b',
                borderColor: '#e2e8f0',
                borderWidth: 1,
                padding: 12,
                callbacks: {
                  label: function(context) {
                    let lbl = context.label || '';
                    if (lbl.length > 30) lbl = lbl.substring(0, 30) + '...';
                    let entry = entries[context.dataIndex];
                    return ' ' + lbl + ': ' + entry.disp_val.toLocaleString('tr-TR', {
                      minimumFractionDigits: 2,
                      maximumFractionDigits: 2
                    }) + ' ' + getSymbol(entry.cur);
                  }
                }
              }
            },
            layout: {
              padding: 10
            }
          }
        });
      }

      // Listeyi Çiz
      if (!entries.length) {
        ul.innerHTML = '<li style="font-size:12px; color:#94a3b8;">Bu kriterde kayıt bulunamadı</li>';
      } else {
        ul.innerHTML = entries.slice(0, 5).map(it => {
          let txt = it.disp_val.toLocaleString('tr-TR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
          }) + ' ' + getSymbol(it.cur);
          return `<li style="display:flex; justify-content:space-between; font-size:12px; padding:8px 10px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px;">
                  <span style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:60%; font-weight:600; color:#334155;">${it.name}</span>
                  <span style="color:#10b981; font-weight:800;">${txt}</span>
              </li>`;
        }).join('');
      }
    }

    // Temsilci veya Radio Button değiştiğinde grafiği güncelle
    spSelect.addEventListener('change', renderSpDetailChart);
    document.querySelectorAll('input[name="sp_detail_type"]').forEach(r => r.addEventListener('change', renderSpDetailChart));

    // Sayfa yüklendiğinde ilk çizimi yap
    renderSpDetailChart();

  })();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<script>
  document.addEventListener('DOMContentLoaded', function() {
    var table = document.getElementById('reportTable');
    if (!table) return;
    table.querySelectorAll('tbody tr.order-row').forEach(function(tr) {
      var oid = tr.getAttribute('data-order-id');
      if (!oid || oid === '0') return;
      tr.style.cursor = 'pointer';
      tr.addEventListener('click', function(ev) {
        var tag = ev.target.tagName.toLowerCase();
        if (['a', 'button', 'input', 'select', 'textarea', 'label'].includes(tag)) return;
        window.location.href = 'order_view.php?id=' + encodeURIComponent(oid);
      });
    });
  });
</script>

<script>
  (function() {
    function trParse(s) {
      return parseFloat(String(s).replace(/\./g, '').replace(',', '.'));
    }

    function trFmt(n) {
      return n.toLocaleString('tr-TR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      });
    }
    window.__renplan_trParse = trParse;
    window.__renplan_trFmt = trFmt;

    function countUp(el, to, ms) {
      const curTxt = (el.dataset.cur || '').trim();
      const from = el.dataset.prev ? trParse(el.dataset.prev) : 0;
      const start = performance.now();

      function step(t) {
        const p = Math.min((t - start) / ms, 1);
        const e = 1 - Math.pow(1 - p, 3);
        const v = from + (to - from) * e;
        el.textContent = trFmt(v) + (curTxt ? (' ' + curTxt) : '');
        if (p < 1) requestAnimationFrame(step);
        else el.dataset.prev = trFmt(to);
      }
      requestAnimationFrame(step);
    }

    document.addEventListener('DOMContentLoaded', function() {
      document.querySelectorAll('.pie-card, .stat-card').forEach(function(el) {
        el.classList.add('will-animate');
      });
      document.querySelectorAll('.stat-card .val').forEach(function(el) {
        const parts = el.textContent.trim().split(/\s+/);
        const cur = parts.pop();
        el.dataset.cur = cur;
        const to = trParse(parts.join(' '));
        if (!isNaN(to)) countUp(el, to, 900);
      });
      const io = new IntersectionObserver(function(entries) {
        entries.forEach(function(e) {
          if (e.isIntersecting) {
            e.target.classList.add('appear');
            io.unobserve(e.target);
          }
        });
      }, {
        threshold: .15
      });
      document.querySelectorAll('.will-animate').forEach(function(n) {
        io.observe(n);
      });
    });
  })();
</script>

<script>
  (function() {
    const io = new IntersectionObserver(function(entries) {
      entries.forEach(function(e) {
        if (e.isIntersecting) {
          e.target.classList.add('appear');
          io.unobserve(e.target);
        }
      });
    }, {
      threshold: .15
    });
    document.addEventListener('DOMContentLoaded', function() {
      document.querySelectorAll('.will-animate').forEach(function(n) {
        io.observe(n);
      });
    });
  })();
</script>

<script>
  $(document).ready(function() {
    // Proje ve Müşteri select kutularını Select2'ye çeviriyoruz
    $('select[name="customer_id"]').select2({
      placeholder: "Müşteri Seçin...",
      allowClear: true,
      width: '100%',
      language: {
        noResults: function() {
          return "Kayıt bulunamadı";
        }
      }
    });

    $('select[name="project_query"]').select2({
      placeholder: "Proje Seçin...",
      allowClear: true,
      width: '100%',
      language: {
        noResults: function() {
          return "Proje bulunamadı";
        }
      }
    });

    // ⭐ YENİ: Menü açıldığında, içindeki gizli arama kutusunu bul ve emojiyi ekle
    $(document).on('select2:open', function() {
      const searchInput = document.querySelector('.select2-search__field');
      if (searchInput) {
        searchInput.placeholder = '🔍 Yazarak ara...';
      }
    });
  });
</script>