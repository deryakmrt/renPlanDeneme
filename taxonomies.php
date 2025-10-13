<?php
require_once __DIR__ . '/includes/helpers.php';
// CSRF fonksiyonları helpers.php'de varsa onları kullan; yoksa no-op fallback tanımla (redeclare korumalı)
if (!function_exists('csrf_field')) { function csrf_field($action='global'){ /* no-op */ } }
if (!function_exists('csrf_check')) { function csrf_check($action='global', $onFailRedirect=null){ return true; } }

require_login();

if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
if (!function_exists('h')) { function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); } }
if (!function_exists('method')) { function method($m){ return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === strtoupper($m); } }
if (!function_exists('redirect')) { function redirect($to){ header('Location: '.$to); exit; } }

$db = pdo();

$t = $_GET['t'] ?? 'categories';
$map = [
  'categories' => ['table' => 'product_categories', 'label' => 'Kategori', 'col' => 'category_id'],
  'brands'     => ['table' => 'product_brands',     'label' => 'Marka',    'col' => 'brand_id'],
];
if (!isset($map[$t])) { $t = 'categories'; }
$conf = $map[$t];
$table = $conf['table'];
$label = $conf['label'];
$prodCol = $conf['col'];

function taxo_find($db,$table,$id){
    $s=$db->prepare("SELECT * FROM {$table} WHERE id=? LIMIT 1"); $s->execute([(int)$id]); return $s->fetch(PDO::FETCH_ASSOC);
}
function taxo_exists_by_name($db,$table,$name,$excludeId=0){
    $name=trim($name); if($name==='') return false;
    $sql="SELECT id FROM {$table} WHERE name=?"; $args=[$name];
    if($excludeId){ $sql.=" AND id<>?"; $args[]=(int)$excludeId; }
    $sql.=" LIMIT 1"; $s=$db->prepare($sql); $s->execute($args);
    return (bool)$s->fetchColumn();
}
function products_using_taxo($db,$col,$id){
    $s=$db->prepare("SELECT COUNT(*) FROM products WHERE {$col}=?"); $s->execute([(int)$id]); return (int)$s->fetchColumn();
}

$a = $_GET['a'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($a==='create' && method('POST')) {
    csrf_check('taxo_'.$t.'_create', "taxonomies.php?t={$t}&a=new");
    $name = trim($_POST['name'] ?? '');
    $errors=[];
    if ($name==='') $errors[] = "{$label} adı zorunlu.";
    if (taxo_exists_by_name($db,$table,$name)) $errors[] = "Bu {$label} zaten var.";

    if ($errors){
        $_SESSION['flash_error']=implode('\n',$errors);
        $_SESSION['form_name']=$name;
        redirect("taxonomies.php?t={$t}&a=new"); exit;
    }
    try {
        $s=$db->prepare("INSERT INTO {$table} (name) VALUES (?)"); $s->execute([$name]);
        $_SESSION['flash_success'] = "{$label} eklendi.";
        redirect("taxonomies.php?t={$t}"); exit;
    } catch (Throwable $e) {
        $_SESSION['flash_error'] = "Kayıt sırasında hata: ".$e->getMessage();
        $_SESSION['form_name']=$name;
        redirect("taxonomies.php?t={$t}&a=new"); exit;
    }
}

if ($a==='update' && method('POST')) {
    csrf_check('taxo_'.$t.'_update', "taxonomies.php?t={$t}&a=edit&id=".$id);
    $name = trim($_POST['name'] ?? '');
    $id   = (int)($_POST['id'] ?? 0);
    $errors=[];
    if ($name==='') $errors[] = "{$label} adı zorunlu.";
    if ($id<=0) $errors[] = "Geçersiz ID.";
    if (taxo_exists_by_name($db,$table,$name,$id)) $errors[] = "Bu {$label} zaten var.";

    if ($errors){
        $_SESSION['flash_error']=implode('\n',$errors);
        $_SESSION['form_name']=$name;
        redirect("taxonomies.php?t={$t}&a=edit&id=".$id); exit;
    }
    try {
        $s=$db->prepare("UPDATE {$table} SET name=? WHERE id=?"); $s->execute([$name,$id]);
        $_SESSION['flash_success'] = "{$label} güncellendi.";
        redirect("taxonomies.php?t={$t}"); exit;
    } catch (Throwable $e) {
        $_SESSION['flash_error'] = "Güncelleme sırasında hata: ".$e->getMessage();
        $_SESSION['form_name']=$name;
        redirect("taxonomies.php?t={$t}&a=edit&id=".$id); exit;
    }
}

if ($a==='delete' && method('POST')) {
    csrf_check('taxo_'.$t.'_delete', "taxonomies.php?t={$t}");
    $id = (int)($_POST['id'] ?? 0);
    if ($id) {
        $cnt = products_using_taxo($db,$prodCol,$id);
        if ($cnt>0) {
            $_SESSION['flash_error'] = "{$label} silinemedi: {$cnt} ürün bu {$label} ile ilişkili.";
        } else {
            try {
                $s=$db->prepare("DELETE FROM {$table} WHERE id=?"); $s->execute([$id]);
                $_SESSION['flash_success'] = "{$label} silindi.";
            } catch (Throwable $e) {
                $_SESSION['flash_error'] = "Silme sırasında hata: ".$e->getMessage();
            }
        }
    }
    redirect("taxonomies.php?t={$t}"); exit;
}

include __DIR__ . '/includes/header.php';

if (!empty($_SESSION['flash_error'])) { echo '<div class="alert danger">'.nl2br(h($_SESSION['flash_error'])).'</div>'; unset($_SESSION['flash_error']); }
if (!empty($_SESSION['flash_success'])) { echo '<div class="alert success">'.h($_SESSION['flash_success']).'</div>'; unset($_SESSION['flash_success']); }

if ($a==='new') {
    $name = $_SESSION['form_name'] ?? '';
    unset($_SESSION['form_name']);
    ?>
    <div class="row mb">
      <a class="btn" href="taxonomies.php?t=categories">Kategoriler</a>
      <a class="btn" href="taxonomies.php?t=brands">Markalar</a>
      <a class="btn" href="taxonomies.php?t=<?= h($t) ?>">Listeye Dön</a>
    </div>
    <div class="card">
      <form class="form" method="post" action="taxonomies.php?t=<?= h($t) ?>&a=create">
        <?php csrf_field('taxo_'.$t.'_create'); ?>
        <div><label><?= h($label) ?> Adı *</label>
          <input name="name" value="<?= h($name) ?>" required placeholder="<?= h($label) ?> adı">
        </div>
        <div class="row mt" style="justify-content:flex-end; gap:.5rem;">
          <a class="btn" href="taxonomies.php?t=<?= h($t) ?>">İptal</a>
          <button class="btn primary">Kaydet</button>
        </div>
      </form>
    </div>
    <?php
    include __DIR__ . '/includes/footer.php'; exit;
}

if ($a==='edit' && $id) {
    $row = taxo_find($db,$table,$id);
    if (!$row) { echo '<div class="alert danger">Kayıt bulunamadı.</div>'; include __DIR__ . '/includes/footer.php'; exit; }
    $name = $_SESSION['form_name'] ?? $row['name'];
    unset($_SESSION['form_name']);
    ?>
    <div class="row mb">
      <a class="btn" href="taxonomies.php?t=categories">Kategoriler</a>
      <a class="btn" href="taxonomies.php?t=brands">Markalar</a>
      <a class="btn" href="taxonomies.php?t=<?= h($t) ?>">Listeye Dön</a>
    </div>
    <div class="card">
      <form class="form" method="post" action="taxonomies.php?t=<?= h($t) ?>&a=update">
        <?php csrf_field('taxo_'.$t.'_update'); ?>
        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
        <div><label><?= h($label) ?> Adı *</label>
          <input name="name" value="<?= h($name) ?>" required>
        </div>
        <div class="row mt" style="justify-content:flex-end; gap:.5rem;">
          <a class="btn" href="taxonomies.php?t=<?= h($t) ?>">İptal</a>
          <button class="btn primary">Güncelle</button>
        </div>
      </form>
    </div>
    <?php
    include __DIR__ . '/includes/footer.php'; exit;
}

$q = trim($_GET['q'] ?? '');
$where = ''; $args=[];
if ($q!==''){ $where = " WHERE name LIKE ?"; $args = ['%'.$q.'%']; }
$stmt = $db->prepare("SELECT id, name FROM {$table} {$where} ORDER BY name ASC");
$stmt->execute($args);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$ids = array_map(function($r){ return (int)$r['id']; }, $rows);
$usage = [];
if ($ids) {
    $in = implode(',', array_fill(0, count($ids), '?'));
    $st = $db->prepare("SELECT {$prodCol} AS tid, COUNT(*) AS c FROM products WHERE {$prodCol} IN ($in) GROUP BY {$prodCol}");
    $st->execute($ids);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $u) { $usage[(int)$u['tid']] = (int)$u['c']; }
}
?>
<div class="row mb">
  <a class="btn primary" href="taxonomies.php?t=<?= h($t) ?>&a=new">Yeni <?= h($label) ?></a>
  <a class="btn" href="taxonomies.php?t=categories">Kategoriler</a>
  <a class="btn" href="taxonomies.php?t=brands">Markalar</a>
  <form class="row" method="get" style="gap:.5rem;">
    <input type="hidden" name="t" value="<?= h($t) ?>">
    <input name="q" placeholder="<?= h($label) ?> ara…" value="<?= h($q) ?>">
    <button class="btn">Ara</button>
  </form>
</div>

<div class="card">
  <table class="table">
    <thead>
      <tr>
        <th>#</th>
        <th><?= h($label) ?></th>
        <th>Kullanım (ürün)</th>
        <th style="width:200px"></th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r): $c = $usage[(int)$r['id']] ?? 0; ?>
      <tr>
        <td><?= (int)$r['id'] ?></td>
        <td><?= h($r['name']) ?></td>
        <td><?= (int)$c ?></td>
        <td class="t-right">
          <a class="btn" href="taxonomies.php?t=<?= h($t) ?>&a=edit&id=<?= (int)$r['id'] ?>">Düzenle</a>
          <form method="post" action="taxonomies.php?t=<?= h($t) ?>&a=delete" style="display:inline" onsubmit="return confirm('Silinsin mi?')">
            <?php csrf_field('taxo_'.$t.'_delete'); ?>
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <button class="btn danger" <?= $c>0 ? 'disabled title="İlişkili ürünler var"' : '' ?>>Sil</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="4" class="t-center muted">Kayıt bulunamadı.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
