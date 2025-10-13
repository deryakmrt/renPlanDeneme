<?php
require_once __DIR__ . '/includes/helpers.php';
require_login();

$db = pdo();
$action = $_GET['a'] ?? 'list';

// Sil (POST)
if ($action === 'delete' && method('POST')) {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    if ($id) {
        // İleride siparişlerle ilişki var; burada doğrudan silmek yerine SET NULL olacak (orders tablosu öyle tanımlı)
        $stmt = $db->prepare("DELETE FROM customers WHERE id=?");
        $stmt->execute([$id]);
    }
    redirect('customers.php');
}

// Kaydet (POST)
if (($action === 'new' || $action === 'edit') && method('POST')) {
    csrf_check();
    $id   = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $billing = trim($_POST['billing_address'] ?? '');
    $shipping = trim($_POST['shipping_address'] ?? '');
        $website = trim($_POST['website'] ?? '');


// URL normalize & validate (surgical)
if ($website !== '') {
  if (!preg_match('~^https?://~i', $website)) { $website = 'https://' . $website; }
  if (filter_var($website, FILTER_VALIDATE_URL) === false) { $website = ''; }
}
// URL normalize & validate
if ($website !== '') {
  if (!preg_match('~^https?://~i', $website)) { $website = 'https://' . $website; }
  if (filter_var($website, FILTER_VALIDATE_URL) === false) { $website = ''; }
}
// normalize website: add scheme if missing and validate
if ($website !== '') {
  if (!preg_match('~^https?://~i', $website)) { $website = 'https://' . $website; }
  if (filter_var($website, FILTER_VALIDATE_URL) === false) { $website = ''; }
}

$vergi_dairesi = trim($_POST['vergi_dairesi'] ?? '');
    $vergi_no = trim($_POST['vergi_no'] ?? '');
$ilce = trim($_POST['ilce'] ?? '');
    $il = trim($_POST['il'] ?? '');
    $ulke = trim($_POST['ulke'] ?? '');


    if ($name === '') {
        $error = 'Müşteri adı zorunlu';
    } else {
        if ($id > 0) {
            $stmt = $db->prepare("UPDATE customers SET name=?, email=?, phone=?, billing_address=?, shipping_address=?, ilce=?, il=?, ulke=?, vergi_dairesi=?, vergi_no=?, website=? WHERE id=?");
            $stmt->execute([$name,$email,$phone,$billing,$shipping,$ilce,$il,$ulke,$vergi_dairesi,$vergi_no,$website,$id]);
        } else {
            $stmt = $db->prepare("INSERT INTO customers (name,email,phone,billing_address,shipping_address,ilce,il,ulke,vergi_dairesi,vergi_no,website) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([$name,$email,$phone,$billing,$shipping,$ilce,$il,$ulke,$vergi_dairesi,$vergi_no,$website]);
            $id = (int)$db->lastInsertId();
        }
        redirect('customers.php');
    }
}

include __DIR__ . '/includes/header.php';

// Form (yeni/düzenle)
if ($action === 'new' || $action === 'edit') {
    $id = (int)($_GET['id'] ?? 0);
    $row = ['id'=>0,'name'=>'','email'=>'','phone'=>'','billing_address'=>'','shipping_address'=>'','ilce'=>'','il'=>'','ulke'=>'','vergi_dairesi'=>'','vergi_no'=>'','website'=>''];
    if ($action === 'edit' && $id) {
        $stmt = $db->prepare("SELECT * FROM customers WHERE id=?");
        $stmt->execute([$id]);
        $row = $stmt->fetch() ?: $row;
    }
    ?>
    <div class="card">
      <h2><?= $row['id'] ? 'Müşteri Düzenle' : 'Yeni Müşteri' ?></h2>
      <?php if (!empty($error)): ?><div class="alert mb"><?= h($error) ?></div><?php endif; ?>
      <form method="post">
        <?php csrf_input(); ?>
        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
<div class="row mt" style="display:flex;flex-wrap:wrap;gap:12px">
  <div style="flex:1;min-width:220px">
    <label>Ad Soyad / Ünvan</label>
    <input name="name" value="<?= h($row['name']) ?>">
  </div>
  <div style="flex:1;min-width:220px">
    <label>Telefon</label>
    <input name="phone" value="<?= h($row['phone']) ?>">
  </div>
  <div style="flex:1;min-width:220px">
    <label>E-posta</label>
    <input type="email" name="email" value="<?= h($row['email']) ?>">
  </div>
</div>
<div class="row mt" style="display:flex;flex-wrap:wrap;gap:12px">
  <div style="flex:1;min-width:220px">
    <label>İlçe</label>
    <input name="ilce" value="<?= h($row['ilce']) ?>" placeholder="İlçe">
  </div>
  <div style="flex:1;min-width:220px">
    <label>İl</label>
    <select name="il">
                <option value="Adana" <?= $row['il']==="Adana"?'selected':'' ?>>Adana</option>
                <option value="Adıyaman" <?= $row['il']==="Adıyaman"?'selected':'' ?>>Adıyaman</option>
                <option value="Afyonkarahisar" <?= $row['il']==="Afyonkarahisar"?'selected':'' ?>>Afyonkarahisar</option>
                <option value="Ağrı" <?= $row['il']==="Ağrı"?'selected':'' ?>>Ağrı</option>
                <option value="Aksaray" <?= $row['il']==="Aksaray"?'selected':'' ?>>Aksaray</option>
                <option value="Amasya" <?= $row['il']==="Amasya"?'selected':'' ?>>Amasya</option>
                <option value="Ankara" <?= $row['il']==="Ankara"?'selected':'' ?>>Ankara</option>
                <option value="Antalya" <?= $row['il']==="Antalya"?'selected':'' ?>>Antalya</option>
                <option value="Ardahan" <?= $row['il']==="Ardahan"?'selected':'' ?>>Ardahan</option>
                <option value="Artvin" <?= $row['il']==="Artvin"?'selected':'' ?>>Artvin</option>
                <option value="Aydın" <?= $row['il']==="Aydın"?'selected':'' ?>>Aydın</option>
                <option value="Balıkesir" <?= $row['il']==="Balıkesir"?'selected':'' ?>>Balıkesir</option>
                <option value="Bartın" <?= $row['il']==="Bartın"?'selected':'' ?>>Bartın</option>
                <option value="Batman" <?= $row['il']==="Batman"?'selected':'' ?>>Batman</option>
                <option value="Bayburt" <?= $row['il']==="Bayburt"?'selected':'' ?>>Bayburt</option>
                <option value="Bilecik" <?= $row['il']==="Bilecik"?'selected':'' ?>>Bilecik</option>
                <option value="Bingöl" <?= $row['il']==="Bingöl"?'selected':'' ?>>Bingöl</option>
                <option value="Bitlis" <?= $row['il']==="Bitlis"?'selected':'' ?>>Bitlis</option>
                <option value="Bolu" <?= $row['il']==="Bolu"?'selected':'' ?>>Bolu</option>
                <option value="Burdur" <?= $row['il']==="Burdur"?'selected':'' ?>>Burdur</option>
                <option value="Bursa" <?= $row['il']==="Bursa"?'selected':'' ?>>Bursa</option>
                <option value="Çanakkale" <?= $row['il']==="Çanakkale"?'selected':'' ?>>Çanakkale</option>
                <option value="Çankırı" <?= $row['il']==="Çankırı"?'selected':'' ?>>Çankırı</option>
                <option value="Çorum" <?= $row['il']==="Çorum"?'selected':'' ?>>Çorum</option>
                <option value="Denizli" <?= $row['il']==="Denizli"?'selected':'' ?>>Denizli</option>
                <option value="Diyarbakır" <?= $row['il']==="Diyarbakır"?'selected':'' ?>>Diyarbakır</option>
                <option value="Düzce" <?= $row['il']==="Düzce"?'selected':'' ?>>Düzce</option>
                <option value="Edirne" <?= $row['il']==="Edirne"?'selected':'' ?>>Edirne</option>
                <option value="Elazığ" <?= $row['il']==="Elazığ"?'selected':'' ?>>Elazığ</option>
                <option value="Erzincan" <?= $row['il']==="Erzincan"?'selected':'' ?>>Erzincan</option>
                <option value="Erzurum" <?= $row['il']==="Erzurum"?'selected':'' ?>>Erzurum</option>
                <option value="Eskişehir" <?= $row['il']==="Eskişehir"?'selected':'' ?>>Eskişehir</option>
                <option value="Gaziantep" <?= $row['il']==="Gaziantep"?'selected':'' ?>>Gaziantep</option>
                <option value="Giresun" <?= $row['il']==="Giresun"?'selected':'' ?>>Giresun</option>
                <option value="Gümüşhane" <?= $row['il']==="Gümüşhane"?'selected':'' ?>>Gümüşhane</option>
                <option value="Hakkari" <?= $row['il']==="Hakkari"?'selected':'' ?>>Hakkari</option>
                <option value="Hatay" <?= $row['il']==="Hatay"?'selected':'' ?>>Hatay</option>
                <option value="Iğdır" <?= $row['il']==="Iğdır"?'selected':'' ?>>Iğdır</option>
                <option value="Isparta" <?= $row['il']==="Isparta"?'selected':'' ?>>Isparta</option>
                <option value="İstanbul" <?= $row['il']==="İstanbul"?'selected':'' ?>>İstanbul</option>
                <option value="İzmir" <?= $row['il']==="İzmir"?'selected':'' ?>>İzmir</option>
                <option value="Kahramanmaraş" <?= $row['il']==="Kahramanmaraş"?'selected':'' ?>>Kahramanmaraş</option>
                <option value="Karabük" <?= $row['il']==="Karabük"?'selected':'' ?>>Karabük</option>
                <option value="Karaman" <?= $row['il']==="Karaman"?'selected':'' ?>>Karaman</option>
                <option value="Kars" <?= $row['il']==="Kars"?'selected':'' ?>>Kars</option>
                <option value="Kastamonu" <?= $row['il']==="Kastamonu"?'selected':'' ?>>Kastamonu</option>
                <option value="Kayseri" <?= $row['il']==="Kayseri"?'selected':'' ?>>Kayseri</option>
                <option value="Kırıkkale" <?= $row['il']==="Kırıkkale"?'selected':'' ?>>Kırıkkale</option>
                <option value="Kırklareli" <?= $row['il']==="Kırklareli"?'selected':'' ?>>Kırklareli</option>
                <option value="Kırşehir" <?= $row['il']==="Kırşehir"?'selected':'' ?>>Kırşehir</option>
                <option value="Kilis" <?= $row['il']==="Kilis"?'selected':'' ?>>Kilis</option>
                <option value="Kocaeli" <?= $row['il']==="Kocaeli"?'selected':'' ?>>Kocaeli</option>
                <option value="Konya" <?= $row['il']==="Konya"?'selected':'' ?>>Konya</option>
                <option value="Kütahya" <?= $row['il']==="Kütahya"?'selected':'' ?>>Kütahya</option>
                <option value="Malatya" <?= $row['il']==="Malatya"?'selected':'' ?>>Malatya</option>
                <option value="Manisa" <?= $row['il']==="Manisa"?'selected':'' ?>>Manisa</option>
                <option value="Mardin" <?= $row['il']==="Mardin"?'selected':'' ?>>Mardin</option>
                <option value="Mersin" <?= $row['il']==="Mersin"?'selected':'' ?>>Mersin</option>
                <option value="Muğla" <?= $row['il']==="Muğla"?'selected':'' ?>>Muğla</option>
                <option value="Muş" <?= $row['il']==="Muş"?'selected':'' ?>>Muş</option>
                <option value="Nevşehir" <?= $row['il']==="Nevşehir"?'selected':'' ?>>Nevşehir</option>
                <option value="Niğde" <?= $row['il']==="Niğde"?'selected':'' ?>>Niğde</option>
                <option value="Ordu" <?= $row['il']==="Ordu"?'selected':'' ?>>Ordu</option>
                <option value="Osmaniye" <?= $row['il']==="Osmaniye"?'selected':'' ?>>Osmaniye</option>
                <option value="Rize" <?= $row['il']==="Rize"?'selected':'' ?>>Rize</option>
                <option value="Sakarya" <?= $row['il']==="Sakarya"?'selected':'' ?>>Sakarya</option>
                <option value="Samsun" <?= $row['il']==="Samsun"?'selected':'' ?>>Samsun</option>
                <option value="Siirt" <?= $row['il']==="Siirt"?'selected':'' ?>>Siirt</option>
                <option value="Sinop" <?= $row['il']==="Sinop"?'selected':'' ?>>Sinop</option>
                <option value="Sivas" <?= $row['il']==="Sivas"?'selected':'' ?>>Sivas</option>
                <option value="Şanlıurfa" <?= $row['il']==="Şanlıurfa"?'selected':'' ?>>Şanlıurfa</option>
                <option value="Şırnak" <?= $row['il']==="Şırnak"?'selected':'' ?>>Şırnak</option>
                <option value="Tekirdağ" <?= $row['il']==="Tekirdağ"?'selected':'' ?>>Tekirdağ</option>
                <option value="Tokat" <?= $row['il']==="Tokat"?'selected':'' ?>>Tokat</option>
                <option value="Trabzon" <?= $row['il']==="Trabzon"?'selected':'' ?>>Trabzon</option>
                <option value="Tunceli" <?= $row['il']==="Tunceli"?'selected':'' ?>>Tunceli</option>
                <option value="Uşak" <?= $row['il']==="Uşak"?'selected':'' ?>>Uşak</option>
                <option value="Van" <?= $row['il']==="Van"?'selected':'' ?>>Van</option>
                <option value="Yalova" <?= $row['il']==="Yalova"?'selected':'' ?>>Yalova</option>
                <option value="Yozgat" <?= $row['il']==="Yozgat"?'selected':'' ?>>Yozgat</option>
                <option value="Zonguldak" <?= $row['il']==="Zonguldak"?'selected':'' ?>>Zonguldak</option>
            </select>
  </div>
  <div style="flex:1;min-width:220px">
    <label>Ülke</label>
    <select name="ulke">
                <option value="Türkiye" <?= $row['ulke']==="Türkiye"?'selected':'' ?>>Türkiye</option>
                <option value="Almanya" <?= $row['ulke']==="Almanya"?'selected':'' ?>>Almanya</option>
                <option value="Amerika Birleşik Devletleri" <?= $row['ulke']==="Amerika Birleşik Devletleri"?'selected':'' ?>>Amerika Birleşik Devletleri</option>
                <option value="Birleşik Krallık" <?= $row['ulke']==="Birleşik Krallık"?'selected':'' ?>>Birleşik Krallık</option>
                <option value="Fransa" <?= $row['ulke']==="Fransa"?'selected':'' ?>>Fransa</option>
                <option value="İtalya" <?= $row['ulke']==="İtalya"?'selected':'' ?>>İtalya</option>
                <option value="İspanya" <?= $row['ulke']==="İspanya"?'selected':'' ?>>İspanya</option>
                <option value="Hollanda" <?= $row['ulke']==="Hollanda"?'selected':'' ?>>Hollanda</option>
                <option value="Belçika" <?= $row['ulke']==="Belçika"?'selected':'' ?>>Belçika</option>
                <option value="İsviçre" <?= $row['ulke']==="İsviçre"?'selected':'' ?>>İsviçre</option>
                <option value="Avusturya" <?= $row['ulke']==="Avusturya"?'selected':'' ?>>Avusturya</option>
                <option value="Çekya" <?= $row['ulke']==="Çekya"?'selected':'' ?>>Çekya</option>
                <option value="Polonya" <?= $row['ulke']==="Polonya"?'selected':'' ?>>Polonya</option>
                <option value="Macaristan" <?= $row['ulke']==="Macaristan"?'selected':'' ?>>Macaristan</option>
                <option value="Romanya" <?= $row['ulke']==="Romanya"?'selected':'' ?>>Romanya</option>
                <option value="Bulgaristan" <?= $row['ulke']==="Bulgaristan"?'selected':'' ?>>Bulgaristan</option>
                <option value="Yunanistan" <?= $row['ulke']==="Yunanistan"?'selected':'' ?>>Yunanistan</option>
                <option value="Rusya" <?= $row['ulke']==="Rusya"?'selected':'' ?>>Rusya</option>
                <option value="Ukrayna" <?= $row['ulke']==="Ukrayna"?'selected':'' ?>>Ukrayna</option>
                <option value="Kanada" <?= $row['ulke']==="Kanada"?'selected':'' ?>>Kanada</option>
                <option value="Meksika" <?= $row['ulke']==="Meksika"?'selected':'' ?>>Meksika</option>
                <option value="Brezilya" <?= $row['ulke']==="Brezilya"?'selected':'' ?>>Brezilya</option>
                <option value="Arjantin" <?= $row['ulke']==="Arjantin"?'selected':'' ?>>Arjantin</option>
                <option value="Şili" <?= $row['ulke']==="Şili"?'selected':'' ?>>Şili</option>
                <option value="Güney Afrika" <?= $row['ulke']==="Güney Afrika"?'selected':'' ?>>Güney Afrika</option>
                <option value="Mısır" <?= $row['ulke']==="Mısır"?'selected':'' ?>>Mısır</option>
                <option value="Fas" <?= $row['ulke']==="Fas"?'selected':'' ?>>Fas</option>
                <option value="Cezayir" <?= $row['ulke']==="Cezayir"?'selected':'' ?>>Cezayir</option>
                <option value="Tunus" <?= $row['ulke']==="Tunus"?'selected':'' ?>>Tunus</option>
                <option value="Birleşik Arap Emirlikleri" <?= $row['ulke']==="Birleşik Arap Emirlikleri"?'selected':'' ?>>Birleşik Arap Emirlikleri</option>
                <option value="Suudi Arabistan" <?= $row['ulke']==="Suudi Arabistan"?'selected':'' ?>>Suudi Arabistan</option>
                <option value="Katar" <?= $row['ulke']==="Katar"?'selected':'' ?>>Katar</option>
                <option value="Kuveyt" <?= $row['ulke']==="Kuveyt"?'selected':'' ?>>Kuveyt</option>
                <option value="Bahreyn" <?= $row['ulke']==="Bahreyn"?'selected':'' ?>>Bahreyn</option>
                <option value="Umman" <?= $row['ulke']==="Umman"?'selected':'' ?>>Umman</option>
                <option value="İran" <?= $row['ulke']==="İran"?'selected':'' ?>>İran</option>
                <option value="Irak" <?= $row['ulke']==="Irak"?'selected':'' ?>>Irak</option>
                <option value="Suriye" <?= $row['ulke']==="Suriye"?'selected':'' ?>>Suriye</option>
                <option value="Lübnan" <?= $row['ulke']==="Lübnan"?'selected':'' ?>>Lübnan</option>
                <option value="İsrail" <?= $row['ulke']==="İsrail"?'selected':'' ?>>İsrail</option>
                <option value="Çin" <?= $row['ulke']==="Çin"?'selected':'' ?>>Çin</option>
                <option value="Japonya" <?= $row['ulke']==="Japonya"?'selected':'' ?>>Japonya</option>
                <option value="Güney Kore" <?= $row['ulke']==="Güney Kore"?'selected':'' ?>>Güney Kore</option>
                <option value="Hindistan" <?= $row['ulke']==="Hindistan"?'selected':'' ?>>Hindistan</option>
                <option value="Pakistan" <?= $row['ulke']==="Pakistan"?'selected':'' ?>>Pakistan</option>
                <option value="Bangladeş" <?= $row['ulke']==="Bangladeş"?'selected':'' ?>>Bangladeş</option>
                <option value="Endonezya" <?= $row['ulke']==="Endonezya"?'selected':'' ?>>Endonezya</option>
                <option value="Malezya" <?= $row['ulke']==="Malezya"?'selected':'' ?>>Malezya</option>
                <option value="Tayland" <?= $row['ulke']==="Tayland"?'selected':'' ?>>Tayland</option>
                <option value="Singapur" <?= $row['ulke']==="Singapur"?'selected':'' ?>>Singapur</option>
                <option value="Avustralya" <?= $row['ulke']==="Avustralya"?'selected':'' ?>>Avustralya</option>
                <option value="Yeni Zelanda" <?= $row['ulke']==="Yeni Zelanda"?'selected':'' ?>>Yeni Zelanda</option>
                <option value="Azerbaycan" <?= $row['ulke']==="Azerbaycan"?'selected':'' ?>>Azerbaycan</option>
                <option value="Gürcistan" <?= $row['ulke']==="Gürcistan"?'selected':'' ?>>Gürcistan</option>
                <option value="Ermenistan" <?= $row['ulke']==="Ermenistan"?'selected':'' ?>>Ermenistan</option>
                <option value="Kazakhstan" <?= $row['ulke']==="Kazakhstan"?'selected':'' ?>>Kazakhstan</option>
                <option value="Kırgızistan" <?= $row['ulke']==="Kırgızistan"?'selected':'' ?>>Kırgızistan</option>
                <option value="Tacikistan" <?= $row['ulke']==="Tacikistan"?'selected':'' ?>>Tacikistan</option>
                <option value="Türkmenistan" <?= $row['ulke']==="Türkmenistan"?'selected':'' ?>>Türkmenistan</option>
                <option value="Özbekistan" <?= $row['ulke']==="Özbekistan"?'selected':'' ?>>Özbekistan</option>
            </select>
  </div>
</div>
<div class="row mt" style="display:flex;flex-wrap:wrap;gap:12px">
  <div style="flex:1;min-width:280px">
    <label class="mt">Fatura Adresi</label>
    <textarea name="billing_address" rows="3"><?= h($row['billing_address']) ?></textarea>
  </div>
  <div style="flex:1;min-width:280px">
    <label class="mt">Sevk Adresi</label>
    <textarea name="shipping_address" rows="3"><?= h($row['shipping_address']) ?></textarea>
  </div>
</div>
<div class="row mt" style="display:flex;flex-wrap:wrap;gap:12px">
  <div style="flex:1;min-width:220px">
    <label>Web Site</label>
    <input type="text" inputmode="url" type="text" inputmode="url" name="website" value="<?= h($row['website']) ?>" placeholder="https://...">
  </div>
  <div style="flex:1;min-width:220px">
    <label>Vergi Dairesi</label>
    <input name="vergi_dairesi" value="<?= h($row['vergi_dairesi']) ?>">
  </div>
  <div style="flex:1;min-width:220px">
    <label>Vergi Numarası</label>
    <input name="vergi_no" value="<?= h($row['vergi_no']) ?>">
  </div>
</div>
<div class="row mt">
          <button class="btn primary"><?= $row['id'] ? 'Güncelle' : 'Kaydet' ?></button>
          <a class="btn" href="customers.php">Vazgeç</a>
        </div>
      </form>
    </div>
    <?php
    include __DIR__ . '/includes/footer.php'; exit;
}

// Liste/Arama
$q = trim($_GET['q'] ?? '');
if ($q !== '') {
    $like = '%'.$q.'%';
    $stmt = $db->prepare("SELECT * FROM customers WHERE name LIKE ? OR email LIKE ? OR phone LIKE ? ORDER BY id DESC");
    $stmt->execute([$like,$like,$like]);
} else {
    $stmt = $db->query("SELECT * FROM customers ORDER BY id DESC");
}
?>
<!-- Üst eylem çubuğu: yan yana hizalı -->
<div class="row mb" style="align-items:center; gap:12px;">
  <a class="btn primary" href="customers.php?a=new" style="flex:0 0 auto;">Yeni Müşteri</a>

  <form class="row" method="get" style="gap:8px; align-items:center; flex:0 0 auto;">
    <input name="q" placeholder="Ad/e-posta/telefon ara…" value="<?= h($q) ?>" style="width:280px; max-width:40vw;">
    <button class="btn">Ara</button>
  </form>
</div>

<div class="card">
  <div class="table-responsive">
<table>
    <tr>
      <th>ID</th>
      <th>Ad</th>
      <th>E-posta</th>
      <th>Telefon</th>
      <th class="right">İşlem</th>
    </tr>
    <?php while($r = $stmt->fetch()): ?>
    <tr>
      <td><?= (int)$r['id'] ?></td>
      <td><?= h($r['name']) ?></td>
      <td><?= h($r['email']) ?></td>
      <td><?= h($r['phone']) ?></td>
      <td class="right">
        <a class="btn" href="customers.php?a=edit&id=<?= (int)$r['id'] ?>">Düzenle</a>
        <form method="post" action="customers.php?a=delete" style="display:inline" onsubmit="return confirm('Silinsin mi?')">
          <?php csrf_input(); ?>
          <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
          <button class="btn" style="background:#450a0a;border-color:#7f1d1d">Sil</button>
        </form>
      </td>
    </tr>
    <?php endwhile; ?>
  </table>
</div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>