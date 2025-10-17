<?php
require_once __DIR__ . '/includes/helpers.php';
require_login();

$db = pdo();

// Varsayılan order
$order = [
    'id' => 0,
    'order_code' => next_order_code(),
    'customer_id' => null,
    'status' => 'pending',
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
        $insIt->execute([$order_id, $pid, $n, trim($units[$i] ?? 'adet'), (float)($qtys[$i] ?? 0), (float)($prices[$i] ?? 0), trim($ozet[$i] ?? ''), trim($kalan[$i] ?? '')]);
    }


    // === MAIL TETİKLEYİCİ (Yeni Sipariş) ===
    try {
        require_once __DIR__ . '/mailing/notify.php';
        if (function_exists('rp_sql_ensure')) {
            rp_sql_ensure();
        }

        // Reply-To hazırla
        $___reply_to = null;
        $___cu_email = null;
        if (function_exists('current_user')) {
            $___cu = current_user();
            if (!empty($___cu['email'])) {
                $___cu_email = $___cu['email'];
            }
        }
        if (isset($_SESSION['user_email']) && $_SESSION['user_email']) {
            $___cu_email = $_SESSION['user_email'];
        }
        if ($___cu_email && filter_var($___cu_email, FILTER_VALIDATE_EMAIL)) {
            $___reply_to = $___cu_email;
        }

        // DOĞRU PAYLOAD: order_send_mail.php ile AYNI YAPIDA
        // Önce customer bilgilerini çek
        $customer_name = '';
        $customer_email = '';
        $customer_phone = '';
        $billing_address = '';
        $shipping_address = '';

        if ($order['customer_id'] > 0) {
            try {
                $cst = $db->prepare("SELECT name, email, phone, billing_address, shipping_address FROM customers WHERE id=? LIMIT 1");
                $cst->execute([$order['customer_id']]);
                if ($c = $cst->fetch(PDO::FETCH_ASSOC)) {
                    $customer_name = $c['name'] ?? '';
                    $customer_email = $c['email'] ?? '';
                    $customer_phone = $c['phone'] ?? '';
                    $billing_address = $c['billing_address'] ?? '';
                    $shipping_address = $c['shipping_address'] ?? '';
                }
            } catch (Throwable $e) {
            }
        }

        // Kalemleri çek (방금 eklendi)
        $items_mail = [];
        try {
            $it = $db->prepare("SELECT oi.*, p.sku, p.image FROM order_items oi LEFT JOIN products p ON p.id=oi.product_id WHERE oi.order_id=? ORDER BY oi.id ASC");
            $it->execute([$order_id]);
            $items_mail = $it->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
        }

        // Base URL (görsel için)
        $scheme = (!empty($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http'));
        $host = $_SERVER['HTTP_HOST'];
        $base_url = $scheme . '://' . $host;

        // Görsel URL düzeltme fonksiyonu
        $fix_image_url = function ($img) use ($base_url) {
            $img = trim($img);
            if (empty($img)) return '';
            if (preg_match('#^https?://#i', $img)) return $img;
            if ($img[0] === '/') return $base_url . $img;
            if (preg_match('#^uploads/#', $img)) return $base_url . '/' . $img;
            if (substr($img, 0, 2) === './') return $base_url . substr($img, 1);
            return $base_url . '/uploads/' . $img;
        };

        // Tarih formatlama (YYYY-MM-DD -> DD-MM-YYYY)
        $fmt_date = function ($val) {
            if (!isset($val)) return '';
            $val = trim((string)$val);
            if ($val === '' || $val === '0000-00-00' || $val === '0000-00-00 00:00:00' || $val === '1970-01-01') return '';
            if (preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:\s+\d{2}:\d{2}:\d{2})?$/', $val, $m)) {
                return $m[3] . '-' . $m[2] . '-' . $m[1];
            }
            $ts = @strtotime($val);
            if (!$ts || $ts <= 0) return '';
            $year = (int)date('Y', $ts);
            if ($year < 1900 || $year > 2100) return '';
            return date('d-m-Y', $ts);
        };

        // PAYLOAD - order_send_mail.php ile AYNI FORMATTA
        $payload_order = [
            'order_code'          => (string)$order['order_code'],
            'revizyon_no'         => (string)$order['revizyon_no'],
            'customer_name'       => $customer_name,
            'customer_id'         => (string)$order['customer_id'],
            'email'               => $customer_email,
            'phone'               => $customer_phone,
            'billing_address'     => $billing_address,
            'shipping_address'    => $shipping_address,
            'siparis_veren'       => (string)$order['siparis_veren'],
            'siparisi_alan'       => (string)$order['siparisi_alan'],
            'siparisi_giren'      => (string)$order['siparisi_giren'],
            'siparis_tarihi'      => $fmt_date($order['siparis_tarihi']),
            'fatura_para_birimi'  => (string)($order['fatura_para_birimi'] ?: $order['currency']),
            'odeme_para_birimi'   => (string)$order['odeme_para_birimi'],
            'odeme_kosulu'        => (string)$order['odeme_kosulu'],
            'proje_adi'           => (string)$order['proje_adi'],
            'nakliye_turu'        => (string)$order['nakliye_turu'],
            'termin_tarihi'       => $fmt_date($order['termin_tarihi']),
            'baslangic_tarihi'    => $fmt_date($order['baslangic_tarihi']),
            'bitis_tarihi'        => $fmt_date($order['bitis_tarihi']),
            'teslim_tarihi'       => $fmt_date($order['teslim_tarihi']),
            'notes'               => (string)$order['notes'],
            'items'               => [] // items, kalemler değil!
        ];

        // Kalemleri ekle
        foreach ($items_mail as $r) {
            $payload_order['items'][] = [
                'gorsel'          => $fix_image_url($r['image'] ?? ''),
                'urun_kod'        => (string)($r['sku'] ?? ''),
                'urun_adi'        => (string)($r['name'] ?? ''),
                'urun_aciklama'   => (string)($r['urun_ozeti'] ?? ''),
                'kullanim_alani'  => (string)($r['kullanim_alani'] ?? ''),
                'miktar'          => (float)($r['qty'] ?? 0),
                'birim'           => (string)($r['unit'] ?? ''),
                'termin_tarihi'   => $fmt_date($r['termin_tarihi'] ?? $order['termin_tarihi'] ?? ''),
                'fiyat'           => (float)($r['price'] ?? 0),
            ];
        }

        // Şimdi mail gönder
        $___ok = false;
        $___err = '';
        if (function_exists('rp_notify_order_created')) {
            list($___ok, $___err) = rp_notify_order_created($order_id, $payload_order);
        }

        // FAIL ise doğrudan gönder
        if (!$___ok) {
            error_log('notify_order_created FAILED: ' . $___err);
            require_once __DIR__ . '/mailing/mailer.php';
            require_once __DIR__ . '/mailing/templates.php';

            $toList = $ccList = $bccList = [];
            if (function_exists('rp_get_recipients')) {
                list($toList, $ccList, $bccList) = rp_get_recipients();
            } else {
                $cfg = function_exists('rp_cfg') ? rp_cfg() : [];
                $toRaw = (string)($cfg['notify']['recipients'] ?? '');
                foreach (explode(',', $toRaw) as $em) {
                    $em = trim($em);
                    if ($em) $toList[] = $em;
                }
            }

            if ($___reply_to) {
                $bccList[] = $___reply_to;
            }

            $viewUrl = function_exists('rp_build_view_url') ? rp_build_view_url('order', $order_id) : ($base_url . '/order_view.php?id=' . $order_id);
            $subject2 = rp_subject('order', $payload_order);
            $html2    = rp_email_html('order', $payload_order, $viewUrl);
            $text2    = rp_email_text('order', $payload_order, $viewUrl);

            list($ok2, $err2) = rp_send_mail($subject2, $html2, $text2, $toList, $ccList, $bccList, $___reply_to);
            if (!$ok2) {
                error_log('direct_send FAILED: ' . $err2);
            }
        }
    } catch (Throwable $e) {
        error_log('notify_order_created exception: ' . $e->getMessage());
    }
    // === /MAIL TETİKLEYİCİ ===


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
