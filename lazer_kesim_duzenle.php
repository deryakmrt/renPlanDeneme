<?php
// lazer_kesim_duzenle.php
require_once __DIR__ . '/includes/helpers.php';
require_login();
$db = pdo();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: lazer_kesim.php'); exit; }

// Yetki Kontrolü
$u = current_user();
$role = $u['role'] ?? 'user';
$can_see_drafts = in_array($role, ['admin', 'sistem_yoneticisi'], true);

// POST İşlemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. DURUMU BELİRLE
    $new_status = $_POST['status'] ?? 'taslak'; 

    // Eğer "Yayınla" butonuna basıldıysa durumu zorla 'tedarik' yap
    if (isset($_POST['yayinla_butonu'])) {
        $new_status = 'tedarik';
    }

    // 2. TARİHLERİ DÜZENLE (Boşsa NULL yap)
    $order_date    = !empty($_POST['order_date'])    ? $_POST['order_date']    : null;
    $deadline_date = !empty($_POST['deadline_date']) ? $_POST['deadline_date'] : null;
    $start_date    = !empty($_POST['start_date'])    ? $_POST['start_date']    : null;
    $end_date      = !empty($_POST['end_date'])      ? $_POST['end_date']      : null;
    $delivery_date = !empty($_POST['delivery_date']) ? $_POST['delivery_date'] : null;

    // 3. VERİTABANINA GÜNCELLE
    $sql = "UPDATE lazer_orders SET 
            customer_id=?, project_name=?, order_code=?, status=?, 
            order_date=?, deadline_date=?, start_date=?, end_date=?, delivery_date=?, notes=? 
            WHERE id=?";
    $stmt = $db->prepare($sql);
    $stmt->execute([
        $_POST['customer_id'],
        $_POST['project_name'],
        $_POST['order_code'],
        $new_status,
        $order_date,
        $deadline_date,
        $start_date,
        $end_date,
        $delivery_date,
        $_POST['notes'] ?? null, // Not verisi
        $id
    ]);
    header('Location: lazer_kesim.php');
    exit;
}

require_once __DIR__ . '/includes/header.php';

// Veriyi Çek
$stmt = $db->prepare("SELECT * FROM lazer_orders WHERE id = ?");
$stmt->execute([$id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) { echo "Sipariş bulunamadı."; require_once __DIR__ . '/includes/footer.php'; exit; }

// Müşterileri Çek
$customers = $db->query("SELECT * FROM customers ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Tarihleri Düzelt (0000-00-00 -> NULL)
function safe_date($d) { return ($d && $d !== '0000-00-00') ? $d : ''; }
?>

<div class="card" style="max-width:800px; margin:0 auto;">
    <h2>Sipariş Düzenle (#<?= $id ?>)</h2>
    
    <form method="post">
        <div class="grid g2" style="gap:20px;">
            <div>
                <label>Müşteri</label>
                <select name="customer_id" required style="width:100%">
                    <?php foreach ($customers as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $order['customer_id'] == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label>Durum</label>
                <?php if ($order['status'] === 'taslak'): ?>
                    <div style="background:#f3f4f6; border:1px solid #d1d5db; padding:8px 12px; border-radius:6px; color:#374151; font-weight:bold; display:flex; align-items:center; gap:8px;">
                        <span>🔒 Taslak (Gizli)</span>
                    </div>
                    <div style="font-size:11px; color:#6b7280; margin-top:4px;">Yayınla diyene kadar kimse görmez.</div>
                    <input type="hidden" name="status" value="taslak">
                <?php else: ?>
                    <select name="status" style="width:100%">
                        <?php 
                        $statuses = [
                            'taslak' => '🔒Taslak', 
                            'tedarik' => 'Tedarik', 
                            'kesimde' => 'Kesim', 
                            'sevkiyat' => 'Sevkiyat', 
                            'teslim_edildi' => 'Teslim Edildi'
                        ];
                        
                        foreach($statuses as $k=>$v): 
                            // KISITLAMA: Yetkisi olmayan, yayındaki siparişi tekrar 'Taslak' yapamaz
                            if ($k === 'taslak' && !$can_see_drafts) continue;
                        ?>
                            <option value="<?= $k ?>" <?= $order['status'] === $k ? 'selected' : '' ?>><?= $v ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
            </div>
            
            <div style="grid-column: span 2;">
                <label>Proje Adı</label>
                <input type="text" name="project_name" value="<?= htmlspecialchars($order['project_name']) ?>" required style="width:100%">
            </div>
            
            <div><label>Sipariş Kodu</label><input type="text" name="order_code" value="<?= htmlspecialchars($order['order_code']) ?>" style="width:100%"></div>
            <div><label>Sipariş Tarihi</label><input type="date" name="order_date" value="<?= safe_date($order['order_date']) ?>" style="width:100%"></div>
            
            <div><label>Termin Tarihi</label><input type="date" name="deadline_date" value="<?= safe_date($order['deadline_date']) ?>" style="width:100%"></div>
            <div><label>Başlangıç Tarihi</label><input type="date" name="start_date" value="<?= safe_date($order['start_date']) ?>" style="width:100%"></div>
            
            <div><label>Bitiş Tarihi</label><input type="date" name="end_date" value="<?= safe_date($order['end_date']) ?>" style="width:100%"></div>
            <div><label>Teslim Tarihi</label><input type="date" name="delivery_date" value="<?= safe_date($order['delivery_date']) ?>" style="width:100%"></div>
            
            <div style="grid-column: span 2;">
                <label>Sipariş Notları</label>
                <textarea name="notes" rows="5" style="width:100%; border:1px solid #cbd5e1; border-radius:6px; padding:10px;" placeholder="Üretim notları, malzeme detayları vb..."><?= htmlspecialchars($order['notes'] ?? '') ?></textarea>
            </div>
        </div>

        <div class="row" style="justify-content:flex-end; gap:10px; margin-top:20px; align-items:center;">
            
            <a href="lazer_kesim.php" class="btn">Vazgeç</a>

            <?php if ($order['status'] === 'taslak'): ?>
                <button type="submit" name="yayinla_butonu" value="1" class="btn" style="background-color:#db2777; color:white; font-weight:bold; box-shadow:0 4px 10px rgba(219, 39, 119, 0.3);">
                    🚀 SİPARİŞİ YAYINLA
                </button>
            <?php endif; ?>

            <button type="submit" class="btn primary">Güncelle</button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>