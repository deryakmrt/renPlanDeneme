<?php
// satinalma-sys/satinalma_rapor.php – FIX: Doğru tablo yapısına göre güncellendi
// satinalma_order_items: talep_id var, order_id yok
// Kolon adı: urun (urun_adi değil)

declare(strict_types=1);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/helpers.php';

$pdo = pdo();
include('../includes/header.php');

// --- Filters ---
$from      = trim($_GET['from']      ?? '');
$to        = trim($_GET['to']        ?? '');
$talep_id  = trim($_GET['talep_id']  ?? '');
$durum     = trim($_GET['durum']     ?? '');

// Build WHERE
$where = [];
$args  = [];

if ($from !== '') {
  $where[] = "oi.created_at >= :from";
  $args[':from'] = $from . ' 00:00:00';
}
if ($to !== '') {
  $where[] = "oi.created_at <= :to";
  $args[':to']   = $to . ' 23:59:59';
}
if ($talep_id !== '') {
  $where[] = "oi.talep_id = :talep_id";
  $args[':talep_id'] = $talep_id;
}
if ($durum !== '') {
  $where[] = "oi.durum = :durum";
  $args[':durum'] = $durum;
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// ---- Summary totals ----
$sqlTotal = "
  SELECT 
    COUNT(oi.id) AS kalem_sayisi,
    COALESCE(SUM(COALESCE(oi.miktar,0)),0) AS toplam_adet,
    COALESCE(SUM(COALESCE(oi.miktar,0) * COALESCE(oi.birim_fiyat,0)),0) AS toplam_tutar
  FROM satinalma_order_items oi
  INNER JOIN satinalma_orders so ON so.id = oi.talep_id
  $whereSql
";
$st = $pdo->prepare($sqlTotal);
$st->execute($args);
$sum = $st->fetch(PDO::FETCH_ASSOC) ?: ['kalem_sayisi' => 0, 'toplam_adet' => 0, 'toplam_tutar' => 0];

// ---- Detailed rows ----
$sqlRows = "
  SELECT 
    oi.id AS item_id,
    oi.talep_id,
    oi.created_at AS siparis_tarihi,
    oi.urun,
    oi.birim,
    oi.durum,
    COALESCE(oi.miktar,0) AS adet,
    COALESCE(oi.birim_fiyat,0) AS birim_fiyat,
    COALESCE(oi.miktar * oi.birim_fiyat,0) AS tutar,
    oi.son_onay,
    s.name AS tedarikci,
    sq.delivery_days,
    sq.payment_term,
    sq.currency,
    sq.shipping_type
  FROM satinalma_order_items oi
  INNER JOIN satinalma_orders so ON so.id = oi.talep_id
  LEFT JOIN suppliers s ON s.id = oi.selected_supplier_id
  LEFT JOIN satinalma_quotes sq ON sq.id = oi.selected_quote_id
  $whereSql
  ORDER BY oi.created_at DESC, oi.id ASC
";
$st2 = $pdo->prepare($sqlRows);
$st2->execute($args);
$rows = $st2->fetchAll(PDO::FETCH_ASSOC);

// ---- Distinct talep_id'ler (filtre için) ----
$taleps = $pdo->query("
  SELECT DISTINCT talep_id 
  FROM satinalma_order_items 
  WHERE talep_id IS NOT NULL 
  ORDER BY talep_id DESC
")->fetchAll(PDO::FETCH_COLUMN);

// Durum listesi - veritabanındaki değerlerle tam eşleşmeli
$durumlar = [
  'Beklemede' => 'Beklemede',
  'Teklif Bekleniyor' => 'Teklif Bekleniyor',
  'Teklif Alındı' => 'Teklif Alındı',
  'Sipariş Verildi' => 'Sipariş Verildi',
  'Teslim Edildi' => 'Teslim Edildi',
  'İptal' => 'İptal'
];
?>

<div class="container">
  <div class="card p-3">
    <h2>📋 Satın Alma Raporu</h2>

    <form method="get">
      <div style="display:flex; gap:12px; flex-wrap:wrap">
        <div style="flex:1">
          <label>📅 Başlangıç Tarihi</label>
          <input type="date" name="from" value="<?= h($from) ?>">
        </div>
        <div style="flex:1">
          <label>📅 Bitiş Tarihi</label>
          <input type="date" name="to" value="<?= h($to) ?>">
        </div>
        <div style="flex:1">
          <label>🔢 Talep ID</label>
          <select name="talep_id">
            <option value="">(Tümü)</option>
            <?php foreach ($taleps as $tid): ?>
              <option value="<?= h($tid) ?>" <?= $talep_id == $tid ? 'selected' : ''; ?>>
                Talep #<?= h($tid) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div style="flex:1">
          <label>📊 Durum</label>
          <select name="durum">
            <option value="">(Tümü)</option>
            <?php foreach ($durumlar as $key => $label): ?>
              <option value="<?= h($key) ?>" <?= $durum === $key ? 'selected' : ''; ?>>
                <?= h($label) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div style="flex:0 0 120px; display:flex; align-items:flex-end">
          <button class="btn primary" type="submit">🔍 Ara</button>
        </div>
      </div>
    </form>

    <!-- Özet İstatistikler -->
    <div style="display:flex; gap:12px; margin-top:20px">
      <div class="stat">
        <div class="stat-label">Toplam Kalem</div>
        <div class="stat-value"><?= h(number_format((float)$sum['kalem_sayisi'], 0, ',', '.')) ?></div>
      </div>
      <div class="stat">
        <div class="stat-label">Toplam Adet</div>
        <div class="stat-value"><?= h(number_format((float)$sum['toplam_adet'], 2, ',', '.')) ?></div>
      </div>
      <div class="stat">
        <div class="stat-label">Toplam Tutar (₺)</div>
        <div class="stat-value"><?= h(number_format((float)$sum['toplam_tutar'], 2, ',', '.')) ?> ₺</div>
      </div>
    </div>

    <!-- Detaylı Tablo -->
    <div class="table-responsive" style="margin-top:20px">
      <table class="table">
        <thead>
          <tr>
            <th>📅 Tarih</th>
            <th>🔢 Talep ID</th>
            <th>🔢 Item ID</th>
            <th>📦 Ürün</th>
            <th>🏢 Tedarikçi</th>
            <th>📊 Durum</th>
            <th class="ta-right">🔢 Miktar</th>
            <th>📏 Birim</th>
            <th class="ta-right">💰 Birim Fiyat</th>
            <th class="ta-right">💰 Tutar</th>
            <th>🚚 Teslimat</th>
            <th>📦 Gönderim</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
            <tr>
              <td colspan="11" class="ta-center">Kayıt bulunamadı</td>
            </tr>
            <?php else:
            foreach ($rows as $r):
              // Durum badge class
              $statusClass = match ($r['durum']) {
                'onaylandi', 'teslim_edildi' => 'badge-success',
                'siparis_verildi', 'teklif_alindi' => 'badge-info',
                'beklemede' => 'badge-warning',
                'iptal' => 'badge-danger',
                default => 'badge-secondary'
              };
            ?>
              <tr>
                <td><?= h(date('d.m.Y H:i', strtotime($r['siparis_tarihi']))) ?></td>
                <td><strong>#<?= h($r['talep_id']) ?></strong></td>
                <td><?= h($r['item_id']) ?></td>
                <td><?= h($r['urun']) ?></td>
                <td><?= h($r['tedarikci'] ?: '-') ?></td>
                <td>
                  <span class="badge <?= $statusClass ?>">
                    <?= h(ucfirst(str_replace('_', ' ', $r['durum']))) ?>
                  </span>
                </td>
                <td class="ta-right"><?= h(number_format((float)$r['adet'], 2, ',', '.')) ?></td>
                <td><?= h($r['birim'] ?: '-') ?></td>
                <td class="ta-right">
                  <?php
                  $currency = $r['currency'] ?? 'TRY';
                  $symbol = $currency === 'USD' ? '$' : ($currency === 'EUR' ? '€' : '₺');
                  echo h(number_format((float)$r['birim_fiyat'], 2, ',', '.')) . ' ' . $symbol;
                  ?>
                </td>
                <td class="ta-right">
                  <strong>
                    <?php
                    echo h(number_format((float)$r['tutar'], 2, ',', '.')) . ' ' . $symbol;
                    ?>
                  </strong>
                </td>
                <td>
                  <?php if ($r['delivery_days']): ?>
                    <?= h($r['delivery_days']) ?> gün
                  <?php else: ?>
                    -
                  <?php endif; ?>
                </td>
                <td>
                  <?php if (!empty($r['shipping_type'])): ?>
                    <span class="badge badge-info"><?= h($r['shipping_type']) ?></span>
                  <?php else: ?>
                    -
                  <?php endif; ?>
                </td>
              </tr>
          <?php endforeach;
          endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Export Butonları -->
    <div style="margin-top:20px; display:flex; gap:10px">
      <button class="btn secondary" onclick="exportToExcel()">📊 Excel'e Aktar</button>
      <button class="btn secondary" onclick="window.print()">🖨️ Yazdır</button>
    </div>
  </div>
</div>

<style>
  .container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
  }

  .card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
  }

  .p-3 {
    padding: 24px;
  }

  h2 {
    margin: 0 0 20px 0;
    color: #2c3e50;
  }

  label {
    display: block;
    margin-bottom: 6px;
    font-weight: 500;
    font-size: 14px;
    color: #495057;
  }

  input,
  select {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ced4da;
    border-radius: 6px;
    font-size: 14px;
  }

  .stat {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 12px;
    padding: 16px 20px;
    min-width: 180px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    color: white;
  }

  .stat-label {
    font-size: 13px;
    margin-bottom: 6px;
    font-weight: 500;
    opacity: 0.9;
  }

  .stat-value {
    font-size: 24px;
    font-weight: 700;
  }

  .table-responsive {
    overflow-x: auto;
    margin-top: 20px;
    border-radius: 8px;
    border: 1px solid #e9ecef;
  }

  .table {
    width: 100%;
    border-collapse: collapse;
    background: white;
  }

  .table th {
    background: #f8f9fa;
    font-weight: 600;
    color: #495057;
    padding: 12px;
    border-bottom: 2px solid #dee2e6;
    white-space: nowrap;
    position: sticky;
    top: 0;
    z-index: 10;
  }

  .table td {
    padding: 10px 12px;
    border-bottom: 1px solid #eef2f7;
  }

  .table tbody tr:hover {
    background: #f8f9fa;
  }

  .ta-right {
    text-align: right;
  }

  .ta-center {
    text-align: center;
  }

  .badge {
    display: inline-block;
    padding: 4px 10px;
    font-size: 11px;
    font-weight: 600;
    border-radius: 12px;
    white-space: nowrap;
    text-transform: capitalize;
  }

  .badge-success {
    background: #d4edda;
    color: #155724;
  }

  .badge-info {
    background: #d1ecf1;
    color: #0c5460;
  }

  .badge-warning {
    background: #fff3cd;
    color: #856404;
  }

  .badge-danger {
    background: #f8d7da;
    color: #721c24;
  }

  .badge-secondary {
    background: #e2e3e5;
    color: #383d41;
  }

  .btn {
    padding: 10px 20px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.2s;
    font-size: 14px;
  }

  .btn.primary {
    background: #007bff;
    color: white;
  }

  .btn.primary:hover {
    background: #0056b3;
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0, 123, 255, 0.3);
  }

  .btn.secondary {
    background: #6c757d;
    color: white;
  }

  .btn.secondary:hover {
    background: #545b62;
  }

  @media print {

    .btn,
    form {
      display: none !important;
    }

    .stat {
      background: white !important;
      color: black !important;
      border: 1px solid #ddd;
    }
  }
</style>

<script>
  function exportToExcel() {
    const table = document.querySelector('.table').cloneNode(true);

    // Remove action columns if any
    const headers = table.querySelectorAll('th');
    const rows = table.querySelectorAll('tbody tr');

    let html = '<table border="1">' + table.innerHTML + '</table>';

    const blob = new Blob(['\ufeff', html], {
      type: 'application/vnd.ms-excel'
    });

    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = 'satinalma_rapor_<?= date("Y-m-d_His") ?>.xls';
    link.click();
    window.URL.revokeObjectURL(url);
  }
</script>

<?php include('../includes/footer.php'); ?>