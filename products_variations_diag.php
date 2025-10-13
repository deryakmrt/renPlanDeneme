<?php
// products_variations_diag.php — hızlı teşhis
@ini_set('display_errors',1); @error_reporting(E_ALL);
header('Content-Type: text/html; charset=utf-8');
echo "<style>body{font-family:system-ui;padding:16px;line-height:1.5} .ok{color:#1a7f37} .err{color:#b91c1c} code{background:#f6f8fa;padding:2px 4px;border-radius:4px}</style>";
function row($k,$ok,$msg=''){ echo "<div><strong>$k:</strong> ".($ok? "<span class='ok'>OK</span>" : "<span class='err'>HATA</span>"); if($msg) echo " — ".htmlspecialchars($msg,ENT_QUOTES,'UTF-8'); echo "</div>"; }

$root = __DIR__;
row('helpers.php', file_exists($root.'/includes/helpers.php'), $root.'/includes/helpers.php');
require_once $root.'/includes/helpers.php';

if (!function_exists('pdo')){ row('pdo()', false, 'helpers.php içinde tanımlı olmalı'); exit; }
try{ $db=pdo(); row('PDO bağlantısı', true, get_class($db)); }catch(Throwable $e){ row('PDO bağlantısı', false, $e->getMessage()); exit; }

function table_exists($db,$t){ try{$st=$db->query("SHOW TABLES LIKE ".$db->quote($t)); return (bool)$st->fetchColumn();}catch(Throwable $e){ return false; } }

$tables = ['products','product_attributes','product_attribute_terms','product_attribute_values','product_variations','product_variation_options','product_categories','product_brands'];
foreach ($tables as $t){ row("Tablo $t", table_exists($db,$t)); }

// engine & types
echo "<hr><h3>SHOW CREATE TABLE products</h3><pre>";
try{ $r=$db->query("SHOW CREATE TABLE products")->fetch(PDO::FETCH_NUM); echo htmlspecialchars($r[1] ?? 'yok',ENT_QUOTES,'UTF-8'); }catch(Throwable $e){ echo htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8'); }
echo "</pre>";

echo "<hr><h3>PHP eklentileri</h3><div>";
row('pdo_mysql', extension_loaded('pdo_mysql'));
row('gd', extension_loaded('gd'));
row('fileinfo', extension_loaded('fileinfo'));
echo "</div>";

echo "<hr><h3>Yükleme klasörü</h3><div>";
$up=$root.'/uploads/products'; if(!is_dir($up)) @mkdir($up,0775,true);
row('uploads/products yazılabilir', is_writable($up), $up);
echo "</div>";

echo "<hr><p>Burdaki her şey OK ise dosyayı entegrasyon adımlarına göre include ederek deneyin.</p>";
