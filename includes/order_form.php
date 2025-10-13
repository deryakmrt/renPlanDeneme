<?php
// includes/order_form.php
// Beklenen: $mode ('new'|'edit'), $order (assoc), $customers, $products, $items
?>
<?php $__role = current_user()['role'] ?? ''; $__is_admin_like = in_array($__role, ['admin','sistem_yoneticisi'], true); ?>

<div class="card">
  <h2><?= $mode==='edit' ? 'Sipariş Düzenle' : 'Yeni Sipariş' ?></h2>

  <?php if ($mode==='edit' && !empty($order['id'])): ?>
    <div class="row" style="justify-content:flex-end; gap:8px; margin-bottom:8px">
      <a class="btn" href="order_view.php?id=<?= (int)$order['id'] ?>">Görüntüle</a>
      <?php if ($__is_admin_like): ?>
      <a class="btn primary" href="order_pdf.php?id=<?= (int)$order['id'] ?>">STF</a>
<?php endif; ?>
      <button type="button" class="btn primary" onclick="this.closest('.card').querySelector('form').submit()">Güncelle</button>
      <a class="btn" href="orders.php">Vazgeç</a>
    </div>
  <?php endif; ?>

  <form method="post">
    <?php csrf_input(); ?>
    <!-- 4'lü gridler (temiz ve tekil) -->
    <div class="grid g4 mt" style="gap:12px">
      <div>
        <label>Durum</label>
        <select name="status">
          <?php foreach(['tedarik'=>'Tedarik','sac lazer'=>'Sac Lazer','boru lazer'=>'Boru Lazer','kaynak'=>'Kaynak','boya'=>'Boya','elektrik montaj'=>'Elektrik Montaj','test'=>'Test','paketleme'=>'Paketleme','sevkiyat'=>'Sevkiyat','teslim edildi'=>'Teslim Edildi'] as $k=>$v): ?>
            <option value="<?= h($k) ?>" <?= ($order['status']??'')===$k?'selected':'' ?>><?= h($v) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div><label>Sipariş Kodu</label><input name="order_code" value="<?= h($order['order_code'] ?? '') ?>"></div>
      <div>
        
<label>Müşteri</label>
<?php if ($mode==='new'): ?>
  <select name="customer_id" required>
    <option value="">– Seç –</option>
    <?php foreach ($customers as $c): ?>
      <option value="<?= (int)$c['id'] ?>"><?= h($c['name']) ?></option>
    <?php endforeach; ?>
  </select>
<?php else: // edit ?>
  <?php
    $__custName = '';
    $__custId = (int)($order['customer_id'] ?? 0);
    if ($__custId) { foreach ($customers as $c) { if ((int)$c['id'] === $__custId) { $__custName = $c['name']; break; } } }
  ?>
  <div class="muted" style="padding:8px 10px; border:1px solid #e5e7eb; border-radius:6px; background:#fafafa;">
    <?= h($__custName ?: '—') ?>
  </div>
  <input type="hidden" name="customer_id" value="<?= (int)$__custId ?>">
  <div style="margin-top:6px">
    <label style="font-size:12px;color:#6b7280">Değiştir:</label>
    <select name="customer_id_override">
      <option value="">—</option>
      <?php foreach ($customers as $c): ?>
        <option value="<?= (int)$c['id'] ?>"><?= h($c['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <small class="muted">Yeni müşteri seçerseniz kaydettiğinizde müşteri güncellenir.</small>
  </div>
<?php endif; ?>
      </div>
      <div><label>Sipariş Tarihi</label><input type="date" name="siparis_tarihi" value="<?= h( ($order['siparis_tarihi'] ?? '') ?: date('Y-m-d') ) ?>"></div>
    </div>

    <div class="grid g4 mt" style="gap:12px">
      <div><label>Proje Adı</label><input name="proje_adi" value="<?= h($order['proje_adi'] ?? '') ?>"></div>
      <div><label>Revizyon No</label><input name="revizyon_no" value="<?= h($order['revizyon_no'] ?? '') ?>"></div>
      <div>
        <label>Fatura Para Birimi</label>
        <select name="fatura_para_birimi">
          <?php $val = $order['fatura_para_birimi'] ?? ''; ?>
          <option value="TL"  <?= $val==='TL'  ?'selected':'' ?>>TL</option>
          <option value="EUR" <?= $val==='EUR' ?'selected':'' ?>>Euro</option>
          <option value="USD" <?= $val==='USD' ?'selected':'' ?>>USD</option>
        </select>
      </div>
      <div>
        <label>Ödeme Para Birimi</label>
        <select name="odeme_para_birimi">
          <?php $val2 = $order['odeme_para_birimi'] ?? ''; ?>
          <option value="TL"  <?= $val2==='TL'  ?'selected':'' ?>>TL</option>
          <option value="EUR" <?= $val2==='EUR' ?'selected':'' ?>>Euro</option>
          <option value="USD" <?= $val2==='USD' ?'selected':'' ?>>USD</option>
        </select>
      </div>
    </div>

    <div class="grid g4 mt" style="gap:12px">
      <div><label>Sipariş Veren</label><input name="siparis_veren" value="<?= h($order['siparis_veren'] ?? '') ?>"></div>
      <div><label>Siparişi Alan</label><input name="siparisi_alan" value="<?= h($order['siparisi_alan'] ?? '') ?>"></div>
      <div><label>Siparişi Giren</label><input name="siparisi_giren" value="<?= h($order['siparisi_giren'] ?? '') ?>"></div>
      <div><label>Ödeme Koşulu</label><input name="odeme_kosulu" value="<?= h($order['odeme_kosulu'] ?? '') ?>"></div>
    </div>

    <div class="grid g4 mt" style="gap:12px">
      <div><label>Termin Tarihi</label><input type="date" name="termin_tarihi" value="<?= h($order['termin_tarihi'] ?? '') ?>"></div>
      <div><label>Başlangıç Tarihi</label><input type="date" name="baslangic_tarihi" value="<?= h($order['baslangic_tarihi'] ?? '') ?>"></div>
      <div><label>Bitiş Tarihi</label><input type="date" name="bitis_tarihi" value="<?= h($order['bitis_tarihi'] ?? '') ?>"></div>
      <div><label>Teslim Tarihi</label><input type="date" name="teslim_tarihi" value="<?= h($order['teslim_tarihi'] ?? '') ?>"></div>
    </div>

    <div class="grid g4 mt" style="gap:12px">
      <div><label>Nakliye Türü</label><input name="nakliye_turu" value="<?= h($order['nakliye_turu'] ?? '') ?>"></div>
      <div></div>
      <div></div>
      <div></div>
    </div>

    <h3 class="mt">Kalemler</h3>
    <div id="items">
      <div class="row mb">
        <button type="button" class="btn" onclick="addRow()">+ Satır Ekle</button>
      </div>
      <table id="itemsTable">
        <tr>
          <th style="width:12%">Stok Kodu</th>
          <th style="width:10%">Ürün Görseli</th>
          <th style="width:22%">Ürün</th>
          <th>Ad</th>
          <th style="width:8%">Birim</th>
          <th style="width:8%">Miktar</th>
          <?php if ($__is_admin_like): ?><th style="width:12%">Birim Fiyat</th><?php endif; ?>
          <th>Ürün Özeti</th>
          <th>Kullanım Alanı</th>
          <?php if ($__is_admin_like): ?><th class="right" style="width:8%">Sil</th><?php endif; ?>
        </tr>
        <?php if (!$items) { $items = [[]]; } ?>
        <?php foreach ($items as $it): ?>
        <tr><td><input name="stok_kodu[]" class="stok-kodu" placeholder="Stok Kodu"></td>
          <td class="urun-gorsel" style="text-align:center"><img class="urun-gorsel-img" style="max-width:64px;max-height:64px;display:none;margin:0 auto" alt=""></td>
          <td>
            <select name="product_id[]" onchange="onPickProduct(this)">
              <option value="">—</option>
              <?php foreach($products as $p): ?>
              <option
                value="<?= (int)$p['id'] ?>"
                data-name="<?= h($p['name']) ?>"
                data-unit="<?= h($p['unit']) ?>"
                data-price="<?= h($p['price']) ?>"
                data-ozet="<?= h($p['urun_ozeti']) ?>"
                data-kalan="<?= h($p['kullanim_alani']) ?>"
                data-image="<?= h($p['image'] ?? '') ?>"
                <?= (isset($it['product_id']) && (int)$it['product_id']===(int)$p['id'])?'selected':'' ?>
              ><?= h($p['name']) ?><?= $p['sku'] ? ' ('.h($p['sku']).')':'' ?></option>
              <?php endforeach; ?>
            </select>
          </td>
          <td><input name="name[]" value="<?= h($it['name'] ?? '') ?>" required></td>
          <td><input name="unit[]" value="<?= h($it['unit'] ?? 'Adet') ?>"></td>
          <td><input name="qty[]" type="number" step="0.01" value="<?= h($it['qty'] ?? '1') ?>"></td>
          <?php if ($__is_admin_like): ?><td><input name="price[]" type="number" step="0.01" value="<?= h($it['price'] ?? '0') ?>"></td><?php endif; ?>
          <td><input name="urun_ozeti[]" value="<?= h($it['urun_ozeti'] ?? '') ?>"></td>
          <td><input name="kullanim_alani[]" value="<?= h($it['kullanim_alani'] ?? '') ?>"></td>
          <?php if ($__is_admin_like): ?><td class="right"><button type="button" class="btn" onclick="delRow(this)">Sil</button></td><?php endif; ?>
        </tr>
        <?php endforeach; ?>
      </table>
    </div>
	    <div class="grid g3 mt" style="gap:12px">
      <div><label class="mt">Notlar</label>
    
<!-- Chat-style Notes Block START -->
<?php
  // Kullanıcı adını header.php ile aynı kaynaktan al: $_SESSION['uname']
  $__user_name = $_SESSION['uname'] ?? '';

  // Eğer boşsa users tablosundan; yoksa diğer fallbacks
  if (!$__user_name) {
      try {
          if (!empty($_SESSION['uid'])) {
              $st = $db->prepare("SELECT name FROM users WHERE id=?");
              $st->execute([ (int)$_SESSION['uid'] ]);
              $__u = $st->fetch(PDO::FETCH_ASSOC);
              if ($__u && !empty($__u['name'])) { $__user_name = $__u['name']; }
          }
      } catch (Throwable $e) { /* sessiz geç */ }
  }

  if (!$__user_name) {
      if (!empty($order['user-name'])) { $__user_name = $order['user-name']; }
      elseif (!empty($order['user_name'])) { $__user_name = $order['user_name']; }
      elseif (!empty($_SESSION['user']['name'])) { $__user_name = $_SESSION['user']['name']; }
      elseif (!empty($_SESSION['user_name'])) { $__user_name = $_SESSION['user_name']; }
      elseif (!empty($auth_user['name'])) { $__user_name = $auth_user['name']; }
      elseif (!empty($current_user['name'])) { $__user_name = $current_user['name']; }
      else { $__user_name = 'Kullanıcı'; }
  }
?>
<div id="notes-block" data-user="<?= h($__user_name) ?>" style="display:flex; flex-direction:column; gap:8px;">
  <div class="notes-wrapper" style="max-height:260px; overflow:auto; padding:8px; background:#fff; border:1px solid #e6e8ee; border-radius:8px;">
    <?php 
      $__notes_text = $order['notes'] ?? '';
      $__notes_lines = array_filter(preg_split("/\r\n|\r|\n/", (string)$__notes_text));
      if (!empty($__notes_lines)):
        foreach ($__notes_lines as $__line): 
          $__date = ''; $__author = ''; $__text = $__line;

          // Yeni format: "Author | DD.MM.YYYY HH:MM: Text"
          if (preg_match('/^(.*?)\s*\|\s*(\d{2}\.\d{2}\.\d{4}\s+\d{2}:\d{2})\s*:\s*(.*)$/u', $__line, $__m)) {
            $__author = trim($__m[1]); $__date = $__m[2]; $__text = $__m[3];
          }
          // Eski formatı da destekle: "DD.MM.YYYY HH:MM | Author: Text"
          elseif (preg_match('/^(\d{2}\.\d{2}\.\d{4}\s+\d{2}:\d{2})\s*\|\s*(.*?):\s*(.*)$/u', $__line, $__m)) {
            $__date = $__m[1]; $__author = trim($__m[2]); $__text = $__m[3];
          }
    ?>
          <div class="note-item" data-original="<?= h($__line) ?>" style="margin-bottom:8px; display:flex; align-items:flex-start; gap:8px;">
            <div style="flex:1 1 auto;">
              <div class="note-meta" style="font-size:12px; color:#6b7280; margin-bottom:2px;">
                <?php if ($__author): ?><strong><?= h($__author) ?></strong> · <?php endif; ?>
                <?php if ($__date): ?><span class="note-time"><?= h($__date) ?></span><?php endif; ?>
              </div>
              <div class="note-text" style="display:inline-block; padding:8px 10px; border:1px solid #e6e8ee; border-radius:12px; background:#f9fafb;">
                <?= h($__text) ?>
              </div>
            </div>
            <button type="button" class="note-del" title="Sil" style="border:none; background:transparent; cursor:pointer; padding:4px 6px; font-size:12px; color:#9aa0ad;">🗑</button>
          </div>
    <?php endforeach; else: ?>
          <div style="color:#8b93a7; font-size:12px;">Henüz not yok.</div>
    <?php endif; ?>
  </div>

  <div class="note-input" style="display:flex; gap:8px; align-items:center;">
    <input type="text" name="new_note" placeholder="Yeni not yaz..." style="flex:1; padding:8px 10px; border:1px solid #e6e8ee; border-radius:8px;" />
    <!-- Var olan backend 'notes' alanı devam etsin diye gizli textarea -->
    <textarea name="notes" id="notes-ghost" style="display:none;"><?= h($order['notes'] ?? '') ?></textarea>
  </div>
</div>

<script>
(function(){
  var container = document.getElementById('notes-block');
  if(!container) return;
  var form = container.closest('form');
  if(!form) return;

  function pad(n){ return (n<10?'0':'')+n; }

  // Yeni not ekleme (submit anında 'Author | dd.mm.yyyy hh:mm: Text' formatında sona ekle)
  form.addEventListener('submit', function(){
    var ghost = document.getElementById('notes-ghost');
    var nn = form.querySelector('input[name="new_note"]');
    if(ghost && nn){
      var val = (nn.value || '').trim();
      if(val){
        var d = new Date();
        var stamp = pad(d.getDate()) + '.' + pad(d.getMonth()+1) + '.' + d.getFullYear() + ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes());
        var username = container.getAttribute('data-user') || 'Kullanıcı';
        var line = username + ' | ' + stamp + ': ' + val;
        var base = (ghost.value || '').replace(/\s+$/,'');
        if(base) base += "\n";
        ghost.value = base + line;
      }
    }
  }, {passive:true});

  // Yardımcı: ghost'u DOM'daki satırlardan yeniden oluştur
  function rebuildGhost(){
    var ghost = document.getElementById('notes-ghost');
    if(!ghost) return;
    var items = container.querySelectorAll('.note-item');
    var lines = [];
    for (var i=0;i<items.length;i++){
      var orig = items[i].getAttribute('data-original');
      if(orig && orig.trim()){
        lines.push(orig.trim());
      } else {
        // Fallback: DOM'dan toparla
        var meta = items[i].querySelector('.note-meta');
        var text = items[i].querySelector('.note-text');
        if(meta && text){
          var authorEl = meta.querySelector('strong');
          var author = authorEl ? authorEl.innerText.trim() : '';
          var dateEl = meta.querySelector('.note-time');
          var date = dateEl ? dateEl.innerText : '';
          var body = text.innerText.trim();
          if(author && date && body){
            lines.push(author + ' | ' + date + ': ' + body);
          }
        }
      }
    }
    ghost.value = lines.join("\n");
  }

  // Sil ve 10s geri al
  var undoState = null; // {index, original, toast, interval}
  function showUndoToast(message, onUndo){
    var toast = document.getElementById('note-undo-toast');
    if(!toast){
      toast = document.createElement('div');
      toast.id = 'note-undo-toast';
      toast.style.position = 'fixed';
      toast.style.right = '16px';
      toast.style.bottom = '16px';
      toast.style.background = '#111827';
      toast.style.color = '#fff';
      toast.style.padding = '10px 12px';
      toast.style.borderRadius = '8px';
      toast.style.boxShadow = '0 4px 14px rgba(0,0,0,.25)';
      toast.style.zIndex = '99999';
      document.body.appendChild(toast);
    }
    toast.innerHTML = '';
    var span = document.createElement('span');
    span.textContent = message + ' ';
    toast.appendChild(span);

    var countdown = 10;
    var countEl = document.createElement('span');
    countEl.textContent = '('+countdown+') ';
    toast.appendChild(countEl);

    var btn = document.createElement('button');
    btn.type = 'button';
    btn.textContent = 'Geri al';
    btn.style.marginLeft = '8px';
    btn.style.background = '#10b981';
    btn.style.color = '#fff';
    btn.style.border = 'none';
    btn.style.borderRadius = '6px';
    btn.style.padding = '6px 10px';
    btn.style.cursor = 'pointer';
    toast.appendChild(btn);

    var interval = setInterval(function(){
      countdown -= 1;
      if(countdown <= 0){
        clearInterval(interval);
        if(undoState && undoState.toast === toast){
          toast.parentNode && toast.parentNode.removeChild(toast);
          undoState = null;
        }
      } else {
        countEl.textContent = '('+countdown+') ';
      }
    }, 1000);

    btn.addEventListener('click', function(){
      clearInterval(interval);
      if(onUndo) onUndo();
      toast.parentNode && toast.parentNode.removeChild(toast);
      undoState = null;
    }, {once:true});

    return {toast, interval};
  }

  container.addEventListener('click', function(ev){
    var btn = ev.target.closest('.note-del');
    if(!btn) return;
    var item = btn.closest('.note-item');
    if(!item) return;

    var items = Array.prototype.slice.call(container.querySelectorAll('.note-item'));
    var index = items.indexOf(item);
    var original = item.getAttribute('data-original') || '';

    item.parentNode.removeChild(item);
    rebuildGhost();

    // Eski undo varsa kapat
    if(undoState && undoState.toast){
      try { undoState.toast.parentNode && undoState.toast.parentNode.removeChild(undoState.toast); } catch(e){}
      try { clearInterval(undoState.interval); } catch(e){}
      undoState = null;
    }

    var res = showUndoToast('Not silindi.', function(){
      // Geri al
      var list = container.querySelector('.notes-wrapper');
      var wrapper = document.createElement('div');
      wrapper.innerHTML = '<div class="note-item" data-original=""></div>';
      var restored = wrapper.firstChild;
      restored.setAttribute('data-original', original);
      restored.style.marginBottom = '8px';
      restored.style.display = 'flex';
      restored.style.alignItems = 'flex-start';
      restored.style.gap = '8px';

      var m = original.match(/^(.*?)\s*\|\s*(\d{2}\.\d{2}\.\d{4}\s+\d{2}:\d{2})\s*:\s*(.*)$/);
      var author = m ? m[1] : '';
      var date = m ? m[2] : '';
      var body = m ? m[3] : original;

      var left = document.createElement('div');
      left.style.flex = '1 1 auto';
      var meta = document.createElement('div');
      meta.className = 'note-meta';
      meta.style.fontSize = '12px';
      meta.style.color = '#6b7280';
      meta.style.marginBottom = '2px';
      meta.innerHTML = (author ? '<strong>'+author+'</strong> · ' : '') + (date ? '<span class="note-time">'+date+'</span>' : '');
      var text = document.createElement('div');
      text.className = 'note-text';
      text.style.display = 'inline-block';
      text.style.padding = '8px 10px';
      text.style.border = '1px solid #e6e8ee';
      text.style.borderRadius = '12px';
      text.style.background = '#f9fafb';
      text.textContent = body;
      left.appendChild(meta);
      left.appendChild(text);

      var del = document.createElement('button');
      del.type = 'button';
      del.className = 'note-del';
      del.title = 'Sil';
      del.style.border = 'none';
      del.style.background = 'transparent';
      del.style.cursor = 'pointer';
      del.style.padding = '4px 6px';
      del.style.fontSize = '12px';
      del.style.color = '#9aa0ad';
      del.textContent = '🗑';

      restored.appendChild(left);
      restored.appendChild(del);

      var current = container.querySelectorAll('.note-item');
      if(index >= 0 && index < current.length){
        current[index].parentNode.insertBefore(restored, current[index]);
      } else {
        list.appendChild(restored);
      }
      rebuildGhost();
    });

    undoState = { index:index, original:original, toast:res.toast, interval:res.interval };
  });
})();
</script>

<!-- Chat-style Notes Block END -->
</div>
      <div class="notes-col notes-col-activity">
  <h4>Hareketler</h4>
  <?php
  // Güvenli: audit_trail varsa yükle
  $___act_loaded = false;
  if (file_exists(__DIR__.'/includes/audit_trail.php')) { @include_once __DIR__.'/includes/audit_trail.php'; $___act_loaded = function_exists('audit_fetch'); }
  elseif (file_exists(__DIR__.'/audit_trail.php')) { @include_once __DIR__.'/audit_trail.php'; $___act_loaded = function_exists('audit_fetch'); }

  $___order_id = 0;
  if (isset($order['id'])) { $___order_id = (int)$order['id']; }
  elseif (isset($order_id)) { $___order_id = (int)$order_id; }
  elseif (isset($_GET['id'])) { $___order_id = (int)$_GET['id']; }

  if (!$___act_loaded) {
    echo '<div class="muted">Audit modülü yok.</div>';
  } else {
    try {
      $___db = function_exists('pdo') ? pdo() : (isset($db) ? $db : null);
      if (!$___db) { echo '<div class="muted">DB yok.</div>'; }
      else {
        $___rows = $___order_id ? audit_fetch($___db, $___order_id, 100, 0) : [];
        if (!$___rows) {
          echo '<div class="muted">Henüz hareket yok.</div>';
        } else {
          foreach ($___rows as $r) {
            $u = trim((string)($r['user_name'] ?? 'Sistem'));
            $field = (string)($r['field'] ?? '');
            $label = '';
            if (!empty($r['meta'])) { $m = json_decode($r['meta'], true); if (isset($m['label'])) $label = (string)$m['label']; }
            $fieldLabel = $label ?: $field;
            $old = (string)($r['old_value'] ?? '');
            $new = (string)($r['new_value'] ?? '');
            $action = (string)($r['action'] ?? '');
            $when = date('d.m.Y H:i', strtotime((string)$r['created_at']));
            $msg = ($action === 'status_change')
              ? 'Durum değişti: <b>'.htmlspecialchars($old,ENT_QUOTES,'UTF-8').'</b> → <b>'.htmlspecialchars($new,ENT_QUOTES,'UTF-8').'</b>'
              : $fieldLabel.' değişti: <b>'.htmlspecialchars($old,ENT_QUOTES,'UTF-8').'</b> → <b>'.htmlspecialchars($new,ENT_QUOTES,'UTF-8').'</b>';
            echo '<div class="activity-item" style="border:1px solid #eee;padding:8px;border-radius:8px;background:#fff;margin-bottom:8px;">'
               . '<div style="font-size:12px;opacity:.7;">'.htmlspecialchars($when,ENT_QUOTES,'UTF-8').' • '.htmlspecialchars($u,ENT_QUOTES,'UTF-8').'</div>'
               . '<div>'.$msg.'</div>'
               . '</div>';
          }
        }
      }
    } catch (Exception $e) {
      echo '<div class="muted">Hareketler yüklenemedi.</div>';
    }
  }
  ?>
</div>
      <div></div>
      <div></div>
    </div>
    <div class="row mt" style="justify-content:flex-end; gap:8px; margin-top:16px">
      <a class="btn" href="order_view.php?id=<?= (int)$order['id'] ?>">Görüntüle</a>
      <?php if ($__is_admin_like): ?><a class="btn primary" href="order_pdf.php?id=<?= (int)$order['id'] ?>">PDF</a><?php endif; ?>
      <a class="btn" style="background-color:#16a34a;border-color:#15803d;color:#fff" href="order_pdf_uretim.php?id=<?= (int)$order['id'] ?>" target="_blank">Üretim Föyü</a>
      <button type="submit" class="btn primary"><?= $mode==='edit' ? 'Güncelle' : 'Kaydet' ?></button>
      <a class="btn" href="orders.php">Vazgeç</a>
    </div>
  </form>
</div>

<script>
function addRow(){
  const tr = document.createElement('tr');
  tr.innerHTML = `
    <td><input name="stok_kodu[]" class="stok-kodu" placeholder="Stok Kodu"></td>
    <td class="urun-gorsel" style="text-align:center"><img class="urun-gorsel-img" style="max-width:64px;max-height:64px;display:none;margin:0 auto" alt=""></td>
    <td>
      <select name="product_id[]" onchange="onPickProduct(this)">
        <option value="">—</option>
        <?php foreach($products as $p): ?>
        <option
          value="<?= (int)$p['id'] ?>"
          data-name="<?= h($p['name']) ?>"
          data-unit="<?= h($p['unit']) ?>"
          data-price="<?= h($p['price']) ?>"
          data-ozet="<?= h($p['urun_ozeti']) ?>"
          data-kalan="<?= h($p['kullanim_alani']) ?>"
                data-image="<?= h($p['image'] ?? '') ?>"
        ><?= h($p['name']) ?><?= $p['sku'] ? ' ('.h($p['sku']).')':'' ?></option>
        <?php endforeach; ?>
      </select>
    </td>
    <td><input name="name[]" required></td>
    <td><input name="unit[]" value="Adet"></td>
    <td><input name="qty[]" type="number" step="0.01" value="1"></td>
    <?php if ($__is_admin_like): ?><td><input name="price[]" type="number" step="0.01" value="0"></td><?php endif; ?>
    <td><input name="urun_ozeti[]"></td>
    <td><input name="kullanim_alani[]"></td>
    <?php if ($__is_admin_like): ?><td class="right"><button type="button" class="btn" onclick="delRow(this)">Sil</button></td><?php endif; ?>
  `;
  document.querySelector('#itemsTable').appendChild(tr);
  bindSkuInputs(tr);
}
function delRow(btn){
  const tr = btn.closest('tr');
  if(!tr) return;
  tr.parentNode.removeChild(tr);
}
function bindSkuInputs(scope){
  var root = scope || document;
  var inputs = root.querySelectorAll('.stok-kodu');
  inputs.forEach(function(inp){
    // Avoid double-binding
    if(inp.dataset.boundSku === '1') return;
    inp.dataset.boundSku = '1';
    inp.addEventListener('change', async function(){
      var code = (this.value || '').trim();
      if(!code) return;
      try{
        var res = await fetch('ajax_product_lookup.php?code=' + encodeURIComponent(code));
        var data = await res.json();
        if(data && data.success){
          var tr = this.closest('tr');
          var sel = tr.querySelector('select[name="product_id[]"]');
          if(sel){ sel.value = String(data.id); try{ sel.dispatchEvent(new Event('change', {bubbles:true})); }catch(_e){ sel.dispatchEvent(new Event('change')); } }
          // Fill fields in case user didn't choose from select
          var name = tr.querySelector('input[name="name[]"]');
          var unit = tr.querySelector('input[name="unit[]"]');
          var price = tr.querySelector('input[name="price[]"]');
          var oz = tr.querySelector('input[name="urun_ozeti[]"]');
          var ka = tr.querySelector('input[name="kullanim_alani[]"]');
          if(name && data.name) name.value = data.name;
          if(unit && data.unit) unit.value = data.unit;
          if(price && (data.price!==undefined)) price.value = data.price;
          if(oz && data.urun_ozeti) oz.value = data.urun_ozeti;
          if(ka && data.kullanim_alani) ka.value = data.kullanim_alani;
        }else{
          alert('Ürün bulunamadı');
        }
      }catch(e){
        alert('Ürün getirilirken hata oluştu');
      }
    });
  });
}

function onPickProduct(sel){
  const opt = sel.options[sel.selectedIndex];
  if(!opt) return;
  const tr = sel.closest('tr');
  tr.querySelector('input[name="name[]"]').value = opt.getAttribute('data-name') || '';
  tr.querySelector('input[name="unit[]"]').value = opt.getAttribute('data-unit') || 'Adet';
  <?php if ($__is_admin_like): ?>tr.querySelector('input[name="price[]"]').value = opt.getAttribute('data-price') || '0';<?php endif; ?>
  tr.querySelector('input[name="urun_ozeti[]"]').value = opt.getAttribute('data-ozet') || '';
  tr.querySelector('input[name="kullanim_alani[]"]').value = opt.getAttribute('data-kalan') || '';
  var raw = opt.getAttribute('data-image') || '';
  if (raw && !(raw.startsWith('http://') || raw.startsWith('https://') || raw.startsWith('/'))) {
    if (raw.startsWith('uploads/')) raw = '/' + raw;
    else if (raw.startsWith('./')) raw = raw.slice(1);
    else raw = '/uploads/' + raw;
  }
  var imgEl = tr.querySelector('.urun-gorsel-img');
  if (imgEl) {
    imgEl.src = raw || '';
    imgEl.alt = raw ? 'Ürün görseli' : '';
    imgEl.style.display = raw ? 'block' : 'none';
  }
}

document.addEventListener('DOMContentLoaded', function(){
  var f = document.querySelector('form');
  if(!f) return;
  // stok kodu inputlarına dinleyici bağla
  try { bindSkuInputs(); } catch(_e){}
  // mevcut seçili ürünler için görselleri doldur (özellikle order_edit)
  document.querySelectorAll('select[name="product_id[]"]').forEach(function(s){
    if (s && s.value) { try { onPickProduct(s); } catch(_e){} }
  });
  // müşteri override hidden alanı
  f.addEventListener('submit', function(){
    var ov = f.querySelector('select[name="customer_id_override"]');
    if(ov && ov.value){
      var hid = f.querySelector('input[name="customer_id"]');
      if(!hid){ hid = document.createElement('input'); hid.type='hidden'; hid.name='customer_id'; f.appendChild(hid); }
      hid.value = ov.value;
    }
  });
});
</script>



<style>
/* === Büyük metin düzenleyici (tooltip/popup) === */
.popover-overlay{position:fixed;inset:0;background:rgba(0,0,0,.2);z-index:9990;display:none}
.popover-editor{position:fixed;z-index:9991;max-width:384px;width:min(60vw,384px);background:#fff;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.2);padding:16px;display:none}
.popover-editor textarea{width:100%;min-height:108px;max-height:36vh;resize:vertical;overflow:auto;font:inherit;line-height:1.4;padding:10px;border:1px solid #e5e7eb;border-radius:10px}
.popover-actions{display:flex;gap:8px;justify-content:flex-end;margin-top:10px}
.popover-editor .field-label{font-weight:600;margin-bottom:6px;display:block}
</style>

<!-- Popover editor container -->
<div id="__popover_overlay" class="popover-overlay"></div>
<div id="__popover" class="popover-editor" role="dialog" aria-modal="true" aria-label="Metin düzenleyici">
  <label class="field-label" id="__popover_label"></label>
  <textarea id="__popover_text"></textarea>
  <div class="popover-actions">
    <button type="button" class="btn" id="__popover_cancel">Vazgeç (Esc)</button>
    <button type="button" class="btn primary" id="__popover_save">Kaydet (Ctrl+Enter)</button>
  </div>
</div>

<script>
(function(){
  function qs(s,sc){return (sc||document).querySelector(s)}
  function qsa(s,sc){return Array.prototype.slice.call((sc||document).querySelectorAll(s))}

  var overlay = qs('#__popover_overlay');
  var pop = qs('#__popover');
  var tarea = qs('#__popover_text');
  var label = qs('#__popover_label');
  var cancelBtn = qs('#__popover_cancel');
  var saveBtn = qs('#__popover_save');
  var currentInput = null;

  
  function openEditor(input){
    currentInput = input;
    label.textContent = input.name.indexOf('urun_ozeti')>-1 ? 'Ürün Özeti' : 'Kullanım Alanı';
    tarea.value = input.value || '';

    // Önce görünür yapıp ölç
    overlay.style.display = 'block';
    pop.style.display = 'block';
    pop.style.top = '-10000px'; // ölçüm için ekrandan uzak
    pop.style.left = '-10000px';

    var rect = input.getBoundingClientRect();
    var vw = window.innerWidth, vh = window.innerHeight;

    // Tercihen input'un altına ve sola hizala
    var desiredTop = rect.bottom + 8;
    var desiredLeft = rect.left;

    var pw = pop.offsetWidth;
    var ph = pop.offsetHeight;

    // Sağdan taşarsa sola kaydır
    if (desiredLeft + pw + 16 > vw) {
      desiredLeft = Math.max(16, vw - pw - 16);
    } else {
      desiredLeft = Math.max(16, desiredLeft);
    }

    // Alttan taşarsa üste yerleştir
    if (desiredTop + ph + 16 > vh) {
      var above = rect.top - ph - 8;
      if (above >= 16) {
        desiredTop = above;
      } else {
        // Sığmıyorsa ekranın ortasına al
        desiredTop = Math.max(16, (vh - ph) / 2);
        desiredLeft = Math.max(16, (vw - pw) / 2);
      }
    }

    // Sayfa kaydırmasını ekle
    pop.style.top = desiredTop + 'px';
    pop.style.left = desiredLeft + 'px';

    // Gerekirse alanı görünür yap
    try { input.scrollIntoView({block:'nearest', inline:'nearest'}); } catch(_e) {}

    setTimeout(function(){ tarea.focus(); tarea.select(); }, 0);
  }

  function closeEditor(){
    overlay.style.display = 'none';
    pop.style.display = 'none';
    currentInput = null;
  }
  function saveEditor(){
    if(!currentInput) return closeEditor();
    currentInput.value = tarea.value.trim();
    // change ve input eventleri tetikle (varsa dinleyiciler güncellensin)
    try{ currentInput.dispatchEvent(new Event('input', {bubbles:true})); }catch(_e){}
    try{ currentInput.dispatchEvent(new Event('change', {bubbles:true})); }catch(_e){}
    closeEditor();
  }

  // Dinleyiciler
  document.addEventListener('click', function(ev){
    var t = ev.target;
    if (t && (t.matches('input[name="urun_ozeti[]"]') || t.matches('input[name="kullanim_alani[]"]'))) {
      ev.preventDefault();
      openEditor(t);
    }
  });
  overlay.addEventListener('click', closeEditor);
  cancelBtn.addEventListener('click', closeEditor);
  saveBtn.addEventListener('click', saveEditor);

  // Klavye kısayolları
  pop.addEventListener('keydown', function(e){
    if(e.key === 'Escape'){ e.preventDefault(); closeEditor(); }
    if((e.ctrlKey || e.metaKey) && e.key === 'Enter'){ e.preventDefault(); saveEditor(); }
  });

  // Pencere yeniden boyutlanınca popover'ı görünür alanda tut
  window.addEventListener('resize', function(){
    if(!currentInput || pop.style.display==='none') return;
    openEditor(currentInput);
  });
})();
</script>

<style></style>

<style></style>

<style>.popover-editor{transform:scale(0.8);transform-origin:top left;}</style>