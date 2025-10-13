<?php
if (!function_exists('h')) { function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); } }
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
if (!function_exists('csrf_token_make')) {
  function csrf_token_make($key='global'){
    if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
    $k = 'csrf_'.$key;
    if (empty($_SESSION[$k])) {
      $_SESSION[$k] = bin2hex(function_exists('random_bytes')?random_bytes(16):(string)mt_rand(100000,999999).microtime(true));
    }
    if ($key==='global') {
      $_SESSION['csrf'] = $_SESSION[$k];
      $_SESSION['csrf_token'] = $_SESSION[$k];
    }
    return $_SESSION[$k];
  }
}
if (!function_exists('csrf_field_both')) {
  function csrf_field_both($action='global'){
    if (function_exists('csrf_field')) { try { echo csrf_field($action); } catch (Throwable $e) {} }
    $tg = csrf_token_make('global');
    $ta = csrf_token_make($action);
    echo '<input type="hidden" name="csrf" value="'.h($tg).'">';
    echo '<input type="hidden" name="_token" value="'.h($tg).'">';
    echo '<input type="hidden" name="csrf_token" value="'.h($tg).'">';
    echo '<input type="hidden" name="csrf_'.h($action).'" value="'.h($ta).'">';
  }
}
if (!function_exists('csrf_check_both')) {
  function csrf_check_both($action='global'){
    if (function_exists('csrf_check')) {
      try { if (csrf_check($action, null) === true) return true; } catch (Throwable $e) {}
    }
    if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
    $expA = $_SESSION['csrf_'.$action] ?? '';
    $expG = $_SESSION['csrf_global'] ?? ($_SESSION['csrf'] ?? $_SESSION['csrf_token'] ?? '');
    $vals = [ $_POST['csrf_'.$action] ?? '', $_POST['_token'] ?? '', $_POST['csrf_token'] ?? '', $_POST['csrf'] ?? '' ];
    foreach ($vals as $v) { if ($v && (($expA && hash_equals($expA,$v)) || ($expG && hash_equals($expG,$v)))) { return true; } }
    return false;
  }
}
if (!function_exists('thumb_path')) {
  function thumb_path($imagePath, $size){
    $dot = strrpos($imagePath, '.');
    if ($dot === false) return $imagePath . '-' . $size;
    return substr($imagePath, 0, $dot) . '-' . $size . substr($imagePath, $dot);
  }
}
if (!function_exists('ensure_thumbs_for')) {
  function ensure_thumbs_for($imageUrlRel){
    if (!$imageUrlRel || !extension_loaded('gd')) return;
    $sizes = ['300x300'=>[300,300],'96x96'=>[96,96]];
    foreach($sizes as $key=>$wh){
      $tRel = thumb_path($imageUrlRel, $key);
      $srcAbs = __DIR__ . '/' . ltrim($imageUrlRel,'/');
      $tAbs = __DIR__ . '/' . ltrim($tRel,'/');
      if (!is_file($tAbs) && is_file($srcAbs)) {
        $info = @getimagesize($srcAbs); if(!$info) continue;
        $w=$info[0]; $h=$info[1]; $mime=strtolower($info['mime'] ?? '');
        $src=null;
        if($mime==='image/jpeg'||$mime==='image/jpg') $src=@imagecreatefromjpeg($srcAbs);
        elseif($mime==='image/png') { $src=@imagecreatefrompng($srcAbs); if($src) imagesavealpha($src,true); }
        elseif($mime==='image/gif') { $src=@imagecreatefromgif($srcAbs); if($src) imagesavealpha($src,true); }
        elseif($mime==='image/webp' && function_exists('imagecreatefromwebp')) { $src=@imagecreatefromwebp($srcAbs); if($src && function_exists('imagepalettetotruecolor')) @imagepalettetotruecolor($src); }
        if($src){
          $tw=$wh[0]; $th=$wh[1];
          $scale = max($tw/max(1,$w), $th/max(1,$h));
          $nw=(int)ceil($w*$scale); $nh=(int)ceil($h*$scale);
          $tmp=imagecreatetruecolor($nw,$nh); imagealphablending($tmp,false); imagesavealpha($tmp,true);
          imagecopyresampled($tmp,$src,0,0,0,0,$nw,$nh,$w,$h);
          $x=max(0,(int)floor(($nw-$tw)/2)); $y=max(0,(int)floor(($nh-$th)/2));
          $dst=imagecreatetruecolor($tw,$th); imagealphablending($dst,false); imagesavealpha($dst,true);
          imagecopy($dst,$tmp,0,0,$x,$y,$tw,$th);
          if($mime==='image/png'||$mime==='image/gif') @imagepng($dst,$tAbs,6); else @imagejpeg($dst,$tAbs,85);
          @imagedestroy($src); @imagedestroy($tmp); @imagedestroy($dst);
        }
      }
    }
  }
}
