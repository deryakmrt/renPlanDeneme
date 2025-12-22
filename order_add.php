<?php
require_once __DIR__ . '/includes/helpers.php';
require_login();

$db = pdo();

// Varsayılan order
$order = [
    'id' => 0,
    'order_code' => next_order_code(),
    'customer_id' => null,
    'status' => 'taslak_gizli',
    'currency' => 'TRY',
    'termin_tarihi' => null,
    'baslangic_tarihi' => null,
    'bitis_tarihi' => null,
    'teslim_tarihi' => null,
    'notes' => '',
    'siparis_veren' => '',
    'siparisi_alan' => '',
    'siparisi_giren' => '',
    'siparis_tarihi' => null,
    'fatura_para_birimi' => '',
    'proje_adi' => '',
    'revizyon_no' => '',
    'nakliye_turu' => '',
    'odeme_kosulu' => '',
    'odeme_para_birimi' => ''
];

if (method('POST')) {
    csrf_check();

    // Para birimi uyumluluk haritalama
    if (isset($_POST['odeme_para_birimi'])) {
        $__tmp_odeme = $_POST['odeme_para_birimi'];
        if ($__tmp_odeme === 'TL') {
            $_POST['currency'] = 'TRY';
        } elseif ($__tmp_odeme === 'EUR') {
            $_POST['currency'] = 'EUR';
        } elseif ($__tmp_odeme === 'USD') {
            $_POST['currency'] = 'USD';
        }
    }

    $fields = [
        'order_code',
        'customer_id',
        'status',
        'currency',
        'termin_tarihi',
        'baslangic_tarihi',
        'bitis_tarihi',
        'teslim_tarihi',
        'notes',
        'siparis_veren',
        'siparisi_alan',
        'siparisi_giren',
        'siparis_tarihi',
        'fatura_para_birimi',
        'proje_adi',
        'revizyon_no',
        'nakliye_turu',
        'odeme_kosulu',
        'odeme_para_birimi'
    ];
    foreach ($fields as $f) {
        $order[$f] = $_POST[$f] ?? $order[$f];
    }
    $order['customer_id'] = (int)$order['customer_id'];

    //Çözüm 2: Retry Mechanism
    $attempt = 0;
    $order_id = null;

    while ($attempt < 3) {
        try {
            // Her denemede yeni kod al
            $order['order_code'] = next_order_code();

            $ins = $db->prepare("INSERT INTO orders (order_code, customer_id, status, currency, termin_tarihi, baslangic_tarihi, bitis_tarihi, teslim_tarihi, notes,
                              siparis_veren, siparisi_alan, siparisi_giren, siparis_tarihi, fatura_para_birimi, proje_adi, revizyon_no, nakliye_turu, odeme_kosulu, odeme_para_birimi)
                             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $ins->execute([
                $order['order_code'],
                $order['customer_id'],
                $order['status'],
                $order['currency'],
                $order['termin_tarihi'],
                $order['baslangic_tarihi'],
                $order['bitis_tarihi'],
                $order['teslim_tarihi'],
                $order['notes'],
                $order['siparis_veren'],
                $order['siparisi_alan'],
                $order['siparisi_giren'],
                $order['siparis_tarihi'],
                $order['fatura_para_birimi'],
                $order['proje_adi'],
                $order['revizyon_no'],
                $order['nakliye_turu'],
                $order['odeme_kosulu'],
                $order['odeme_para_birimi']
            ]);
            $order_id = (int)$db->lastInsertId();
            break;  // Başarılı, döngüden çık

        } catch (PDOException $e) {
            // Sadece duplicate order_code hatası ise tekrar dene
            if ($e->getCode() == 23000 && strpos($e->getMessage(), 'order_code') !== false) {
                $attempt++;         // Tekrar dene
                usleep(100000);     // 0.1 saniye bekle (diğeri bitsin)
                if ($attempt >= 3) {
                    die('Sipariş kodu oluşturulamadı, lütfen tekrar deneyin.');
                }
            } else {
                // Başka bir hata, fırlat
                throw $e;
            }
        }
    }

    // Eğer hala order_id yoksa
    if (!$order_id) {
        die('Sipariş kaydedilemedi.');
    }

    // Kalemler
    $p_ids  = $_POST['product_id'] ?? [];
    $names  = $_POST['name'] ?? [];
    $units  = $_POST['unit'] ?? [];
    $qtys   = $_POST['qty'] ?? [];
    $prices = $_POST['price'] ?? [];
    $ozet   = $_POST['urun_ozeti'] ?? [];
    $kalan  = $_POST['kullanim_alani'] ?? [];
    for ($i = 0; $i < count($names); $i++) {
        $n = trim($names[$i] ?? '');
        if ($n === '') continue;

        // product_id kontrolü - 0 ise NULL yap
        $pid = (int)($p_ids[$i] ?? 0);
        if ($pid === 0) $pid = null;

        $insIt = $db->prepare("INSERT INTO order_items (order_id, product_id, name, unit, qty, price, urun_ozeti, kullanim_alani) VALUES (?,?,?,?,?,?,?,?)");
        
        // Miktar (virgülü noktaya çevir)
        $raw_qty = $qtys[$i] ?? 0;
        $val_qty = is_string($raw_qty) ? (float)str_replace(',', '.', $raw_qty) : (float)$raw_qty;

        // Fiyat (virgülü noktaya çevir)
        $raw_prc = $prices[$i] ?? 0;
        $val_prc = is_string($raw_prc) ? (float)str_replace(',', '.', $raw_prc) : (float)$raw_prc;

        $insIt->execute([
            $order_id, 
            $pid, 
            $n, 
            trim($units[$i] ?? 'adet'), 
            $val_qty, 
            $val_prc, 
            trim($ozet[$i] ?? ''), 
            trim($kalan[$i] ?? '')
        ]);
    }




    redirect('orders.php');
}

// Dropdown verileri
$customers = $db->query("SELECT id,name FROM customers ORDER BY name ASC")->fetchAll();
$products  = $db->query("SELECT id,sku,name,unit,price,urun_ozeti,kullanim_alani,image FROM products ORDER BY name ASC")->fetchAll();
$items = [];

include __DIR__ . '/includes/header.php';
$mode = 'new';
include __DIR__ . '/includes/order_form.php';
include __DIR__ . '/includes/footer.php';
