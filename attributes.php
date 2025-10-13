<?php
require_once __DIR__ . '/includes/helpers.php';
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
require_login();

if (!function_exists('csrf_field')) { function csrf_field($action='global'){ /* no-op */ } }
if (!function_exists('csrf_check')) { function csrf_check($action='global', $redir=null){ return true; } }
if (!function_exists('h')) { function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); } }
if (!function_exists('method')) { function method($m){ return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === strtoupper($m); } }
if (!function_exists('redirect')) { function redirect($to){ header('Location: '.$to); exit; } }

try { if (!function_exists('pdo')) throw new Exception('pdo() yok'); $db = pdo(); }
catch (Throwable $e) { http_response_code(500); echo 'DB Hatası: '.h($e->getMessage()); exit; }

function slugify($s){
  $orig=(string)$s;
  if(function_exists('iconv')){
    $tmp=@iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$orig);
    if($tmp!==false) $orig=$tmp;
  }
  $s=strtolower($orig);
  $s=preg_replace('/[^a-z0-9]+/','-',$s);
  $s=trim($s,'-');
  return $s ?: ('item-'.substr(sha1(uniqid('',true)),0,6));
}

$view = $_GET['a'] ?? 'list';
$attr_id = isset($_GET['attr_id']) ? (int)$_GET['attr_id'] : 0;

// Attribute create
if ($view==='attr_create' && method('POST')){
  csrf_check('attr_create','attributes.php?a=new');
  $name=trim($_POST['name'] ?? ''); $slug=trim($_POST['slug'] ?? '');
  if($name===''){ $_SESSION['flash_error']='Öznitelik adı zorunlu.'; redirect('attributes.php?a=new'); }
  if($slug===''){ $slug=slugify($name); }
  try{ $db->prepare("INSERT INTO product_attributes(name,slug) VALUES(?,?)")->execute([$name,$slug]);
       $_SESSION['flash_success']='Öznitelik eklendi.'; redirect('attributes.php'); }
  catch(Throwable $e){ $_SESSION['flash_error']='Hata: '.$e->getMessage(); redirect('attributes.php?a=new'); }
  exit;
}
// Attribute update
if ($view==='attr_update' && method('POST')){
  csrf_check('attr_update','attributes.php');
  $id=(int)$_POST['id']; $name=trim($_POST['name'] ?? ''); $slug=trim($_POST['slug'] ?? '');
  if($id<=0 || $name===''){ $_SESSION['flash_error']='Eksik bilgi'; redirect('attributes.php'); }
  if($slug===''){ $slug=slugify($name); }
  try{ $db->prepare("UPDATE product_attributes SET name=?,slug=? WHERE id=?")->execute([$name,$slug,$id]);
       $_SESSION['flash_success']='Güncellendi.'; }
  catch(Throwable $e){ $_SESSION['flash_error']='Hata: '.$e->getMessage(); }
  redirect('attributes.php'); exit;
}
// Attribute delete
if ($view==='attr_delete' && method('POST')){
  csrf_check('attr_delete','attributes.php');
  $id=(int)$_POST['id'];
  try{ $db->prepare("DELETE FROM product_attributes WHERE id=?")->execute([$id]); $_SESSION['flash_success']='Silindi.'; }
  catch(Throwable $e){ $_SESSION['flash_error']='Hata: '.$e->getMessage(); }
  redirect('attributes.php'); exit;
}
// Term CRUD
if ($view==='term_create' && method('POST')){
  csrf_check('term_create','attributes.php?a=terms&attr_id='.(int)($_POST['attribute_id'] ?? 0));
  $aid=(int)($_POST['attribute_id'] ?? 0); $name=trim($_POST['name'] ?? ''); $slug=trim($_POST['slug'] ?? '');
  if($aid<=0 || $name===''){ $_SESSION['flash_error']='Eksik bilgi'; redirect('attributes.php'); }
  if($slug===''){ $slug=slugify($name); }
  try{ $db->prepare("INSERT INTO product_attribute_terms(attribute_id,name,slug) VALUES(?,?,?)")->execute([$aid,$name,$slug]);
       $_SESSION['flash_success']='Terim eklendi.'; }
  catch(Throwable $e){ $_SESSION['flash_error']='Hata: '.$e->getMessage(); }
  redirect('attributes.php?a=terms&attr_id='.$aid); exit;
}
if ($view==='term_update' && method('POST')){
  csrf_check('term_update','attributes.php');
  $id=(int)$_POST['id']; $aid=(int)$_POST['attribute_id']; $name=trim($_POST['name'] ?? ''); $slug=trim($_POST['slug'] ?? '');
  if($id<=0 || $aid<=0 || $name===''){ $_SESSION['flash_error']='Eksik bilgi'; redirect('attributes.php'); }
  if($slug===''){ $slug=slugify($name); }
  try{ $db->prepare("UPDATE product_attribute_terms SET name=?, slug=? WHERE id=? AND attribute_id=?")->execute([$name,$slug,$id,$aid]);
       $_SESSION['flash_success']='Güncellendi.'; }
  catch(Throwable $e){ $_SESSION['flash_error']='Hata: '.$e->getMessage(); }
  redirect('attributes.php?a=terms&attr_id='.$aid); exit;
}
if ($view==='term_delete' && method('POST')){
  csrf_check('term_delete','attributes.php');
  $id=(int)$_POST['id']; $aid=(int)$_POST['attribute_id'];
  try{ $db->prepare("DELETE FROM product_attribute_terms WHERE id=? AND attribute_id=?")->execute([$id,$aid]);
       $_SESSION['flash_success']='Silindi.'; }
  catch(Throwable $e){ $_SESSION['flash_error']='Hata: '.$e->getMessage(); }
  redirect('attributes.php?a=terms&attr_id='.$aid); exit;
}

include __DIR__ . '/includes/header.php';
if (!empty($_SESSION['flash_error'])) { echo '<div class="alert danger">'.nl2br(h($_SESSION['flash_error'])).'</div>'; unset($_SESSION['flash_error']); }
if (!empty($_SESSION['flash_success'])) { echo '<div class="alert success">'.h($_SESSION['flash_success']).'</div>'; unset($_SESSION['flash_success']); }

if ($view==='new'){
  ?>
  <div class="row mb"><a class="btn" href="attributes.php">Öznitelikler</a></div>
  <div class="card">
    <form class="form" method="post" action="attributes.php?a=attr_create">
      <?php csrf_field('attr_create'); ?>
      <div><label>Öznitelik Adı *</label><input name="name" required></div>
      <div><label>Slug</label><input name="slug" placeholder="boş: otomatik"></div>
      <div class="row mt" style="justify-content:flex-end; gap:.5rem;">
        <a class="btn" href="attributes.php">İptal</a><button class="btn primary">Kaydet</button>
      </div>
    </form>
  </div>
  <?php include __DIR__ . '/includes/footer.php'; exit;
}

if ($view==='edit'){
  $id=(int)($_GET['id'] ?? 0);
  $s=$db->prepare("SELECT * FROM product_attributes WHERE id=?"); $s->execute([$id]); $row=$s->fetch(PDO::FETCH_ASSOC);
  if(!$row){ echo '<div class="alert danger">Kayıt yok</div>'; include __DIR__ . '/includes/footer.php'; exit; }
  ?>
  <div class="row mb"><a class="btn" href="attributes.php">Öznitelikler</a></div>
  <div class="card">
    <form class="form" method="post" action="attributes.php?a=attr_update">
      <?php csrf_field('attr_update'); ?>
      <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
      <div><label>Öznitelik Adı *</label><input name="name" value="<?= h($row['name']) ?>" required></div>
      <div><label>Slug</label><input name="slug" value="<?= h($row['slug']) ?>"></div>
      <div class="row mt" style="justify-content:flex-end; gap:.5rem;">
        <a class="btn" href="attributes.php">İptal</a><button class="btn primary">Güncelle</button>
      </div>
    </form>
  </div>
  <?php include __DIR__ . '/includes/footer.php'; exit;
}

if ($view==='terms'){
  $aid=(int)$attr_id;
  $s=$db->prepare("SELECT * FROM product_attributes WHERE id=?"); $s->execute([$aid]); $attr=$s->fetch(PDO::FETCH_ASSOC);
  if(!$attr){ echo '<div class="alert danger">Öznitelik yok</div>'; include __DIR__ . '/includes/footer.php'; exit; }
  $st=$db->prepare("SELECT * FROM product_attribute_terms WHERE attribute_id=? ORDER BY name ASC"); $st->execute([$aid]); $terms=$st->fetchAll(PDO::FETCH_ASSOC);
  ?>
  <div class="row mb">
    <a class="btn" href="attributes.php">Öznitelikler</a>
    <a class="btn primary" href="attributes.php?a=new">Yeni Öznitelik</a>
  </div>
  <div class="card">
    <h3><?= h($attr['name']) ?> — Terimler</h3>
    <form class="form" method="post" action="attributes.php?a=term_create">
      <?php csrf_field('term_create'); ?>
      <input type="hidden" name="attribute_id" value="<?= (int)$attr['id'] ?>">
      <div class="grid" style="grid-template-columns: 1fr 1fr; gap: 1rem;">
        <div><label>Terim Adı *</label><input name="name" required></div>
        <div><label>Slug</label><input name="slug" placeholder="boş: otomatik"></div>
      </div>
      <div class="row mt" style="justify-content:flex-end; gap:.5rem;">
        <button class="btn primary">Ekle</button>
      </div>
    </form>
  </div>
  <div class="card mt">
    <table class="table">
      <thead><tr><th>#</th><th>Terim</th><th>Slug</th><th style="width:220px"></th></tr></thead>
      <tbody>
        <?php foreach ($terms as $t): ?>
          <tr>
            <td><?= (int)$t['id'] ?></td>
            <td><?= h($t['name']) ?></td>
            <td><?= h($t['slug']) ?></td>
            <td class="t-right">
              <form class="row" method="post" action="attributes.php?a=term_update" style="gap:.5rem; display:inline-flex; align-items:center;">
                <?php csrf_field('term_update'); ?>
                <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                <input type="hidden" name="attribute_id" value="<?= (int)$attr['id'] ?>">
                <input name="name" value="<?= h($t['name']) ?>" style="width:180px">
                <input name="slug" value="<?= h($t['slug']) ?>" style="width:140px">
                <button class="btn">Kaydet</button>
              </form>
              <form method="post" action="attributes.php?a=term_delete" style="display:inline" onsubmit="return confirm('Silinsin mi?')">
                <?php csrf_field('term_delete'); ?>
                <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                <input type="hidden" name="attribute_id" value="<?= (int)$attr['id'] ?>">
                <button class="btn danger">Sil</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$terms): ?><tr><td colspan="4" class="t-center muted">Terim yok.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php include __DIR__ . '/includes/footer.php'; exit;
}

$rows=$db->query("SELECT a.*, (SELECT COUNT(*) FROM product_attribute_terms t WHERE t.attribute_id = a.id) AS term_count FROM product_attributes a ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="row mb"><a class="btn primary" href="attributes.php?a=new">Yeni Öznitelik</a></div>
<div class="card">
  <table class="table">
    <thead><tr><th>#</th><th>Öznitelik</th><th>Slug</th><th>Terim</th><th style="width:260px"></th></tr></thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= (int)$r['id'] ?></td>
          <td><?= h($r['name']) ?></td>
          <td><?= h($r['slug']) ?></td>
          <td><?= (int)$r['term_count'] ?></td>
          <td class="t-right">
            <a class="btn" href="attributes.php?a=terms&attr_id=<?= (int)$r['id'] ?>">Terimleri Yönet</a>
            <a class="btn" href="attributes.php?a=edit&id=<?= (int)$r['id'] ?>">Düzenle</a>
            <form method="post" action="attributes.php?a=attr_delete" style="display:inline" onsubmit="return confirm('Silinsin mi? Bu işlem terimleri de siler!')">
              <?php csrf_field('attr_delete'); ?>
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <button class="btn danger">Sil</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="5" class="t-center muted">Kayıt yok.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
