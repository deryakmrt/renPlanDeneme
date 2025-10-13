<?php
// mailing/templates.php – ÜSTF (order_pdf_uretim.php) ile birebir alanlar + TARİH FİX

if (!function_exists('rp_subject')) {
function rp_subject(string $type, array $p): string {
  if ($type!=='order') return 'Bildirim';
  $sub = 'ÜRETİM SİPARİŞ FÖYÜ';
  if (!empty($p['order_code'])) { $sub .= ': ' . $p['order_code']; }
  if (!empty($p['revizyon_no'])) { $sub .= ' - ' . $p['revizyon_no']; }
  return $sub;
}}

if (!function_exists('rp_email_html')) {
function rp_email_html(string $type, array $p, string $view_url=''): string {
  if ($type!=='order') return '<p>Bildirim</p>';
  $e = fn($s)=>htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');

  $orderNo = trim(($p['order_code'] ?? '').( ($p['revizyon_no'] ?? '')!=='' ? ' - '.$p['revizyon_no'] : ''));
  $cari = trim(($p['customer_name'] ?? '')."\n".($p['email'] ?? ''));
  $sevk = trim($p['shipping_address'] ?? '');

  ob_start(); ?>
<!doctype html>
<html><head><meta charset="utf-8">
<meta name="x-apple-disable-message-reformatting">
<title><?= $e(rp_subject('order',$p)) ?></title>
</head>
<body style="margin:0;padding:0;background:#f6f8fb;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;background:#f6f8fb;">
    <tr><td align="center" style="padding:24px 12px">
      <table role="presentation" width="720" cellpadding="0" cellspacing="0" style="background:#fff;border:1px solid #e6eaf2;border-radius:12px;overflow:hidden">
        <!-- Başlık -->
        <tr><td style="padding:16px 20px; background:#EC7323; color:#fff; font:bold 18px/1.2 -apple-system,Segoe UI,Roboto,Arial">
          ÜRETİM SİPARİŞ TAKİP FORMU
        </td></tr>

        <!-- Sipariş No -->
        <tr><td style="padding:12px 20px; text-align:center; font:600 14px/1.3 -apple-system,Segoe UI,Roboto,Arial">
          Sipariş No: <?= $e($orderNo) ?>
        </td></tr>

        <!-- Cari Bilgileri & Sevk Adresi -->
        <tr><td style="padding:0 20px 10px 20px">
          <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse">
            <tr>
              <td style="width:50%; vertical-align:top; padding-right:6px">
                <div style="border:1px solid #111; padding:10px">
                  <div style="font-weight:700; margin:0 0 6px 0">Cari Bilgileri:</div>
                  <div style="white-space:pre-line"><?= $e($cari) ?></div>
                </div>
              </td>
              <td style="width:50%; vertical-align:top; padding-left:6px">
                <div style="border:1px solid #111; padding:10px">
                  <div style="font-weight:700; margin:0 0 6px 0">Sevk Adresi:</div>
                  <div style="white-space:pre-line"><?= $e($sevk) ?></div>
                </div>
              </td>
            </tr>
          </table>
        </td></tr>

        <!-- 5x5 Bilgi Tablosu -->
        <tr><td style="padding:0 20px 12px 20px">
          <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #111">
            <tr>
              <td style="border-right:1px solid #111; padding:0; width:50%">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                  <?php
                  $L = [
                    ['Siparişi Veren', $p['siparis_veren'] ?? ''],
                    ['Siparişi Alan',  $p['siparisi_alan'] ?? ''],
                    ['Siparişi Giren', $p['siparisi_giren'] ?? ''],
                    ['Sipariş Tarihi', $p['siparis_tarihi'] ?? ''],
                    ['Fatura Para Birimi', $p['fatura_para_birimi'] ?? ''],
                  ];
                  foreach($L as $row): ?>
                  <tr>
                    <td style="border-bottom:1px solid #111; padding:8px 8px; width:42%; font-weight:600"><?= $e($row[0]) ?></td>
                    <td style="border-bottom:1px solid #111; padding:8px 8px"><?= $e($row[1]) ?></td>
                  </tr>
                  <?php endforeach; ?>
                </table>
              </td>
              <td style="padding:0">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                  <?php
                  $R = [
                    ['Proje Adı', $p['proje_adi'] ?? ''],
                    ['Revizyon No', $p['revizyon_no'] ?? ''],
                    ['Nakliye Türü', $p['nakliye_turu'] ?? ''],
                    ['Ödeme Koşulu', $p['odeme_kosulu'] ?? ''],
                    ['Ödeme Para Birimi', $p['odeme_para_birimi'] ?? ''],
                  ];
                  foreach($R as $row): ?>
                  <tr>
                    <td style="border-bottom:1px solid #111; border-left:1px solid #111; padding:8px 8px; width:42%; font-weight:600"><?= $e($row[0]) ?></td>
                    <td style="border-bottom:1px solid #111; padding:8px 8px"><?= $e($row[1]) ?></td>
                  </tr>
                  <?php endforeach; ?>
                </table>
              </td>
            </tr>
          </table>
        </td></tr>

        <!-- Kalemler Tablosu -->
        <tr><td style="padding:0 20px 16px 20px">
          <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse; border:1px solid #111">
            <thead>
              <tr style="background:#f3f4f6">
                <th align="left"  style="border-right:1px solid #111; padding:8px 6px; width:6%">S.No</th>
                <th align="center" style="border-right:1px solid #111; padding:8px 6px; width:12%">Görsel</th>
                <th align="left"  style="border-right:1px solid #111; padding:8px 6px; width:14%">Ürün Kod</th>
                <th align="left"  style="border-right:1px solid #111; padding:8px 6px;">Ürün Açıklama</th>
                <th align="left"  style="border-right:1px solid #111; padding:8px 6px; width:16%">Kullanım Alanı</th>
                <th align="right" style="border-right:1px solid #111; padding:8px 6px; width:10%">Miktar</th>
                <th align="left"  style="border-right:1px solid #111; padding:8px 6px; width:8%">Birim</th>
                <th align="left"  style="padding:8px 6px; width:12%">Termin Tarihi</th>
              </tr>
            </thead>
            <tbody>
              <?php $i=1; foreach(($p['items']??[]) as $it): ?>
                <tr>
                  <td style="border-top:1px solid #111; border-right:1px solid #111; padding:8px 6px"><?= $i++ ?></td>
                  <td style="border-top:1px solid #111; border-right:1px solid #111; padding:6px; text-align:center">
                    <?php if (!empty($it['gorsel'])): ?>
                      <img src="<?= $e($it['gorsel']) ?>" alt="Ürün" style="max-width:52px; max-height:52px; display:inline-block">
                    <?php else: ?>
                      <span style="color:#9ca3af">—</span>
                    <?php endif; ?>
                  </td>
                  <td style="border-top:1px solid #111; border-right:1px solid #111; padding:8px 6px"><?= $e($it['urun_kod'] ?? '') ?></td>
                  <td style="border-top:1px solid #111; border-right:1px solid #111; padding:8px 6px">
                    <div style="font-weight:600"><?= $e($it['urun_adi'] ?? '') ?></div>
                    <div style="color:#374151; font-size:12px; margin-top:2px"><?= $e($it['urun_aciklama'] ?? '') ?></div>
                  </td>
                  <td style="border-top:1px solid #111; border-right:1px solid #111; padding:8px 6px"><?= $e($it['kullanim_alani'] ?? '') ?></td>
                  <td align="right" style="border-top:1px solid #111; border-right:1px solid #111; padding:8px 6px"><?= number_format((float)($it['miktar'] ?? 0), 2, ',', '.') ?></td>
                  <td style="border-top:1px solid #111; border-right:1px solid #111; padding:8px 6px"><?= $e($it['birim'] ?? '') ?></td>
                  <td style="border-top:1px solid #111; padding:8px 6px"><?= $e($it['termin_tarihi'] ?? '') ?></td>
                </tr>
              <?php endforeach; ?>
              <?php if (empty($p['items'])): ?>
                <tr><td colspan="8" style="border-top:1px solid #111; padding:12px 8px; color:#6b7280">Kalem bulunamadı.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </td></tr>

        <?php if($view_url): ?>
        <tr><td style="padding:0 20px 20px 20px">
          <a href="<?= $e($view_url) ?>" style="display:inline-block; background:#EC7323;color:#fff; text-decoration:none; padding:10px 16px; border-radius:8px; font:600 14px/1 -apple-system,Segoe UI,Roboto,Arial">Siparişi Görüntüle</a>
        </td></tr>
        <?php endif; ?>

      </table>
    </td></tr>
  </table>
</body></html>
<?php
  return (string)ob_get_clean();
}} // rp_email_html

if (!function_exists('rp_email_text')) {
function rp_email_text(string $type, array $p, string $view_url=''): string {
  if ($type!=='order') return (string)($p['message'] ?? 'Bilgilendirme');
  $L = [
    'Sipariş No' => trim(($p['order_code'] ?? '').( ($p['revizyon_no'] ?? '')!=='' ? ' - '.$p['revizyon_no'] : '')),
    'Cari Bilgileri' => trim(($p['customer_id'] ?? '')."\n".($p['email'] ?? '')),
    'Sevk Adresi' => (string)($p['shipping_address'] ?? ''),
    'Siparişi Veren' => (string)($p['siparis_veren'] ?? ''),
    'Siparişi Alan'  => (string)($p['siparisi_alan'] ?? ''),
    'Siparişi Giren' => (string)($p['siparisi_giren'] ?? ''),
    'Sipariş Tarihi' => (string)($p['siparis_tarihi'] ?? ''),
    'Fatura Para Birimi' => (string)($p['fatura_para_birimi'] ?? ''),
    'Proje Adı' => (string)($p['proje_adi'] ?? ''),
    'Revizyon No' => (string)($p['revizyon_no'] ?? ''),
    'Nakliye Türü' => (string)($p['nakliye_turu'] ?? ''),
    'Ödeme Koşulu' => (string)($p['odeme_kosulu'] ?? ''),
    'Ödeme Para Birimi' => (string)($p['odeme_para_birimi'] ?? ''),
  ];
  $lines=[];
  foreach($L as $k=>$v){ if($v!==''){ $lines[] = $k . ': ' . $v; } }
  // Items (sade)
  if (!empty($p['items'])) {
    $lines[]=''; $lines[]='Kalemler:';
    $i=1;
    foreach ($p['items'] as $it) {
      $lines[] = sprintf('%d) %s | %s | %s %s | %s',
        $i++,
        (string)($it['urun_kod'] ?? ''),
        (string)($it['urun_adi'] ?? ''),
        number_format((float)($it['miktar'] ?? 0), 2, ',', '.'),
        (string)($it['birim'] ?? ''),
        (string)($it['termin_tarihi'] ?? '')
      );
    }
  }
  if ($view_url) { $lines[]=''; $lines[]='Görüntüle: '.$view_url; }
  return implode("\n", $lines);
}}