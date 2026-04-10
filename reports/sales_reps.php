<?php

/**
 * =========================================================================================
 * REPORT_ORDERS.PHP (SALES REPS CONTROLLER)
 * =========================================================================================
 * Bu dosya, Satış ve Finans raporlarının "Controller" (Kontrolör) katmanıdır. 
 * Sadece veri çekme, harici servislerle haberleşme ve hesaplama işlemlerini yapar. 
 * Görünüm (HTML/CSS) işlemleri app/Views/reports/sales_reps_view.php dosyasına devredilmiştir.
 * =========================================================================================
 */

declare(strict_types=1);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

/**
 * -----------------------------------------------------------------------------------------
 * 1. BAŞLANGIÇ & YETKİLENDİRME (SETUP & AUTH)
 * -----------------------------------------------------------------------------------------
 */
require_once __DIR__ . '/../includes/helpers.php';
require_login();

// --- 🔒 ADMİN VE MUHASEBE YETKİ KONTROLÜ ---
$__role = current_user()['role'] ?? '';
if (!in_array($__role, ['admin', 'muhasebe'])) {
  die('<div style="margin:50px auto; max-width:500px; padding:30px; background:#fff1f2; border:2px solid #fda4af; border-radius:12px; color:#e11d48; font-family:sans-serif; text-align:center; box-shadow:0 10px 25px rgba(225,29,72,0.1);">
        <h2 style="margin-top:0; font-size:24px;">⛔ YETKİSİZ ERİŞİM</h2>
        <p style="font-size:15px; line-height:1.5;">Bu finansal raporları ve grafikleri yalnızca <b>Yönetici (Admin) veya Muhasebe</b> yetkisine sahip kullanıcılar görüntüleyebilir.</p>
        <a href="../index.php" style="display:inline-block; margin-top:15px; padding:10px 20px; background:#e11d48; color:#fff; text-decoration:none; border-radius:8px; font-weight:bold;">Ana Sayfaya Dön</a>
    </div>');
}

$db = pdo();

/**
 * -----------------------------------------------------------------------------------------
 * 2. YARDIMCI FONKSİYONLAR (HELPER FUNCTIONS)
 * -----------------------------------------------------------------------------------------
 */
if (!function_exists('h')) {
  function h(string $s = null): string
  {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
  }
}

if (!function_exists('fmt_tr_money')) {
  function fmt_tr_money(mixed $v): string
  {
    if ($v === null || $v === '') return '';
    return number_format((float)$v, 4, ',', '.');
  }

  function fmt_tr_date(mixed $s): string
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
  function tr_to_float(string $s): ?float
  {
    $s = str_replace(['.', ','], ['', '.'], $s);
    return is_numeric($s) ? (float)$s : null;
  }
}

if (!function_exists('normalize_currency')) {
  function normalize_currency(mixed $cur): string
  {
    $cur = strtoupper(trim((string)$cur));
    if ($cur === '' || $cur === '—') return '—';
    if ($cur === 'TL' || $cur === '₺' || $cur === 'TRL') return 'TRY';
    if ($cur === 'US$' || $cur === '$') return 'USD';
    if ($cur === '€' || $cur === 'EURO') return 'EUR';
    return $cur;
  }
}

function inparam(string $k, mixed $d = null): mixed
{
  return (isset($_GET[$k]) && $_GET[$k] !== '') ? trim((string)$_GET[$k]) : $d;
}

function inparam_arr(string $k): array
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
/**
 * -----------------------------------------------------------------------------------------
 * 3. HTTP İSTEKLERİ VE FİLTRELER (REQUEST PARAMETERS)
 * -----------------------------------------------------------------------------------------
 */
$filters = [
  'date_from'     => inparam('date_from'),
  'date_to'       => inparam('date_to'),
  'customer_id'   => inparam('customer_id'),
  'product_query' => inparam('product_query'),
  'project_query' => inparam('project_query'),
  'currency'      => inparam('currency'),
  'min_unit'      => inparam('min_unit'),
  'max_unit'      => inparam('max_unit'),
  'prod_status'   => inparam_arr('prod_status'),
];

/**
 * -----------------------------------------------------------------------------------------
 * 4. VERİTABANI İŞLEMLERİ (MODEL LAYER)
 * -----------------------------------------------------------------------------------------
 */
require_once __DIR__ . '/../app/Models/ReportModel.php';
$reportModel = new ReportModel($db);
$dbResult = $reportModel->getSalesData($filters);
$rows = $dbResult['rows'];
$queryError = $dbResult['error'];

/**
 * -----------------------------------------------------------------------------------------
 * 5. CSV DIŞA AKTARMA (EXPORT LOGIC)
 * -----------------------------------------------------------------------------------------
 */
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

/**
 * -----------------------------------------------------------------------------------------
 * 6. FİNANS VE GÜNCEL KURLAR (SERVICE LAYER)
 * -----------------------------------------------------------------------------------------
 */
$totalsByCurrency = [];
foreach ($rows as $r) {
  $cur = normalize_currency($r['currency'] ?? '—');
  if (!isset($totalsByCurrency[$cur])) $totalsByCurrency[$cur] = 0.0;
  $totalsByCurrency[$cur] += (float)($r['line_total'] ?? 0);
}

// TCMB Güncel Kur Çekimi (Servis Üzerinden)
require_once __DIR__ . '/../app/Services/FinanceService.php';
$financeService = new FinanceService();
$rates = $financeService->getCurrentExchangeRates();
$usd_rate = $rates['USD'];
$eur_rate = $rates['EUR'];

/**
 * -----------------------------------------------------------------------------------------
 * 7. VERİ İŞLEME VE MATEMATİKSEL HESAPLAMALAR (BUSINESS LOGIC)
 * -----------------------------------------------------------------------------------------
 */
// Siparişlerin toplam kalem maliyetlerini (KDV'li) önceden hesapla ki, 
// mühürlü fatura toplamını satırlara tam oranlayabilelim (kur_usd 0 olsa bile kusursuz çalışır)
$order_kalem_totals = [];
foreach ($rows as $r) {
  $oid = $r['order_id'];
  $raw = (float)($r['line_total'] ?? 0);
  $kdv = isset($r['kdv_orani']) ? (float)$r['kdv_orani'] : 20;
  $order_kalem_totals[$oid] = ($order_kalem_totals[$oid] ?? 0) + ($raw * (1 + ($kdv / 100)));
}

// Grafikler için TL Bazlı Toplamlar ve Ekranda Gösterilecek Ham Toplamlar
$agg_customer_try = [];
$agg_project_try  = [];
$agg_category_try = [];
$cur_customer     = [];
$cur_project      = [];
$cur_category     = [];

// Satış temsilcisi (siparisi_alan) verileri
$salesperson_orders      = [];
$processed_orders_for_sp = [];
$salesperson_details     = []; // Temsilci Detay Analizi İçin 
$sp_agg_proj             = [];
$sp_cur_proj             = []; // Dövizli yapı için
$sp_agg_grp              = [];
$sp_cur_grp              = []; // Dövizli yapı için 

foreach ($rows as $r) {
  $raw_amt = (float)($r['line_total'] ?? 0);
  $kdv_orani = isset($r['kdv_orani']) ? (float)$r['kdv_orani'] : 20;
  $raw_amt_kdvli = $raw_amt * (1 + ($kdv_orani / 100)); // Grafikler KDV Dahil hesaplanır

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
    // 2. MÜHÜRSÜZ VEYA AÇIK SİPARİŞ: Kendi saf KDV'li fiyatını kullan
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

  // Kategori & SKU Akıllı Fallback
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
  $g = $best_group;

  // Siparişi Alan (Sabit Liste ve Diğer Gruplaması)
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

  // Satış Temsilcisi Detay Analizi Verisi (Döviz Korumalı)
  $sp_agg_proj[$sp][$p] = ($sp_agg_proj[$sp][$p] ?? 0) + $amt_try;
  if (!isset($sp_cur_proj[$sp][$p][$cur])) $sp_cur_proj[$sp][$p][$cur] = 0;
  $sp_cur_proj[$sp][$p][$cur] += $amt;

  $family = $best_group;
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

// TL'ye çevrilmiş değerlere göre büyükten küçüğe sırala
arsort($agg_customer_try);
arsort($agg_project_try);
arsort($agg_category_try);
arsort($salesperson_orders); // Satış Temsilcisini en çok satandan küçüğe sırala

function get_dominant_info(array $tryTotals, array $bucketMap): array
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
      'cur' => $dom_cur,                 
      'val' => $curMap[$dom_cur],        
      'try_val' => $tryVal               
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

  // Detay analizi verisini döviz bilgisiyle oluştur
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
  'salesperson_details' => $salesperson_details,
];

/**
 * -----------------------------------------------------------------------------------------
 * 8. GELİŞTİRİLMİŞ SATIŞ TEMSİLCİSİ KARTI VERİSİ
 * -----------------------------------------------------------------------------------------
 */
$salesperson_enhanced = [];

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

  // Sipariş sayısı (Her siparişi 1 kez say)
  if (!isset($salesperson_enhanced[$sp]['processed_orders'][$oid])) {
    $salesperson_enhanced[$sp]['processed_orders'][$oid] = true;
    $salesperson_enhanced[$sp]['order_count']++;
  }

  // Toplam fiyat (KDV Dahil hesaplama)
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
    $subtotal = $fatura_toplam_muhur2 * $oran2;
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

  // Ürün grupları (Akıllı Kategori/SKU Fallback)
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

// Ürün grubu sayısını hesapla
foreach ($salesperson_enhanced as $sp => &$data) {
  $data['product_group_count'] = count($data['product_groups']);
  unset($data['product_groups']);
  unset($data['processed_orders']);
}
unset($data);

// Mevcut chart_payload'a ekle
$chart_payload['salesperson_enhanced'] = $salesperson_enhanced;

/**
 * -----------------------------------------------------------------------------------------
 * 9. GÖRÜNÜM (VIEW) YÜKLEMESİ
 * -----------------------------------------------------------------------------------------
 */
require_once __DIR__ . '/../app/Views/reports/sales_reps_view.php';
