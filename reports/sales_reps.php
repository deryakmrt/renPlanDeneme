<?php

/**
 * =========================================================================================
 * REPORT_ORDERS.PHP (SALES REPS CONTROLLER)
 * =========================================================================================
 * Bu dosya, Satış ve Finans raporlarının "Controller" (Kontrolör) katmanıdır. 
 * Sadece veri çekme, harici servislerle haberleşme ve hesaplama işlemlerini yapar. 
 * Görünüm (HTML/CSS) işlemleri app/Views/reports/sales_reps_view.php dosyasına devredilmiştir.
 *
 * [USD BAZLI ANALİTİK - v2]
 * Grafikler ve sıralamalar artık TL değil USD cinsinden hesaplanmaktadır.
 * Kur öncelik sırası:
 * 1. Fatura edilmiş + orders.kur_usd / kur_eur > 0  → Manuel (özel) kur
 * 2. Fatura edilmiş + orders.kur_usd / kur_eur = 0  → TCMB tarihsel kur (fatura tarihi)
 * 3. Henüz fatura edilmemiş (açık sipariş)          → Bugünkü güncel TCMB kuru
 * Görsel listeler (müşteri / proje / kategori karşısı) orijinal para biriminde kalmaya devam eder.
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
if (!in_array($__role, ['admin', 'muhasebe', 'sistem_yoneticisi'])) {
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
    $s = trim($s);
    if ($s === '') return null;

    if (strpos($s, '.') !== false && strpos($s, ',') !== false) {
        $s = str_replace('.', '', $s);
        $s = str_replace(',', '.', $s);
    } 
    elseif (strpos($s, ',') !== false) {
        $s = str_replace(',', '.', $s);
    }
    return is_numeric($s) ? (float)$s : null;
  }
}

function normalize_currency($cur): string
{
  $cur = strtoupper(trim((string)$cur));
  if ($cur === '' || $cur === '—') return '—';
  if (in_array($cur, ['TL', '₺', 'TRL', 'TRY'])) return 'TRY';
  if (strpos($cur, 'USD') !== false || strpos($cur, '$') !== false || strpos($cur, 'DOLAR') !== false) return 'USD';
  if (strpos($cur, 'EUR') !== false || strpos($cur, '€') !== false || strpos($cur, 'AVRO') !== false) return 'EUR';
  return $cur;
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

function get_tcmb_historical_rate(string $date, string $currency, float $fallback): float
{
  static $cache = [];

  try {
    $dt = new DateTime($date);
  } catch (Throwable $e) {
    return $fallback;
  }

  $dow = (int)$dt->format('N');
  if ($dow === 6) $dt->modify('-1 day');
  if ($dow === 7) $dt->modify('-2 days');

  $cacheKey = $dt->format('Ymd') . '_' . $currency;
  if (isset($cache[$cacheKey])) return $cache[$cacheKey];

  $urlMonth = $dt->format('Ym');
  $dayMonYr = $dt->format('dmY');
  $url = "https://www.tcmb.gov.tr/kurlar/{$urlMonth}/{$dayMonYr}.xml";

  $rate = null;
  try {
    $ctx     = stream_context_create(['http' => ['timeout' => 3]]);
    $xmlData = @file_get_contents($url, false, $ctx);
    if ($xmlData) {
      $tcmb = @simplexml_load_string($xmlData);
      if ($tcmb) {
        foreach ($tcmb->Currency as $c) {
          if ((string)$c['CurrencyCode'] === $currency) {
            $rate = (float)$c->ForexSelling;
            break;
          }
        }
      }
    }
  } catch (Throwable $e) {}

  if (!$rate || $rate <= 0.0) $rate = $fallback;

  $cache[$cacheKey] = $rate;
  return $rate;
}

function resolve_usd_rate(array $row, string $cur, bool $is_invoiced, array $current_rates): float
{
  if ($cur === 'USD') return 1.0; 

  if ($cur === 'TRY' || $cur === '—') {
    $usd_try = resolve_usd_try_kuru($row, $is_invoiced, $current_rates);
    return ($usd_try > 0) ? (1.0 / $usd_try) : (1.0 / $current_rates['USD']);
  }

  if ($cur === 'EUR') {
    $eur_try = resolve_eur_try_kuru($row, $is_invoiced, $current_rates);
    $usd_try = resolve_usd_try_kuru($row, $is_invoiced, $current_rates);
    return ($usd_try > 0) ? ($eur_try / $usd_try) : ($current_rates['EUR'] / $current_rates['USD']);
  }

  return 1.0;
}

function resolve_usd_try_kuru(array $row, bool $is_invoiced, array $current_rates): float
{
  if ($is_invoiced) {
    $raw    = (string)($row['kur_usd'] ?? '');
    $manual = ($raw !== '') ? (tr_to_float($raw) ?? 0.0) : 0.0;
    if ($manual > 0) return $manual; 

    $date = !empty($row['fatura_tarihi']) ? (string)$row['fatura_tarihi'] : (string)($row['order_date'] ?? date('Y-m-d'));
    return get_tcmb_historical_rate($date, 'USD', $current_rates['USD']);
  }
  return $current_rates['USD']; 
}

function resolve_eur_try_kuru(array $row, bool $is_invoiced, array $current_rates): float
{
  if ($is_invoiced) {
    $raw    = (string)($row['kur_eur'] ?? '');
    $manual = ($raw !== '') ? (tr_to_float($raw) ?? 0.0) : 0.0;
    if ($manual > 0) return $manual; 

    $date = !empty($row['fatura_tarihi']) ? (string)$row['fatura_tarihi'] : (string)($row['order_date'] ?? date('Y-m-d'));
    return get_tcmb_historical_rate($date, 'EUR', $current_rates['EUR']);
  }
  return $current_rates['EUR'];
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
  'salesperson'   => inparam('salesperson'), // Satış temsilcisi filtresi
];

// Performans Analizi paneli tarih filtresi JS tarafında çalışır, PHP'de kullanılmaz
$sp_date_from = null;
$sp_date_to   = null;

require_once __DIR__ . '/../app/Models/ReportModel.php';
$reportModel = new ReportModel($db);
$dbResult = $reportModel->getSalesData($filters);

// Askıya alınan siparişleri ve seçili temsilci dışındakileri filtrele
$_filter_sp = $filters['salesperson'] ?? '';
$_temsilciler_sabit_upper = ['ALİ ALTUNAY', 'FATİH SERHAT ÇAÇIK', 'HASAN BÜYÜKOBA', 'HİKMET ŞİMŞEK', 'MUHAMMET YAZGAN', 'MURAT SEZER'];

$rows = array_filter($dbResult['rows'], function($r) use ($_filter_sp, $_temsilciler_sabit_upper) {
    // Askıya alınanları at
    if (mb_strtolower(trim((string)($r['order_status'] ?? '')), 'UTF-8') === 'askiya_alindi') return false;

    // Satış temsilcisi filtresi
    if (!empty($_filter_sp)) {
        $raw_sp = trim((string)($r['siparisi_alan'] ?? ''));
        if ($_filter_sp === 'Belirtilmemiş') {
            if ($raw_sp !== '') return false;
        } else {
            $upper_sp = mb_strtoupper(str_replace(['i', 'ı'], ['İ', 'I'], $raw_sp), 'UTF-8');
            $lower_sp = mb_strtolower(str_replace(['I', 'İ'], ['ı', 'i'], $raw_sp), 'UTF-8');
            $title_sp = mb_convert_case($lower_sp, MB_CASE_TITLE, 'UTF-8');
            $resolved = in_array($upper_sp, $_temsilciler_sabit_upper) ? $title_sp : ($title_sp . ' (Diğer)');
            if ($resolved !== $_filter_sp) return false;
        }
    }

    return true;
});

$queryError = $dbResult['error'];

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
  $filename = 'satis_raporu_' . date('Ymd_His') . '.csv';
  header('Content-Type: text/csv; charset=UTF-8');
  header('Content-Disposition: attachment; filename="' . $filename . '"');
  echo "\xEF\xBB\xBF";
  $out = fopen('php://output', 'w');
  fputcsv($out, ['Siparişi Alan', 'Müşteri', 'Proje', 'Sipariş Kodu', 'Ürün', 'SKU', 'Miktar', 'Birim', 'Birim Fiyat', 'Para Birimi', 'Satır Toplam', 'Sipariş Tarihi']);
  $temsilciler_sabit = ['ALİ ALTUNAY', 'FATİH SERHAT ÇAÇIK', 'HASAN BÜYÜKOBA', 'HİKMET ŞİMŞEK', 'MUHAMMET YAZGAN', 'MURAT SEZER'];
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
 * STAT KARTLARI: Toplam USD ve Toplam TRY (KDV'siz net + KDV ayrı)
 *
 * Her kalem için:
 *   net = line_total (qty * price, KDV hariç)
 *   kdv = net * kdv_orani / 100
 *
 * Fatura edilmiş sipariş:
 *   Para birimi: fatura_para_birimi (yoksa order_currency)
 *   Tutar baz: fatura_toplam'dan kalem oranıyla pay alınır
 *   Kur: kur_usd/kur_eur (manuel) → yoksa fatura tarihindeki TCMB
 *
 * Fatura edilmemiş sipariş:
 *   Tutar baz: line_total (net)
 *   Kur: bugünkü TCMB
 *
 * USD hesabı:
 *   - Fatura USD → direkt
 *   - Fatura TRY → fatura_tutarı / kur_usd
 *   - Fatura EUR → fatura_tutarı * kur_eur / kur_usd
 *   - Faturasız  → net / gunluk_usd
 *
 * TRY hesabı:
 *   - Fatura TRY → direkt
 *   - Fatura USD → fatura_tutarı * kur_usd
 *   - Fatura EUR → fatura_tutarı * kur_eur
 *   - Faturasız TRY → direkt, diğer → net * gunluk_kur
 * -----------------------------------------------------------------------------------------
 */

// Önce FinanceService'i yükle (rates lazım)
require_once __DIR__ . '/../app/Services/FinanceService.php';
$financeService = new FinanceService();
$rates   = $financeService->getCurrentExchangeRates();
$usd_rate = $rates['USD'];
$eur_rate = $rates['EUR'];

// Sipariş bazında kalem KDV'siz toplamlarını hesapla (fatura oranı için payda)
$order_kalem_net_totals = [];
foreach ($rows as $r) {
  $oid = $r['order_id'];
  $net = (float)($r['line_total'] ?? 0); // KDV hariç
  $order_kalem_net_totals[$oid] = ($order_kalem_net_totals[$oid] ?? 0) + $net;
}

$stat_usd_net = 0.0; // KDV'siz USD toplamı
$stat_usd_kdv = 0.0; // KDV tutarı (USD)
$stat_try_net = 0.0; // KDV'siz TRY toplamı
$stat_try_kdv = 0.0; // KDV tutarı (TRY)

// Sipariş başına mükerrer fatura tutarı uygulamasını önlemek için
$processed_stat_orders = [];

foreach ($rows as $r) {
  $oid      = $r['order_id'];
  $net_line = (float)($r['line_total'] ?? 0); // Bu kalemin KDV'siz tutarı
  $kdv_rate = isset($r['kdv_orani']) ? (float)$r['kdv_orani'] : 20.0;
  $kdv_line = $net_line * $kdv_rate / 100.0;

  // Fatura kontrolü
  $status_str        = mb_strtolower(trim((string)($r['order_status'] ?? '')), 'UTF-8');
  $fatura_toplam_val = (float)($r['fatura_toplam'] ?? 0);
  $is_invoiced       = ($fatura_toplam_val > 0 || str_contains($status_str, 'fatura'));

  if ($is_invoiced && $fatura_toplam_val > 0) {
    // --- FATURA EDİLMİŞ ---
    $raw_cur = !empty($r['fatura_para_birimi']) ? $r['fatura_para_birimi'] : (!empty($r['order_currency']) ? $r['order_currency'] : 'TRY');
    $cur = normalize_currency($raw_cur);

    // Bu kalemin fatura toplamındaki payı (net oranına göre)
    $payda = $order_kalem_net_totals[$oid] ?? 1;
    if ($payda <= 0) $payda = 1;
    $oran = $net_line / $payda; // Bu kalemin ağırlığı

    // Fatura tutarı KDV dahil → KDV'siz ve KDV'si ayrıştır
    $fatura_kdv_dahil = $fatura_toplam_val * $oran;
    $fatura_net       = $fatura_kdv_dahil / (1 + $kdv_rate / 100);
    $fatura_kdv       = $fatura_kdv_dahil - $fatura_net;

    // Kur belirle
    $kur_usd_f = resolve_usd_try_kuru($r, true, $rates);
    $kur_eur_f = resolve_eur_try_kuru($r, true, $rates);

    // USD hesabı
    if ($cur === 'USD') {
      $stat_usd_net += $fatura_net;
      $stat_usd_kdv += $fatura_kdv;
    } elseif ($cur === 'TRY') {
      $stat_usd_net += ($kur_usd_f > 0) ? ($fatura_net / $kur_usd_f) : ($fatura_net / $rates['USD']);
      $stat_usd_kdv += ($kur_usd_f > 0) ? ($fatura_kdv / $kur_usd_f) : ($fatura_kdv / $rates['USD']);
    } elseif ($cur === 'EUR') {
      $usd_try = ($kur_usd_f > 0) ? $kur_usd_f : $rates['USD'];
      $eur_try = ($kur_eur_f > 0) ? $kur_eur_f : $rates['EUR'];
      $stat_usd_net += $fatura_net * ($eur_try / $usd_try);
      $stat_usd_kdv += $fatura_kdv * ($eur_try / $usd_try);
    }

    // TRY hesabı
    if ($cur === 'TRY') {
      $stat_try_net += $fatura_net;
      $stat_try_kdv += $fatura_kdv;
    } elseif ($cur === 'USD') {
      $stat_try_net += $fatura_net * (($kur_usd_f > 0) ? $kur_usd_f : $rates['USD']);
      $stat_try_kdv += $fatura_kdv * (($kur_usd_f > 0) ? $kur_usd_f : $rates['USD']);
    } elseif ($cur === 'EUR') {
      $kur_eur_f2 = ($kur_eur_f > 0) ? $kur_eur_f : $rates['EUR'];
      $stat_try_net += $fatura_net * $kur_eur_f2;
      $stat_try_kdv += $fatura_kdv * $kur_eur_f2;
    }

  } else {
    // --- FATURA EDİLMEMİŞ ---
    $raw_cur = !empty($r['kalem_para_birimi']) ? $r['kalem_para_birimi'] : (!empty($r['order_currency']) ? $r['order_currency'] : ($r['currency'] ?? 'TRY'));
    $cur = normalize_currency($raw_cur);

    // USD hesabı (günlük kur)
    if ($cur === 'USD') {
      $stat_usd_net += $net_line;
      $stat_usd_kdv += $kdv_line;
    } elseif ($cur === 'TRY') {
      $stat_usd_net += ($rates['USD'] > 0) ? ($net_line / $rates['USD']) : 0;
      $stat_usd_kdv += ($rates['USD'] > 0) ? ($kdv_line / $rates['USD']) : 0;
    } elseif ($cur === 'EUR') {
      $stat_usd_net += ($rates['USD'] > 0) ? ($net_line * $rates['EUR'] / $rates['USD']) : 0;
      $stat_usd_kdv += ($rates['USD'] > 0) ? ($kdv_line * $rates['EUR'] / $rates['USD']) : 0;
    }

    // TRY hesabı (günlük kur)
    if ($cur === 'TRY') {
      $stat_try_net += $net_line;
      $stat_try_kdv += $kdv_line;
    } elseif ($cur === 'USD') {
      $stat_try_net += $net_line * $rates['USD'];
      $stat_try_kdv += $kdv_line * $rates['USD'];
    } elseif ($cur === 'EUR') {
      $stat_try_net += $net_line * $rates['EUR'];
      $stat_try_kdv += $kdv_line * $rates['EUR'];
    }
  }
}

$stat_usd_total = $stat_usd_net + $stat_usd_kdv;
$stat_try_total = $stat_try_net + $stat_try_kdv;

// Eski $totalsByCurrency artık kullanılmıyor, view uyumluluğu için boş bırak
$totalsByCurrency = [];

// rates, usd_rate, eur_rate ve order_kalem_net_totals yukarıda tanımlandı.
// Grafik hesapları için KDV'li kalem toplamları (fatura oranı için payda):
$order_kalem_totals = [];
foreach ($rows as $r) {
  $oid = $r['order_id'];
  $raw = (float)($r['line_total'] ?? 0);
  $kdv = isset($r['kdv_orani']) ? (float)$r['kdv_orani'] : 20;
  $order_kalem_totals[$oid] = ($order_kalem_totals[$oid] ?? 0) + ($raw * (1 + ($kdv / 100)));
}

$agg_customer_usd = [];
$agg_project_usd  = [];
$agg_category_usd = [];
$cur_customer     = [];
$cur_project      = [];
$cur_category     = [];

$salesperson_orders      = [];
$processed_orders_for_sp = [];
$salesperson_details     = []; 
$sp_agg_proj             = [];
$sp_cur_proj             = []; 
$sp_agg_grp              = [];
$sp_cur_grp              = []; 
$sp_raw_rows             = []; // Tarih bazlı ham satırlar (JS filtresi için)

foreach ($rows as $r) {
  $oid = $r['order_id'];
  $raw_amt = (float)($r['line_total'] ?? 0);
  $kdv_orani = isset($r['kdv_orani']) ? (float)$r['kdv_orani'] : 20;
  $raw_amt_kdvli = $raw_amt * (1 + ($kdv_orani / 100)); 

  $status_str = mb_strtolower(trim((string)($r['order_status'] ?? '')), 'UTF-8');
  $fatura_toplam_muhur = (float)($r['fatura_toplam'] ?? 0);
  $is_invoiced = ($fatura_toplam_muhur > 0 || str_contains($status_str, 'fatura'));

  if ($is_invoiced && $fatura_toplam_muhur > 0) {
    $order_curr = !empty($r['order_currency']) ? $r['order_currency'] : (!empty($r['currency']) ? $r['currency'] : 'TL');
    $raw_cur = !empty($r['fatura_para_birimi']) ? $r['fatura_para_birimi'] : $order_curr;
    
    $order_kalem_total = $order_kalem_totals[$oid] ?? 1;
    if ($order_kalem_total <= 0) $order_kalem_total = 1;

    $oran = $raw_amt_kdvli / $order_kalem_total;
    $amt = $fatura_toplam_muhur * $oran; 
  } else {
    $order_curr = !empty($r['order_currency']) ? $r['order_currency'] : (!empty($r['currency']) ? $r['currency'] : 'TL');
    $kalem_curr = trim((string)($r['kalem_para_birimi'] ?? ''));
    if (empty($kalem_curr) || strtoupper($kalem_curr) === 'TL' || strtoupper($kalem_curr) === 'TRY') {
        $raw_cur = $order_curr;
    } else {
        $raw_cur = $kalem_curr;
    }
    
    $order_genel_toplam = (float)($r['order_genel_toplam'] ?? 0);
    if ($order_genel_toplam <= 0) $order_genel_toplam = (float)($r['fatura_toplam'] ?? 0);

    if ($order_genel_toplam > 0) {
      $order_kalem_total = $order_kalem_totals[$oid] ?? 1;
      if ($order_kalem_total <= 0) $order_kalem_total = 1;
      $oran = $raw_amt_kdvli / $order_kalem_total;
      $amt = $order_genel_toplam * $oran;
    } else {
      $amt = $raw_amt_kdvli;
    }
  }

  $cur = normalize_currency($raw_cur);

  $usd_multiplier = resolve_usd_rate($r, $cur, $is_invoiced, $rates);
  $amt_usd = $amt * $usd_multiplier; 

  $c = trim((string)($r['customer_name'] ?? 'Diğer'));
  if ($c === '') $c = 'Diğer';
  
  // YENİ: Önce ana projeye (linked_project_name) bak, o yoksa eski proje adına bak
  $linked_project = trim((string)($r['linked_project_name'] ?? ''));
  if ($linked_project !== '') {
      $p = $linked_project;
  } else {
      $p = trim((string)($r['project_name'] ?? 'Diğer'));
      if ($p === '') $p = 'Diğer';
  }

  $cat_name = trim((string)($r['category_name'] ?? ''));
  if ($cat_name !== '') {
    $best_group = $cat_name; 
  } else {
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

  $raw_sp = trim((string)($r['siparisi_alan'] ?? ''));
  $temsilciler_sabit = ['ALİ ALTUNAY', 'FATİH SERHAT ÇAÇIK', 'HASAN BÜYÜKOBA', 'HİKMET ŞİMŞEK', 'MUHAMMET YAZGAN', 'MURAT SEZER'];

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

  if (!isset($processed_orders_for_sp[$oid])) {
    $processed_orders_for_sp[$oid] = true;
    $salesperson_orders[$sp] = ($salesperson_orders[$sp] ?? 0) + 1;
  }

  $agg_customer_usd[$c] = ($agg_customer_usd[$c] ?? 0) + $amt_usd;
  $agg_project_usd[$p]  = ($agg_project_usd[$p]  ?? 0) + $amt_usd;
  $agg_category_usd[$g] = ($agg_category_usd[$g] ?? 0) + $amt_usd;

  $sp_agg_proj[$sp][$p] = ($sp_agg_proj[$sp][$p] ?? 0) + $amt_usd;
  if (!isset($sp_cur_proj[$sp][$p][$cur])) $sp_cur_proj[$sp][$p][$cur] = 0;
  $sp_cur_proj[$sp][$p][$cur] += $amt;

  $family = $best_group;
  $sp_agg_grp[$sp][$family] = ($sp_agg_grp[$sp][$family] ?? 0) + $amt_usd;
  if (!isset($sp_cur_grp[$sp][$family][$cur])) $sp_cur_grp[$sp][$family][$cur] = 0;
  $sp_cur_grp[$sp][$family][$cur] += $amt;

  // Tarih bazlı ham veri (JS tarafında anlık filtreleme için)
  $row_date_key = substr(trim((string)($r['order_date'] ?? '')), 0, 10); // YYYY-MM-DD
  $sp_raw_rows[$sp][] = [
    'd' => $row_date_key,
    'p' => $p,
    'g' => $family,
    'u' => $amt_usd,
    'c' => $cur,
    'v' => $amt,
  ];

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

arsort($agg_customer_usd);
arsort($agg_project_usd);
arsort($agg_category_usd);
arsort($salesperson_orders); 

function get_dominant_info(array $usdTotals, array $bucketMap): array
{
  $out = [];
  foreach ($usdTotals as $label => $usdVal) {
    $curMap = $bucketMap[$label] ?? [];
    if (empty($curMap)) {
      $out[$label] = ['cur' => 'USD', 'val' => $usdVal, 'usd_val' => $usdVal];
      continue;
    }
    arsort($curMap);
    $dom_cur = array_key_first($curMap);
    $out[$label] = [
      'cur'     => $dom_cur,         
      'val'     => $curMap[$dom_cur], 
      'usd_val' => $usdVal            
    ];
  }
  return $out;
}

// 6 sabit temsilcinin hepsini başlangıçta sıfır değeriyle ekle (satışı olmayanlar da görünsün)
$temsilciler_sabit_liste = ['ALİ ALTUNAY', 'FATİH SERHAT ÇAÇIK', 'HASAN BÜYÜKOBA', 'HİKMET ŞİMŞEK', 'MUHAMMET YAZGAN', 'MURAT SEZER'];
$sp_formatted = [];
foreach ($temsilciler_sabit_liste as $sabit_isim) {
  $lower_s = mb_strtolower(str_replace(['I', 'İ'], ['ı', 'i'], $sabit_isim), 'UTF-8');
  $title_s = mb_convert_case($lower_s, MB_CASE_TITLE, 'UTF-8');
  $sp_formatted[$title_s] = [
    'cur'     => 'Adet',
    'val'     => 0,
    'usd_val' => 0
  ];
  $salesperson_details[$title_s] = [
    'projects' => [],
    'groups'   => []
  ];
}

foreach ($salesperson_orders as $name => $count) {
  $sp_formatted[$name] = [
    'cur'     => 'Adet',
    'val'     => $count,
    'usd_val' => $count
  ];

  $salesperson_details[$name] = [
    'projects' => get_dominant_info($sp_agg_proj[$name] ?? [], $sp_cur_proj[$name] ?? []),
    'groups'   => get_dominant_info($sp_agg_grp[$name] ?? [], $sp_cur_grp[$name] ?? [])
  ];
}

$chart_payload = [
  'customer'            => get_dominant_info($agg_customer_usd, $cur_customer),
  'project'             => get_dominant_info($agg_project_usd,  $cur_project),
  'category'            => get_dominant_info($agg_category_usd, $cur_category),
  'salesperson'         => $sp_formatted,
  'salesperson_details' => $salesperson_details,
  'sp_raw_rows'         => $sp_raw_rows, // Tarih bazlı ham veri (JS filtresi için)
];

$salesperson_enhanced = [];

// 6 sabit temsilciyi sifir degerle baslatiyoruz (tarih filtresinde de boş gorunsunler)
$temsilciler_sabit_te = ['ALİ ALTUNAY', 'FATİH SERHAT ÇAÇIK', 'HASAN BÜYÜKOBA', 'HİKMET ŞİMŞEK', 'MUHAMMET YAZGAN', 'MURAT SEZER'];
foreach ($temsilciler_sabit_te as $_te_isim) {
  $_te_lower = mb_strtolower(str_replace(['I', 'İ'], ['ı', 'i'], $_te_isim), 'UTF-8');
  $_te_title = mb_convert_case($_te_lower, MB_CASE_TITLE, 'UTF-8');
  $salesperson_enhanced[$_te_title] = [
    'order_count'       => 0,
    'total_price_usd'   => 0,
    'product_groups'    => [],
    'currency'          => 'USD',
    'original_price'    => 0,
    'original_currency' => 'TRY',
    'processed_orders'  => []
  ];
}

foreach ($rows as $row) {
  $raw_sp = trim($row['siparisi_alan'] ?? '');
  $temsilciler_sabit = ['ALİ ALTUNAY', 'FATİH SERHAT ÇAÇIK', 'HASAN BÜYÜKOBA', 'HİKMET ŞİMŞEK', 'MUHAMMET YAZGAN', 'MURAT SEZER'];

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
      'order_count'       => 0,
      'total_price_usd'   => 0,   
      'product_groups'    => [],
      'currency'          => 'USD',
      'original_price'    => 0,
      'original_currency' => 'TRY',
      'processed_orders'  => []
    ];
  }

  $oid = $row['order_id'];

  if (!isset($salesperson_enhanced[$sp]['processed_orders'][$oid])) {
    $salesperson_enhanced[$sp]['processed_orders'][$oid] = true;
    $salesperson_enhanced[$sp]['order_count']++;
  }

  $raw_amt_kdvli = (float)($row['line_total'] ?? 0) * (1 + ((isset($row['kdv_orani']) ? (float)$row['kdv_orani'] : 20) / 100));

  $status_str2 = mb_strtolower(trim((string)($row['order_status'] ?? '')), 'UTF-8');
  $fatura_toplam_muhur2 = (float)($row['fatura_toplam'] ?? 0);
  $is_invoiced2 = ($fatura_toplam_muhur2 > 0 || str_contains($status_str2, 'fatura'));

  if ($is_invoiced2 && $fatura_toplam_muhur2 > 0) {
    $order_curr2 = !empty($row['order_currency']) ? $row['order_currency'] : (!empty($row['currency']) ? $row['currency'] : 'TL');
    $raw_cur2 = !empty($row['fatura_para_birimi']) ? $row['fatura_para_birimi'] : $order_curr2;
    
    $order_kalem_total2 = $order_kalem_totals[$oid] ?? 1;
    if ($order_kalem_total2 <= 0) $order_kalem_total2 = 1;

    $oran2 = $raw_amt_kdvli / $order_kalem_total2;
    $subtotal = $fatura_toplam_muhur2 * $oran2;
  } else {
    $order_curr2 = !empty($row['order_currency']) ? $row['order_currency'] : (!empty($row['currency']) ? $row['currency'] : 'TL');
    $kalem_curr2 = trim((string)($row['kalem_para_birimi'] ?? ''));
    if (empty($kalem_curr2) || strtoupper($kalem_curr2) === 'TL' || strtoupper($kalem_curr2) === 'TRY') {
        $raw_cur2 = $order_curr2;
    } else {
        $raw_cur2 = $kalem_curr2;
    }
    
    $order_genel_toplam2 = (float)($row['order_genel_toplam'] ?? 0);
    if ($order_genel_toplam2 <= 0) $order_genel_toplam2 = (float)($row['fatura_toplam'] ?? 0);

    if ($order_genel_toplam2 > 0) {
      $order_kalem_total2 = $order_kalem_totals[$oid] ?? 1;
      if ($order_kalem_total2 <= 0) $order_kalem_total2 = 1;
      $oran2 = $raw_amt_kdvli / $order_kalem_total2;
      $subtotal = $order_genel_toplam2 * $oran2;
    } else {
      $subtotal = $raw_amt_kdvli;
    }
  }

  $cur = normalize_currency($raw_cur2);

  if ($salesperson_enhanced[$sp]['original_currency'] === $cur || $salesperson_enhanced[$sp]['original_price'] == 0) {
    $salesperson_enhanced[$sp]['original_currency'] = $cur;
    $salesperson_enhanced[$sp]['original_price'] += $subtotal;
  }

  $usd_multiplier2 = resolve_usd_rate($row, $cur, $is_invoiced2, $rates);
  $salesperson_enhanced[$sp]['total_price_usd'] += ($subtotal * $usd_multiplier2);

  $cat_name2 = trim((string)($row['category_name'] ?? ''));
  if ($cat_name2 !== '') {
    $group = $cat_name2;
  } else {
    $raw_sku2  = trim($row['sku'] ?? '');
    $raw_name2 = trim($row['product_name'] ?? '');
    $group     = 'DİĞER';
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

foreach ($salesperson_enhanced as $sp => &$data) {
  $data['product_group_count'] = count($data['product_groups']);
  unset($data['product_groups']);
  unset($data['processed_orders']);
}
unset($data);

$chart_payload['salesperson_enhanced'] = $salesperson_enhanced;

require_once __DIR__ . '/../app/Views/reports/sales_reps_view.php';