<?php ob_start(); ?>

<style>
  table tr th, table tr td {
    text-align: center;
  }

  
/* === SCOPED: wpstat pill === */
.wpstat-wrap{display:flex;flex-direction:column;align-items:center;gap:.35rem}
.wpstat-track{display:block; width:140px;height:24px;background:#e8eaee;border-radius:999px;position:relative;box-shadow:inset 0 1px 2px rgba(0,0,0,.06)}
.wpstat-bar{flex:0 0 auto; height:100%;border-radius:999px;display:flex;align-items:center;justify-content:center;white-space:normal;max-width:120px;text-align:center;line-height:1.2;transition:width .2s ease}
.wpstat-bar.wpstat-wip{background:#ffd34d;color:#1f2328}
.wpstat-bar.wpstat-done{background:#22a652;color:#fff}
.wpstat-pct{display:inline-flex;align-items:center;gap:.35rem;font-size:.82rem;font-weight:700;line-height:1}
.wpstat-ico{display:inline-flex;align-items:center}
.wpstat-label{font-size:.8rem;color:#667085;text-transform:capitalize}
td .wpstat-wrap{margin:auto}

.wpstat-bar.wpstat-red{background:#ef4444;color:#fff}
.wpstat-bar.wpstat-orange{background:#f97316;color:#fff}
.wpstat-bar.wpstat-amber{background:#f59e0b;color:#fff}
.wpstat-bar.wpstat-yellow{background:#eab308;color:#111}
.wpstat-bar.wpstat-lime{background:#84cc16;color:#111}
.wpstat-bar.wpstat-green{background:#22c55e;color:#fff}
.wpstat-bar.wpstat-teal{background:#14b8a6;color:#fff}
.wpstat-bar.wpstat-blue{background:#3b82f6;color:#fff}
.wpstat-bar.wpstat-purple{background:#8b5cf6;color:#fff}
.wpstat-bar.wpstat-done{background:#16a34a;color:#fff}



/* === Animated loader effect for Üretim Durumu bar (scoped) === */
.wpstat-bar{position:relative; overflow:hidden;}
.wpstat-pct{position:relative; z-index:1;}
.wpstat-bar::before{
  content:"";
  position:absolute;
  top:0; bottom:0;
  left:-40%;
  width:40%;
  pointer-events:none;
  background: linear-gradient(90deg, rgba(255,255,255,0) 0%, rgba(255,255,255,.45) 50%, rgba(255,255,255,0) 100%);
  animation: wpstat-sweep 1.2s linear infinite;
  mix-blend-mode: screen;
  opacity:.85;
}
/* Stop the loader shine when completed */
.wpstat-bar.wpstat-done::before{ display:none; }

@keyframes wpstat-sweep{
  from { transform: translateX(0); }
  to   { transform: translateX(260%); }
}
\1

/* Also stop shine when width is 100% (safety) */
.wpstat-bar[style*="width:100%"]::before,
.wpstat-bar[style*="width: 100%"]::before { display:none; }

.order-row{cursor:pointer;}
.order-row:hover{background:rgba(0,0,0,0.04);} 

.termin-badge{display:flex;flex-direction:column;align-items:center}

.termin-badge .badge, .bitis-badge .badge{font-size:12px !important}
.bitis-badge{display:flex;flex-direction:column;align-items:center}

.bitis-badge .badge{white-space:normal;max-width:120px;text-align:center;line-height:1.2;text-align:center;line-height:1;padding:3px 8px;display:inline-block}
.bitis-badge .bitis-date{font-size:.78rem;opacity:.75;margin-top:.25rem;white-space:normal;max-width:120px;text-align:center;line-height:1.2}

.termin-badge .termin-date{font-size:.78rem;opacity:.75;margin-top:.25rem;white-space:normal;max-width:120px;text-align:center;line-height:1.2}
.btn-ustf{background-color:#16a34a !important;color:#fff !important;}

/* === Orders list column tuning (no global width change) === */
.orders-table{table-layout:fixed}

/* Percent widths that sum to 100% so the table doesn't grow */
.orders-table th:nth-child(1), .orders-table td:nth-child(1){width:2%}   /* checkbox */
.orders-table th:nth-child(2), .orders-table td:nth-child(2){width:9%; overflow:hidden; text-overflow:ellipsis; white-space:normal;max-width:120px;text-align:center;line-height:1.2}   /* Müşteri */
.orders-table th:nth-child(3), .orders-table td:nth-child(3){width:12%; overflow:hidden; text-overflow:ellipsis; white-space:normal;max-width:120px;text-align:center;line-height:1.2}  /* Proje Adı */
.orders-table th:nth-child(4), .orders-table td:nth-child(4){width:7%;  overflow:hidden; text-overflow:ellipsis; white-space:normal;max-width:120px;text-align:center;line-height:1.2}  /* Sipariş Kodu */
.orders-table th:nth-child(5), .orders-table td:nth-child(5){width:12%} /* Üretim Durumu */
.orders-table th:nth-child(6), .orders-table td:nth-child(6){width:8%;  white-space:normal;max-width:120px;text-align:center;line-height:1.2}   /* Sipariş Tarihi */
.orders-table th:nth-child(7), .orders-table td:nth-child(7){width:11%} /* Termin Tarihi (badge + date) */
.orders-table th:nth-child(8), .orders-table td:nth-child(8){width:8%;  white-space:normal;max-width:120px;text-align:center;line-height:1.2}   /* Başlangıç Tarihi */
.orders-table th:nth-child(9), .orders-table td:nth-child(9){width:11%; white-space:normal;max-width:120px;text-align:center;line-height:1.2}  /* Bitiş Tarihi */
.orders-table td:nth-child(9){white-space:normal !important}
.orders-table th:nth-child(10), .orders-table td:nth-child(10){width:8%; white-space:normal;max-width:120px;text-align:center;line-height:1.2}  /* Teslim Tarihi */
.orders-table th:nth-child(11), .orders-table td:nth-child(11){width:12%}/* İşlem */

/* Keep progress pill responsive in narrower cell */
.orders-table .wpstat-track{width:100%; max-width:160px}

/* two-line clamp ONLY for customer & project cells */
.twolines{
  display: -webkit-box;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
  overflow: hidden;
  white-space: normal;
}

/* --- Widen only 'Sipariş Kodu' (col 4) by +5% --- */
.orders-table th:nth-child(4),
.orders-table td:nth-child(4){width:12%; max-width:12%}

/* --- Center align only the listed headers (cols 2..11) --- */
.orders-table tr:first-child th:nth-child(n+2){ text-align: center; }

/* --- Center align data cells (cols 2..11) to match headers --- */
.orders-table tr td:nth-child(n+2){ text-align: center !important; }

/* --- Force-center only the date columns (6..10) --- */
.orders-table tr:first-child th:nth-child(6),
.orders-table tr:first-child th:nth-child(7),
.orders-table tr:first-child th:nth-child(8),
.orders-table tr:first-child th:nth-child(9),
.orders-table tr:first-child th:nth-child(10){ text-align: center !important; }

.orders-table tr td:nth-child(6),
.orders-table tr td:nth-child(7),
.orders-table tr td:nth-child(8),
.orders-table tr td:nth-child(9),
.orders-table tr td:nth-child(10){ text-align: center !important; }

/* --- Force-centering ONLY for 'Termin Tarihi' (7) and 'Başlangıç Tarihi' (8) columns --- */
.orders-table td:nth-child(7),
.orders-table td:nth-child(8){ text-align: center !important; }

/* Center their inner elements as well (badge, dates, spans) */
.orders-table td:nth-child(7) *,
.orders-table td:nth-child(8) *{ text-align: center !important; margin-left: auto; margin-right: auto; }

/* If termin badge exists, keep it centered */
.orders-table td:nth-child(7) .termin-badge{display:flex;flex-direction:column;align-items:center}

/* --- Make only the action buttons (İşlem column) smaller to fit one line --- */
.orders-table td:nth-child(11){ white-space:normal;max-width:120px;text-align:center;line-height:1.2; }

.orders-table td:nth-child(11) .btn{
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 4px 8px;          /* smaller padding */
  font-size: 12px;           /* smaller text */
  line-height: 1.1;
  min-width: 0;
  height: auto;
  border-radius: 10px;       /* smaller radius */
  margin-right: 6px;         /* compact spacing */
}

.orders-table td:nth-child(11) .btn svg{
  width: 16px;
  height: 16px;
}

.orders-table td:nth-child(11) .btn.primary,
.orders-table td:nth-child(11) .btn-ustf{
  padding:3px 8px;         /* pills also smaller */
  font-weight: 700;
}

/* --- Tighten action button spacing so ÜSTF fits (only in İşlem column) --- */
.orders-table td:nth-child(11) .btn{ margin-right: 2px !important; }
.orders-table td:nth-child(11) .btn:last-child{ margin-right: 0 !important; }

/* --- Reduce 'Üretim Durumu' (col 5) width by 1% --- */
.orders-table th:nth-child(5), .orders-table td:nth-child(5){ width:11% !important; }

/* --- Further reduce 'Üretim Durumu' (col 5) by 2% --- */
.orders-table th:nth-child(5), .orders-table td:nth-child(5){ width:9% !important; }

/* --- Checkbox görünürlüğünü garanti et: sadece 1, 2 ve 3. kolon --- */
.orders-table th:nth-child(1),
.orders-table td:nth-child(1){
  min-width: 52px !important;
  width: 52px !important;  /* sabit px, çökmesin */
}

/* Müşteri (col 2) -%1 */
.orders-table th:nth-child(2), .orders-table td:nth-child(2){ width:8% !important; }

/* Proje Adı (col 3) -%1 */
.orders-table th:nth-child(3), .orders-table td:nth-child(3){ width:11% !important; }

/* OVERLAY percent label across full track so it's always fully visible */
.wpstat-track{position:relative}
.wpstat-track .wpstat-pct{
  position:absolute;
  inset:0;
  display:flex;
  align-items:center;
  justify-content:center;
  z-index:2;
  pointer-events:none;
  font-weight:700;
  text-align:center;
}
.wpstat-track .wpstat-bar{position:relative; z-index:1}
</style>

<?php
require_once __DIR__ . '/includes/helpers.php';
require_login();

$db = pdo();
$action = $_GET['a'] ?? 'list';
// 🔴 Tüm siparişleri sil (POST, transaction)
// Yeni sayfalara yönlendir (ayrı dosyalar)
if ($action === 'new') { redirect('order_add.php'); }
if ($action === 'edit') { $id = (int)($_GET['id'] ?? 0); redirect('order_edit.php?id=' . $id); }

// --------- TOPLU GÜNCELLE (POST) ---------
if ($action === 'bulk_update' && method('POST')) {
    csrf_check();
    $allowed_statuses = ['tedarik','sac lazer','boru lazer','kaynak','boya','elektrik montaj','test','paketleme','sevkiyat','teslim edildi'];
    $new_status = trim($_POST['bulk_status'] ?? '');
    $ids = $_POST['order_ids'] ?? [];

    // Güvenlik: id'leri integer'a çevir, 0'ları temizle
    if (is_array($ids)) { $ids = array_values(array_filter(array_map('intval', $ids))); } else { $ids = []; }

    if ($new_status && in_array($new_status, $allowed_statuses, true) && !empty($ids)) {
        // Tek sorgu ile toplu güncelle (IN)
        $in = implode(',', array_fill(0, count($ids), '?'));
        $params = array_merge([$new_status], $ids);
        $st = $db->prepare("UPDATE orders SET status=? WHERE id IN ($in)");
        $st->execute($params);
    }
    // Listeye dön
    redirect('orders.php');
}




// --------- SİL (POST) ---------
if ($action === 'delete' && method('POST')) {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    if ($id) {
        $stmt = $db->prepare("DELETE FROM orders WHERE id=?");
        $stmt->execute([$id]); // ON DELETE CASCADE ile order_items da silinir
    }
    redirect('orders.php');
}

// --------- KAYDET (POST) ---------
if (($action === 'new' || $action === 'edit') && method('POST')) {
    csrf_check();

    $id = (int)($_POST['id'] ?? 0);
    $order_code = trim($_POST['order_code'] ?? '');
    $customer_id = (int)($_POST['customer_id'] ?? 0);
    $status = $_POST['status'] ?? 'pending';
    $fatura_para_birimi = $_POST['fatura_para_birimi'] ?? '';
    $odeme_para_birimi  = $_POST['odeme_para_birimi']  ?? '';
    $allowed_currencies = ['TL','EUR','USD'];
    if (!in_array($fatura_para_birimi, $allowed_currencies, true)) $fatura_para_birimi = '';
    if (!in_array($odeme_para_birimi,  $allowed_currencies, true)) $odeme_para_birimi  = '';
    // Geriye dönük uyumluluk için orders.currency = ödeme para birimi
    $currency = ($odeme_para_birimi==='TL' ? 'TRY' : ($odeme_para_birimi ?: 'TRY'));

    $termin_tarihi    = $_POST['termin_tarihi']    ?: null;
    $baslangic_tarihi = $_POST['baslangic_tarihi'] ?: null;
    $bitis_tarihi     = $_POST['bitis_tarihi']     ?: null;
    $teslim_tarihi    = $_POST['teslim_tarihi']    ?: null;
    $notes = trim($_POST['notes'] ?? '');

    // Kalemler
    $p_ids  = $_POST['product_id'] ?? [];
    $names  = $_POST['name'] ?? [];
    $units  = $_POST['unit'] ?? [];
    $qtys   = $_POST['qty'] ?? [];
    $prices = $_POST['price'] ?? [];
    $ozet   = $_POST['urun_ozeti'] ?? [];
    $kalan  = $_POST['kullanim_alani'] ?? [];

    if (!$order_code) {
        $order_code = next_order_code();
    }

    if ($id > 0) {
        // Güncelle
        $stmt = $db->prepare("UPDATE orders SET order_code=?, customer_id=?, status=?, currency=?, termin_tarihi=?, baslangic_tarihi=?, bitis_tarihi=?, teslim_tarihi=?, notes=? WHERE id=?");
        $stmt->execute([$order_code,$customer_id,$status,$currency,$termin_tarihi,$baslangic_tarihi,$bitis_tarihi,$teslim_tarihi,$notes,$id]);

        // Eski kalemleri sil ve yeniden ekle
        $db->prepare("DELETE FROM order_items WHERE order_id=?")->execute([$id]);
        $order_id = $id;
        // Yeni para birimi alanlarını (varsa) güncelle
        try {
            $colCheck = $db->prepare("SHOW COLUMNS FROM orders LIKE ?");
            $colCheck->execute(['fatura_para_birimi']); $hasFatura = (bool)$colCheck->fetch();
            $colCheck->execute(['odeme_para_birimi']);  $hasOdeme  = (bool)$colCheck->fetch();
            if ($hasFatura || $hasOdeme) {
                $sql = "UPDATE orders SET "
                     . ($hasFatura ? "fatura_para_birimi=:f," : "")
                     . ($hasOdeme  ? "odeme_para_birimi=:o,"  : "");
                $sql = rtrim($sql, "," ) . " WHERE id=:id";
                $q = $db->prepare($sql);
                if ($hasFatura) $q->bindValue(":f", $fatura_para_birimi);
                if ($hasOdeme)  $q->bindValue(":o", $odeme_para_birimi);
                $q->bindValue(":id", $order_id, PDO::PARAM_INT);
                $q->execute();
            }
        } catch (Throwable $e) { /* sessiz geç */ }
        } else {
        // Yeni
        $stmt = $db->prepare("INSERT INTO orders (order_code, customer_id, status, currency, termin_tarihi, baslangic_tarihi, bitis_tarihi, teslim_tarihi, notes) VALUES (?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$order_code,$customer_id,$status,$currency,$termin_tarihi,$baslangic_tarihi,$bitis_tarihi,$teslim_tarihi,$notes]);
        $order_id = (int)$db->lastInsertId();
        // Yeni para birimi alanlarını (varsa) güncelle
        try {
            $colCheck = $db->prepare("SHOW COLUMNS FROM orders LIKE ?");
            $colCheck->execute(['fatura_para_birimi']); $hasFatura = (bool)$colCheck->fetch();
            $colCheck->execute(['odeme_para_birimi']);  $hasOdeme  = (bool)$colCheck->fetch();
            if ($hasFatura || $hasOdeme) {
                $sql = "UPDATE orders SET "
                     . ($hasFatura ? "fatura_para_birimi=:f," : "")
                     . ($hasOdeme  ? "odeme_para_birimi=:o,"  : "");
                $sql = rtrim($sql, "," ) . " WHERE id=:id";
                $q = $db->prepare($sql);
                if ($hasFatura) $q->bindValue(":f", $fatura_para_birimi);
                if ($hasOdeme)  $q->bindValue(":o", $odeme_para_birimi);
                $q->bindValue(":id", $order_id, PDO::PARAM_INT);
                $q->execute();
            }
        } catch (Throwable $e) { /* sessiz geç */ }
        }// Kalemleri ekle
    for ($i=0; $i < count($names); $i++) {
        $n  = trim($names[$i] ?? '');
        if ($n === '') continue; // boş satırı atla

        $pid = (int)($p_ids[$i] ?? 0);
        $u   = trim($units[$i] ?? 'adet');
        $q   = (float)($qtys[$i] ?? 0);
        $pr  = (float)($prices[$i] ?? 0);
        $oz  = trim($ozet[$i] ?? '');
        $ka  = trim($kalan[$i] ?? '');

        $ins = $db->prepare("INSERT INTO order_items (order_id, product_id, name, unit, qty, price, urun_ozeti, kullanim_alani) VALUES (?,?,?,?,?,?,?,?)");
        $ins->execute([$order_id, $pid, $n, $u, $q, $pr, $oz, $ka]);
    }

    redirect('orders.php');
}

// --------- FORM (YENİ / DÜZENLE) GET ---------
include __DIR__ . '/includes/header.php';

if ($action === 'new' || $action === 'edit') {
    $id = (int)($_GET['id'] ?? 0);
    $order = [
        'id'=>0,'order_code'=>'','customer_id'=>null,'status'=>'pending','currency'=>'TRY',
        'termin_tarihi'=>null,'baslangic_tarihi'=>null,'bitis_tarihi'=>null,'teslim_tarihi'=>null,'notes'=>''
    ];
    $items = [];

    if ($action === 'edit' && $id) {
        $stmt = $db->prepare("SELECT * FROM orders WHERE id=?");
        $stmt->execute([$id]);
        $order = $stmt->fetch() ?: $order;

        $it = $db->prepare("SELECT * FROM order_items WHERE order_id=? ORDER BY id ASC");
        $it->execute([$id]);
        $items = $it->fetchAll();
    } else {
        // yeni sipariş için default kodu göster
        $order['order_code'] = next_order_code();
    }

    // Müşteri ve Ürün listeleri
    $customers = $db->query("SELECT id,name FROM customers ORDER BY name ASC")->fetchAll();
    $products  = $db->query("SELECT id,sku,name,unit,price,urun_ozeti,kullanim_alani FROM products ORDER BY name ASC")->fetchAll();

    ?>
    <div class="card">
      <h2><?= $order['id'] ? 'Sipariş Düzenle' : 'Yeni Sipariş' ?></h2>
      <?php if (!empty($order['id'])): ?>
      <div class="row" style="color:#000; font-size:14px; justify-content:flex-end; gap:8px; margin-bottom:8px">
        <a class="btn" href="order_view.php?id=<?= (int)$order['id'] ?>" title="Görüntüle" aria-label="Görüntüle"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 5c-7.633 0-11 7-11 7s3.367 7 11 7 11-7 11-7-3.367-7-11-7zm0 12a5 5 0 1 1 .001-10.001A5 5 0 0 1 12 17z"/><circle cx="12" cy="12" r="3"/></svg><</a>
<?php $___role = current_user()['role'] ?? ''; if (in_array($___role, ['admin','sistem_yoneticisi'], true)): ?>
        <a class="btn primary" href="order_pdf.php?id=<?= (int)$order['id'] ?>" title="STF PDF" aria-label="STF PDF">STF</a>
<?php endif; ?>
        <a class="btn btn-ustf" href="order_pdf_uretim.php?id=<?= (int)$order['id'] ?>" title="ÜSTF PDF" aria-label="ÜSTF PDF">ÜSTF</a>
        <a class="btn" href="order_send_mail.php?id=<?= isset($o)?(int)$o['id']:(int)$order['id'] ?>" title="Mail" aria-label="Mail">Mail</a>
                <a class="btn" href="order_send_mail.php?id=<?= (int)$o['id'] ?>" title="E-posta gönder" aria-label="E-posta gönder"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16"><path d="M3 5h18a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2zm0 2v.217l9 5.4 9-5.4V7H3zm18 10V9.383l-8.553 5.132a2 2 0 0 1-1.894 0L2 9.383V17h19z"/></svg></a>
<a class="btn" href="order_send_mail.php?id=<?= (int)$order['id'] ?>" title="E-posta gönder" aria-label="E-posta gönder"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false"><path d="M3 5h18a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2zm0 2v.217l9 5.4 9-5.4V7H3zm18 10V9.383l-8.553 5.132a2 2 0 0 1-1.894 0L2 9.383V17a0 0 0 0 0 0 0h19z"/></svg></a>
      </div>
      <?php endif; ?>
      <form method="post">
        <?php csrf_input(); ?>
        <input type="hidden" name="id" value="<?= (int)$order['id'] ?>">

        <div class="row" style="color:#000; font-size:14px; gap:12px">
          <div style="color:#000; font-size:12px; flex:1">
            <label>Sipariş Kodu</label>
            <input name="order_code" value="<?= h($order['order_code']) ?>">
          </div>
          <div style="color:#000; font-size:12px; flex:2">
            <label>Müşteri</label>
            <select name="customer_id" required>
              <option value="">— Seçin —</option>
              <?php foreach($customers as $c): ?>
                <option value="<?= (int)$c['id'] ?>" <?= (int)$order['customer_id']===(int)$c['id']?'selected':'' ?>>
                  <?= h($c['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="row mt" style="color:#000; font-size:14px; gap:12px">
          <div>
            <label>Durum</label>
            <select name="status" style="color:#000; font-size:14px; max-width:150px;">
              <?php foreach(['tedarik'=>'Tedarik','sac lazer'=>'Sac Lazer','boru lazer'=>'Boru Lazer','kaynak'=>'Kaynak','boya'=>'Boya','elektrik montaj'=>'Elektrik Montaj','test'=>'Test','paketleme'=>'Paketleme','sevkiyat'=>'Sevkiyat','teslim edildi'=>'Teslim Edildi'] as $k=>$v): ?>
                <option value="<?= h($k) ?>" <?= $order['status']===$k?'selected':'' ?>><?= h($v) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label>Fatura Para Birimi</label>
            <select name="fatura_para_birimi">
              <?php $val = $order['fatura_para_birimi'] ?? ''; ?>
              <option value="TL"  <?= $val==='TL'  ?'selected':'' ?>>TL</option>
              <option value="EUR" <?= $val==='EUR' ?'selected':'' ?>>Euro</option>
              <option value="USD" <?= $val==='USD' ?'selected':'' ?>>USD</option>
            </select>
          </div>
          <div>
            <label>Ödeme Para Birimi</label>
            <select name="odeme_para_birimi">
              <?php $val2 = $order['odeme_para_birimi'] ?? ''; ?>
              <option value="TL"  <?= $val2==='TL'  ?'selected':'' ?>>TL</option>
              <option value="EUR" <?= $val2==='EUR' ?'selected':'' ?>>Euro</option>
              <option value="USD" <?= $val2==='USD' ?'selected':'' ?>>USD</option>
            </select>
          </div>
          <div>
            <label>Termin Tarihi</label>
            <input type="date" name="termin_tarihi" value="<?= h($order['termin_tarihi']) ?>">
          </div>
          <div>
            <label>Başlangıç Tarihi</label>
            <input type="date" name="baslangic_tarihi" value="<?= h($order['baslangic_tarihi']) ?>">
          </div>
          <div>
            <label>Bitiş Tarihi</label>
            <input type="date" name="bitis_tarihi" value="<?= h($order['bitis_tarihi']) ?>">
          </div>
          <div>
            <label>Teslim Tarihi</label>
            <input type="date" name="teslim_tarihi" value="<?= h($order['teslim_tarihi']) ?>">
          </div>
        </div>

        <label class="mt">Notlar</label>
        <textarea name="notes" rows="3"><?= h($order['notes']) ?></textarea>

        <h3 class="mt">Kalemler</h3>
        <div id="items">
          <div class="row mb">
            <button type="button" class="btn" onclick="addRow()">+ Satır Ekle</button>
          </div>
          <div class="table-responsive">
<table id="itemsTable">
            <tr>
              <th style="color:#000; font-size:14px; width:22%">Ürün</th>
              <th>Ad</th>
              <th style="color:#000; font-size:14px; width:8%">Birim</th>
              <th style="color:#000; font-size:14px; width:8%">Miktar</th>
              <th style="color:#000; font-size:14px; width:12%">Birim Fiyat</th>
              <th>Ürün Özeti</th>
              <th>Kullanım Alanı</th>
              <th class="right" style="color:#000; font-size:14px; width:8%">Sil</th>
            </tr>
            <?php
            if (!$items) { $items = [[]]; } // en az 1 boş satır
            foreach ($items as $it):
            ?>
            <tr>
              <td>
                <select name="product_id[]" onchange="onPickProduct(this)">
                  <option value="">—</option>
                  <?php foreach($products as $p): ?>
                  <option
                    value="<?= (int)$p['id'] ?>"
                    data-name="<?= h($p['name']) ?>"
                    data-unit="<?= h($p['unit']) ?>"
                    data-price="<?= h($p['price']) ?>"
                    data-ozet="<?= h($p['urun_ozeti']) ?>"
                    data-kalan="<?= h($p['kullanim_alani']) ?>"
                    <?= (isset($it['product_id']) && (int)$it['product_id']===(int)$p['id'])?'selected':'' ?>
                  ><?= h($p['name']) ?><?= $p['sku'] ? ' ('.h($p['sku']).')':'' ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td><input name="name[]" value="<?= h($it['name'] ?? '') ?>" required></td>
              <td><input name="unit[]" value="<?= h($it['unit'] ?? 'adet') ?>"></td>
              <td><input name="qty[]" type="number" step="0.01" value="<?= h($it['qty'] ?? '1') ?>"></td>
              <td><input name="price[]" type="number" step="0.01" value="<?= h($it['price'] ?? '0') ?>"></td>
              <td><input name="urun_ozeti[]" value="<?= h($it['urun_ozeti'] ?? '') ?>"></td>
              <td><input name="kullanim_alani[]" value="<?= h($it['kullanim_alani'] ?? '') ?>"></td>
              <td class="right"><button type="button" class="btn" onclick="delRow(this)">Sil</button>  <a class="btn btn-ustf" href="order_pdf_uretim.php?id=<?= (int)$o['id'] ?>" title="ÜSTF PDF" aria-label="ÜSTF PDF" target="_blank" rel="noopener noreferrer">ÜSTF</a>
      </td>
            </tr>
            <?php endforeach; ?>
          </table>
</div>

<!-- === Üretim Durumu: Hızlı filtre çubuğu (tıklayınca filtreler) === -->
<div class="row" style="color:#000; font-size:14px; margin:8px 0 4px; display:flex; align-items:center; flex-wrap:wrap; gap:8px;">
  
</div>
<style>
  .status-quick-filter a.active{ text-decoration:underline; }
</style>
<!-- === /Üretim Durumu hızlı filtre === -->
<?php if (($action ?? 'list') === 'list' && ($total_pages ?? 1) > 1): 
  $qs = $_GET; unset($qs['page']); $base = 'orders.php?' . http_build_query($qs);
  function page_link($p,$base){ return $base . (strpos($base,'?')!==false ? '&' : '?') . 'page=' . $p; }
?>
<div class="row" style="color:#000; font-size:14px; margin:12px 0; gap:6px; display:flex; align-items:center; flex-wrap:wrap;">
  <a class="btn" href="<?= page_link(max(1,$page-1), $base) ?>">&laquo; Önceki</a>
  <span>Sayfa <?= (int)$page ?> / <?= (int)$total_pages ?></span>
  <a class="btn" href="<?= page_link(min($total_pages,$page+1), $base) ?>">Sonraki &raquo;</a>
</div>
<?php endif; ?>

</div>

        <div class="row mt">
          <button class="btn primary"><?= $order['id'] ? 'Güncelle' : 'Kaydet' ?></button>
          <a class="btn" href="orders.php">Vazgeç</a>
        </div>
      </form>
    </div>

    <script>
    function addRow(){
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>
          <select name="product_id[]" onchange="onPickProduct(this)">
            <option value="">—</option>
            <?php foreach($products as $p): ?>
            <option
              value="<?= (int)$p['id'] ?>"
              data-name="<?= h($p['name']) ?>"
              data-unit="<?= h($p['unit']) ?>"
              data-price="<?= h($p['price']) ?>"
              data-ozet="<?= h($p['urun_ozeti']) ?>"
              data-kalan="<?= h($p['kullanim_alani']) ?>"
            ><?= h($p['name']) ?><?= $p['sku'] ? ' ('.h($p['sku']).')':'' ?></option>
            <?php endforeach; ?>
          </select>
        </td>
        <td><input name="name[]" required></td>
        <td><input name="unit[]" value="adet"></td>
        <td><input name="qty[]" type="number" step="0.01" value="1"></td>
        <td><input name="price[]" type="number" step="0.01" value="0"></td>
        <td><input name="urun_ozeti[]"></td>
        <td><input name="kullanim_alani[]"></td>
        <td class="right"><button type="button" class="btn" onclick="delRow(this)">Sil</button>  <a class="btn btn-ustf" href="order_pdf_uretim.php?id=<?= (int)$o['id'] ?>" title="ÜSTF PDF" aria-label="ÜSTF PDF" target="_blank" rel="noopener noreferrer">ÜSTF</a>
      </td>
      `;
      document.querySelector('#itemsTable').appendChild(tr);
    }
    function delRow(btn){
      const tr = btn.closest('tr');
      if(!tr) return;
      const tbody = tr.parentNode;
      tbody.removeChild(tr);
    }
    function onPickProduct(sel){
      const opt = sel.options[sel.selectedIndex];
      if(!opt) return;
      const tr = sel.closest('tr');
      tr.querySelector('input[name="name[]"]').value = opt.getAttribute('data-name') || '';
      tr.querySelector('input[name="unit[]"]').value = opt.getAttribute('data-unit') || 'adet';
      tr.querySelector('input[name="price[]"]').value = opt.getAttribute('data-price') || '0';
      tr.querySelector('input[name="urun_ozeti[]"]').value = opt.getAttribute('data-ozet') || '';
      tr.querySelector('input[name="kullanim_alani[]"]').value = opt.getAttribute('data-kalan') || '';
    }
    </script>
    <?php
    include __DIR__ . '/includes/footer.php'; exit;
}



// --------- LİSTE / FİLTRE ---------
$q = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? '';
$per_page = 20;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $per_page;

$params = [];
$sql = "SELECT o.*, c.name AS customer_name FROM orders o LEFT JOIN customers c ON c.id=o.customer_id WHERE 1=1";

if ($q !== '') {
    $sql .= " AND (o.order_code LIKE ? OR c.name LIKE ? OR o.proje_adi LIKE ?)";
    $params[] = '%'.$q.'%';
    $params[] = '%'.$q.'%';
    $params[] = '%'.$q.'%';
}
if ($status !== '') {
    $sql .= " AND o.status = ?";
    $params[] = $status;
}
$sql .= " ORDER BY CASE WHEN LOWER(o.status) = 'tedarik' THEN 1 WHEN LOWER(o.status) = 'sac lazer' THEN 2 WHEN LOWER(o.status) = 'boru lazer' THEN 3 WHEN LOWER(o.status) = 'kaynak' THEN 4 WHEN LOWER(o.status) = 'boya' THEN 5 WHEN LOWER(o.status) = 'elektrik montaj' THEN 6 WHEN LOWER(o.status) = 'test' THEN 7 WHEN LOWER(o.status) = 'paketleme' THEN 8 WHEN LOWER(o.status) = 'sevkiyat' THEN 9 WHEN LOWER(o.status) = 'teslim edildi' THEN 10 ELSE 999 END ASC, CASE WHEN o.order_code REGEXP '-[0-9]+$' THEN CAST(SUBSTRING_INDEX(o.order_code, '-', -1) AS UNSIGNED) ELSE 0 END DESC, o.order_code DESC";
// Toplam sayfa sayısı için COUNT(*)
$count_stmt = $db->prepare("SELECT COUNT(*) FROM (" . $sql . ") t");
$count_stmt->execute($params);

// === Quick status counts for filter bar (counts reflect current search 'q') ===
$status_labels = [
  '' => 'Tümü',
  'tedarik' => 'Tedarik',
  'sac lazer' => 'Sac Lazer',
  'boru lazer' => 'Boru Lazer',
  'kaynak' => 'Kaynak',
  'boya' => 'Boya',
  'elektrik montaj' => 'Elektrik Montaj',
  'test' => 'Test',
  'paketleme' => 'Paketleme',
  'sevkiyat' => 'Sevkiyat',
  'teslim edildi' => 'Teslim Edildi',
];
$__cnt_params = [];
$__cnt_sql = "SELECT o.status, COUNT(*) AS cnt FROM orders o LEFT JOIN customers c ON c.id=o.customer_id WHERE 1=1";
if ($q !== '') {
    $__cnt_sql .= " AND (o.order_code LIKE ? OR c.name LIKE ? OR o.proje_adi LIKE ?)";
    $__cnt_params[] = '%'.$q.'%';
    $__cnt_params[] = '%'.$q.'%';
    $__cnt_params[] = '%'.$q.'%';
}
$__cnt_sql .= " GROUP BY o.status";
$__cnt_stmt = $db->prepare($__cnt_sql);
$__cnt_stmt->execute($__cnt_params);
$status_counts = [];
while ($__r = $__cnt_stmt->fetch(PDO::FETCH_ASSOC)) {
    $k = $__r['status'] ?? '';
    $status_counts[$k] = (int)$__r['cnt'];
}
$total_in_scope = 0;
foreach ($status_counts as $__v) { $total_in_scope += $__v; }

if (!function_exists('__orders_status_link')) {
    function __orders_status_link($value){
        $qs = $_GET;
        unset($qs['page']);
        if ($value === '' || $value === null) { unset($qs['status']); }
        else { $qs['status'] = $value; }
        $base = 'orders.php';
        return $base . (empty($qs) ? '' : ('?' . http_build_query($qs)));
    }
}


$total = (int)$count_stmt->fetchColumn();
$total_pages = max(1, (int)ceil($total / $per_page));

// LIMIT/OFFSET
$sql .= " LIMIT " . (int)$per_page . " OFFSET " . (int)$offset;


$stmt = $db->prepare($sql);
$stmt->execute($params);
?>
<div class="row mb" style="color:#000; font-size:14px; align-items:center; gap:12px; flex-wrap:nowrap; flex-direction:row;align-items:center;flex-wrap:nowrap;gap:12px;">
  <a class="btn primary" href="order_add.php">Yeni Sipariş</a>
  <form method="post" action="orders.php?a=bulk_update" style="color:#000; font-size:14px; display:flex;gap:8px;align-items:center;flex-direction:row;flex-wrap:nowrap;" id="bulkForm" onsubmit="return collectBulkIds(this)"><?php csrf_input(); ?>
    <select name="bulk_status" style="color:#000; font-size:14px; max-width:160px;">
      <a href="order_new.php" class="btn primary">Yeni Sipariş</a>
<option value="">Toplu İşlemler</option>
      <option value="tedarik">Tedarik</option>
      <option value="sac lazer">Sac Lazer</option>
      <option value="boru lazer">Boru Lazer</option>
      <option value="kaynak">Kaynak</option>
      <option value="boya">Boya</option>
      <option value="elektrik montaj">Elektrik Montaj</option>
      <option value="test">Test</option>
      <option value="paketleme">Paketleme</option>
      <option value="sevkiyat">Sevkiyat</option>
      <option value="teslim edildi">Teslim Edildi</option>
    </select>
    <button type="submit" class="btn">Uygula</button>
  </form>
  
<?php if (($action ?? 'list') === 'list') : ?><form method="post" action="orders.php?a=delete_all" onsubmit="return confirm('TÜM siparişleri silmek istediğinize emin misiniz? Bu işlem geri alınamaz.');" style="color:#000; font-size:14px; display:inline-block;margin-left:8px;"><?php csrf_input(); ?></form><?php endif; ?>
  <form class="row" method="get" style="color:#000; font-size:14px; gap:8px; align-items:center; flex:0 0 auto;">
    <input name="q" placeholder="Sipariş kodu / müşteri ara…" value="<?= h($q) ?>" style="color:#000; font-size:14px; width:280px; max-width:40vw;">
    <select name="status" style="color:#000; font-size:14px; min-width:180px;">
      <option value="">Durum (hepsi)</option>
      <?php foreach(['tedarik'=>'Tedarik','sac lazer'=>'Sac Lazer','boru lazer'=>'Boru Lazer','kaynak'=>'Kaynak','boya'=>'Boya','elektrik montaj'=>'Elektrik Montaj','test'=>'Test','paketleme'=>'Paketleme','sevkiyat'=>'Sevkiyat','teslim edildi'=>'Teslim Edildi'] as $k=>$v): ?>
        <option value="<?= h($k) ?>" <?= $status===$k?'selected':'' ?>><?= h($v) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn">Ara</button>
  </form>
</div>


<!-- Üretim Durumu: Yatay Filtre -->
<?php
  // Sadece arama (q) kapsamına göre adetler; status filtreye dahil edilmez
  $__cnt_params = [];
  $__cnt_sql = "SELECT o.status, COUNT(*) AS cnt FROM orders o LEFT JOIN customers c ON c.id=o.customer_id WHERE 1=1";
  if ($q !== '') {
    $__cnt_sql .= " AND (o.order_code LIKE ? OR c.name LIKE ? OR o.proje_adi LIKE ?)";
    $__cnt_params[] = '%'.$q.'%';
    $__cnt_params[] = '%'.$q.'%';
    $__cnt_params[] = '%'.$q.'%';
  }
  $__cnt_sql .= " GROUP BY o.status";
  $__cnt_stmt = $db->prepare($__cnt_sql);
  $__cnt_stmt->execute($__cnt_params);
  $__status_counts = [];
  $__total_in_scope = 0;
  while ($__r = $__cnt_stmt->fetch(PDO::FETCH_ASSOC)) {
    $k = $__r['status'] ?? '';
    $v = (int)($__r['cnt'] ?? 0);
    $__status_counts[$k] = $v;
    $__total_in_scope += $v;
  }
  if (!function_exists('__orders_status_link2')) {
    function __orders_status_link2($value){
      $qs = $_GET; unset($qs['page']);
      if ($value === '' || $value === null) { unset($qs['status']); }
      else { $qs['status'] = $value; }
      $base = 'orders.php';
      return $base . (empty($qs) ? '' : ('?' . http_build_query($qs)));
    }
  }
  $__labels = [
    'tedarik'=>'Tedarik', 'sac lazer'=>'Sac Lazer', 'boru lazer'=>'Boru Lazer',
    'kaynak'=>'Kaynak', 'boya'=>'Boya', 'elektrik montaj'=>'Elektrik Montaj',
    'test'=>'Test', 'paketleme'=>'Paketleme', 'sevkiyat'=>'Sevkiyat', 'teslim edildi'=>'Teslim Edildi',
  ];
  $__order = ['tedarik','sac lazer','boru lazer','kaynak','boya','elektrik montaj','test','paketleme','sevkiyat','teslim edildi'];
  $__isAll = ($status === '' || $status === null);
?>

<div class="card">
  <div class="table-responsive">
<?php if (($action ?? 'list') === 'list' && ($total_pages ?? 1) > 1): 
  $qs = $_GET; unset($qs['page']);
  $base = 'orders.php' . (empty($qs) ? '' : ('?' . http_build_query($qs)));
  if (!function_exists('__orders_page_link')) {
    function __orders_page_link($p,$base){ return $base . (strpos($base,'?')!==false ? '&' : '?') . 'page=' . (int)$p; }
  }
  $first_link = __orders_page_link(1, $base);
  $prev_link  = __orders_page_link(max(1,$page-1), $base);
  $next_link  = __orders_page_link(min($total_pages,$page+1), $base);
  $last_link  = __orders_page_link($total_pages, $base);
  $window = 2;
  $start = max(1, $page - $window);
  $end   = min($total_pages, $page + $window);
?>
<div class="row" style="color:#000; font-size:14px; margin:10px 0; display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap;">
  


<!-- YATAY DURUM FİLTRESİ (TABLO İÇİ) START -->
<div class="status-quick-filter" style="font-size:14px" style="color:#000; font-size:14px; font-size:.95rem;">
    <?php
      $ordered_statuses = ['', 'tedarik','sac lazer','boru lazer','kaynak','boya','elektrik montaj','test','paketleme','sevkiyat','teslim edildi'];
      $first = true;
      foreach ($ordered_statuses as $sk) {
        $label = $status_labels[$sk] ?? ($sk ?: 'Tümü');
        $cnt   = ($sk === '') ? (int)$total_in_scope : (int)($status_counts[$sk] ?? 0);
        if (!$first) { echo " | "; }
        $first = false;
        $isActive = ($sk === '' ? ($status === '' || $status === null) : ($status === $sk));
        echo '<a href="'.h(__orders_status_link($sk)).'" class="'.($isActive?'active':'').'" style="color:#000; font-size:14px; text-decoration:none;'.($isActive?'font-weight:700;':'').'">'.h($label).' ('.(int)$cnt.')</a>';
      }
    ?>
  </div>
<!-- YATAY DURUM FİLTRESİ (TABLO İÇİ) END -->
<!-- pager removed -->

  <form method="get" class="row" style="color:#000; font-size:14px; gap:6px; align-items:center; flex:0 0 auto;">
    <label>Sayfa:</label>
    <input type="number" name="page" value="<?= (int)$page ?>" min="1" max="<?= (int)$total_pages ?>" style="color:#000; font-size:14px; width:72px">
    <?php foreach($qs as $k=>$v): ?>
      <input type="hidden" name="<?= h($k) ?>" value="<?= h($v) ?>">
    <?php endforeach; ?>
    <button class="btn">Git</button>
  </form>
<?php 
  // Always-on pager compute
  $qs = $_GET; unset($qs['page']);
  $base = 'orders.php' . (empty($qs) ? '' : ('?' . http_build_query($qs)));
  if (!function_exists('__orders_page_link')) {
    function __orders_page_link($p,$base){ return $base . (strpos($base,'?')!==false ? '&' : '?') . 'page=' . (int)$p; }
  }
  $total_pages = max(1, (int)($total_pages ?? 1));
  $page = max(1, (int)($page ?? 1));
  $window = 2;
  $start = max(1, $page - $window);
  $end   = min($total_pages, $page + $window);
  $first_link = __orders_page_link(1, $base);
  $prev_link  = __orders_page_link(max(1,$page-1), $base);
  $next_link  = __orders_page_link(min($total_pages,$page+1), $base);
  $last_link  = __orders_page_link($total_pages, $base);
?>
<div class="pager d-flex gap-1" style="color:#000; font-size:14px; flex:1 1 auto; margin-top:6px;">
  <?php if ($page > 1): ?>
    <a class="btn" href="<?= h($first_link) ?>">&laquo; İlk</a>
    <a class="btn" href="<?= h($prev_link) ?>">&lsaquo; Önceki</a>
  <?php else: ?>
    <span class="btn disabled">&laquo; İlk</span>
    <span class="btn disabled">&lsaquo; Önceki</span>
  <?php endif; ?>

  <?php for($i=$start;$i<=$end;$i++): $lnk = __orders_page_link($i, $base); ?>
    <a class="btn <?= $i==(int)$page?'btn-primary':'' ?>" href="<?= h($lnk) ?>"><?= (int)$i ?></a>
  <?php endfor; ?>

  <?php if ($page < $total_pages): ?>
    <a class="btn" href="<?= h($next_link) ?>">Sonraki &rsaquo;</a>
    <a class="btn" href="<?= h($last_link) ?>">Son &raquo;</a>
  <?php else: ?>
    <span class="btn disabled">Sonraki &rsaquo;</span>
    <span class="btn disabled">Son &raquo;</span>
  <?php endif; ?>
</div>

</div>
<?php endif; ?>
<?php if ((($view ?? 'list') === 'list') && (($total_pages ?? 1) <= 1)): ?>
<!-- YATAY DURUM FİLTRESİ (FALLBACK, TABLO İÇİ) START -->
<!-- YATAY DURUM FİLTRESİ (TABLO İÇİ) START -->
<div class="status-quick-filter" style="font-size:14px" style="color:#000; font-size:14px; font-size:.95rem;">
    <?php
      $ordered_statuses = ['', 'tedarik','sac lazer','boru lazer','kaynak','boya','elektrik montaj','test','paketleme','sevkiyat','teslim edildi'];
      $first = true;
      foreach ($ordered_statuses as $sk) {
        $label = $status_labels[$sk] ?? ($sk ?: 'Tümü');
        $cnt   = ($sk === '') ? (int)$total_in_scope : (int)($status_counts[$sk] ?? 0);
        if (!$first) { echo " | "; }
        $first = false;
        $isActive = ($sk === '' ? ($status === '' || $status === null) : ($status === $sk));
        echo '<a href="'.h(__orders_status_link($sk)).'" class="'.($isActive?'active':'').'" style="color:#000; font-size:14px; text-decoration:none;'.($isActive?'font-weight:700;':'').'">'.h($label).' ('.(int)$cnt.')</a>';
      }
    ?>
  </div>
<!-- YATAY DURUM FİLTRESİ (TABLO İÇİ) END -->
<!-- YATAY DURUM FİLTRESİ (FALLBACK, TABLO İÇİ) END -->
<?php endif; ?>

<table class="orders-table">
    <tr>
      <th><input type='checkbox' id='checkAll' onclick="document.querySelectorAll('.orderCheck').forEach(cb=>cb.checked=this.checked)"></th>
      <th>Müşteri</th>
      <th>Proje Adı</th>
      <th>Sipariş Kodu</th>
      <th>Üretim Durumu</th>
      <th style="color:#000; font-size:14px; text-align:center">Sipariş Tarihi</th>
      <th style="color:#000; font-size:14px; text-align:center">Termin Tarihi</th>
      <th style="color:#000; font-size:14px; text-align:center">Başlangıç Tarihi</th>
      <th style="color:#000; font-size:14px; text-align:center">Bitiş Tarihi</th>
      <th style="color:#000; font-size:14px; text-align:center">Teslim Tarihi</th>
      <th class="right">İşlem</th>
    </tr>
    <?php 
$status_steps = [
  'tedarik'=>1,'sac lazer'=>2,'boru lazer'=>3,'kaynak'=>4,'boya'=>5,
  'elektrik montaj'=>6,'test'=>7,'paketleme'=>8,'sevkiyat'=>9,'teslim edildi'=>10
];
$status_labels = [
  'tedarik'=>'Tedarik','sac lazer'=>'Sac Lazer','boru lazer'=>'Boru Lazer','kaynak'=>'Kaynak','boya'=>'Boya',
  'elektrik montaj'=>'Elektrik Montaj','test'=>'Test','paketleme'=>'Paketleme','sevkiyat'=>'Sevkiyat','teslim edildi'=>'Teslim Edildi'
];

// === Üretim durumu kapsül bileşeni (scoped) ===
function __wpstat_icon_svg($key){
    switch($key){
        case 'box':   return '<svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M3 7l9 5 9-5-9-4-9 4z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M3 7v10l9 5 9-5V7" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/></svg>';
        case 'laser': return '<svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M3 12h10" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/><path d="M14 12l7-4v8l-7-4z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/></svg>';
        case 'weld':  return '<svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M3 17l8-8" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/><path d="M11 9l6 6" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/><circle cx="11" cy="9" r="1.5" fill="currentColor"/></svg>';
        case 'brush': return '<svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M4 14c0 3 2 5 5 5 3 0 5-2 5-5v-2H4v2z" stroke="currentColor" stroke-width="1.7"/><path d="M14 7h6v3a2 2 0 0 1-2 2h-4V7z" stroke="currentColor" stroke-width="1.7"/></svg>';
        case 'bolt':  return '<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M13 2L3 14h7l-1 8 11-14h-7l1-6z"/></svg>';
        case 'lab':   return '<svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M9 3v6l-4 7a4 4 0 0 0 3.5 6h7a4 4 0 0 0 3.5-6l-4-7V3" stroke="currentColor" stroke-width="1.7"/><path d="M9 9h6" stroke="currentColor" stroke-width="1.7"/></svg>';
        case 'truck': return '<svg width="14" height="14" viewBox="0 0 24 24" fill="none"><rect x="3" y="7" width="10" height="7" rx="1.5" stroke="currentColor" stroke-width="1.7"/><path d="M13 10h4l3 3v1h-7v-4z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><circle cx="7.5" cy="17.5" r="1.9" stroke="currentColor" stroke-width="1.7"/><circle cx="18.5" cy="17.5" r="1.9" stroke="currentColor" stroke-width="1.7"/></svg>';
        case 'check': return '<svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M4 12l5 5 11-11" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"/></svg>';
        default:      return '<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="6"/></svg>';
    }
}
function __wpstat_icon_key($status){
    switch($status){
        case 'tedarik': return 'box';
        case 'sac lazer': return 'laser';
        case 'boru lazer': return 'laser';
        case 'kaynak': return 'weld';
        case 'boya': return 'brush';
        case 'elektrik montaj': return 'bolt';
        case 'test': return 'lab';
        case 'paketleme': return 'box';
        case 'sevkiyat': return 'truck';
        case 'teslim edildi': return 'check';
        default: return 'box';
    }
}

function __wpstat_class_by_pct($pct){
    if ($pct <= 10) return 'wpstat-red';
    if ($pct <= 20) return 'wpstat-orange';
    if ($pct <= 30) return 'wpstat-amber';
    if ($pct <= 40) return 'wpstat-yellow';
    if ($pct <= 50) return 'wpstat-lime';
    if ($pct <= 60) return 'wpstat-green';
    if ($pct <= 70) return 'wpstat-teal';
    if ($pct <= 80) return 'wpstat-blue';
    if ($pct <= 90) return 'wpstat-purple';
    return 'wpstat-done';
}

function render_status_pill($status_raw){
    $map = [
        'tedarik'=>1,'sac lazer'=>2,'boru lazer'=>3,'kaynak'=>4,'boya'=>5,
        'elektrik montaj'=>6,'test'=>7,'paketleme'=>8,'sevkiyat'=>9,'teslim edildi'=>10
    ];
    $labels = [
        'tedarik'=>'Tedarik','sac lazer'=>'Sac Lazer','boru lazer'=>'Boru Lazer','kaynak'=>'Kaynak','boya'=>'Boya',
        'elektrik montaj'=>'Elektrik Montaj','test'=>'Test','paketleme'=>'Paketleme','sevkiyat'=>'Sevkiyat','teslim edildi'=>'Teslim Edildi'
    ];
    $k = strtolower(trim((string)$status_raw));
    if(!isset($map[$k])) $k = 'tedarik';
    $step = (int)$map[$k];
    $pct = max(10, min(100, $step*10));
    $done = ($pct>=100);
    $class = __wpstat_class_by_pct($pct);
    $icon = __wpstat_icon_svg(__wpstat_icon_key($k));
    $label = $labels[$k] ?? $status_raw;

    ob_start(); ?>
    <div class="wpstat-wrap">
      <div class="wpstat-track">
        <div class="wpstat-bar <?= $class ?>" style="font-size:14px; width: <?= (int)$pct ?>%; max-width: <?= (int)$pct ?>%"></div>
        <span class="wpstat-pct"><i class="wpstat-ico"><?= $icon ?></i>%<?= (int)$pct ?></span>
      </div>
      <div class="wpstat-label"><?= htmlspecialchars($label, ENT_QUOTES) ?></div>
    </div>
    <?php return ob_get_clean();
}

function progress_color_by_pct($pct){
  if ($pct >= 100) return '#22c55e';       // green
  if ($pct >= 90)  return '#16a34a';       // darker green
  if ($pct >= 70)  return '#3b82f6';       // blue
  if ($pct >= 40)  return '#f59e0b';       // amber
  return '#ef4444';                         // red
}
?>

<?php
function fmt_date_dmy($s){
    if(!$s || $s === '0000-00-00' || strtolower((string)$s) === 'null') {
        return '—';
    }
    $t = strtotime($s);
    if(!$t) return '—';
    return date('d-m-Y', $t);
}
?>


<?php
function bitis_badge_html($bitis = null, $termin = null){
    // Wrapper style: 2-row grid (badge + date), fixed height so dates align across columns
    $wrapStyle = 'display:grid;grid-template-rows:1fr auto;row-gap:4px;height:48px;align-items:end;justify-items:center';
    $badgeBase = 'font-size:10px !important;line-height:1.2;padding:3px 8px;display:inline-block;max-width:120px;text-align:center;white-space:normal';

    if(!$bitis || $bitis==='0000-00-00'){
        return '<div class="bitis-badge" style="'.$wrapStyle.'"><span class="badge gray" style="'.$badgeBase.'">—</span><div class="bitis-date" style="font-size:.78rem;opacity:.75;white-space:nowrap"></div></div>';
    }

    $dateHtml = '<div class="bitis-date" style="font-size:.78rem;opacity:.75;white-space:nowrap">'.fmt_date_dmy($bitis).'</div>';

    if(!$termin || $termin==='0000-00-00'){
        return '<div class="bitis-badge" style="'.$wrapStyle.'">'.$dateHtml.'</div>';
    }

    try{
        $dBitis  = new DateTime($bitis);
        $dTermin = new DateTime($termin);
    }catch(Exception $e){
        return '<div class="bitis-badge" style="'.$wrapStyle.'">'.$dateHtml.'</div>';
    }

    $signedDays = (int)$dBitis->diff($dTermin)->format('%r%a'); // pozitif: bitiş terminden önce
    $absDays    = abs($signedDays);

    if($signedDays > 0){
        $txt = 'Üretim '.$absDays.' gün önce bitti';
        $cls = 'green';
    } elseif($signedDays === 0){
        $txt = 'Üretim tam gününde tamamlandı';
        $cls = 'green';
    } else {
        $txt = 'Üretim '.$absDays.' gün gecikti';
        $cls = 'red';
    }
    $title = 'Bitiş: '.fmt_date_dmy($bitis).' • Termin: '.fmt_date_dmy($termin);
    $badge = '<span class="badge '.$cls.'" style="'.$badgeBase.'" title="'.htmlspecialchars($title, ENT_QUOTES, 'UTF-8').'">'.$txt.'</span>';
    return '<div class="bitis-badge" style="'.$wrapStyle.'">'.$badge.$dateHtml.'</div>';
}
?>



<?php

function termin_badge_html($termin, $teslim=null){
    // Wrapper style: 2-row grid (badge + date), fixed height so dates align across columns
    $wrapStyle = 'display:grid;grid-template-rows:1fr auto;row-gap:4px;height:48px;align-items:end;justify-items:center';
    $badgeBase = 'font-size:10px !important;line-height:1.2;padding:3px 8px;display:inline-block;max-width:120px;text-align:center;white-space:normal';

    if(!$termin || $termin==='0000-00-00'){
        return '<div class="termin-badge" style="'.$wrapStyle.'"><span class="badge gray" style="'.$badgeBase.'">—</span><div class="termin-date" style="font-size:.78rem;opacity:.75;white-space:nowrap"></div></div>';
    }

    $today   = new DateTime('today');
    $dTermin = new DateTime($termin);
    $dateHtml = '<div class="termin-date" style="font-size:.78rem;opacity:.75;white-space:nowrap">'.fmt_date_dmy($termin).'</div>';

    if($teslim && $teslim!=='0000-00-00'){
        $dTeslim = new DateTime($teslim);
        $diff = (int)$dTeslim->diff($dTermin)->format('%r%a'); // teslim - termin
        if($dTeslim < $dTermin){
            return '<div class="termin-badge" style="'.$wrapStyle.'"><span class="badge green" style="'.$badgeBase.'">'.abs($diff).' gün önce teslim</span>'.$dateHtml.'</div>';
        } elseif($dTeslim == $dTermin){
            return '<div class="termin-badge" style="'.$wrapStyle.'"><span class="badge green" style="'.$badgeBase.'">Tam gününde teslim</span>'.$dateHtml.'</div>';
        } else {
            return '<div class="termin-badge" style="'.$wrapStyle.'"><span class="badge red" style="'.$badgeBase.'">'.abs($diff).' gün gecikmeli teslim</span>'.$dateHtml.'</div>';
        }
    }

    $diff = (int)$today->diff($dTermin)->format('%r%a'); // termin - bugün
    if($diff > 0){
        return '<div class="termin-badge" style="'.$wrapStyle.'"><span class="badge orange" style="'.$badgeBase.'">'.$diff.' gün kaldı</span>'.$dateHtml.'</div>';
    } elseif($diff == 0){
        return '<div class="termin-badge" style="'.$wrapStyle.'"><span class="badge orange" style="'.$badgeBase.'">Bugün</span>'.$dateHtml.'</div>';
    } else {
        return '<div class="termin-badge" style="'.$wrapStyle.'"><span class="badge red" style="'.$badgeBase.'">'.abs($diff).' gün gecikti</span>'.$dateHtml.'</div>';
    }
}
?>
<?php while($o = $stmt->fetch()): ?>
    <tr class="order-row" data-order-id="<?= (int)$o['id'] ?>"><td><input type='checkbox' class='orderCheck' name='order_ids[]' value='<?= (int)$o['id'] ?>'></td><td><div class="twolines"><?= h($o['customer_name']) ?></div></td>
      <td><div class="twolines"><?= h($o['proje_adi']) ?></div></td>
      <td><?= h($o['order_code']) ?></td>
      <td><?= render_status_pill($o['status']); ?></td>
      <td><div style="color:#000; font-size:12px; display:flex;justify-content:center;align-items:center;width:100%"><?= fmt_date_dmy($o['siparis_tarihi'] ?? null) ?></div></td>
      <td><div style="color:#000; font-size:12px; display:flex;justify-content:center;align-items:center;width:100%"><?= termin_badge_html($o['termin_tarihi'] ?? null, $o['teslim_tarihi'] ?? null) ?></div></td>
      <td><div style="color:#000; font-size:12px; display:flex;justify-content:center;align-items:center;width:100%"><?= fmt_date_dmy($o['baslangic_tarihi'] ?? null) ?></div></td>
      <td><div style="color:#000; font-size:12px; display:flex;justify-content:center;align-items:center;width:100%"><?= bitis_badge_html($o['bitis_tarihi'] ?? null, $o['termin_tarihi'] ?? null) ?></div></td>
      <td><div style="color:#000; font-size:12px; display:flex;justify-content:center;align-items:center;width:100%"><?= fmt_date_dmy($o['teslim_tarihi'] ?? null) ?></div></td>
      <td class="right">
        <a class="btn" href="order_edit.php?id=<?= (int)$o['id'] ?>" title="Düzenle" aria-label="Düzenle"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zm2.92 2.33H5v-.92l8.06-8.06.92.92L5.92 19.58zM20.71 7.04a1 1 0 0 0 0-1.41l-2.34-2.34a1 1 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg></a>
        <a class="btn" href="order_view.php?id=<?= (int)$o['id'] ?>" title="Görüntüle" aria-label="Görüntüle"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 5c-7.633 0-11 7-11 7s3.367 7 11 7 11-7 11-7-3.367-7-11-7zm0 12a5 5 0 1 1 .001-10.001A5 5 0 0 1 12 17z"/><circle cx="12" cy="12" r="3"/></svg></a>
<?php $___role = current_user()['role'] ?? ''; if (in_array($___role, ['admin','sistem_yoneticisi'], true)): ?>
        <a class="btn primary" href="order_pdf.php?id=<?= (int)$o['id'] ?>" title="STF PDF" aria-label="STF PDF">STF</a>
        
        <?php endif; ?><a class="btn btn-ustf" href="order_pdf_uretim.php?id=<?= (int)$o['id'] ?>" title="ÜSTF PDF" aria-label="ÜSTF PDF">ÜSTF</a>
      
<?php if ((function_exists('has_role') && has_role('admin')) || ((function_exists('current_user') ? (current_user()['role'] ?? '') : '') === 'admin')): ?>
  <a class="btn btn-danger" href="/order_delete.php?id=<?= (int)$o['id'] ?>&confirm=EVET" onclick="return confirm('Bu siparişi kalıcı olarak silmek istediğinize emin misiniz? Bu işlem geri alınamaz.');">Sil</a>
<?php endif; ?>
</td>
    </tr>
<?php endwhile; ?>
  </table>
<script>
(function(){
  function setupRowClicks(){
    document.querySelectorAll('tr.order-row').forEach(function(tr){
      tr.addEventListener('click', function(e){
        if (e.target.closest('a,button,input,select,label,textarea,.btn,.orderCheck,svg,path')) return;
        var id = tr.dataset.orderId;
        if (id) { window.location.href = 'order_edit.php?id='+id; }
      });
    });
  }
  document.addEventListener('DOMContentLoaded', setupRowClicks);
  if (document.readyState !== 'loading') setupRowClicks();
})();
</script>

<?php if (($action ?? 'list') === 'list' && ($total_pages ?? 1) > 1): 
  $qs = $_GET; unset($qs['page']);
  $base = 'orders.php' . (empty($qs) ? '' : ('?' . http_build_query($qs)));
  if (!function_exists('__orders_page_link')) {
    function __orders_page_link($p,$base){ return $base . (strpos($base,'?')!==false ? '&' : '?') . 'page=' . (int)$p; }
  }
  $first_link = __orders_page_link(1, $base);
  $prev_link  = __orders_page_link(max(1,$page-1), $base);
  $next_link  = __orders_page_link(min($total_pages,$page+1), $base);
  $last_link  = __orders_page_link($total_pages, $base);
  $window = 2;
  $start = max(1, $page - $window);
  $end   = min($total_pages, $page + $window);
?>
<div class="row" style="color:#000; font-size:14px; margin:10px 0; display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap;">
  <!-- pager removed -->

  <form method="get" class="row" style="color:#000; font-size:14px; gap:6px; align-items:center; flex:0 0 auto;">
    <label>Sayfa:</label>
    <input type="number" name="page" value="<?= (int)$page ?>" min="1" max="<?= (int)$total_pages ?>" style="color:#000; font-size:14px; width:72px">
    <?php foreach($qs as $k=>$v): ?>
      <input type="hidden" name="<?= h($k) ?>" value="<?= h($v) ?>">
    <?php endforeach; ?>
    <button class="btn">Git</button>
  </form>
</div>
<?php endif; ?>
</div>
</div>


<script>
function collectBulkIds(form){
  var checks = document.querySelectorAll('.orderCheck:checked');
  // Temizle (sayfayı yeniden göndermelerde çoğalmaması için)
  Array.from(form.querySelectorAll('input[name="order_ids[]"]')).forEach(function(el){ el.remove(); });
  var count = 0;
  checks.forEach(function(cb){
    var val = cb.value;
    if (val && /^\d+$/.test(val)) {
      var hidden = document.createElement('input');
      hidden.type = 'hidden';
      hidden.name = 'order_ids[]';
      hidden.value = val;
      form.appendChild(hidden);
      count++;
    }
  });
  if (count === 0) { alert('Lütfen en az bir sipariş seçin.'); return false; }
  // durum seçili mi?
  var sel = form.querySelector('select[name="bulk_status"]');
  if (!sel || !sel.value) { alert('Lütfen bir üretim durumu seçin.'); return false; }
  return true;
}
</script>


<?php include __DIR__ . '/includes/footer.php'; ?>
<!-- ren-toast-script -->
<script src="assets/js/mail_toast.js"></script>



<script>
document.addEventListener('DOMContentLoaded', function () {
  // Only action column (11th) anchors in the orders list
  document.querySelectorAll('.orders-table tr td:nth-child(11) a')
    .forEach(function(a){
      a.setAttribute('target','_blank');
      a.setAttribute('rel','noopener noreferrer');
    });
});
</script>

<!-- injected: align page form + ensure pager presence -->
<script id="orders-pager-aligner">
document.addEventListener('DOMContentLoaded', function () {
  try {
    // Find the "Sayfa:" form
    var pageForm = null;
    var labels = Array.from(document.querySelectorAll('label'));
    var lbl = labels.find(function(l) { return /\bSayfa\s*:\s*/.test(l.textContent || ''); });
    if (lbl) pageForm = lbl.closest('form');

    // Find the pager (prefer the one near the status filter / table)
    var pager = null;
    var pagers = Array.from(document.querySelectorAll('.pager'));
    if (pagers.length) {
      // pick the first visible one or the first
      pager = pagers.find(function(p) { return p.offsetParent !== null; }) || pagers[0];
    }

    // If no pager exists (e.g., single page), create a placeholder so UI is consistent
    if (!pager) {
      pager = document.createElement('div');
      pager.className = 'pager d-flex gap-1';
      pager.style.cssText = 'display:flex; align-items:center; flex-wrap:wrap; gap:8px; margin:6px 0;';
      function btn(txt) { var s = document.createElement('span'); s.className='btn disabled'; s.textContent = txt; return s; }
      pager.appendChild(btn('« İlk'));
      pager.appendChild(btn('‹ Önceki'));
      var num = document.createElement('span'); num.className='btn disabled'; num.textContent='1'; pager.appendChild(num);
      pager.appendChild(btn('Sonraki ›'));
      pager.appendChild(btn('Son »'));
      // Insert after the horizontal status filter if possible, else after the first table
      var filt = document.querySelector('.status-quick-filter');
      if (filt && filt.parentElement) { filt.parentElement.insertAdjacentElement('afterend', pager); }
      else { var tbl = document.querySelector('table'); if (tbl) tbl.insertAdjacentElement('afterend', pager); }
    }

    // Ensure pager is flex & visible
    pager.style.display = 'flex';
    pager.style.alignItems = 'center';
    pager.style.flexWrap = 'wrap';
    pager.style.gap = '8px';

    // Move the "Sayfa" form to the right end of pager
    if (pageForm && pager && !pager.contains(pageForm)) {
      pageForm.style.marginLeft = 'auto';
      pageForm.style.display = 'inline-flex';
      pageForm.style.alignItems = 'center';
      pageForm.style.gap = '8px';
      pager.appendChild(pageForm);
    }
  } catch (e) {
    console && console.warn && console.warn('orders pager aligner error:', e);
  }
});
</script>




<!-- mail-button-inject -->
<script>
(function(){
  function mk(id){
    var a=document.createElement('a');
    a.className='btn';
    a.href='order_send_mail.php?id='+id;
    a.title='E-posta gönder';
    a.setAttribute('aria-label','E-posta gönder');
    a.innerHTML='✉︎';
    return a;
  }
  function injectList(){
    // Find the small "eye" (view) anchor and insert one mail button after it
    var eyes = document.querySelectorAll('a[title="Görüntüle"], a[aria-label="Görüntüle"], a[href^="order_view.php?id="]');
    eyes.forEach(function(eye){
      if(eye.dataset.mailInjected) return;
      var href=eye.getAttribute('href')||'';
      var m=href.match(/order_view\.php\?id=(\d+)/);
      if(!m) return;
      var id=m[1];
      // don't duplicate if next sibling already points to order_send_mail
      var ns=eye.nextElementSibling;
      if(ns && ns.tagName==='A' && /order_send_mail\.php\?id=/.test(ns.getAttribute('href')||'')) {
        eye.dataset.mailInjected='1'; return;
      }
      eye.after(mk(id));
      eye.dataset.mailInjected='1';
    });
  }
  if(document.readyState==='loading'){
    document.addEventListener('DOMContentLoaded', injectList);
  } else { injectList(); }
  // In case of dynamic reloads/pagination
  setTimeout(injectList, 500);
  setTimeout(injectList, 1500);
})();
</script>

