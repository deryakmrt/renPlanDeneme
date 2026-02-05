<?php

require_once __DIR__ . '/includes/helpers.php';
require_login();

$db = pdo();
$pc = $db->query('SELECT COUNT(*) FROM products')->fetchColumn();
$cc = $db->query('SELECT COUNT(*) FROM customers')->fetchColumn();
// SİPARİŞ SAYISI (Yetkiye Göre Filtreli)
$sqlOrders = "SELECT COUNT(*) FROM orders";
if (!in_array(current_user()['role']??'', ['admin','sistem_yoneticisi'])) {
    // Admin değilse taslakları sayma
    $sqlOrders .= " WHERE status != 'taslak_gizli'";
}
$oc = $db->query($sqlOrders)->fetchColumn();

include __DIR__ . '/includes/header.php';
?>

<!-- Inline minimal styles to guarantee the dashboard layout -->
<style>
/* tiles */
.tile-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px}
@media (max-width:1100px){.tile-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media (max-width:720px){.tile-grid{grid-template-columns:1fr}}
.tile{position:relative;border-radius:22px;padding:18px;background:linear-gradient(135deg,#e0e7ff 0%,#f5f3ff 100%);
  box-shadow:0 10px 24px rgba(17,24,39,.12),inset 0 1px 0 rgba(255,255,255,.6);border:1px solid rgba(255,255,255,.5);overflow:hidden;
  transition:transform .18s ease,box-shadow .18s ease;cursor:pointer}
.tile:hover{transform:translateY(-4px) scale(1.01);box-shadow:0 16px 36px rgba(17,24,39,.18)}
.tile .icon{width:42px;height:42px;border-radius:12px;display:grid;place-items:center;background:rgba(255,255,255,.6);
  border:1px solid rgba(255,255,255,.8);box-shadow:inset 0 1px 0 rgba(255,255,255,.8)}
.tile .title{margin:10px 0 0;font-weight:700;font-size:16px;color:#0f172a}
.tile .value{font-size:48px;font-weight:800;line-height:1;margin-top:6px;letter-spacing:-.02em;color:#0b1220}
.tile.t-blue{background:linear-gradient(135deg,#dbeafe 0%,#e9d5ff 100%)}
.tile.t-teal{background:linear-gradient(135deg,#ccfbf1 0%,#bfdbfe 100%)}
.tile.t-yellow{background:linear-gradient(135deg,#fef3c7 0%,#fde68a 100%)}
.tile.t-green{background:linear-gradient(135deg,#dcfce7 0%,#bbf7d0 100%)}
.tile.t-orange{background:linear-gradient(135deg,#ffedd5 0%,#fed7aa 100%)}
.tile.t-purple{background:linear-gradient(135deg,#ede9fe 0%,#ddd6fe 100%)}
.tile a.stretch{position:absolute;inset:0;z-index:1}

/* quick actions */
.quick-actions{background:rgba(255,255,255,.6);border:1px solid rgba(255,255,255,.7);border-radius:18px;padding:14px;
  box-shadow:0 6px 18px rgba(17,24,39,.12);margin-top:18px}

/* widgets */
.widgets{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px;margin-top:18px}
@media (max-width:1100px){.widgets{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media (max-width:720px){.widgets{grid-template-columns:1fr}}
.widget{background:rgba(255,255,255,.65);border:1px solid rgba(255,255,255,.75);border-radius:18px;box-shadow:0 8px 20px rgba(17,24,39,.12);
  padding:16px;overflow:hidden}
.widget h4{margin:0 0 12px;font-size:15px;font-weight:700;color:#0f172a}
.list{margin:0;padding:0;list-style:none}
.list li{display:flex;align-items:center;justify-content:space-between;padding:10px 0;border-bottom:1px dashed rgba(15,23,42,.12)}
.list li:last-child{border-bottom:none}
.badge{font-size:12px;padding:4px 8px;border-radius:999px;background:#eef2ff;color:#312e81}
.badge.danger{background:#fee2e2;color:#991b1b}
.badge.warn{background:#ffedd5;color:#9a3412}
.badge.ok{background:#dcfce7;color:#065f46}
.widget .see-all{display:inline-block;margin-top:8px;font-size:12px;text-decoration:underline}

/* calendar container spacing when embedded */
.mt{margin-top:16px}
.list li a.row-link{display:inline-block;max-width:calc(100% - 72px);text-decoration:none;color:inherit}
</style>

<div class="tile-grid">
  <div class="tile t-yellow">
    <a href="orders.php" class="stretch" aria-label="Siparişler"></a>
    <div class="icon">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M6 3h12v18l-3-2-3 2-3-2-3 2V3Z" stroke="currentColor" stroke-width="1.6"/><path d="M9 7h6M9 11h6M9 15h6" stroke="currentColor" stroke-width="1.6"/></svg>
    </div>
    <div class="title">Sipariş</div>
    <div class="value"><?= (int)$oc ?></div>
  </div>  
    <div class="tile t-blue">
    <a href="products.php" class="stretch" aria-label="Ürünler"></a>
    <div class="icon">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M3 7.5 12 3l9 4.5-9 4.5L3 7.5Z" stroke="currentColor" stroke-width="1.6"/><path d="M12 21V12M21 7.5V16.5L12 21 3 16.5V7.5" stroke="currentColor" stroke-width="1.6"/></svg>
    </div>
    <div class="title">Ürün</div>
    <div class="value"><?= (int)$pc ?></div>
  </div>
  <div class="tile t-teal">
    <a href="customers.php" class="stretch" aria-label="Müşteriler"></a>
    <div class="icon">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="7.5" r="3.5" stroke="currentColor" stroke-width="1.6"/><path d="M4 20c0-3.314 3.582-6 8-6s8 2.686 8 6" stroke="currentColor" stroke-width="1.6"/></svg>
    </div>
    <div class="title">Müşteri</div>
    <div class="value"><?= (int)$cc ?></div>
  </div>
  <div class="tile t-green">
    <a href="#" class="stretch" aria-label="Faturalar"></a>
    <div class="icon">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M8 3h8a2 2 0 0 1 2 2v13l-3-2-3 2-3-2-3 2V5a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.6"/><path d="M9 7h6M9 11h6" stroke="currentColor" stroke-width="1.6"/></svg>
    </div>
    <div class="title">Faturalar</div>
    <div class="value">—</div>
  </div>
  <div class="tile t-orange">
    <a href="#" class="stretch" aria-label="Stok"></a>
    <div class="icon">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M4 4h16v12H4z" stroke="currentColor" stroke-width="1.6"/><path d="M7 8h10M7 12h10" stroke="currentColor" stroke-width="1.6"/></svg>
    </div>
    <div class="title">Stok</div>
    <div class="value">—</div>
  </div>
  <div class="tile t-purple">
    <a href="#" class="stretch" aria-label="Raporlar"></a>
    <div class="icon">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M5 20V8m4 12V4m4 16v-8m4 8v-5" stroke="currentColor" stroke-width="1.6"/></svg>
    </div>
    <div class="title">Raporlar</div>
    <div class="value">—</div>
  </div>
</div>

<div class="quick-actions mt">
  <h3>Hızlı İşlemler</h3>
  <div class="row mt">
    <a href="products.php?a=new" class="btn primary">Yeni Ürün</a>
    <a href="customers.php?a=new" class="btn">Yeni Müşteri</a>
    <a href="order_add.php" class="btn">Yeni Sipariş</a>
    <a href="satinalma-sys/talep_olustur.php" class="btn">Yeni Talep</a>
    <a href="orders.php" class="btn">Tüm Siparişler</a>
  </div>
</div>

<?php
// Widgets data
// Last orders with customer names
$lastOrders = $db->query('
  SELECT o.id, o.customer_id, c.name AS customer_name
  FROM orders o
  LEFT JOIN customers c ON c.id = o.customer_id
  ORDER BY o.id DESC
  LIMIT 10
')->fetchAll(PDO::FETCH_ASSOC);


// Dynamic tasks: Son değiştirilen siparişlerin notlarını göster
$tasks = [];
try {
  // En son değiştirilen siparişleri audit_log'dan bul
  $rows = [];
  try {
    // Önce audit_log'dan en son değiştirilen siparişleri bul
    $st = $db->query("
      SELECT o.id, o.order_code, o.customer_id, o.notes AS note, al.ts AS last_modified,
             c.name AS customer_name
      FROM orders o
      LEFT JOIN customers c ON c.id = o.customer_id
      LEFT JOIN (
        SELECT object_id, MAX(ts) AS ts
        FROM audit_log
        WHERE object_type = 'orders'
        GROUP BY object_id
      ) al ON al.object_id = o.id
      WHERE o.notes IS NOT NULL AND TRIM(o.notes) <> ''
      ORDER BY COALESCE(al.ts, o.created_at) DESC
      LIMIT 10
    ");
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
  } catch (Throwable $e1) {
    // Fallback: audit_log yoksa sadece orders.notes'a bak
    $st2 = $db->query("
      SELECT o.id, o.order_code, o.customer_id, o.notes AS note, o.created_at AS last_modified,
             c.name AS customer_name
      FROM orders o
      LEFT JOIN customers c ON c.id = o.customer_id
      WHERE o.notes IS NOT NULL AND TRIM(o.notes) <> ''
      ORDER BY o.id DESC
      LIMIT 10
    ");
    $rows = $st2->fetchAll(PDO::FETCH_ASSOC);
  }

  if (!$rows) {
    $tasks = [['title' => 'Henüz not bulunamadı', 'badge' => '', 'url' => '#']];
  } else {
    foreach ($rows as $r) {
      // orders.notes formatı: "kullanici | tarih saat: not\r\nkullanici2 | tarih2: not2"
      $fullNotes = (string)($r['note'] ?? '');
      
      // En son notu bul (satırları ayır ve en sonuncuyu al)
      $noteLines = preg_split('/[\r\n]+/', $fullNotes);
      $noteLines = array_filter(array_map('trim', $noteLines)); // Boşları temizle
      $lastNoteLine = end($noteLines); // Son satır
      
      if (!$lastNoteLine) {
        continue; // Boşsa atla
      }
      
      // Format: "derya | 05.02.2026 12:47: deneme"
      $userName = '';
      $noteText = $lastNoteLine;
      $noteDate = '';
      
      // Parse et
      if (preg_match('/^([^|]+)\s*\|\s*([^:]+):\s*(.+)$/u', $lastNoteLine, $matches)) {
        $userName = trim($matches[1]);
        $noteDate = trim($matches[2]);
        $noteText = trim($matches[3]);
      }
      
      // Özet oluştur
      $noteText = preg_replace('/\s+/', ' ', $noteText);
      if (function_exists('mb_strimwidth')) {
        $summary = mb_strimwidth($noteText, 0, 90, '…', 'UTF-8');
      } else {
        $summary = substr($noteText, 0, 90) . (strlen($noteText) > 90 ? '…' : '');
      }

      $prefixParts = [];
      if (!empty($r['order_code'])) $prefixParts[] = '#' . $r['order_code'];
      else if (!empty($r['id'])) $prefixParts[] = 'Sipariş #' . (int)$r['id'];
      
      // Kullanıcı adı ekle
      if ($userName) {
        $prefixParts[] = $userName;
      }
      
      $prefixParts[] = !empty($r['customer_name']) ? $r['customer_name'] : ('Müşteri #' . (int)($r['customer_id'] ?? 0));
      $prefix = implode(' · ', array_filter($prefixParts));

      // badge - en son değiştirilme zamanından hesapla
      $badge = '';
      
      // Önce noteDate'i parse etmeyi dene (05.02.2026 12:47 formatı)
      if ($noteDate && preg_match('/(\d{2})\.(\d{2})\.(\d{4})\s+(\d{2}):(\d{2})/', $noteDate, $dm)) {
        $ts = mktime((int)$dm[4], (int)$dm[5], 0, (int)$dm[2], (int)$dm[1], (int)$dm[3]);
      } else {
        // Fallback: last_modified kullan
        $ts = !empty($r['last_modified']) ? strtotime($r['last_modified']) : 0;
      }
      
      if ($ts) {
        $diff = time() - $ts;
        if     ($diff < 60)   $badge = 'Az önce';
        elseif ($diff < 3600) $badge = floor($diff/60) . ' dk';
        elseif ($diff < 86400)$badge = floor($diff/3600) . ' sa';
        else                   $badge = date('d.m.Y', $ts);
      }

      $orderId = (int)($r['id'] ?? 0);
      $url = $orderId ? ('order_edit.php?id=' . $orderId) : '#';

      $tasks[] = ['title' => $summary . ' — ' . $prefix, 'badge' => $badge, 'url' => $url];
    }
  }
} catch (Throwable $e) {
  $tasks = [['title' => 'Notlar okunamadı', 'badge' => '', 'url' => '#']];
}


// Upcoming deliveries within next 7 days based on termin_tarihi
$upcoming = $db->query("
  SELECT o.id, o.customer_id, o.termin_tarihi, c.name AS customer_name
  FROM orders o
  LEFT JOIN customers c ON c.id = o.customer_id
  WHERE o.termin_tarihi IS NOT NULL
    AND o.termin_tarihi >= CURDATE()
    AND o.termin_tarihi <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
  ORDER BY o.termin_tarihi ASC
  LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="widgets">
  <div class="widget">
    <h4>Son Siparişler</h4>
    <ul class="list">
      <?php foreach ($lastOrders as $o): ?>
        <li>
          <span>#<?= (int)$o['id'] ?> · <?= htmlspecialchars($o['customer_name'] ?: ('Müşteri #' . (int)$o['customer_id'])) ?></span>
          <a class="badge" href="order_edit.php?id=<?= (int)$o['id'] ?>">Aç</a>
        </li>
      <?php endforeach; ?>
      <?php if (!$lastOrders): ?>
        <li><span>Henüz sipariş yok</span></li>
      <?php endif; ?>
    </ul>
    <a class="see-all" href="orders.php">Tümünü Gör →</a>
  </div>

  <div class="widget">
    <h4>Sipariş Notları</h4>
    <ul class="list">
      <?php foreach ($tasks as $t): ?>
        <li>
          <a href="<?= htmlspecialchars($t['url'] ?? '#') ?>" class="row-link"><?= htmlspecialchars($t['title']) ?></a>
          <span class="badge"><?= htmlspecialchars($t['badge']) ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>

  <div class="widget">
    <h4>Teslimatı Yaklaşan</h4>
    <ul class="list">
      <?php foreach ($upcoming as $u): ?>
        <?php
          $d1 = new DateTime(date('Y-m-d'));
          $d2 = new DateTime($u['termin_tarihi']);
          $diff = (int)$d1->diff($d2)->format('%r%a');
          if ($diff <= 0) { $label = 'Bugün'; $cls='danger'; }
          elseif ($diff <= 2) { $label = $diff.' gün kaldı'; $cls='warn'; }
          else { $label = $diff.' gün kaldı'; $cls='ok'; }
        ?>
        <li>
          <span>#<?= (int)$u['id'] ?> · <?= htmlspecialchars($u['customer_name'] ?: ('Müşteri #' . (int)$u['customer_id'])) ?> · <?= htmlspecialchars(date('d.m.Y', strtotime($u['termin_tarihi']))) ?></span>
          <span>
            <span class="badge <?= $cls ?>"><?= htmlspecialchars($label) ?></span>
            <a class="badge" href="order_edit.php?id=<?= (int)$u['id'] ?>">Aç</a>
          </span>
        </li>
      <?php endforeach; ?>
      <?php if (!$upcoming): ?>
        <li><span>Önümüzdeki 7 gün içinde teslimat yok</span></li>
      <?php endif; ?>
    </ul>
    <a class="see-all" href="orders.php?filter=yaklasan">Tümünü Gör →</a>
  </div>
</div>

<!-- Embedded calendar -->
<div class="mt">
  <?php define('CAL_EMBED', true); define('CAL_EMBED_STYLES', true); include __DIR__ . '/calendar.php'; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>