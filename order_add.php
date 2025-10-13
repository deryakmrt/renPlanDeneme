<?php
require_once __DIR__ . '/includes/helpers.php';
require_login();

$db = pdo();

// Varsayılan order
$order = [
  'id'=>0,
  'order_code'=>next_order_code(),
  'customer_id'=>null,
  'status'=>'pending',
  'currency'=>'TRY',
  'termin_tarihi'=>null,
  'baslangic_tarihi'=>null,
  'bitis_tarihi'=>null,
  'teslim_tarihi'=>null,
  'notes'=>'',
  'siparis_veren'=>'','siparisi_alan'=>'','siparisi_giren'=>'',
  'siparis_tarihi'=>null,'fatura_para_birimi'=>'','proje_adi'=>'',
  'revizyon_no'=>'','nakliye_turu'=>'','odeme_kosulu'=>'','odeme_para_birimi'=>''
];

if (method('POST')) {
  csrf_check();

  // Para birimi uyumluluk haritalama
  if (isset($_POST['odeme_para_birimi'])) {
    $__tmp_odeme = $_POST['odeme_para_birimi'];
    if ($__tmp_odeme === 'TL')   { $_POST['currency'] = 'TRY'; }
    elseif ($__tmp_odeme === 'EUR') { $_POST['currency'] = 'EUR'; }
    elseif ($__tmp_odeme === 'USD') { $_POST['currency'] = 'USD'; }
  }

  $fields = ['order_code','customer_id','status','currency','termin_tarihi','baslangic_tarihi','bitis_tarihi','teslim_tarihi','notes',
    'siparis_veren','siparisi_alan','siparisi_giren','siparis_tarihi','fatura_para_birimi','proje_adi','revizyon_no','nakliye_turu','odeme_kosulu','odeme_para_birimi'];
  foreach ($fields as $f) { $order[$f] = $_POST[$f] ?? $order[$f]; }
  $order['customer_id'] = (int)$order['customer_id'];

  $ins = $db->prepare("INSERT INTO orders (order_code, customer_id, status, currency, termin_tarihi, baslangic_tarihi, bitis_tarihi, teslim_tarihi, notes,
                        siparis_veren, siparisi_alan, siparisi_giren, siparis_tarihi, fatura_para_birimi, proje_adi, revizyon_no, nakliye_turu, odeme_kosulu, odeme_para_birimi)
                       VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
  $ins->execute([
    $order['order_code'],$order['customer_id'],$order['status'],$order['currency'],$order['termin_tarihi'],$order['baslangic_tarihi'],$order['bitis_tarihi'],$order['teslim_tarihi'],$order['notes'],
    $order['siparis_veren'],$order['siparisi_alan'],$order['siparisi_giren'],$order['siparis_tarihi'],$order['fatura_para_birimi'],$order['proje_adi'],$order['revizyon_no'],$order['nakliye_turu'],$order['odeme_kosulu'],$order['odeme_para_birimi']
  ]);
  $order_id = (int)$db->lastInsertId();

  // Kalemler
  $p_ids  = $_POST['product_id'] ?? [];
  $names  = $_POST['name'] ?? [];
  $units  = $_POST['unit'] ?? [];
  $qtys   = $_POST['qty'] ?? [];
  $prices = $_POST['price'] ?? [];
  $ozet   = $_POST['urun_ozeti'] ?? [];
  $kalan  = $_POST['kullanim_alani'] ?? [];
  for ($i=0; $i<count($names); $i++) {
    $n = trim($names[$i] ?? ''); if ($n==='') continue;
    $insIt = $db->prepare("INSERT INTO order_items (order_id, product_id, name, unit, qty, price, urun_ozeti, kullanim_alani) VALUES (?,?,?,?,?,?,?,?)");
    $insIt->execute([$order_id, (int)($p_ids[$i] ?? 0), $n, trim($units[$i] ?? 'adet'), (float)($qtys[$i] ?? 0), (float)($prices[$i] ?? 0), trim($ozet[$i] ?? ''), trim($kalan[$i] ?? '')]);
  }

  
// === MAIL TETİKLEYİCİ (Yeni Sipariş) ===
try {
    require_once __DIR__ . '/mailing/notify.php';
    if (function_exists('rp_sql_ensure')) { rp_sql_ensure(); }

    // SAFE Reply-To
    $___reply_to = null;
    $___cu_email = null;
    if (function_exists('current_user')) {
        $___cu = current_user();
        if (!empty($___cu['email'])) { $___cu_email = $___cu['email']; }
    }
    if (isset($_SESSION['user_email']) && $_SESSION['user_email']) {
        $___cu_email = $_SESSION['user_email'];
    }
    if ($___cu_email && filter_var($___cu_email, FILTER_VALIDATE_EMAIL)) {
        $___reply_to = $___cu_email;
    } else {
        $___reply_to = null;
    }

    // Proje adı boşsa müşteri adını kullan
    $proje_adi2 = trim((string)($proje_adi ?? ''));
    if ($proje_adi2 === '' && !empty($customer_id)) {
        try {
            $cst = $db->prepare("SELECT name FROM customers WHERE id=? LIMIT 1");
            $cst->execute([$customer_id]);
            $proje_adi2 = (string)($cst->fetchColumn() ?: '');
        } catch (Throwable $e) {}
    }

    // Kalemleri POST'tan toparla
    $kalemler_mail = [];
    $names  = $_POST['name'] ?? [];
    $units  = $_POST['unit'] ?? [];
    $qtys   = $_POST['qty'] ?? $_POST['quantity'] ?? [];
    $prices = $_POST['price'] ?? [];
    $cnt = max(count($names), count($qtys), count($units), count($prices));
    for ($i=0; $i<$cnt; $i++) {
        $n = trim((string)($names[$i] ?? ''));
        if ($n === '') continue;
        $kalemler_mail[] = [
            'urun'  => $n,
            'miktar'=> (float)($qtys[$i] ?? 0),
            'birim' => (string)($units[$i] ?? ''),
            'fiyat' => (float)($prices[$i] ?? 0),
        ];
    }

    $siparis_eden2   = $_SESSION['user_email'] ?? (function_exists('current_user') ? (current_user()['email'] ?? '') : '');
    $siparis_tarihi2 = $siparis_tarihi ?? date('Y-m-d');
    $payload2 = [
        'ren_kodu'       => (string)($order_code ?? ''),
        'proje_adi'      => $proje_adi2,
        'talep_eden'     => (string)$siparis_eden2,
        'siparis_tarihi' => $siparis_tarihi2,
        'notlar'         => (string)($notes ?? ''),
        'kalemler'       => $kalemler_mail,
        'reply_to'       => $___reply_to,
    ];

    // Önce notify katmanı
    $___ok = false; $___err = '';
    if (function_exists('rp_notify_order_created')) {
        list($___ok,$___err) = rp_notify_order_created($order_id, $payload2);
    }

    // FAIL ise doğrudan gönder
    if (!$___ok) {
        error_log('notify_order_created FAILED: ' . $___err);
        require_once __DIR__ . '/mailing/mailer.php';
        require_once __DIR__ . '/mailing/templates.php';

        // Alıcılar
        $toList = $ccList = $bccList = [];
        if (function_exists('rp_get_recipients')) {
            list($toList, $ccList, $bccList) = rp_get_recipients();
        } else {
            $cfg = function_exists('rp_cfg') ? rp_cfg() : [];
            $toRaw = (string)($cfg['notify']['recipients'] ?? '');
            foreach (explode(',', $toRaw) as $em) { $em = trim($em); if ($em) $toList[] = $em; }
        }

        // Ek garanti için BCC: oturum kullanıcısı (geçerli e-posta ise)
        if ($___reply_to) { $bccList[] = $___reply_to; }

        $viewUrl = function_exists('rp_build_view_url') ? rp_build_view_url('order', $order_id) : ('order_view.php?id=' . $order_id);
        $subject2 = rp_subject('order', $payload2);
        $html2    = rp_email_html('order', $payload2, $viewUrl);
        $text2    = rp_email_text('order', $payload2, $viewUrl);

        list($ok2,$err2) = rp_send_mail($subject2, $html2, $text2, $toList, $ccList, $bccList);
        if (!$ok2) { error_log('direct_send FAILED: ' . $err2); }
    }
} catch (Throwable $e) {
    error_log('notify_order_created exception: '.$e->getMessage());
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
