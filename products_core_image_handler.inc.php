<?php
require_once __DIR__ . '/products_variations_addon.inc.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $pid = 0;
  if (isset($_POST['id'])) $pid = (int)$_POST['id'];
  if (!$pid && isset($_GET['id'])) $pid = (int)$_GET['id'];
  if ($pid>0 && !empty($_FILES['image']['name']) && is_uploaded_file($_FILES['image']['tmp_name'])) {
    $uploadDir = __DIR__ . '/uploads/products'; if (!is_dir($uploadDir)) @mkdir($uploadDir,0775,true);
    $uploadUrl = 'uploads/products';
    $namef=$_FILES['image']['name']; $tmp=$_FILES['image']['tmp_name']; $ext=strtolower(pathinfo($namef,PATHINFO_EXTENSION)); if(!$ext)$ext='jpg';
    $fname='p_'.$pid.'_'.date('Ymd_His').'_'.(function_exists('random_bytes')?bin2hex(random_bytes(2)):(string)mt_rand(1000,9999)).'.'.$ext;
    $dest=$uploadDir.'/'.$fname;
    if (move_uploaded_file($tmp,$dest)) {
      $rel=$uploadUrl.'/'.$fname; ensure_thumbs_for($rel);
      if (isset($db) && $db instanceof PDO) { $db->prepare("UPDATE products SET image=?, updated_at=NOW() WHERE id=?")->execute([$rel,$pid]); }
      elseif (function_exists('pdo')) { $pdo = pdo(); $pdo->prepare("UPDATE products SET image=?, updated_at=NOW() WHERE id=?")->execute([$rel,$pid]); }
    }
  }
}
