<?php
require_once __DIR__ . '/includes/helpers.php';
require_login();

// Dompdf autoloader (çoklu fallback)
$__autoload_paths = [
  __DIR__ . '/vendor/dompdf/dompdf/autoload.inc.php',
  __DIR__ . '/vendor/autoload.php',
  __DIR__ . '/dompdf/autoload.inc.php',
  __DIR__ . '/includes/dompdf/autoload.inc.php',
  __DIR__ . '/vendor/dompdf/autoload.inc.php',
  __DIR__ . '/vendor/dompdf/dompdf/vendor/autoload.php'
];
$__loaded = false;
foreach ($__autoload_paths as $__p) { if (file_exists($__p)) { require_once $__p; $__loaded = true; break; } }
if (!$__loaded) { die('Dompdf autoloader bulunamadı'); }

use Dompdf\Dompdf;
use Dompdf\Options;

mb_internal_encoding('UTF-8');

$id = (int)($_GET['id'] ?? 0);
if (!$id) die('Geçersiz ID');

$db = pdo();
$st = $db->prepare("SELECT o.*, c.name AS customer_name, c.billing_address, c.shipping_address, c.email, c.phone, o.revizyon_no
                    FROM orders o LEFT JOIN customers c ON c.id=o.customer_id WHERE o.id=?");
$st->execute([$id]);
$o = $st->fetch();
if (!$o) die('Sipariş bulunamadı');

$it = $db->prepare("SELECT oi.*, p.sku, p.image AS image FROM order_items oi LEFT JOIN products p ON p.id=oi.product_id WHERE oi.order_id=? ORDER BY oi.id ASC");
$it->execute([$id]);
$items = $it->fetchAll();

// Logo: önce yerel, yoksa uzak
$CUSTOM_LOGO = file_exists(__DIR__ . '/assets/renled-logo.png') ? (__DIR__ . '/assets/renled-logo.png') : 'https://renplan.ditetra.com/assets/renled-logo.png';
$logo_src = $CUSTOM_LOGO;

$fmt = function($n) { return number_format((float)$n, 2, ',', '.'); };

// --- Para birimi sembolü haritalama ---
$fpb = strtoupper(trim((string)($o['fatura_para_birimi'] ?? $o['currency'] ?? '')));
switch ($fpb) {
  case 'TL': case 'TRY': $currencySymbol = '₺'; break;
  case 'USD': $currencySymbol = '$'; break;
  case 'EUR': case 'EURO': $currencySymbol = '€'; break;
  default: $currencySymbol = $fpb ?: '₺';
}

function fmt_date($val, $with_time=false) {
  // Normalize and guard against empty/invalid dates
  if (!isset($val)) return '-';
  $val = trim((string)$val);
  if ($val === '' || $val === '0000-00-00' || $val === '0000-00-00 00:00:00' || $val === '1970-01-01' || $val === '1970-01-01 00:00:00' || $val === '30-11--0001') {
    return '-';
  }
  $ts = @strtotime($val);
  if (!$ts || $ts <= 0) return '-';
  $year = (int)date('Y', $ts);
  if ($year < 1900 || $year > 2100) return '-';
  return $with_time ? date('d-m-Y H:i:s', $ts) : date('d-m-Y', $ts);
}

// --- Tarihleri önceden biçimle ---
$olusturulma = date('d-m-Y H:i:s');
$siparis_tarihi_fmt = fmt_date($o['siparis_tarihi'] ?? '');
$termin_tarihi_fmt  = fmt_date($o['termin_tarihi'] ?? '');

ob_start();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <style>
    * { box-sizing: border-box; }
    body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 11px; line-height:1.25; color:#000; margin:0; }
    @page { margin: 12mm 10mm; }

    table { border-collapse: collapse; border-spacing:0; }

    /* Üst başlık */
    table.head { width:100%; margin-bottom: 4mm; }
    table.head td { vertical-align: middle; }
    .logo img { max-height: 18mm; display:block; }
    .titles .t1 { font-size: 16pt; font-weight: 700; margin:0; }
    .titles .t2 { font-size: 12pt; font-weight: 700; margin:2px 0 0 0; }
    .orderno { text-align:center; font-weight:700; margin: 2mm 0 3mm 0; }

    /* İki kolonlu üst kutular */
    table.twocol { width:100%; }
    table.twocol td { width:50%; vertical-align: top; }
    table.twocol td.left { padding-right: 2mm; }
    table.twocol td.right { padding-left: 2mm; }

    .card { border: 0.3mm solid #000; border-radius: 0; padding: 3mm; }
    .section-title { font-weight: 700; margin: 0 0 2mm 0; }

    /* 5x5 bilgi tabloları */
    table.kv { width:100%; }
    table.kv td { border: 0.3mm solid #000; padding: 1mm 2mm; vertical-align: top; }
    table.kv td.label { width: 40mm; font-weight: 700; }

    /* Ürün tablosu */
    table.items { width:100%;
    /* Column widths (reordered): S.No, Görsel, Ürün, Kullanım Alanı, Miktar, Birim, Termin, Fiyat, Toplam */
    table.items col:nth-child(1) { width: 8mm; }   /* S.No */
    table.items col:nth-child(2) { width: 18mm; }  /* Görsel */
    table.items col:nth-child(3) { width: 70mm; }  /* Ürün Açıklama */
    table.items col:nth-child(4) { width: 22mm; }  /* Kullanım Alanı */
    table.items col:nth-child(5) { width: 16mm; }  /* Miktar */
    table.items col:nth-child(6) { width: 16mm; }  /* Birim */
    table.items col:nth-child(7) { width: 16mm; }  /* Termin Tarihi */
    table.items col:nth-child(8) { width: 14mm; }  /* Fiyat */
    table.items col:nth-child(9) { width: 14mm; }  /* Toplam */

    /* Column widths (9 cols, total ≈ 190mm) */
    /* S.No */
    /* Görsel */
    /* Ürün Açıklama */
    /* Miktar */
    /* Birim */
    /* Kullanım Alanı */
    /* Termin Tarihi */
    /* Fiyat */
    /* Toplam */
 margin-top: 4mm; }
    table.items th, table.items td { border: 0.3mm solid #000; padding: 1mm; vertical-align: top; word-wrap: break-word; overflow: hidden; }
    table.items th { text-align: center; font-weight: 700; background: #f2f4f7; }
    td.num { white-space: nowrap; text-align: right; }
    td.center { text-align: center; }
    .small { font-size: 10px; }

    table.totals { margin-top: 3mm; width: 60mm; margin-left: auto; }
    table.totals td { padding: 1mm 2mm; }
    table.totals .label { text-align: right; font-weight: 700; }
    table.totals .value { text-align: right; }
  </style>
</head>
<body>

<!-- Başlık -->
<table class="head">
  <tr>
    <td class="logo" style="width: 40mm;">
      <?php if (!empty($logo_src)): ?>
        <img src="<?= h($logo_src) ?>" alt="Logo">
      <?php endif; ?>
    </td>
    <td class="titles">
      <div class="t1">STF (YURTİÇİ)</div>
      <div class="t2">SİPARİŞ TAKİP VE TEYİT FORMU (YURTİÇİ)</div>
    </td>
  </tr>
</table>

<!-- Sipariş No satırı -->
<div class="orderno">Sipariş No: <?= h(($o['order_code'] ?? '') . (isset($o['revizyon_no']) ? ' - ' . $o['revizyon_no'] : '')) ?></div>

<!-- Üstte iki kutu: Cari Bilgileri & Sevk Adresi -->
<table class="twocol">
  <tr>
    <td class="left">
      <div class="card">
        <div class="section-title">Cari Bilgileri:</div>
        <div><?= h($o['customer_name'] ?? '') ?></div>
        <div><?= nl2br(h($o['billing_address'] ?? '')) ?></div>
        <div><?= h($o['email'] ?? '') ?><?= (!empty($o['phone']) ? ' • ' . h($o['phone']) : '') ?></div>
      </div>
    </td>
    <td class="right">
      <div class="card">
        <div class="section-title">Sevk Adresi:</div>
        <div><?= h($o['customer_name'] ?? '') ?></div>
<div><?= nl2br(h($o['shipping_address'] ?? '')) ?></div>
<div><?= h($o['email'] ?? '') ?><?= (!empty($o['phone']) ? ' • ' . h($o['phone']) : '') ?></div>
      </div>
    </td>
  </tr>
</table>

<!-- Alt iki tablo: 5 satır solda, 5 satır sağda -->
<table class="twocol" style="margin-top:3mm;">
  <tr>
    <td class="left">
      <table class="kv">
        <tr><td class="label">Siparişi Veren</td><td><?= h($o['siparis_veren'] ?? '') ?></td></tr>
        <tr><td class="label">Siparişi Alan</td><td><?= h($o['siparisi_alan'] ?? '') ?></td></tr>
        <tr><td class="label">Siparişi Giren</td><td><?= h($o['siparisi_giren'] ?? '') ?></td></tr>
        <tr><td class="label">Sipariş Tarihi</td><td><?= $siparis_tarihi_fmt ?></td></tr>
        <tr><td class="label">Fatura Para Birimi</td><td><?= h($o['fatura_para_birimi'] ?? $o['currency'] ?? '') ?></td></tr>
      </table>
    </td>
    <td class="right">
      <table class="kv">
        <tr><td class="label">Proje Adı</td><td><?= h($o['proje_adi'] ?? '') ?></td></tr>
        <tr><td class="label">Revizyon No</td><td><?= h($o['revizyon_no'] ?? '') ?></td></tr>
        <tr><td class="label">Nakliye Türü</td><td><?= h($o['nakliye_turu'] ?? '') ?></td></tr>
        <tr><td class="label">Ödeme Koşulu</td><td><?= h($o['odeme_kosulu'] ?? '') ?></td></tr>
        <tr><td class="label">Ödeme Para Birimi</td><td><?= h($o['odeme_para_birimi'] ?? '') ?></td></tr>
      </table>
    </td>
  </tr>
  <tr>
    <td class="left" style="padding-top:2mm;">
      <table class="kv">
        
        
      </table>
    </td>
    <td></td>
  </tr>
</table>

<!-- Ürün Tablosu -->
<table class="items">
  <colgroup>
    <col style="width:8.0mm">
    <col style="width:18.0mm">
    <col style="width:56.7mm">
    <col style="width:37.7mm">
    <col style="width:22.0mm">
    <col style="width:16.0mm">
    <col style="width:16.0mm">
    <col style="width:16.0mm">
    <col style="width:14.0mm">
    <col style="width:14.0mm">
  </colgroup>
  <thead>
    <tr>
      <th>S.No</th>
      <th>Görsel</th>
      <th>Ürün Kod</th>
      <th>Ürün Açıklama</th>
      <th>Kullanım Alanı</th>
      <th>Miktar</th>
      <th>Birim</th>
      <th>Termin Tarihi</th>
      <th>Fiyat</th>
      <th>Toplam</th>
    </tr>
  </thead>
  <tbody>
  <?php $i=1; $ara=0.0; foreach($items as $it): $satirTop = (float)($it['price'] ?? 0) * (float)($it['qty'] ?? 0); $ara += $satirTop; ?>
    <tr>
      <td class="center" style="width:8mm; min-width:8mm; max-width:8mm; padding-left:0.6mm; padding-right:0.6mm;"><?= $i++ ?></td>
      <td>
        <?php if (!empty($it['image'])): ?>
          <img src="<?= h($it['image']) ?>" style="max-width:18mm;max-height:18mm;display:block">
        <?php endif; ?>
      </td>
      <td><?= h($it['sku'] ?? '') ?></td>
      <td>
        <div><strong><?= h($it['name'] ?? '') ?></strong></div>
        <?php if (!empty($it['urun_ozeti'])): ?>
          <div class="small"><?= nl2br(h($it['urun_ozeti'])) ?></div>
        <?php endif; ?>
      </td>
      <td><?= h($it['kullanim_alani'] ?? '') ?></td><td class="num"><?= isset($it['qty']) ? number_format((float)$it['qty'],2,',','.') : '' ?></td><td class="center"><?= h($it['unit'] ?? '') ?></td><td class="center"><?= fmt_date($it['termin_tarihi'] ?? ($o['termin_tarihi'] ?? '')) ?></td>
      <td class="num"><?= $fmt($it['price'] ?? 0) ?> <?= h($currencySymbol) ?></td>
      <td class="num"><?= $fmt($satirTop) ?> <?= h($currencySymbol) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<?php $kdv_orani = 0.20; $kdv = $ara * $kdv_orani; $genel = $ara + $kdv; ?>
<table class="totals">
  <tr><td class="label">Ara Toplam</td><td class="value"><?= $fmt($ara) ?> <?= h($currencySymbol) ?></td></tr>
  <tr><td class="label">KDV %20</td><td class="value"><?= $fmt($kdv) ?> <?= h($currencySymbol) ?></td></tr>
  <tr><td class="label">Genel Toplam</td><td class="value"><?= $fmt($genel) ?> <?= h($currencySymbol) ?></td></tr>
</table>

</body>
</html>
<?php
$html = ob_get_clean();

$options = new Options();
$options->set('defaultFont', 'DejaVu Sans');
$options->set('isRemoteEnabled', true);
$options->set('isHtml5ParserEnabled', true);
$options->setChroot(__DIR__);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream('siparis_' . ($o['order_code'] ?? 'pdf') . '.pdf', ['Attachment' => false]);
