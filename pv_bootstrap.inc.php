<?php
// pv_bootstrap.inc.php — v2 (fix: no duplicate session_start notices)
@ini_set('display_errors', 1);
@error_reporting(E_ALL);

require_once __DIR__ . '/includes/helpers.php';

// Safe session helper (call ONLY after helpers/config is loaded)
if (!function_exists('pv_session_start')) {
  function pv_session_start(){
    if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
  }
}

// Ensure PDO
if (!isset($db) && function_exists('pdo')) { $db = pdo(); }

if (!function_exists('h')) {
  function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

// CSRF (standalone, does NOT call session_start directly)
if (!function_exists('pv_csrf_token')) {
  function pv_csrf_token($key='global'){
    pv_session_start();
    $k = 'pv_csrf_'.$key;
    if (empty($_SESSION[$k])) {
      $_SESSION[$k] = bin2hex(function_exists('random_bytes') ? random_bytes(16) : (string)mt_rand(100000,999999).microtime(true));
    }
    return $_SESSION[$k];
  }
}
if (!function_exists('pv_csrf_field')) {
  function pv_csrf_field($action='global'){
    $t = pv_csrf_token($action);
    echo '<input type="hidden" name="pv_csrf" value="'.h($t).'">';
  }
}
if (!function_exists('pv_csrf_check')) {
  function pv_csrf_check($action='global'){
    pv_session_start();
    $exp = $_SESSION['pv_csrf_'.$action] ?? '';
    $val = $_POST['pv_csrf'] ?? '';
    return ($exp && $val && hash_equals($exp, $val));
  }
}

// Thumbs (safe)
if (!function_exists('pv_thumb_path')) {
  function pv_thumb_path($imagePath, $size){
    $dot = strrpos($imagePath, '.');
    if ($dot === false) return $imagePath . '-' . $size;
    return substr($imagePath, 0, $dot) . '-' . $size . substr($imagePath, $dot);
  }
}
if (!function_exists('pv_ensure_thumbs_for')) {
  function pv_ensure_thumbs_for($imageUrlRel){
    if (!$imageUrlRel || !extension_loaded('gd')) return;
    $sizes = ['300x300' => [300,300], '96x96' => [96,96]];
    foreach ($sizes as $key => $wh) {
      $tRel = pv_thumb_path($imageUrlRel, $key);
      $srcAbs = __DIR__ . '/' . ltrim($imageUrlRel,'/');
      $tAbs   = __DIR__ . '/' . ltrim($tRel,'/');
      if (!is_file($tAbs) && is_file($srcAbs)) {
        $info = @getimagesize($srcAbs); if(!$info) continue;
        $w = $info[0]; $h = $info[1];
        $src = null;
        $mime = strtolower($info['mime'] ?? '');
        if ($mime === 'image/jpeg' || $mime === 'image/jpg') { $src=@imagecreatefromjpeg($srcAbs); }
        elseif ($mime === 'image/png') { $src=@imagecreatefrompng($srcAbs); if($src) imagesavealpha($src,true); }
        elseif ($mime === 'image/gif') { $src=@imagecreatefromgif($srcAbs); if($src) imagesavealpha($src,true); }
        elseif ($mime === 'image/webp' && function_exists('imagecreatefromwebp')) { $src=@imagecreatefromwebp($srcAbs); if($src && function_exists('imagepalettetotruecolor')) @imagepalettetotruecolor($src); }
        if($src){
          $tw=$wh[0]; $th=$wh[1];
          $scale = max($tw/max(1,$w), $th/max(1,$h));
          $nw = (int)ceil($w*$scale); $nh=(int)ceil($h*$scale);
          $tmp=imagecreatetruecolor($nw,$nh); imagealphablending($tmp,false); imagesavealpha($tmp,true);
          imagecopyresampled($tmp,$src,0,0,0,0,$nw,$nh,$w,$h);
          $x=max(0,(int)floor(($nw-$tw)/2)); $y=max(0,(int)floor(($nh-$th)/2));
          $dst=imagecreatetruecolor($tw,$th); imagealphablending($dst,false); imagesavealpha($dst,true);
          imagecopy($dst,$tmp,0,0,$x,$y,$tw,$th);
          if ($mime === 'image/png' || $mime === 'image/gif') { @imagepng($dst,$tAbs,6); }
          else { @imagejpeg($dst,$tAbs,85); }
          @imagedestroy($src); @imagedestroy($tmp); @imagedestroy($dst);
        }
      }
    }
  }
}
