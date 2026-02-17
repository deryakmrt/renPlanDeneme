<?php
// lazer_kesim_ekle.php

// 1. ÖNCE YARDIMCI FONKSİYONLARI ÇAĞIR (HTML ÇIKTISI VERMEZ)
require_once __DIR__ . '/includes/helpers.php';
require_login();
$db = pdo();

// 2. POST İŞLEMİNİ EN BAŞTA YAP (YÖNLENDİRME HATASINI ÇÖZER)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $db->prepare("INSERT INTO lazer_orders (customer_id, project_name, order_code, status, order_date, deadline_date) VALUES (?, ?, ?, 'taslak', ?, ?)");
    // Tarih boşsa NULL yap, doluysa kendisini kullan
    $order_date    = !empty($_POST['order_date'])    ? $_POST['order_date']    : null;
    $deadline_date = !empty($_POST['deadline_date']) ? $_POST['deadline_date'] : null;

    $stmt->execute([
        $_POST['customer_id'],
        $_POST['project_name'],
        $_POST['order_code'],
        $order_date,
        $deadline_date
    ]);
    
    // Yönlendirme artık hata vermez çünkü HTML çıktısı henüz başlamadı
    header('Location: lazer_kesim.php');
    exit;
}

// 3. OTOMATİK SİPARİŞ KODU OLUŞTUR (2026...)
$yearPrefix = date('Y'); // Örn: 2026

// Bu yıla ait en büyük sayısal kodu bul
$stmt = $db->prepare("SELECT MAX(CAST(order_code AS UNSIGNED)) FROM lazer_orders WHERE order_code LIKE ?");
$stmt->execute([$yearPrefix . '%']);
$max_code = $stmt->fetchColumn();

if ($max_code) {
    // Varsa bir artır (2026005 -> 2026006)
    $next_code = $max_code + 1;
} else {
    // Yoksa bu yılın ilk siparişi (2026001)
    $next_code = $yearPrefix . '001';
}

// 4. HTML ÇIKTISINI ŞİMDİ BAŞLAT
require_once __DIR__ . '/includes/header.php';

// Müşterileri Çek
$customers = $db->query("SELECT * FROM customers ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="card" style="max-width:600px; margin:0 auto;">
    <h2>Yeni Lazer Kesim Siparişi</h2>
    <form method="post">
        <div class="row mb">
            <label>Müşteri</label>
            <select name="customer_id" required style="width:100%">
                <option value="">Seçiniz...</option>
                <?php foreach ($customers as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="row mb">
            <label>Proje Adı</label>
            <input type="text" name="project_name" required style="width:100%">
        </div>
        <div class="row mb">
            <label>Sipariş Kodu (Otomatik)</label>
            <input type="text" name="order_code" value="<?= $next_code ?>" style="width:100%; font-family:monospace; font-weight:bold; letter-spacing:1px;">
        </div>
        <div class="row mb">
            <label>Sipariş Tarihi</label>
            <input type="date" name="order_date" value="<?= date('Y-m-d') ?>" style="width:100%">
        </div>
        <div class="row mb">
            <label>Termin Tarihi</label>
            <input type="date" name="deadline_date" style="width:100%">
        </div>
        <div class="row" style="justify-content:flex-end; gap:10px; margin-top:20px;">
            <a href="lazer_kesim.php" class="btn">İptal</a>
            <button type="submit" class="btn primary">Kaydet</button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>