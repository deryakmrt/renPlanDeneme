<?php
// includes/order_form.php
// Beklenen: $mode ('new'|'edit'), $order (assoc), $customers, $products, $items
?>
<?php $__role = current_user()['role'] ?? ''; $__is_admin_like = in_array($__role, ['admin','sistem_yoneticisi'], true); ?>

<style>
/* Sayfa yüklenirken select'leri anında gizle (FOUC önleme) */
select[name="product_id[]"] {
    display: none !important;
}
</style>

<div class="card">
  <h2><?= $mode==='edit' ? 'Sipariş Düzenle' : 'Yeni Sipariş' ?></h2>

  <?php if ($mode==='edit' && !empty($order['id'])): ?>
    <div class="row" style="justify-content:flex-end; gap:8px; margin-bottom:8px">
      <a class="btn" href="order_view.php?id=<?= (int)$order['id'] ?>">Görüntüle</a>
      <?php if ($__is_admin_like): ?>
      <a class="btn primary" href="order_pdf.php?id=<?= (int)$order['id'] ?>">STF</a>
<?php endif; ?>
      <button type="button" class="btn primary" onclick="this.closest('.card').querySelector('form').requestSubmit()">Güncelle</button>
      <a class="btn" href="orders.php">Vazgeç</a>
    </div>
  <?php endif; ?>

  <form method="post">
    <?php csrf_input(); ?>
    <!-- 4'lü gridler (temiz ve tekil) -->
    <div class="grid g4 mt" style="gap:12px">
      <div>
        <label>Durum</label>
        <?php if (($order['status'] ?? '') === 'taslak_gizli'): ?>
            <div style="padding:8px; border:1px dashed #d97706; background:#fffbeb; border-radius:6px; color:#d97706;">
                <div style="font-weight:bold; display:flex; align-items:center; gap:6px;">
                    🔒 Taslak (Gizli)
                </div>
                <div style="font-size:11px; margin-top:2px;">Yayınla diyene kadar kimse görmez.</div>
                <input type="hidden" name="status" value="taslak_gizli">
            </div>
        <?php else: ?>
            <select name="status">
              <?php 
              // Normal Durum Listesi
              $status_list = [
                  'tedarik' => 'Tedarik',
                  'sac lazer' => 'Sac Lazer',
                  'boru lazer' => 'Boru Lazer',
                  'kaynak' => 'Kaynak',
                  'boya' => 'Boya',
                  'elektrik montaj' => 'Elektrik Montaj',
                  'test' => 'Test',
                  'paketleme' => 'Paketleme',
                  'sevkiyat' => 'Sevkiyat',
                  'teslim edildi' => 'Teslim Edildi'
              ];
              // Eğer veritabanında listede olmayan bir durum varsa (örn: eski 'taslak'), onu da ekle ki bozulmasın
              $__curStat = $order['status'] ?? '';
              if ($__curStat && !isset($status_list[$__curStat]) && $__curStat !== 'taslak_gizli') {
                  $status_list[$__curStat] = ucfirst($__curStat);
              }

              foreach($status_list as $k=>$v): ?>
                <option value="<?= h($k) ?>" <?= ($order['status']??'')===$k?'selected':'' ?>><?= h($v) ?></option>
              <?php endforeach; ?>
            </select>
        <?php endif; ?>
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
          <th style="width:40px">⋮⋮</th>
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
        <?php 
          $rn = 0; 
          foreach ($items as $it): 
          $rn++; 

          // --- YENİ: Kayıtlı ürünün SKU'sunu bul ---
          $current_sku = '';
          if (!empty($it['product_id'])) {
              foreach ($products as $p) {
                  if ((int)$p['id'] === (int)$it['product_id']) {
                      $current_sku = $p['sku'] ?? '';
                      break;
                  }
              }
          }
          // -----------------------------------------
        ?>
        <tr>
          <td class="drag-handle" style="cursor:move; vertical-align:middle; text-align:center; color:#9ca3af; font-size:18px; user-select:none; width:50px;">
            <div style="display:flex; align-items:center; justify-content:center; gap:2px;">
                <span class="row-index"><?= $rn ?></span> ⋮⋮
            </div>
          </td>
          <td><input name="stok_kodu[]" class="stok-kodu" placeholder="Stok Kodu" value="<?= h($current_sku) ?>"></td>
          <td class="urun-gorsel" style="text-align:center; vertical-align:middle;">
            <?php 
                // 1. Veritabanından gelen resmi al
                $showImg = $it['image'] ?? ''; 
                
                // 2. Eğer resim boşsa ve product_id varsa, products listesinden ürünü bulup resmini çek
                if (empty($showImg) && !empty($it['product_id'])) {
                    foreach ($products as $pr) {
                        if ((int)$pr['id'] === (int)$it['product_id']) {
                            $showImg = $pr['image'] ?? '';
                            break;
                        }
                    }
                }
                
                // 3. Hala boşsa ve Parent ID varsa, babadan çek (child ürünler için)
                if (empty($showImg) && !empty($it['parent_id'])) {
                    foreach ($products as $pr) {
                        if ((int)$pr['id'] === (int)$it['parent_id']) {
                            $showImg = $pr['image'] ?? '';
                            break;
                        }
                    }
                }

                // 4. Resim yolunu doğrula
                $finalSrc = '';
                if (!empty($showImg)) {
                    // Uploads klasörü
                    if (file_exists(__DIR__ . '/../uploads/product_images/' . $showImg)) {
                        $finalSrc = 'uploads/product_images/' . $showImg;
                    } 
                    // Eski images klasörü
                    else if (file_exists(__DIR__ . '/../images/' . $showImg)) {
                        $finalSrc = 'images/' . $showImg;
                    }
                    // URL veya kök dizin kontrolü
                    else {
                        $finalSrc = (preg_match('~^https?://~',$showImg) || strpos($showImg,'/')===0) ? $showImg : '/'.ltrim($showImg,'/');
                    }
                }
            ?>
            
            <?php if(!empty($finalSrc)): ?>
                <a href="javascript:void(0);" onclick="openModal('<?= h($finalSrc) ?>'); return false;">
                    <img class="urun-gorsel-img" src="<?= h($finalSrc) ?>" style="max-width:64px; max-height:64px; object-fit:contain; border-radius:4px; border:1px solid #e2e8f0; background:#fff; display:block; margin:0 auto;">
                </a>
            <?php else: ?>
                <img class="urun-gorsel-img" style="max-width:64px; max-height:64px; display:none; margin:0 auto" alt="">
                <span style="font-size:20px; color:#cbd5e1; display:block; margin-top:5px;">📦</span>
            <?php endif; ?>
          </td>
          <td>
            <select name="product_id[]" onchange="onPickProduct(this)">
              <option value="">—</option>
              <?php foreach($products as $p): ?>
              <option
                value="<?= (int)$p['id'] ?>"
                data-sku="<?= h($p['sku'] ?? '') ?>" 
                data-name="<?= h($p['name']) ?>"
                data-unit="<?= h($p['unit']) ?>"
                data-price="<?= h($p['price']) ?>"
                data-ozet="<?= h($p['urun_ozeti']) ?>"
                data-kalan="<?= h($p['kullanim_alani']) ?>"
                data-image="<?= h($p['image'] ?? '') ?>"
                data-parent-id="<?= (int)($p['parent_id'] ?? 0) ?>"
                <?= (isset($it['product_id']) && (int)$it['product_id']===(int)$p['id'])?'selected':'' ?>
              ><?= h($p['display_name'] ?? $p['name']) ?><?= $p['sku'] ? ' ('.h($p['sku']).')':'' ?></option>
              <?php endforeach; ?>
            </select>
          </td>
          <td><input name="name[]" value="<?= h($it['name'] ?? '') ?>" required></td>
          <td><input name="unit[]" value="<?= h($it['unit'] ?? 'Adet') ?>"></td>
          <td><input name="qty[]" type="text" class="formatted-number" value="<?= number_format((float)($it['qty'] ?? 1), 2, ',', '.') ?>"></td>
          <?php if ($__is_admin_like): ?><td><input name="price[]" type="text" class="formatted-number" value="<?= number_format((float)($it['price'] ?? 0), 2, ',', '.') ?>"></td><?php endif; ?>
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

  <div class="note-input" style="display:flex; gap:8px; align-items:center; position:relative;">
    <input type="text" id="temp_note_input" 
           onkeydown="if(event.key==='Enter'){event.preventDefault(); document.getElementById('btn_add_note_ui').click();}" 
           placeholder="(+yeni not ekle)" 
           style="flex:1; padding:8px 10px; border:1px solid #e6e8ee; border-radius:8px; padding-right:70px;" />
    
    <div style="position:absolute; right:6px; display:flex; gap:4px;">
        <button type="button" id="btn_add_note_ui" class="btn-bonibon btn-bonibon-ok" title="Listeye Ekle">✔</button>
        <button type="button" id="btn_cancel_note_ui" class="btn-bonibon btn-bonibon-cancel" title="Temizle">⨉</button>
    </div>

    <textarea name="notes" id="notes-ghost" style="display:none;"><?= h($order['notes'] ?? '') ?></textarea>
</div>
</div>

<script>
(function(){
  var container = document.getElementById('notes-block');
  if(!container) return;
  
  // Elementleri seç
  var inp = document.getElementById('temp_note_input');
  var btnAdd = document.getElementById('btn_add_note_ui');
  var btnCancel = document.getElementById('btn_cancel_note_ui');
  var ghost = document.getElementById('notes-ghost');
  var listWrapper = container.querySelector('.notes-wrapper');

  function pad(n){ return (n<10?'0':'')+n; }

  // 1. Notu Listeye ve Gizli Alana Ekleme Fonksiyonu
  function addNoteToUI() {
      var val = (inp.value || '').trim();
      if(!val) return; // Boşsa işlem yapma

      // Tarih ve İsim Oluştur
      var d = new Date();
      var stamp = pad(d.getDate()) + '.' + pad(d.getMonth()+1) + '.' + d.getFullYear() + ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes());
      var username = container.getAttribute('data-user') || 'Kullanıcı';
      
      // Format: İsim | Tarih: Not
      var fullLine = username + ' | ' + stamp + ': ' + val;

      // A) GÖRSEL OLARAK LİSTEYE EKLE (HTML OLUŞTUR)
      var itemDiv = document.createElement('div');
      itemDiv.className = 'note-item';
      itemDiv.setAttribute('data-original', fullLine);
      itemDiv.style.cssText = 'margin-bottom:8px; display:flex; align-items:flex-start; gap:8px; animation: fadeIn 0.3s;';
      
      itemDiv.innerHTML = `
        <div style="flex:1 1 auto;">
          <div class="note-meta" style="font-size:12px; color:#6b7280; margin-bottom:2px;">
            <strong>${username}</strong> · <span class="note-time">${stamp}</span>
          </div>
          <div class="note-text" style="display:inline-block; padding:8px 10px; border:1px solid #e6e8ee; border-radius:12px; background:#eff6ff;">
            ${val.replace(/</g, "&lt;").replace(/>/g, "&gt;")}
          </div>
        </div>
        <button type="button" class="note-del" title="Sil" style="border:none; background:transparent; cursor:pointer; padding:4px 6px; font-size:12px; color:#9aa0ad;">🗑</button>
      `;

      // Varsa "Henüz not yok" yazısını kaldır
      if(listWrapper.innerText.trim() === 'Henüz not yok.') {
          listWrapper.innerHTML = '';
      }

      listWrapper.appendChild(itemDiv);
      listWrapper.scrollTop = listWrapper.scrollHeight; // En alta kaydır

      // B) GİZLİ TEXTAREA'YA EKLE (Veritabanı için)
      // Mevcut ghost içeriğini al, yeni satırı ekle
      var currentGhost = ghost.value.replace(/\s+$/,''); // Sondaki boşlukları temizle
      if(currentGhost) currentGhost += "\n";
      ghost.value = currentGhost + fullLine;

      // C) INPUTU TEMİZLE
      inp.value = '';
      inp.focus();
  }

  // Buton Olayları
  if(btnAdd) {
      btnAdd.addEventListener('click', function(e){
          e.preventDefault(); // Form submit olmasın
          addNoteToUI();
      });
  }

  if(btnCancel) {
      btnCancel.addEventListener('click', function(e){
          e.preventDefault();
          inp.value = ''; // Sadece temizle
          inp.focus();
      });
  }

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
      
      <?php if (($order['status'] ?? '') === 'taslak_gizli' && $mode === 'edit'): ?>
          <button type="submit" name="yayinla_butonu" value="1" class="btn" style="background-color:#cd94ff; color:#fff; font-weight:bold; margin-left:5px;">
             🚀 SİPARİŞİ YAYINLA
          </button>
      <?php endif; ?>

      <a class="btn" href="orders.php">Vazgeç</a>
    </div>
  </form>
</div>
<?php if ($mode === 'edit' && !empty($order['id'])): ?>
<div class="card mt" style="border-top:4px solid #3b82f6;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
        <h3 style="margin:0;">📁 Proje Dosyaları (Google Drive)</h3>
        <span style="font-size:12px; color:#666;">
            15 GB Alan • 
            <a href="https://drive.google.com/drive/folders/1fQeSige0mjICeLkjKVxspD7TlMY16C6U?authuser=renplancloud@gmail.com" target="_blank" style="text-decoration:none; color:#3b82f6;">
    📂 Klasörü Aç &rarr;
</a>
        </span>
    </div>

    <div class="file-list" style="margin-bottom:20px;">
        <?php
        // Dosyaları çekelim
        $f_stmt = $db->prepare("SELECT * FROM order_files WHERE order_id = ? ORDER BY id DESC");
        $f_stmt->execute([$order['id']]);
        $files = $f_stmt->fetchAll();

        if (count($files) > 0):
        ?>
            <table style="width:100%; border-collapse:collapse; font-size:13px;">
                <thead>
                    <tr style="background:#f3f4f6; text-align:left; color:#555;">
                        <th style="padding:8px; border-bottom:1px solid #e5e7eb;">Dosya Adı</th>
                        <th style="padding:8px; border-bottom:1px solid #e5e7eb;">Yükleyen</th>
                        <th style="padding:8px; border-bottom:1px solid #e5e7eb;">Tarih</th>
                        <th style="padding:8px; border-bottom:1px solid #e5e7eb; text-align:right;">İşlem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($files as $file): ?>
                    <tr>
                        <td style="padding:8px; border-bottom:1px solid #eee;">
                            <a href="<?= h($file['web_view_link']) ?>" target="_blank" style="text-decoration:none; color: #2563eb; font-weight:500; display:flex; align-items:center; gap:6px;">
                                📄 <?= h($file['file_name']) ?>
                                <small style="color: #999;">↗</small>
                            </a>
                        </td>
                        <td style="padding:8px; border-bottom:1px solid #eee; color:#444;">
                            <?= h($file['uploaded_by'] ?? '-') ?>
                        </td>
                        <td style="padding:8px; border-bottom:1px solid #eee; color:#666;">
                            <?= date('d.m.Y H:i', strtotime($file['created_at'])) ?>
                        </td>
                        <td style="padding:8px; border-bottom:1px solid #eee; text-align:right;">
                            <a href="delete_file.php?id=<?= $file['id'] ?>&order_id=<?= $order['id'] ?>" 
                               onclick="return confirm('Bu dosyayı Drive\'dan ve buradan silmek istediğinize emin misiniz?');"
                               style="color: #dc2626; text-decoration:none; font-size:12px; border:1px solid #fee2e2; background:#fef2f2; padding:4px 8px; border-radius:4px;">
                               Sil 🗑
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div style="padding:15px; background: #f9fafb; border:1px dashed #d1d5db; border-radius:6px; text-align:center; color:#6b7280;">
                Henüz bu siparişe ait dosya yüklenmemiş.
            </div>
        <?php endif; ?>
    </div>

    <div style="background: #f0f9ff; padding:15px; border-radius:8px; border:1px solid #bae6fd;">
        <form action="upload_drive.php" method="POST" enctype="multipart/form-data" style="display:flex; align-items:center; gap:10px;">
            <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
            
            <div style="flex:1;">
                <label style="display:block; font-size:12px; font-weight:bold; color: #0369a1; margin-bottom:4px;">Yeni Dosya Seç (PDF, DWG, Excel...)</label>
                <input type="file" name="file_upload" required style="width:100%; padding:8px; background: #fff; border:1px solid #cbd5e1; border-radius:4px;">
            </div>
            
            <button type="submit" class="btn" style="background-color: #0284c7; color:white; height:42px; margin-top:18px;">
                ☁️ Drive'a Yükle
            </button>
        </form>
        <div style="font-size:11px; color: #0c4a6e; margin-top:6px;">
            * Dosyalar güvenli bir şekilde Google Drive hesabınıza yüklenir.
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function addRow(){
  const tr = document.createElement('tr');
  tr.innerHTML = `
    <td class="drag-handle" style="cursor:move; vertical-align:middle; text-align:center; color:#9ca3af; font-size:18px; user-select:none; width:50px;">
        <div style="display:flex; align-items:center; justify-content:center; gap:2px;">
            <span class="row-index"></span> ⋮⋮
        </div>
    </td>
    <td><input name="stok_kodu[]" class="stok-kodu" placeholder="Stok Kodu"></td>
    <td class="urun-gorsel" style="text-align:center; vertical-align:middle;">
        <img class="urun-gorsel-img" style="max-width:64px; max-height:64px; display:none; margin:0 auto" alt="">
        <span class="no-img-icon" style="font-size:20px; color:#cbd5e1; display:block; margin-top:5px;">📦</span>
    </td>
    <td>
      <select name="product_id[]" onchange="onPickProduct(this)">
        <option value="">—</option>
        <?php foreach($products as $p): ?>
        <option
          value="<?= (int)$p['id'] ?>"
          data-sku="<?= h($p['sku'] ?? '') ?>"
          data-name="<?= h($p['name']) ?>"
          data-unit="<?= h($p['unit']) ?>"
          data-price="<?= h($p['price']) ?>"
          data-ozet="<?= h($p['urun_ozeti']) ?>"
          data-kalan="<?= h($p['kullanim_alani']) ?>"
          data-image="<?= h($p['image'] ?? '') ?>"
          data-parent-id="<?= (int)($p['parent_id'] ?? 0) ?>"
        ><?= h($p['display_name'] ?? $p['name']) ?><?= $p['sku'] ? ' ('.h($p['sku']).')':'' ?></option>
        <?php endforeach; ?>
      </select>
    </td>
    <td><input name="name[]" required></td>
    <td><input name="unit[]" value="Adet"></td>
    <td><input name="qty[]" type="text" class="formatted-number" value="1,00"></td>
    <?php if ($__is_admin_like): ?><td><input name="price[]" type="text" class="formatted-number" value="0,00"></td><?php endif; ?>
    <td><input name="urun_ozeti[]"></td>
    <td><input name="kullanim_alani[]"></td>
    <?php if ($__is_admin_like): ?><td class="right"><button type="button" class="btn" onclick="delRow(this)">Sil</button></td><?php endif; ?>
  `;
  document.querySelector('#itemsTable').appendChild(tr);
  bindSkuInputs(tr);
  renumberRows();
  
  // ÖNEMLİ: Yeni eklenen satır için custom dropdown oluştur
  initAccordionDropdowns();
}

function delRow(btn){
  const tr = btn.closest('tr');
  if(!tr) return;
  tr.parentNode.removeChild(tr);
  renumberRows(); // Silince numaraları kaydır
}

// YENİ: Satırları numaralandırma fonksiyonu
function renumberRows() {
    const rows = document.querySelectorAll('#itemsTable tr');
    let count = 0;
    rows.forEach((tr) => {
        // Başlık satırını (th) atla, içinde td olanları say
        if (tr.querySelector('td')) {
            count++;
            const span = tr.querySelector('.row-index');
            if (span) span.textContent = count;
        }
    });
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
          
          if(sel){ 
            sel.value = String(data.id); 
            
            // Custom dropdown'u da güncelle
            var wrapper = sel.closest('.custom-select-wrapper');
            if (wrapper) {
              var trigger = wrapper.querySelector('.custom-select-trigger');
              if (trigger && sel.selectedIndex >= 0) {
                var opt = sel.options[sel.selectedIndex];
                if (opt) {
                  trigger.textContent = opt.textContent.replace(/[⊿•▼]/g, '').trim();
                }
              }
            }
            
            // ÖNEMLİ: Görseli hemen yükle (onPickProduct'ı manuel çağır)
            onPickProduct(sel);
          }
          
          // Fill fields in case user didn't choose from select
          var name = tr.querySelector('input[name="name[]"]');
          var unit = tr.querySelector('input[name="unit[]"]');
          var price = tr.querySelector('input[name="price[]"]');
          var oz = tr.querySelector('input[name="urun_ozeti[]"]');
          var ka = tr.querySelector('input[name="kullanim_alani[]"]');
          if(name && data.name) name.value = data.name;
          if(unit && data.unit) unit.value = data.unit;
          // Fiyatı TR formatına (virgüllü) çevirip yazıyoruz:
          if(price && (data.price!==undefined)) {
             var pVal = parseFloat(data.price);
             price.value = pVal.toLocaleString('tr-TR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
          }
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
  console.log('onPickProduct çağrıldı, sel:', sel);
  const opt = sel.options[sel.selectedIndex];
  if(!opt) {
    console.log('Option bulunamadı!');
    return;
  }
  console.log('Seçilen option:', opt);
  const tr = sel.closest('tr');
  
  // YENİ: Stok Kodu (SKU) alanını doldur
  var skuInput = tr.querySelector('input[name="stok_kodu[]"]');
  if(skuInput) {
      skuInput.value = opt.getAttribute('data-sku') || '';
  }

  tr.querySelector('input[name="name[]"]').value = opt.getAttribute('data-name') || '';
  tr.querySelector('input[name="unit[]"]').value = opt.getAttribute('data-unit') || 'Adet';
  
  <?php if ($__is_admin_like): ?>
    // Data attribute'dan gelen noktalı fiyatı al, virgüllüye çevir
    var rawPrice = opt.getAttribute('data-price') || '0';
    var floatPrice = parseFloat(rawPrice);
    var trPrice = floatPrice.toLocaleString('tr-TR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    tr.querySelector('input[name="price[]"]').value = trPrice;
  <?php endif; ?>
  tr.querySelector('input[name="urun_ozeti[]"]').value = opt.getAttribute('data-ozet') || '';
  tr.querySelector('input[name="kullanim_alani[]"]').value = opt.getAttribute('data-kalan') || '';
  
  // GÖRSEL MANTĞI (order_pdf.php'deki gibi parent kontrolü ile)
  var raw = opt.getAttribute('data-image') || '';
  
  console.log('Seçilen ürün ID:', opt.value);
  console.log('data-image:', raw);
  console.log('data-parent-id:', opt.getAttribute('data-parent-id'));
  
  // Eğer görsel boşsa ve parent_id varsa, parent'ın görselini kullan
  if (!raw) {
    var parentId = opt.getAttribute('data-parent-id') || '0';
    console.log('Görsel yok, parent_id kontrol ediliyor:', parentId);
    if (parentId && parentId !== '0') {
      var parentOpt = sel.querySelector('option[value="' + parentId + '"]');
      if (parentOpt) {
        raw = parentOpt.getAttribute('data-image') || '';
        console.log('Parent\'tan alınan görsel:', raw);
      }
    }
  }
  
  // --- GÖRSEL YOLU HESAPLAMA (DÜZELTİLMİŞ) ---
  var finalImgSrc = '';
  if (raw) {
    // 1. Tam URL veya Kök Dizin (/) ile başlıyorsa
    if (raw.match(/^https?:\/\//) || raw.indexOf('/') === 0) {
      finalImgSrc = raw;
    }
    // 2. "uploads/" ile başlıyorsa (başında slash yoksa)
    else if (raw.indexOf('uploads/') === 0) {
      finalImgSrc = '/' + raw;
    }
    // 3. Sadece dosya adıysa (varsayılan klasöre bak)
    else {
      finalImgSrc = '/uploads/product_images/' + raw;
    }

    // --- KRİTİK DÜZELTME: Çift '/uploads/uploads/' Kontrolü ---
    // Yol nasıl oluşursa oluşsun, sonucunda çift klasör varsa düzeltilir.
    if (finalImgSrc.indexOf('/uploads/uploads/') > -1) {
        finalImgSrc = finalImgSrc.replace('/uploads/uploads/', '/uploads/');
    }
  }
  // -----------------------------------------------------------
  
  console.log('Final görsel yolu:', finalImgSrc);
  
  var imgEl = tr.querySelector('.urun-gorsel-img');
  var noImgIcon = tr.querySelector('.no-img-icon');
  
  if (imgEl) {
    if (finalImgSrc) {
      imgEl.src = finalImgSrc;
      imgEl.alt = 'Ürün görseli';
      imgEl.style.display = 'block';
      if (noImgIcon) noImgIcon.style.display = 'none';
    } else {
      imgEl.style.display = 'none';
      if (noImgIcon) noImgIcon.style.display = 'block';
    }
  }
}

document.addEventListener('DOMContentLoaded', function(){
  var f = document.querySelector('form');
  if(!f) return;
// --- YENİ EKLENEN GÜVENLİK KODU: Ürün Seçilmeden Fiyat Girilmesini Engelle ---
  var mainForm = document.querySelector('form');
  if(mainForm) {
      mainForm.addEventListener('submit', function(e){
          // Tablodaki tüm satırları gez
          var rows = document.querySelectorAll('#itemsTable tr');
          
          for(var i=0; i<rows.length; i++){
              var row = rows[i];
              // Bu satırdaki Ürün Seçimi (Select) ve Fiyat (Input) alanlarını bul
              var sel = row.querySelector('select[name="product_id[]"]');
              var priceInp = row.querySelector('input[name="price[]"]');
              
              // Eğer bu satırda ürün seçimi yoksa (başlık satırıysa) geç
              if(!sel) continue; 

              // Fiyat değerini parse et (1.000,50 -> 1000.50)
              var priceVal = 0;
              if(priceInp && priceInp.value) {
                  var cleanVal = priceInp.value.toString().replace(/\./g, '').replace(',', '.');
                  priceVal = parseFloat(cleanVal);
              }

              // KURAL: Eğer Fiyat 0'dan büyükse VE (Ürün seçilmemişse veya değeri boşsa)
              if(priceVal > 0 && (!sel.value || sel.value === '0' || sel.value === '')) {
                  e.preventDefault(); // Kaydetmeyi durdur
                  e.stopPropagation(); // Diğer işlemleri durdur
                  
                  alert('DİKKAT: Tabloda fiyat girdiğiniz bir satırda henüz ÜRÜN SEÇMEDİNİZ!\n\nLütfen önce listeden ürün seçin, sonra kaydedin.');
                  
                  // Hatayı göstermek için o satıra git ve kırmızı yap
                  sel.scrollIntoView({behavior: 'smooth', block: 'center'});
                  sel.style.border = '2px solid red';
                  sel.focus();
                  if(priceInp) priceInp.style.backgroundColor = '#fee2e2';

                  // İlk hatada döngüden çık
                  return;
              }
          }
      }, true); // true: Event capture, daha öncelikli çalışmasını sağlar
  }
  // --- GÜVENLİK KODU SONU --- 
  // Sortable.js - Drag & Drop
  var tbody = document.querySelector('#itemsTable tbody');
  if (!tbody) tbody = document.querySelector('#itemsTable');
  if (tbody && typeof Sortable !== 'undefined') {
    new Sortable(tbody, {
      handle: '.drag-handle',
      animation: 150,
      ghostClass: 'sortable-ghost',
      dragClass: 'sortable-drag',
      // YENİ: Sürükleme bitince numaraları güncelle
      onEnd: function() {
          renumberRows();
      }
    });
  }
  
  // stok kodu inputlarına dinleyici bağla
  try { bindSkuInputs(); } catch(_e){}
  // mevcut seçili ürünler için SADECE görselleri getir (Metinleri ve Fiyatı EZME!)
  document.querySelectorAll('select[name="product_id[]"]').forEach(function(s){
    if (s && s.value) {
       try {
         var opt = s.options[s.selectedIndex];
         if(opt){
           // Sadece RESİM mantığını buraya aldık.
           // Fiyat veya Özet alanlarına dokunmuyoruz.
           var raw = opt.getAttribute('data-image') || '';
           
           // Eğer görsel boşsa ve parent_id varsa, parent'ın görselini kullan
           if (!raw) {
             var parentId = opt.getAttribute('data-parent-id') || '0';
             if (parentId && parentId !== '0') {
               var parentOpt = s.querySelector('option[value="' + parentId + '"]');
               if (parentOpt) {
                 raw = parentOpt.getAttribute('data-image') || '';
               }
             }
           }
           
           var finalImgSrc = '';
           if (raw) {
             if (raw.startsWith('http://') || raw.startsWith('https://')) {
               finalImgSrc = raw;
             } else if (raw.startsWith('/uploads/uploads/')) {
               // Çift uploads hatası düzelt
               finalImgSrc = raw.replace('/uploads/uploads/', '/uploads/');
             } else if (raw.startsWith('/')) {
               finalImgSrc = raw;
             } else if (raw.startsWith('uploads/')) {
               finalImgSrc = '/' + raw;
             } else {
               finalImgSrc = '/uploads/product_images/' + raw;
             }
           }
           
           var tr = s.closest('tr');
           var imgEl = tr.querySelector('.urun-gorsel-img');
           var noImgIcon = tr.querySelector('.no-img-icon');
           
           if (imgEl) {
             if (finalImgSrc) {
               imgEl.src = finalImgSrc;
               imgEl.alt = 'Ürün görseli';
               imgEl.style.display = 'block';
               if (noImgIcon) noImgIcon.style.display = 'none';
             } else {
               imgEl.style.display = 'none';
               if (noImgIcon) noImgIcon.style.display = 'block';
             }
           }
         }
       } catch(_e){}
    }
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

// Görsel modal fonksiyonu
function openModal(imageSrc) {
  // Modal zaten varsa kaldır
  var existingModal = document.getElementById('image-modal');
  if (existingModal) existingModal.remove();
  
  // Yeni modal oluştur
  var modal = document.createElement('div');
  modal.id = 'image-modal';
  modal.style.cssText = 'position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8); z-index:999999; display:flex; align-items:center; justify-content:center; cursor:pointer;';
  
  var img = document.createElement('img');
  img.src = imageSrc;
  img.style.cssText = 'max-width:90%; max-height:90%; object-fit:contain; border-radius:8px; box-shadow:0 10px 40px rgba(0,0,0,0.5);';
  
  modal.appendChild(img);
  document.body.appendChild(modal);
  
  // Modal'a tıklayınca kapat
  modal.addEventListener('click', function() {
    modal.remove();
  });
  
  // ESC tuşuyla kapat
  document.addEventListener('keydown', function escHandler(e) {
    if (e.key === 'Escape') {
      modal.remove();
      document.removeEventListener('keydown', escHandler);
    }
  });
}
</script>
<!-- Sortable.js for drag-drop -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>

<style>
/* --- YENİ EKLENEN STİLLER (Satır No & Highlight) --- */
.row-index {
    display: inline-block;
    width: 20px;
    color: #cbd5e1; /* Silik gri */
    font-size: 11px;
    font-weight: bold;
    text-align: right;
    margin-right: 6px;
    user-select: none;
}
/* Düzenlenen satırın rengi (Turuncu çerçeve ve zemin) */
tr.active-editing td {
    background-color: #fff7ed !important;
    border-top: 1px solid #fdba74 !important;
    border-bottom: 1px solid #fdba74 !important;
}

/* === POP-UP EDİTÖR STİLLERİ (GÜNCELLENMİŞ) === */
.popover-overlay {
  position: fixed; inset: 0; background: transparent; z-index: 9990; display: none;
}

.popover-editor {
  position: fixed;
  z-index: 9991;
  background: #fff;
  border-radius: 8px;
  box-shadow: 0 10px 40px rgba(0,0,0,0.4);
  display: none;
  flex-direction: column;
  
  /* Varsayılan Boyutlar (Küçültüldü) */
  width: 400px;
  height: 250px;
  
  /* Kenardan tutup büyütme (Resize) */
  resize: both;
  overflow: hidden; /* Resize tutamacının görünmesi için şart */
  min-width: 320px;
  min-height: 250px;
  max-width: 98vw;
  max-height: 98vh;
  border: 1px solid #d1d5db;
}

/* Başlık (Sürükleme Alanı) */
.popover-header {
  flex: 0 0 auto;
  background: #f9fafb;
  padding: 10px 15px;
  border-bottom: 1px solid #e5e7eb;
  cursor: grab; /* Tutma imleci */
  display: flex;
  justify-content: space-between;
  align-items: center;
  user-select: none;
}
.popover-header:active { cursor: grabbing; }

.field-label {
  font-weight: 700; color: #1f2937; font-size: 14px; margin: 0;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 300px;
}

/* Font Buton Grubu */
.popover-toolbar {
  display: flex; gap: 4px;
}
.popover-toolbar button {
  cursor: pointer;
}

/* İçerik Alanı */
.popover-body {
  flex: 1 1 auto;
  padding: 0;
  display: flex;
  flex-direction: column;
  background: #fff;
  position: relative;
}

.popover-editor textarea {
  flex: 1;
  width: 100% !important;
  height: 100% !important;
  resize: none; /* Dış kutu büyüdüğü için textarea sabit kalsın */
  border: none;
  padding: 15px;
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  line-height: 1.5;
  box-sizing: border-box;
  font-size: 14px; /* Varsayılan */
  outline: none;
  color: #111;
}

/* Alt Butonlar */
.popover-actions {
  flex: 0 0 auto;
  padding: 10px 15px;
  background: #fff;
  border-top: 1px solid #e5e7eb;
  display: flex;
  justify-content: flex-end;
  gap: 8px;
}
/* Bonibon Butonlar (Notlar için) */
.btn-bonibon {
    width: 20px;
    height: 20px;
    border-radius: 50%; /* Tam yuvarlak */
    border: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    transition: all 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}
.btn-bonibon:hover { transform: scale(1.15); box-shadow: 0 4px 8px rgba(0,0,0,0.15); }
.btn-bonibon:active { transform: scale(0.95); }

/* Onay (Yeşil) */
.btn-bonibon-ok {
    background-color: #d1fae5; /* Açık nane yeşili */
    color: #059669;            /* Koyu yeşil */
    border: 1px solid #10b981;
}
/* İptal (Kırmızı) */
.btn-bonibon-cancel {
    background-color: #fee2e2; /* Açık kırmızı */
    color: #dc2626;            /* Koyu kırmızı */
    border: 1px solid #ef4444;
}
</style>

<div id="__popover_overlay" class="popover-overlay"></div>
<div id="__popover" class="popover-editor" role="dialog" aria-modal="true">
  
  <div class="popover-header" id="__popover_header">
    <label class="field-label" id="__popover_label">Ürün Özeti</label>
    
    <div class="popover-toolbar">
      <button type="button" class="btn btn-sm" id="__popover_dec" style="padding:4px 10px; font-weight:bold; background:#fff; border:1px solid #d1d5db; min-width:30px;" title="Küçült">A-</button>
      <button type="button" class="btn btn-sm" id="__popover_inc" style="padding:4px 10px; font-weight:bold; background:#fff; border:1px solid #d1d5db; min-width:30px;" title="Büyüt">A+</button>
    </div>
  </div>

  <div class="popover-body">
    <textarea id="__popover_text" spellcheck="false"></textarea>
  </div>
  
  <div class="popover-actions">
    <button type="button" class="btn" id="__popover_cancel">Vazgeç (Esc)</button>
    <button type="button" class="btn primary" id="__popover_save">Kaydet (Ctrl+Enter)</button>
  </div>
</div>

<script>
(function(){
  // Elementleri Seç
  var overlay = document.getElementById('__popover_overlay');
  var pop = document.getElementById('__popover');
  var header = document.getElementById('__popover_header');
  var tarea = document.getElementById('__popover_text');
  var label = document.getElementById('__popover_label');
  var cancelBtn = document.getElementById('__popover_cancel');
  var saveBtn = document.getElementById('__popover_save');
  var btnInc = document.getElementById('__popover_inc');
  var btnDec = document.getElementById('__popover_dec');
  
  var currentInput = null;
  var currentFontSize = 14;

  // --- 1. FONT DEĞİŞTİRME FONKSİYONU ---
  function updateFont(){
    // !important kullanarak diğer stilleri ezmesini sağlıyoruz
    tarea.style.setProperty('font-size', currentFontSize + 'px', 'important');
  }
  
  // Buton Olayları (addEventListener ile daha güvenli)
  btnInc.addEventListener('click', function(e){
    e.stopPropagation(); // Sürüklemeyi tetikleme
    if(currentFontSize < 48) {
        currentFontSize += 2;
        updateFont();
    }
  });
  
  btnDec.addEventListener('click', function(e){
    e.stopPropagation();
    if(currentFontSize > 10) {
        currentFontSize -= 2;
        updateFont();
    }
  });

  // --- 2. SÜRÜKLEME MANTIĞI (DRAG) ---
  var isDragging = false;
  var dragOffsetX = 0;
  var dragOffsetY = 0;

  header.onmousedown = function(e) {
    // Eğer tıklanan yer bir butonsa sürükleme yapma
    if(e.target.closest('button')) return;
    
    isDragging = true;
    dragOffsetX = e.clientX - pop.offsetLeft;
    dragOffsetY = e.clientY - pop.offsetTop;
    header.style.cursor = 'grabbing';
  };

  document.onmousemove = function(e) {
    if (isDragging) {
      var newX = e.clientX - dragOffsetX;
      var newY = e.clientY - dragOffsetY;
      pop.style.left = newX + 'px';
      pop.style.top = newY + 'px';
    }
  };

  document.onmouseup = function() {
    isDragging = false;
    header.style.cursor = 'grab';
  };

  // --- 3. AÇILMA VE KONUMLANDIRMA ---
  function openEditor(input){
    currentInput = input;
    
    var row = input.closest('tr');
    var prodName = '';
    var rowNum = '?';

    if(row){
        // 1. Aktif satırı boya (öncekileri temizle)
        document.querySelectorAll('tr.active-editing').forEach(r => r.classList.remove('active-editing'));
        row.classList.add('active-editing');

        // 2. Ürün ismini al
        var nameInp = row.querySelector('input[name="name[]"]');
        if(nameInp) prodName = nameInp.value;

        // 3. Satır numarasını al
        var idxSpan = row.querySelector('.row-index');
        if(idxSpan) rowNum = idxSpan.textContent;
    }

   // Başlığı ayarla: "Ürün Özeti 5- Vida"
    var field = input.name.indexOf('urun_ozeti') > -1 ? 'Ürün Özeti' : 'Kullanım Alanı';
    var title = field + ' ' + rowNum;
    if(prodName) title += '- ' + prodName;
    
    label.textContent = title;

    // Değeri yükle
    tarea.value = input.value || '';
    updateFont(); // Mevcut font ayarını uygula

    // Görünür yap
    overlay.style.display = 'block';
    pop.style.display = 'flex';

    // Konumlandırma (Input'un hemen altına)
    var rect = input.getBoundingClientRect();
    var topPos = rect.bottom + 8;
    var leftPos = rect.left;

    // Ekran dışına taşarsa düzelt (Yeni boyutlara göre: 400x250)
    if(leftPos + 400 > window.innerWidth) leftPos = window.innerWidth - 420;
    if(leftPos < 10) leftPos = 10;
    
    if(topPos + 250 > window.innerHeight) {
        // Alta sığmıyorsa üste aç
        topPos = rect.top - 260; 
    }

    pop.style.top = topPos + 'px';
    pop.style.left = leftPos + 'px';
    
    // Boyutları sıfırla
    pop.style.width = '400px';
    pop.style.height = '250px';

    setTimeout(function(){ tarea.focus(); }, 50);
  }

  function closeEditor(){
    overlay.style.display = 'none';
    pop.style.display = 'none';
    currentInput = null;
    // Aktif satır rengini kaldır
    document.querySelectorAll('tr.active-editing').forEach(r => r.classList.remove('active-editing'));
  }

  function saveEditor(){
    if(currentInput) {
      currentInput.value = tarea.value.trim();
      // Tetikleyiciler
      try{ currentInput.dispatchEvent(new Event('input', {bubbles:true})); }catch(e){}
      try{ currentInput.dispatchEvent(new Event('change', {bubbles:true})); }catch(e){}
    }
    closeEditor();
  }

  // --- 4. GLOBAL DİNLEYİCİLER ---
  document.addEventListener('click', function(e){
    // Inputlara tıklanınca aç
    if(e.target && (e.target.matches('input[name="urun_ozeti[]"]') || e.target.matches('input[name="kullanim_alani[]"]'))){
      e.preventDefault(); // Inputa odaklanmayı engelle, pop-up aç
      openEditor(e.target);
    }
  });

  overlay.addEventListener('click', closeEditor);
  cancelBtn.addEventListener('click', closeEditor);
  saveBtn.addEventListener('click', saveEditor);

  // Klavye kısayolları
  pop.addEventListener('keydown', function(e){
    if(e.key === 'Escape') closeEditor();
    if((e.ctrlKey || e.metaKey) && e.key === 'Enter') saveEditor();
  });

})();
</script>
<style>
  /* Sayfa yüklenirken select'leri gizle */
    select[name="product_id[]"] {
        display: none !important;
    }
    
    /* CUSTOM SELECT-DROPDOWN STİLLERİ */
    .custom-select-wrapper {
        position: relative;
        display: block;
        width: 100%;
    }
    
    .custom-select-trigger {
        position: relative; 
        padding: 8px 12px; 
        background: #fff; 
        border: 1px solid #cbd5e1;
        border-radius: 6px; 
        cursor: pointer; 
        min-height: 38px; 
        display: flex; 
        align-items: center; 
        justify-content: space-between;
        color: #334155;
        transition: all 0.2s;
        user-select: none;
    }
    .custom-select-trigger:hover { border-color: #64748b; background: #f8fafc; }
    
    /* AÇILIR LİSTE (BODY'YE TAŞINACAK) */
    .custom-options {
        display: none; /* Varsayılan gizli */
        position: absolute; /* Sayfaya yapışık */
        background: #fff;
        border: 1px solid #94a3b8; 
        border-radius: 8px; 
        box-shadow: 0 10px 40px rgba(0,0,0,0.3); /* Derin gölge */
        z-index: 99999999; /* En üst katman */
        max-height: 400px; 
        overflow-y: auto; 
        min-width: 500px; /* Genişlik garantisi */
    }

    .custom-options.open { display: block; }

    /* SATIRLAR */
    .custom-option {
        padding: 12px 15px; /* Daha rahat tıklama alanı */
        border-bottom: 1px solid #e2e8f0; 
        cursor: pointer;
        display: flex; 
        align-items: center; 
        transition: background 0.1s;
        color: #475569;
        font-size: 13px;
    }
    
    /* NET HOVER EFEKTİ (İstediğin gibi belirgin) */
    .custom-option:hover { 
        background: #0ea5e9; /* Canlı mavi */
        color: #fff;         /* Beyaz yazı */
    }
    
    /* 🔴 ANA ÜRÜN STİLİ */
    .option-parent { 
        font-weight: 700; 
        color: #1e293b; 
        background: #f1f5f9;
    }
    /* Ana ürün hover olunca */
    .option-parent:hover { background: #0284c7; color:#fff; }
    
    /* SOLDAKİ OK BUTONU */
    .toggle-btn {
        display: inline-flex;
        align-items: center; justify-content: center;
        width: 26px; height: 26px;
        margin-right: 12px;
        border-radius: 4px; 
        background: #fff; 
        border: 1px solid #cbd5e1;
        color: #64748b;
        font-size: 10px; 
        font-weight: bold;
        transition: 0.2s;
    }
    .toggle-btn:hover { background: #e2e8f0; color: #000; transform: scale(1.1); }
    /* Parent hover olunca buton rengini koru veya uydur */
    .option-parent:hover .toggle-btn { color: #000; }

    .toggle-btn.expanded { background: #f59e0b; color: #fff; border-color:#d97706; transform: rotate(180deg); } 

    /* 🟡 ÇOCUK ÜRÜN (VARYASYON) */
    .option-child { 
        display: none; 
        background: #fffbeb; 
        padding-left: 50px; 
        color: #b45309; 
        border-left: 5px solid #fcd34d;
    }
    .option-child.visible { display: flex; } 
    /* Çocuk hover */
    .option-child:hover { background: #0ea5e9; color:#fff; border-left-color: #0284c7; }

</style>

<script>
// Sayfa yüklenirken select'leri hemen gizle
document.querySelectorAll('select[name="product_id[]"]').forEach(s => s.style.display = 'none');

document.addEventListener('DOMContentLoaded', function() {
    initAccordionDropdowns();
    
    // Dinamik satır ekleme takibi
    const observer = new MutationObserver(function(mutations) {
        if (mutations.some(m => m.addedNodes.length)) initAccordionDropdowns();
    });
    const tbody = document.querySelector('table tbody'); 
    if(tbody) observer.observe(tbody, { childList: true });

    // Pencere boyutu değişirse her şeyi kapat (kaymayı önlemek için)
    window.addEventListener('resize', closeAllDropdowns);
    // Dışarı tıklayınca kapat
    document.addEventListener('click', function(e) {
        if(!e.target.closest('.custom-options') && !e.target.closest('.custom-select-trigger')) {
            closeAllDropdowns();
        }
    });
});

function closeAllDropdowns() {
    document.querySelectorAll('.custom-options.open').forEach(el => {
        el.classList.remove('open');
        // Listeyi ait olduğu satıra (wrapper'a) geri gönder! (Temizlik)
        if (el._originalWrapper) {
            el._originalWrapper.appendChild(el);
        }
    });
}

function initAccordionDropdowns() {
    const selects = document.querySelectorAll('select[name="product_id[]"]:not(.enhanced)');
    
    selects.forEach(select => {
        select.classList.add('enhanced'); 
        select.style.display = 'none';    
        
        const wrapper = document.createElement('div');
        wrapper.className = 'custom-select-wrapper';
        
        const trigger = document.createElement('div');
        trigger.className = 'custom-select-trigger';
        
        let rawText = select.options[select.selectedIndex].textContent || 'Ürün Seçiniz...';
        trigger.textContent = rawText.replace(/[⊿•▼]/g, '').trim();
        
        const optionsList = document.createElement('div');
        optionsList.className = 'custom-options';
        optionsList._originalWrapper = wrapper; // Sahibini unutma
        
        Array.from(select.options).forEach(opt => {
            // Boş option'ı atla
            if (!opt.value) return;
            
            const div = document.createElement('div');
            div.className = 'custom-option';
            div.dataset.value = opt.value;
            let text = opt.textContent;

            // --- ANA ÜRÜN ---
            if (text.includes('⊿')) {
                div.classList.add('option-parent');
                let cleanName = text.replace('⊿', '').replace('▼', '').trim();
                
                if (text.includes('▼')) {
                    const btn = document.createElement('span');
                    btn.className = 'toggle-btn';
                    btn.innerText = '▼';
                    
                    btn.onclick = (e) => {
                        e.stopPropagation();
                        btn.classList.toggle('expanded');
                        
                        // Kardeş kontrolü
                        let sibling = div.nextElementSibling;
                        while(sibling && sibling.classList.contains('option-child')) {
                            sibling.classList.toggle('visible');
                            sibling = sibling.nextElementSibling;
                        }
                    };
                    div.appendChild(btn);
                    
                    const nameSpan = document.createElement('span');
                    nameSpan.innerText = cleanName;
                    div.appendChild(nameSpan);
                } else {
                    div.innerHTML = `<span style="margin-left:38px">${cleanName}</span>`; 
                }
            } 
            // --- ÇOCUK ÜRÜN ---
            else if (text.includes('•')) {
                div.classList.add('option-child'); 
                div.innerText = text.replace('•', '').trim();
            } 
            // --- NORMAL ---
            else {
                div.innerText = text;
            }
            
            // Seçim
            div.addEventListener('click', function(e) {
                // Toggle butona tıklandıysa seçim yapma
                if(e.target.classList.contains('toggle-btn')) return;
                
                select.value = this.dataset.value;
                // Trigger'daki metni güncelle (temiz, sadece ürün adı)
                let displayText = this.textContent.replace(/[⊿•▼]/g, '').trim();
                trigger.textContent = displayText;
                
                closeAllDropdowns();
                
                // Change event'ini tetikle (onPickProduct çalışsın)
                select.dispatchEvent(new Event('change', {bubbles: true}));
            });
            
            optionsList.appendChild(div);
        });
        
        select.parentNode.insertBefore(wrapper, select);
        wrapper.appendChild(select);
        wrapper.appendChild(trigger);
        wrapper.appendChild(optionsList); // Şimdilik burada dursun
        
        // --- AÇMA TETİKLEYİCİSİ (TELEPORT MANTIĞI) ---
        trigger.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            // Zaten açıksa kapat
            if (optionsList.classList.contains('open')) {
                closeAllDropdowns();
                return;
            }

            closeAllDropdowns(); // Diğerlerini kapat
            
            // 1. LİSTEYİ BODY'YE TAŞI (Tablodan Kurtar)
            document.body.appendChild(optionsList);
            
            // 2. KONUMU HESAPLA (Sayfa bazlı absolute)
            const rect = trigger.getBoundingClientRect();
            const scrollX = window.scrollX || window.pageXOffset;
            const scrollY = window.scrollY || window.pageYOffset;

            // Genişlik: En az 500px, ama tetikleyici daha genişse ona uy
            optionsList.style.width = Math.max(rect.width, 500) + 'px';
            optionsList.style.left = (rect.left + scrollX) + 'px';
            
            // Yer kontrolü (Aşağıda yer var mı?)
            const spaceBelow = window.innerHeight - rect.bottom;
            const listHeight = 400; // Max yükseklik
            
            if (spaceBelow < listHeight && rect.top > listHeight) {
                // Yukarı Aç (Tetikleyicinin üstüne)
                optionsList.style.top = (rect.top + scrollY - listHeight - 2) + 'px';
                optionsList.style.bottom = 'auto';
            } else {
                // Aşağı Aç
                optionsList.style.top = (rect.bottom + scrollY + 2) + 'px';
                optionsList.style.bottom = 'auto';
            }

            optionsList.classList.add('open');
        });
    });
}
</script>