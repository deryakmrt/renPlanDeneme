<?php
//merhaba
require_once __DIR__ . '/../includes/helpers.php';
include('../includes/header.php');

// PDO nesnesini helpers.php üzerinden alıyoruz
$pdo = pdo();

function sa_h($v)
{
  return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
$TABLE = 'satinalma_orders';

if (!$pdo) {
  http_response_code(500);
  echo "<pre>DB yok.</pre>";
  include('../includes/footer.php');
  exit;
}

// ---- helper: column exists
function col_exists(PDO $pdo, $table, $col)
{
  try {
    $q = $pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c");
    $q->execute([':t' => $table, ':c' => $col]);
    return (bool)$q->fetchColumn();
  } catch (Throwable $e) {
    return false;
  }
}

$HAS_URUN   = col_exists($pdo, $TABLE, 'urun');
$HAS_TALEP_T = col_exists($pdo, $TABLE, 'talep_tarihi');
$HAS_DURUM   = col_exists($pdo, $TABLE, 'durum');

// ---- Durum renkleri fonksiyonu
function getStatusBadge($durum)
{
  if (!$durum) $durum = 'Beklemede';

  $renkler = [
    'Beklemede' => 'warning',
    'Onaylandı' => 'success',
    'Sipariş Edildi' => 'info',
    'Tamamlandı' => 'primary',
    'Reddedildi' => 'danger',
    'İptal' => 'secondary'
  ];
  $renk = $renkler[$durum] ?? 'light';
  return '<span class="badge bg-' . $renk . '">' . htmlspecialchars($durum) . '</span>';
}

// ---- Özet istatistikleri hesapla
function getTalepIstatistikleri($pdo, $TABLE)
{
  $stats = [
    'toplam' => 0,
    'beklemede' => 0,
    'onaylandi' => 0,
    'siparis_edildi' => 0,
    'tamamlandi' => 0
  ];

  try {
    $q = $pdo->query("SELECT COUNT(*) FROM `$TABLE`");
    $stats['toplam'] = (int)$q->fetchColumn();

    $q = $pdo->query("SELECT durum, COUNT(*) as sayi FROM `$TABLE` GROUP BY durum");
    $durumlar = $q->fetchAll(PDO::FETCH_ASSOC);

    foreach ($durumlar as $d) {
      $durumName = trim($d['durum']);
      switch (mb_strtolower($durumName)) {
        case 'beklemede':
          $stats['beklemede'] = (int)$d['sayi'];
          break;
        case 'onaylandı':
          $stats['onaylandi'] = (int)$d['sayi'];
          break;
        case 'sipariş edildi':
          $stats['siparis_edildi'] = (int)$d['sayi'];
          break;
        case 'tamamlandı':
          $stats['tamamlandi'] = (int)$d['sayi'];
          break;
      }
    }
  } catch (Exception $e) {
    echo "<!-- İstatistik hatası: " . $e->getMessage() . " -->";
  }

  return $stats;
}

$istatistikler = getTalepIstatistikleri($pdo, $TABLE);

// ---- paging + search
$perPage = max(5, min(100, (int)($_GET['per'] ?? 20)));
$page    = max(1, (int)($_GET['page'] ?? 1));
$q       = trim((string)($_GET['q'] ?? ''));
$ds      = trim((string)($_GET['ds'] ?? ''));
$de      = trim((string)($_GET['de'] ?? ''));
$durum   = trim((string)($_GET['durum'] ?? ''));
$offset  = ($page - 1) * $perPage;

// where + params
$where  = "1=1";
$params = [];
if ($q !== '') {
  $where .= " AND (t.order_code LIKE :kw OR t.proje_ismi LIKE :kw OR o.order_code LIKE :kw OR o.proje_adi LIKE :kw)";
  $params[':kw'] = "%$q%";
}
if ($HAS_TALEP_T) {
  if ($ds !== '' && preg_match('~^\d{4}-\d{2}-\d{2}$~', $ds)) {
    $where .= " AND t.talep_tarihi >= :ds";
    $params[':ds'] = $ds;
  }
  if ($de !== '' && preg_match('~^\d{4}-\d{2}-\d{2}$~', $de)) {
    $where .= " AND t.talep_tarihi <= :de";
    $params[':de'] = $de;
  }
}
if ($HAS_DURUM && $durum !== '' && strtolower($durum) !== 'hepsi') {
  $where .= " AND t.durum = :durum";
  $params[':durum'] = $durum;
}

// SQL sorgularını yap
try {
  $ct = $pdo->prepare("SELECT COUNT(*) FROM `$TABLE` t LEFT JOIN orders o ON o.proje_adi = t.proje_ismi WHERE $where");
  foreach ($params as $k => $v) {
    $ct->bindValue($k, $v);
  }
  $ct->execute();
  $total = (int)$ct->fetchColumn();

  // dynamic select fields
  $fields = "t.id,t.order_code,CONCAT(COALESCE(o.order_code, t.order_code), ' - ', COALESCE(o.proje_adi, t.proje_ismi)) AS proje_ismi,t.talep_tarihi,t.miktar,t.birim,t.durum";
  if ($HAS_URUN) {
    $fields = "t.id,t.order_code,CONCAT(COALESCE(o.order_code, t.order_code), ' - ', COALESCE(o.proje_adi, t.proje_ismi)) AS proje_ismi,t.talep_tarihi,t.urun,t.miktar,t.birim,t.durum";
  }

  $sql = "SELECT $fields 
          FROM `$TABLE` t 
          LEFT JOIN (
              SELECT o.proje_adi, o.order_code 
              FROM orders o 
              GROUP BY o.proje_adi
          ) o ON o.proje_adi = t.proje_ismi 
          WHERE $where 
          ORDER BY t.id DESC 
          LIMIT :lim OFFSET :off";

  $st = $pdo->prepare($sql);
  $st->bindValue(':lim', $perPage, PDO::PARAM_INT);
  $st->bindValue(':off', $offset, PDO::PARAM_INT);
  foreach ($params as $k => $v) {
    $st->bindValue($k, $v);
  }
  $st->execute();
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

  $pages = max(1, (int)ceil($total / $perPage));

  echo "<!-- DEBUG: Total: $total, Rows: " . count($rows) . ", Page: $page/$pages -->";
} catch (Exception $e) {
  // Hata olsa bile değişkenleri initialize et
  error_log("SQL Hatası (talepler.php): " . $e->getMessage()); // Log'a yaz
  $rows = [];
  $total = 0;
  $pages = 1;
  // Kullanıcıya göstermek için hata mesajı
  $sqlError = $e->getMessage();
}
?>

<style>
  /* Tüm sayfa için görünürlük garantisi */
  body,
  body * {
    visibility: visible !important;
  }

  .container {
    display: block !important;
    max-width: 1400px;
    margin: 20px auto;
    padding: 20px;
  }

  .card {
    background: white;
    border-radius: 12px;
    padding: 24px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    margin-bottom: 20px;
  }

  .filters {
    display: flex;
    flex-wrap: wrap;
    gap: .5rem;
    align-items: end;
    margin-bottom: .75rem;
  }

  .filters .form-group {
    display: flex;
    flex-direction: column;
  }

  .filters label {
    font-size: 12px;
    font-weight: 600;
    margin-bottom: 4px;
    color: #374151;
  }

  .filters input[type="text"],
  .filters input[type="date"],
  .filters select {
    padding: .35rem .5rem;
    border: 1px solid #DADDE1;
    border-radius: 10px;
    font: inherit;
  }

  .filters .btn {
    padding: .45rem .75rem;
    text-decoration: none;
    border: 1px solid #DADDE1;
    border-radius: 10px;
    background: white;
    cursor: pointer;
  }

  .filters .btn-primary {
    background: #3b82f6;
    color: white;
    border-color: #3b82f6;
  }

  /* Özet kartları */
  .summary-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
  }

  .summary-card {
    background: white;
    border-radius: 10px;
    padding: 1.5rem;
    text-align: center;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    border-left: 4px solid #007bff;
  }

  .summary-card.warning {
    border-left-color: #ffc107;
  }

  .summary-card.success {
    border-left-color: #28a745;
  }

  .summary-card.info {
    border-left-color: #bc52e2ff;
  }

  .summary-card.primary {
    border-left-color: #00ddffff;
  }

  .summary-card h3 {
    margin: 0;
    font-size: 2rem;
    font-weight: bold;
  }

  .summary-card p {
    margin: 0.5rem 0 0 0;
    color: #666;
  }

  /* Aktif filtreler */
  .active-filters {
    background: #f8f9fa;
    padding: 0.75rem;
    border-radius: 5px;
    margin-bottom: 1rem;
    border-left: 4px solid #007bff;
  }

  .filter-tag {
    display: inline-flex;
    align-items: center;
    background: #e9ecef;
    padding: 0.25rem 0.5rem;
    border-radius: 15px;
    margin-right: 0.5rem;
    margin-bottom: 0.25rem;
  }

  .filter-tag .remove {
    margin-left: 0.5rem;
    cursor: pointer;
    color: #6c757d;
  }

  .filter-tag .remove:hover {
    color: #dc3545;
  }

  /* Çoklu seçim */
  .bulk-actions {
    background: #fff3cd;
    padding: 0.75rem;
    border-radius: 5px;
    margin-bottom: 1rem;
    display: none;
    border-left: 4px solid #ffc107;
  }

  .select-all-checkbox {
    margin-right: 1rem;
  }

  /* Tablo stilleri */
  .table-responsive {
    overflow-x: auto;
    margin-top: 20px;
  }

  .table {
    width: 100%;
    border-collapse: collapse;
    background: white;
  }

  .table th,
  .table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #e5e7eb;
  }

  .table th {
    background: #f9fafb;
    font-weight: 600;
    color: #374151;
  }

  .table th:first-child,
  .table td:first-child {
    width: 40px;
  }

  .table tbody tr:hover {
    background: #f9fafb;
  }

  .badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
  }

  .badge.bg-warning {
    background: #fef3c7;
    color: #92400e;
  }

  .badge.bg-success {
    background: #d1fae5;
    color: #065f46;
  }

  .badge.bg-info {
    background: #ddd6fe;
    color: #5b21b6;
  }

  .badge.bg-primary {
    background: #cffafe;
    color: #155e75;
  }

  .badge.bg-danger {
    background: #fee2e2;
    color: #991b1b;
  }

  .badge.bg-secondary {
    background: #e5e7eb;
    color: #374151;
  }

  .btn-sm {
    padding: 8px 16px;
    font-size: 14px;
    border-radius: 8px;
    text-decoration: none;
    display: inline-block;
    margin-right: 4px;
    font-weight: 500;
    transition: all 0.2s ease;
    border: 1px solid transparent;
  }
  .btn-sm.btn-primary {
  background: #3b82f6;
  color: white;
  border-color: #3b82f6;
}

.btn-sm.btn-primary:hover {
  background: #2563eb;
  border-color: #2563eb;
  transform: translateY(-1px);
  box-shadow: 0 4px 8px rgba(59, 130, 246, 0.3);
}

.btn-sm.btn-danger {
  background: white;
  color: #ef4444;
  border: 1px solid #e5e7eb;
}

.btn-sm.btn-danger:hover {
  background: #fef2f2;
  border-color: #fecaca;
  color: #dc2626;
  transform: translateY(-1px);
  box-shadow: 0 4px 8px rgba(239, 68, 68, 0.1);
}

/* Buton grubu için */
.table td:last-child {
  white-space: nowrap;
}

  .btn-primary {
    background: #ffffffff;
    color: white;
    border: 1px solid #000000ff;
  }

  .btn-danger {
    background: #e2c6c6ff;
    color: white;
    border: 1px solid #ef4444;
  }

  .ta-center {
    text-align: center;
  }

  .pager {
    display: flex;
    gap: 8px;
    justify-content: center;
    margin-top: 20px;
  }

  .d-flex {
    display: flex;
  }

  .align-items-center {
    align-items: center;
  }

  .gap-1 {
    gap: 8px;
  }

  .justify-center {
    justify-content: center;
  }
</style>

<div class="container">
  <div class="card">
    <h2>📋 Talepler</h2>

    <!-- Özet Kartları -->
    <div class="summary-cards">
      <div class="summary-card">
        <h3><?php echo $istatistikler['toplam']; ?></h3>
        <p>Toplam Talep</p>
      </div>
      <div class="summary-card warning">
        <h3><?php echo $istatistikler['beklemede']; ?></h3>
        <p>Beklemede</p>
      </div>
      <div class="summary-card success">
        <h3><?php echo $istatistikler['onaylandi']; ?></h3>
        <p>Onaylandı</p>
      </div>
      <div class="summary-card info">
        <h3><?php echo $istatistikler['siparis_edildi']; ?></h3>
        <p>Sipariş Edildi</p>
      </div>
      <div class="summary-card primary">
        <h3><?php echo $istatistikler['tamamlandi']; ?></h3>
        <p>Tamamlandı</p>
      </div>
    </div>

    <!-- Aktif Filtreler -->
    <div class="active-filters" id="activeFilters">
      <strong>Aktif Filtreler:</strong>
      <?php if ($q !== ''): ?>
        <span class="filter-tag">
          Arama: "<?php echo sa_h($q); ?>"
          <span class="remove" onclick="removeFilter('q')">×</span>
        </span>
      <?php endif; ?>
      <?php if ($ds !== ''): ?>
        <span class="filter-tag">
          Başlangıç: <?php echo sa_h($ds); ?>
          <span class="remove" onclick="removeFilter('ds')">×</span>
        </span>
      <?php endif; ?>
      <?php if ($de !== ''): ?>
        <span class="filter-tag">
          Bitiş: <?php echo sa_h($de); ?>
          <span class="remove" onclick="removeFilter('de')">×</span>
        </span>
      <?php endif; ?>
      <?php if ($durum !== '' && $durum !== 'hepsi'): ?>
        <span class="filter-tag">
          Durum: <?php echo sa_h($durum); ?>
          <span class="remove" onclick="removeFilter('durum')">×</span>
        </span>
      <?php endif; ?>
      <?php if ($q === '' && $ds === '' && $de === '' && ($durum === '' || $durum === 'hepsi')): ?>
        <span class="text-muted">Aktif filtre yok</span>
      <?php endif; ?>
    </div>

    <!-- Çoklu İşlemler -->
    <div class="bulk-actions" id="bulkActions">
      <div class="d-flex align-items-center">
        <strong id="selectedCount">0 talep seçildi</strong>
        <div style="margin-left: 1rem;">
          <select id="bulkStatus" style="padding: 6px 12px; border-radius: 6px;">
            <option value="">Durum seçin</option>
            <option value="Beklemede">Beklemede</option>
            <option value="Onaylandı">Onaylandı</option>
            <option value="Sipariş Edildi">Sipariş Edildi</option>
            <option value="Tamamlandı">Tamamlandı</option>
          </select>
          <button class="btn btn-sm btn-primary" style="margin-left: 8px;" onclick="updateBulkStatus()">Uygula</button>
          <button class="btn btn-sm" style="margin-left: 8px;" onclick="clearSelection()">Seçimi Temizle</button>
        </div>
      </div>
    </div>

    <form method="get" class="filters">
      <div class="form-group">
        <label for="search-q">🔎 Arama</label>
        <input type="text" id="search-q" name="q" value="<?php echo sa_h($q); ?>" placeholder="Kod, proje">
      </div>
      <div class="form-group">
        <label for="date-start">Başlangıç Tarihi</label>
        <input type="date" id="date-start" name="ds" value="<?php echo sa_h($ds); ?>">
      </div>
      <div class="form-group">
        <label for="date-end">Bitiş Tarihi</label>
        <input type="date" id="date-end" name="de" value="<?php echo sa_h($de); ?>">
      </div>
      <div class="form-group">
        <label for="durum-select">Durum</label>
        <select name="durum" id="durum-select">
          <?php foreach (['hepsi' => 'Hepsi', 'Beklemede' => 'Beklemede', 'Onaylandı' => 'Onaylandı', 'Sipariş Edildi' => 'Sipariş Edildi', 'Tamamlandı' => 'Tamamlandı'] as $v => $lbl): ?>
            <option value="<?php echo sa_h($v); ?>" <?php echo ($durum === $v ? 'selected' : ''); ?>><?php echo sa_h($lbl); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label for="per-page">Sonuç</label>
        <select name="per" id="per-page">
          <?php foreach ([10, 20, 30, 50, 100] as $opt): ?>
            <option value="<?php echo $opt; ?>" <?php echo ((int)$perPage === $opt ? 'selected' : ''); ?>><?php echo $opt; ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>&nbsp;</label>
        <button class="btn btn-primary" type="submit">Uygula</button>
      </div>
      <div class="form-group">
        <label>&nbsp;</label>
        <a class="btn" href="<?php echo site_url('/satinalma-sys/talepler.php'); ?>">Temizle</a>
      </div>
    </form>

    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th>
              <input type="checkbox" id="selectAll" class="select-all-checkbox" onchange="toggleSelectAll(this)">
            </th>
            <th>🔖 Kod</th>
            <th>🗂️ Proje İsmi</th>
            <th>📅 Talep Tarihi</th>
            <th>📊 Durum</th>
            <th>🔧 İşlem</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rows || count($rows) === 0): ?>
            <tr>
              <td colspan="6" class="ta-center">Kayıt yok.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($rows as $r): ?>
              <tr>
                <td>
                  <input type="checkbox" class="talep-checkbox" value="<?php echo (int)$r['id']; ?>" onchange="updateSelection()">
                </td>
                <td><?php echo sa_h($r['order_code']); ?></td>
                <td><?php echo sa_h($r['proje_ismi']); ?></td>
                <td><?php echo sa_h($r['talep_tarihi'] ? date('d-m-Y', strtotime($r['talep_tarihi'])) : '-'); ?></td>
                <td><?php echo getStatusBadge($r['durum']); ?></td>
                <td>
                  <a class="btn-sm btn-primary" href="<?php echo site_url('satinalma-sys/talep_duzenle.php?id=' . (int)$r['id']); ?>">Düzenle</a>
                  <a class="btn-sm btn-danger" href="<?php echo site_url('satinalma-sys/talep_sil.php?id=' . (int)$r['id']); ?>" onclick="return confirm('Bu talebi silmek istediğinize emin misiniz?');">Sil</a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <?php if ($pages > 1): ?>
      <div class="pager">
        <?php for ($i = 1; $i <= $pages; $i++):
          $link = '?page=' . $i . ($q !== '' ? '&q=' . urlencode($q) : '') . ($ds !== '' ? '&ds=' . urlencode($ds) : '') . ($de !== '' ? '&de=' . urlencode($de) : '') . ($durum !== '' && $durum !== 'hepsi' ? '&durum=' . urlencode($durum) : '');
        ?>
          <a class="btn <?php echo $i == (int)$page ? 'btn-primary' : ''; ?>" href="<?php echo sa_h($link); ?>"><?php echo $i; ?></a>
        <?php endfor; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<script>
  // Aktif filtre kaldırma
  function removeFilter(param) {
    const url = new URL(window.location.href);
    url.searchParams.delete(param);
    window.location.href = url.toString();
  }

  // Çoklu seçim fonksiyonları
  function toggleSelectAll(checkbox) {
    const checkboxes = document.querySelectorAll('.talep-checkbox');
    checkboxes.forEach(cb => cb.checked = checkbox.checked);
    updateSelection();
  }

  function updateSelection() {
    const selected = document.querySelectorAll('.talep-checkbox:checked');
    const selectedCount = selected.length;
    const bulkActions = document.getElementById('bulkActions');
    const selectedCountEl = document.getElementById('selectedCount');

    selectedCountEl.textContent = selectedCount + ' talep seçildi';
    bulkActions.style.display = selectedCount > 0 ? 'block' : 'none';

    const totalCheckboxes = document.querySelectorAll('.talep-checkbox').length;
    const selectAll = document.getElementById('selectAll');
    selectAll.checked = selectedCount === totalCheckboxes && totalCheckboxes > 0;
    selectAll.indeterminate = selectedCount > 0 && selectedCount < totalCheckboxes;
  }

  function clearSelection() {
    document.querySelectorAll('.talep-checkbox').forEach(cb => cb.checked = false);
    document.getElementById('selectAll').checked = false;
    document.getElementById('selectAll').indeterminate = false;
    updateSelection();
  }

  function updateBulkStatus() {
    const selected = document.querySelectorAll('.talep-checkbox:checked');
    const newStatus = document.getElementById('bulkStatus').value;

    if (selected.length === 0) {
      alert('Lütfen en az bir talep seçin.');
      return;
    }

    if (!newStatus) {
      alert('Lütfen bir durum seçin.');
      return;
    }

    if (confirm(selected.length + ' talebin durumunu "' + newStatus + '" olarak güncellemek istediğinize emin misiniz?')) {
      const talepIds = Array.from(selected).map(cb => cb.value);
      alert('Çoklu güncelleme özelliği geliştirme aşamasındadır. Seçilen talepler: ' + talepIds.join(', '));
    }
  }

  document.addEventListener('DOMContentLoaded', function() {
    updateSelection();
    console.log('Sayfa yüklendi, tablo elementi:', document.querySelector('.table'));
    console.log('Satır sayısı:', document.querySelectorAll('.table tbody tr').length);
  });
</script>

<?php include('../includes/footer.php'); ?>
<?php
echo "<!-- SAYFA SONU: Script buraya kadar geldi -->";
?>