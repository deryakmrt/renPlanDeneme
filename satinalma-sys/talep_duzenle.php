<?php

declare(strict_types=1);

// AJAX isteklerini talep_ajax.php'ye yönlendir
if (!empty($_GET['ajax']) || !empty($_POST['ajax'])) {
  require_once __DIR__ . '/satinalma-sys/talep_ajax.php';
  exit;
}

// NORMAL SAYFA İÇİN DEVAM ET
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
$helpers = dirname(__DIR__) . '/includes/helpers.php';
if (is_file($helpers)) require_once $helpers;

// ID KONTROLÜ
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
  http_response_code(400);
  die("Geçersiz ID parametresi.");
}

// PDO bağlantısı =======VERITABANI AYARLARI===========
$pdo = (isset($pdo) && $pdo instanceof PDO) ? $pdo
  : ((isset($DB) && $DB instanceof PDO) ? $DB : ((isset($db) && $db instanceof PDO) ? $db : null));

if (!$pdo && defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER')) {
  $pass = defined('DB_PASS') ? DB_PASS : '';
  $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
  try {
    $pdo = new PDO($dsn, DB_USER, $pass, [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
  } catch (Throwable $e) {
    die('DB bağlantı hatası');
  }
}

if (!$pdo) {
  die("PDO bulunamadı");
}

// Tabloları oluştur
try {
  createRequiredTables($pdo);
} catch (Exception $e) {
  error_log('Table creation error: ' . $e->getMessage());
}

function createRequiredTables($pdo)
{
  $pdo->exec("CREATE TABLE IF NOT EXISTS suppliers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            contact_person VARCHAR(255),
            phone VARCHAR(50),
            email VARCHAR(255),
            address TEXT,
            durum TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  $pdo->exec("CREATE TABLE IF NOT EXISTS product_suppliers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            product_name VARCHAR(255) NOT NULL,
            supplier_id INT NOT NULL,
            is_preferred TINYINT(1) DEFAULT 0,
            last_price DECIMAL(10,2) DEFAULT NULL,
            last_quote_date DATE DEFAULT NULL,
            total_orders INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_product_supplier (product_name(100), supplier_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  $pdo->exec("CREATE TABLE IF NOT EXISTS satinalma_quotes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_item_id INT NOT NULL,
            supplier_id INT NOT NULL,
            price DECIMAL(10,2) NOT NULL,
            currency VARCHAR(10) DEFAULT 'TRY',
            quote_date DATE,
            note TEXT,
            selected TINYINT(1) DEFAULT 0,
            delivery_days INT DEFAULT NULL,
            payment_term VARCHAR(100) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

$TABLE = 'satinalma_orders';

// Helpers
if (!function_exists('h')) {
  function h($v)
  {
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
  }
}
if (!function_exists('f')) {
  function f($k, $d = null)
  {
    return isset($_POST[$k]) ? trim((string)$_POST[$k]) : $d;
  }
}

// Kayıt getir
$s = $pdo->prepare("SELECT * FROM `$TABLE` WHERE id = :id LIMIT 1");
$s->execute([':id' => $id]);
$row = $s->fetch();
if (!$row) {
  http_response_code(404);
  die("Kayıt bulunamadı.");
}

// Mevcut kalemleri yükle
$existing_items = [];
try {
  $qq = $pdo->prepare("
            SELECT 
                soi.*,
                COUNT(DISTINCT sq.id) as quote_count,
                MIN(sq.price) as best_price,
                MIN(sq.currency) as best_price_currency,
                s.name as selected_supplier,
                sq_sel.price as selected_price,
                sq_sel.id as selected_quote_id,
                sq_sel.currency as selected_currency,
                sq_sel.payment_term as selected_payment_term,
                sq_sel.delivery_days as selected_delivery_days,
                sq_sel.supplier_id as selected_supplier_id,
                sq_sel.note as selected_note,
                sq_sel.quote_date as selected_quote_date,
                GROUP_CONCAT(DISTINCT s2.name SEPARATOR ', ') as quoted_suppliers
            FROM satinalma_order_items soi
            LEFT JOIN satinalma_quotes sq ON soi.id = sq.order_item_id
            LEFT JOIN satinalma_quotes sq_sel ON soi.id = sq_sel.order_item_id AND sq_sel.selected = 1
            LEFT JOIN suppliers s ON sq_sel.supplier_id = s.id
            LEFT JOIN satinalma_quotes sq2 ON soi.id = sq2.order_item_id
            LEFT JOIN suppliers s2 ON sq2.supplier_id = s2.id
            WHERE soi.talep_id = ? 
            GROUP BY soi.id
            ORDER BY soi.id ASC
        ");
  $qq->execute([$id]);
  $existing_items = $qq->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
  error_log('existing_items error: ' . $e->getMessage());
}

// POST: güncelle
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $urunler = isset($_POST['urun']) ? (array)$_POST['urun'] : [];
  $miktarlar = isset($_POST['miktar']) ? (array)$_POST['miktar'] : [];
  $birimler = isset($_POST['birim']) ? (array)$_POST['birim'] : [];
  $birim_fiyatlar = isset($_POST['birim_fiyat']) ? (array)$_POST['birim_fiyat'] : [];
  $durumlar = isset($_POST['item_durum']) ? (array)$_POST['item_durum'] : [];

  $kalemler = [];
  $N = max(count($urunler), count($miktarlar), count($birimler), count($birim_fiyatlar));

  for ($i = 0; $i < $N; $i++) {
    $u = isset($urunler[$i]) ? trim((string)$urunler[$i]) : '';
    $m = isset($miktarlar[$i]) && $miktarlar[$i] !== '' ? (float)$miktarlar[$i] : null;
    $b = isset($birimler[$i]) ? trim((string)$birimler[$i]) : '';
    $f = isset($birim_fiyatlar[$i]) && $birim_fiyatlar[$i] !== '' ? (float)$birim_fiyatlar[$i] : null;
    $d = isset($durumlar[$i]) ? trim((string)$durumlar[$i]) : 'Beklemede';

    if ($u === '' && $m === null && $b === '' && $f === null) continue;
    $kalemler[] = ['urun' => $u, 'miktar' => $m, 'birim' => $b, 'birim_fiyat' => $f, 'durum' => $d];
  }

  if (empty($kalemler)) {
    $kalemler[] = ['urun' => f('urun', ''), 'miktar' => (f('miktar', '') !== '' ? (float)f('miktar') : null), 'birim' => f('birim', ''), 'birim_fiyat' => (f('birim_fiyat', '') !== '' ? (float)f('birim_fiyat') : null), 'durum' => 'Beklemede'];
  }

  $first = $kalemler[0];
  $durum = f('durum', $row['durum'] ?? 'Beklemede');

  $sql = "UPDATE `$TABLE` SET
                talep_tarihi = :talep_tarihi,
                proje_ismi = :proje_ismi,
                durum = :durum,
                onay_tarihi = :onay_tarihi,
                verildigi_tarih = :verildigi_tarih,
                termin_tarihi = :termin_tarihi,
                teslim_tarihi = :teslim_tarihi,
                urun = :urun,
                miktar = :miktar,
                birim = :birim,
                birim_fiyat = :birim_fiyat,
                updated_at = NOW()
              WHERE id = :id LIMIT 1";

  $u = $pdo->prepare($sql);
  $ok = $u->execute([
    ':talep_tarihi' => f('talep_tarihi') ?: null,
    ':proje_ismi' => f('proje_ismi'),
    ':durum' => $durum,
    ':onay_tarihi' => f('onay_tarihi') ?: null,
    ':verildigi_tarih' => f('verildigi_tarih') ?: null,
    ':termin_tarihi' => f('termin_tarihi') ?: null,
    ':teslim_tarihi' => f('teslim_tarihi') ?: null,
    ':urun' => $first['urun'],
    ':miktar' => $first['miktar'],
    ':birim' => $first['birim'],
    ':birim_fiyat' => $first['birim_fiyat'],
    ':id' => $id,
  ]);

  if ($ok) {
    try {
      $existing = $pdo->prepare("SELECT id FROM satinalma_order_items WHERE talep_id = ? ORDER BY id ASC");
      $existing->execute([$id]);
      $existingIds = $existing->fetchAll(PDO::FETCH_COLUMN);

      $update = $pdo->prepare("UPDATE satinalma_order_items SET urun=?, miktar=?, birim=?, birim_fiyat=?, durum=? WHERE id=?");
      $insert = $pdo->prepare("INSERT INTO satinalma_order_items (talep_id, urun, miktar, birim, birim_fiyat, durum) VALUES (?,?,?,?,?,?)");

      foreach ($kalemler as $index => $rowi) {
        if (isset($existingIds[$index])) {
          $update->execute([
            $rowi['urun'],
            $rowi['miktar'],
            $rowi['birim'],
            $rowi['birim_fiyat'],
            $rowi['durum'],
            $existingIds[$index]
          ]);
        } else {
          $insert->execute([
            $id,
            $rowi['urun'],
            $rowi['miktar'],
            $rowi['birim'],
            $rowi['birim_fiyat'],
            $rowi['durum']
          ]);
        }
      }
      if (count($existingIds) > count($kalemler)) {
        $idsToDelete = array_slice($existingIds, count($kalemler));
        $placeholders = implode(',', array_fill(0, count($idsToDelete), '?'));
        $delete = $pdo->prepare("DELETE FROM satinalma_order_items WHERE id IN ($placeholders)");
        $delete->execute($idsToDelete);
      }
    } catch (Throwable $e) {
      error_log('update items failed: ' . $e->getMessage());
    }

    $url = '/satinalma-sys/talepler.php?ok=1';
    header('Location: ' . $url, true, 302);
    exit;
  }
}
include('../includes/header.php');
?>
<!--============SCRIPT=====================-->
<!-- CRITICAL: JavaScript MUST load BEFORE any buttons are rendered -->
<script>
  (function() {
    'use strict';

    console.log('Talep script loading...');

    // GLOBAL DEĞİŞKENLER
    let currentItemId = 0;
    let currentProductName = '';
    let supplierData = [];
    let currentQuotes = [];

    // ÜRÜN SATIRI İŞLEMLERİ
    window.addProductRow = function() {
      const template = document.getElementById('productRowTemplate');
      const list = document.getElementById('productList');
      if (!template || !list) return;

      const clone = template.content.cloneNode(true);
      const row = clone.querySelector('.product-row');
      row.setAttribute('data-item-id', 'new_' + Date.now());

      list.appendChild(clone);

      // YENİ: Autocomplete ekle
      const newInput = row.querySelector('input[name="urun[]"]');
      if (newInput) {
        setupProductAutocomplete(newInput);
      }

      window.showNotification('Satır eklendi', 'success');
    };

    window.removeProductRow = function(btn) {
      const list = document.getElementById('productList');
      if (!list) return;
      const rows = list.querySelectorAll('.product-row');

      if (rows.length > 1) {
        btn.closest('.product-row').remove();
        window.showNotification('Silindi', 'success');
      } else {
        window.showNotification('En az 1 satır gerekli', 'warning');
      }
    };

    window.toggleSupplierInfo = function(btn) {
      const row = btn.closest('.product-row');
      if (!row) return;
      const info = row.querySelector('.supplier-info');

      if (!info) return;
      if (info.classList.contains('active')) {
        info.classList.remove('active');
        btn.textContent = 'Detay';
      } else {
        info.classList.add('active');
        btn.textContent = '🔼Gizle';
      }
    };

    // MODAL İŞLEMLERİ
    window.openSupplierModal = function(itemId, productName) {
      console.log('openSupplierModal called:', {
        itemId,
        productName
      }); // DEBUG

      if (!itemId || String(itemId).startsWith('new_') || itemId == 0) {
        // Yeni satır - ürün adı varsa devam et
        if (!productName || productName.trim() === '') {
          window.showNotification('Önce ürün adı girin', 'warning');
          return;
        }
      }

      currentItemId = parseInt(itemId) || 0;
      currentProductName = productName || '';

      const modal = document.getElementById('supplierModal');
      if (!modal) return;
      const nameEl = document.getElementById('currentProductName');
      if (nameEl) nameEl.textContent = productName || 'Ürün';
      modal.classList.add('show');

      loadSuppliers(productName);
      window.switchTab('existing');
    };

    window.closeSupplierModal = function() {
      const modal = document.getElementById('supplierModal');
      if (modal) modal.classList.remove('show');
      currentItemId = 0;
    };

    window.openQuoteModal = function(supplierId, supplierName) {
      if (currentItemId === 0 && !currentProductName) {
        window.showNotification('Önce ürün adı girin', 'warning');
        return;
      }

      const modal = document.getElementById('quoteModal');
      if (!modal) return;
      const qName = document.getElementById('quoteSupplierName');
      if (qName) qName.textContent = supplierName;
      const qItem = document.getElementById('quoteItemId');
      const qSupp = document.getElementById('quoteSupplierId');
      const qDate = document.getElementById('quoteDate');
      if (qItem) qItem.value = currentItemId;
      if (qSupp) qSupp.value = supplierId;
      if (qDate) qDate.value = new Date().toISOString().split('T')[0];

      const existingQuote = currentQuotes.find(q => q && q.supplier_id == supplierId);
      if (existingQuote) {
        const quotePrice = document.getElementById('quotePrice');
        const quoteCurrency = document.getElementById('quoteCurrency');
        const deliveryDays = document.getElementById('deliveryDays');
        const paymentTerm = document.getElementById('paymentTerm');
        const shippingType = document.getElementById('shippingType');
        const quoteNotes = document.getElementById('quoteNotes');

        if (quotePrice) quotePrice.value = existingQuote.price || '';
        if (quoteCurrency) quoteCurrency.value = existingQuote.currency || 'TRY';
        if (deliveryDays) deliveryDays.value = existingQuote.delivery_days || '';
        if (paymentTerm) paymentTerm.value = existingQuote.payment_term || '';
        if (shippingType) shippingType.value = existingQuote.shipping_type || '';
        if (quoteNotes) quoteNotes.value = existingQuote.note || '';
      } else {
        const qForm = document.getElementById('quoteForm');
        if (qForm) qForm.reset();
        if (qItem) qItem.value = currentItemId;
        if (qSupp) qSupp.value = supplierId;
      }

      modal.classList.add('show');
    };

    window.closeQuoteModal = function() {
      const modal = document.getElementById('quoteModal');
      if (modal) modal.classList.remove('show');
    };

    function closeAllModals() {
      document.querySelectorAll('.modal').forEach(m => m.classList.remove('show'));
    }

    // TAB DEĞİŞTİRME
    window.switchTab = function(tabName) {
      document.querySelectorAll('.supplier-tab').forEach(t => t.classList.remove('active'));

      if (tabName === 'existing') {
        const tabs = document.querySelectorAll('.supplier-tab');
        if (tabs[0]) tabs[0].classList.add('active');

        const ex = document.getElementById('existingSuppliers');
        const nw = document.getElementById('newSupplierForm');
        if (ex) ex.style.display = 'block';
        if (nw) nw.style.display = 'none';
      } else {
        const tabs = document.querySelectorAll('.supplier-tab');
        if (tabs[1]) tabs[1].classList.add('active');

        const ex = document.getElementById('existingSuppliers');
        const nw = document.getElementById('newSupplierForm');
        if (ex) ex.style.display = 'none';
        if (nw) nw.style.display = 'block';
      }
    };

    window.openSupplierModalFromRow = function(btn) {
      const row = btn.closest('.product-row');
      if (!row) return;

      const itemId = row.getAttribute('data-item-id') || 0;
      const productInput = row.querySelector('input[name="urun[]"]');
      const productName = productInput ? productInput.value.trim() : '';

      console.log('openSupplierModalFromRow:', {
        itemId,
        productName
      }); // DEBUG

      if (!productName) {
        window.showNotification('Önce ürün adı girin', 'warning');
        return;
      }

      // item_id varsa ve geçerli bir sayıysa normal akış
      // Yoksa product_name ile geçmiş tedarikçileri getir
      const parsedItemId = parseInt(itemId);
      // Yeni satır kontrolü
      if (isNaN(parsedItemId) || parsedItemId <= 0 || String(itemId).startsWith('new_')) {
        window.showNotification('Yeni ürün için önce formu kaydedin', 'warning');
        return;
      }

      if (isNaN(parsedItemId) || parsedItemId <= 0 || String(itemId).startsWith('new_')) {
        currentItemId = 0;
        currentProductName = productName;

        const modal = document.getElementById('supplierModal');
        if (!modal) return;
        const nameEl = document.getElementById('currentProductName');
        if (nameEl) nameEl.textContent = productName || 'Ürün';
        modal.classList.add('show');

        // Geçmiş tedarikçileri yükle
        loadSuppliers(productName);
        window.switchTab('existing');
      } else {
        window.openSupplierModal(itemId, productName);
      }
    };

    // TEDARİKÇİ SEÇİM FONKSİYONU
    window.selectSupplier = function(supplierId, supplierName, hasQuote) {
      if (!hasQuote) {
        window.openQuoteModal(supplierId, supplierName);
        return;
      }

      const quote = currentQuotes.find(q => q && q.supplier_id == supplierId);
      if (!quote || !quote.id) {
        window.showNotification('Teklif bulunamadı', 'danger');
        return;
      }

      const formData = new FormData();
      formData.append('quote_id', quote.id);
      formData.append('item_id', currentItemId);

      fetch('/satinalma-sys/talep_ajax.php?action=select_quote', {
          method: 'POST',
          body: formData
        })
        .then(r => r.json())
        .then(data => {
          if (data.success) {
            window.showNotification('Tedarikçi seçildi✅', 'success');
            window.closeSupplierModal();

            // HARD RELOAD ile cache'i temizle
            setTimeout(() => {
              window.location.reload(true);
            }, 800);
          } else {
            window.showNotification(data.error || 'Hata', 'danger');
          }
        })
        .catch(err => window.showNotification('Hata: ' + err, 'danger'));
    };

    // TEDARİKÇİLERİ YÜKLEME
    function loadSuppliers(productName) {
      const list = document.getElementById('supplierList');
      if (!list) return;

      list.innerHTML = '<div class="text-center">Yükleniyor...</div>';

      const itemId = parseInt(currentItemId) || 0;

      // İLK OLARAK: Eğer item_id varsa önce normal akışı dene
      if (itemId > 0) {
        const url = '/satinalma-sys/talep_ajax.php?action=get_suppliers&item_id=' + itemId + '&product_name=' + encodeURIComponent(productName || '');

        fetch(url)
          .then(r => r.json())
          .then(data => {
            console.log('get_suppliers response:', data);

            if (data.error) throw new Error(data.error);

            supplierData = data.suppliers || [];
            currentQuotes = data.quotes || [];
            console.log('Loaded quotes:', currentQuotes); // DEBUG - para birimlerini kontrol et

            // YENI MANTIK: Eğer quotes varsa ama 3'ten azsa, geçmiş bilgileri de göster
            if (currentQuotes.length > 0 && currentQuotes.length < 3) {
              console.log('Few quotes, loading historical data too...');
              loadAndMergeHistorical(productName, data);
            }
            // Çok teklif varsa sadece mevcut teklifleri göster
            else if (currentQuotes.length >= 3) {
              renderSuppliers(supplierData, currentQuotes, data.selected_quote || null);
            }
            // Hiç teklif yoksa geçmiş tedarikçileri getir
            else {
              console.log('No quotes found, loading historical suppliers...');
              loadHistoricalSuppliers(productName);
            }
          })
          .catch(err => {
            console.error('Supplier load error:', err);
            list.innerHTML = '<div class="alert alert-danger">Hata: ' + err.message + '</div>';
          });
      }
      // item_id yoksa direkt geçmiş tedarikçileri getir
      else if (productName) {
        loadHistoricalSuppliers(productName);
      } else {
        list.innerHTML = '<div class="alert alert-warning">Ürün adı gerekli</div>';
      }
    }

    // YENİ FONKSIYON: Mevcut verilerle geçmiş verileri birleştir
    function loadAndMergeHistorical(productName, currentData) {
      const url = '/satinalma-sys/talep_ajax.php?action=get_product_suppliers&product_name=' + encodeURIComponent(productName);

      fetch(url)
        .then(r => r.json())
        .then(historicalData => {
          console.log('Historical data loaded for merge:', historicalData);

          if (historicalData.error) throw new Error(historicalData.error);

          // Mevcut quotes'u kullan ama geçmiş bilgileri de ekle
          renderSuppliersWithHistory(
            currentData.suppliers || [],
            currentData.quotes || [],
            currentData.selected_quote || null,
            historicalData.suppliers || [],
            historicalData.historical_count || 0
          );
        })
        .catch(err => {
          console.error('Historical merge error:', err);
          // Hata olursa sadece mevcut teklifleri göster
          renderSuppliers(currentData.suppliers || [], currentData.quotes || [], currentData.selected_quote || null);
        });
    }

    // YENİ RENDER FONKSIYONU: Hem mevcut teklifler hem geçmiş bilgiler
    function renderSuppliersWithHistory(suppliers, quotes, selectedQuote, historicalSuppliers, historicalCount) {
      const list = document.getElementById('supplierList');
      if (!list) return;

      if (!suppliers || suppliers.length === 0) {
        list.innerHTML = '<div class="text-center text-muted">Tedarikçi yok</div>';
        return;
      }

      // Geçmiş bilgileri map'e çevir (hızlı erişim için)
      const historicalMap = {};
      historicalSuppliers.forEach(h => {
        if (h.has_history == 1) {
          historicalMap[h.id] = {
            avg_price: h.avg_price,
            last_quote_date: h.last_quote_date,
            quote_count: h.quote_count
          };
        }
      });

      // En düşük fiyatı bul (hem mevcut hem geçmiş)
      let lowestPrice = null;
      quotes.forEach(q => {
        const price = parseFloat(q.price || 0);
        if (lowestPrice === null || price < lowestPrice) {
          lowestPrice = price;
        }
      });
      Object.values(historicalMap).forEach(h => {
        const price = parseFloat(h.avg_price || 0);
        if (price > 0 && (lowestPrice === null || price < lowestPrice)) {
          lowestPrice = price;
        }
      });

      let html = '';

      // Bilgilendirme mesajı
      if (selectedQuote) {
        html += '<div class="alert alert-success mb-3">';
        html += '<strong>Seçili:</strong> ' + (selectedQuote.supplier_name || '');
        const selSymbol = selectedQuote.currency === 'USD' ? '$' : (selectedQuote.currency === 'EUR' ? '€' : '₺');
        html += '<br><small>Fiyat: ' + selSymbol + parseFloat(selectedQuote.price || 0).toFixed(2) + '</small>';
        html += '</div>';
      }

      if (historicalCount > 0) {
        html += '<div class="alert alert-info mb-3">';
        html += '<strong>📊 Bu ürün için ' + historicalCount + ' tedarikçiden geçmiş teklif var</strong><br>';
        html += '<small>Geçmişi olan firmalar ⭐ ile işaretlidir';
        if (lowestPrice) {
          // En düşük fiyatın para birimini bul
          let lowestCurrency = 'TRY';
          quotes.forEach(q => {
            if (q && parseFloat(q.price || 0) === lowestPrice) {
              lowestCurrency = q.currency || 'TRY';
            }
          });
          const lowestSymbol = lowestCurrency === 'USD' ? '$' : (lowestCurrency === 'EUR' ? '€' : '₺');
          html += ' | <span style="color:#28a745;font-weight:600;">En düşük: ' + lowestSymbol + lowestPrice.toFixed(2) + '</span>';
        }
        html += '</small></div>';
      }

      // FİLTRE BUTONU
      html += '<div class="mb-3">';
      html += '<label class="filter-checkbox">';
      html += '<input type="checkbox" id="filterHistoricalOnly">';
      html += '<span>Sadece geçmişi olanları göster</span>';
      html += '</label>';
      html += '</div>';

      suppliers.forEach(s => {
        if (!s || !s.id) return;

        const quote = quotes.find(q => q && q.supplier_id == s.id);
        const hasQuote = !!quote;
        // Para birimi bilgisini quote'tan al
        const currency = quote && quote.currency ? quote.currency : 'TRY';
        const currencySymbol = currency === 'USD' ? '$' : (currency === 'EUR' ? '€' : '₺');
        const isSelected = selectedQuote && selectedQuote.supplier_id == s.id;

        // Geçmiş bilgileri al
        const historical = historicalMap[s.id];
        const hasHistory = !!historical;

        const currentPrice = hasQuote ? parseFloat(quote.price || 0) : null;
        const avgPrice = historical ? parseFloat(historical.avg_price || 0) : null;

        // En iyi fiyat kontrolü
        const isBestPrice = (currentPrice && lowestPrice && Math.abs(currentPrice - lowestPrice) < 0.01) ||
          (avgPrice && lowestPrice && Math.abs(avgPrice - lowestPrice) < 0.01);

        const dataAttr = hasHistory ? 'data-has-history="1"' : 'data-has-history="0"';

        html += '<div class="supplier-item ' + (hasQuote ? 'has-quote' : '') + ' ' + (isSelected ? 'selected' : '') + ' ' + (hasHistory ? 'has-history' : '') + '" ' + dataAttr + '>';

        html += '<div class="supplier-item-header">';
        html += '<div class="supplier-name">' + (s.name || 'İsimsiz');
        if (isSelected) html += ' <span style="color: #28a745;">✓</span>';
        if (hasHistory) html += ' <span style="color: #ffc107; font-size: 1.2em;">⭐</span>';
        html += '</div>';

        if (hasQuote) {
          const priceStyle = isBestPrice ? 'color:#28a745;font-weight:700;font-size:1.3rem;' : '';
          html += '<div class="supplier-price" style="' + priceStyle + '">';
          if (isBestPrice) html += '✓ ';
          html += currencySymbol + currentPrice.toFixed(2);
          html += '</div>';
        } else if (hasHistory && avgPrice) {
          const priceStyle = isBestPrice ? 'color:#28a745;font-weight:700;' : '';
          html += '<div class="supplier-price" style="' + priceStyle + '">Ort: ₺' + avgPrice.toFixed(2) + '</div>';
        }
        html += '</div>';

        html += '<div class="supplier-details">';
        html += '<div>📞 ' + (s.phone || '-') + '</div>';
        html += '<div>👤 ' + (s.contact_person || '-') + '</div>';
        html += '</div>';

        // Geçmiş bilgi kutusu
        if (hasHistory && historical) {
          const boxStyle = isBestPrice && !hasQuote ?
            'background:#d4edda;border-left:3px solid #28a745;' :
            'background:#fff3cd;border-left:3px solid #ffc107;';

          html += '<div style="margin-top:8px;padding:10px;border-radius:4px;' + boxStyle + '">';
          html += '<small style="font-weight:600;color:' + (isBestPrice && !hasQuote ? '#155724' : '#856404') + ';">';
          html += '📋 Geçmiş: </small>';
          // Para birimi bilgisini kullan
          const histSymbol = quote && quote.currency ?
            (quote.currency === 'USD' ? '$' : (quote.currency === 'EUR' ? '€' : '₺')) : '₺';
          if (avgPrice) html += '<small>Ort: ' + histSymbol + avgPrice.toFixed(2) + '</small> ';
          if (historical.quote_count) html += '<small>(' + historical.quote_count + ' teklif)</small>';
          html += '</div>';
        }

        if (hasQuote) {
          html += '<div class="d-flex gap-2 mt-2">';
          html += '<button type="button" class="btn btn-' + (isSelected ? 'success' : 'primary') + ' btn-sm flex-fill" ';
          html += 'onclick="selectSupplier(' + s.id + ', \'' + (s.name || '').replace(/'/g, "\\'") + '\', true)">';
          html += (isSelected ? '✓ SEÇİLİ' : '🎯Seç');
          html += '</button>';
          html += '<button type="button" class="btn btn-outline btn-sm" ';
          html += 'onclick="openQuoteModal(' + s.id + ', \'' + (s.name || '').replace(/'/g, "\\'") + '\')">';
          html += 'Düzenle</button>';
          html += '</div>';
        } else {
          html += '<button type="button" class="btn btn-primary btn-sm mt-2 w-100" ';
          html += 'onclick="openQuoteModal(' + s.id + ', \'' + (s.name || '').replace(/'/g, "\\'") + '\')">';
          html += '💰 Teklif Gir</button>';
        }

        html += '</div>';
      });

      list.innerHTML = html;

      // Filtre event listener
      const filterCheckbox = document.getElementById('filterHistoricalOnly');
      if (filterCheckbox) {
        filterCheckbox.addEventListener('change', function() {
          const showOnlyHistorical = this.checked;
          document.querySelectorAll('#supplierList .supplier-item').forEach(item => {
            const hasHistory = item.getAttribute('data-has-history') === '1';
            item.style.display = (showOnlyHistorical && !hasHistory) ? 'none' : 'block';
          });
        });
      }
    }

    // YENİ FONKSIYON: Geçmiş tedarikçileri yükle
    function loadHistoricalSuppliers(productName) {
      const list = document.getElementById('supplierList');
      if (!list) return;

      if (!productName) {
        list.innerHTML = '<div class="alert alert-warning">Ürün adı gerekli</div>';
        return;
      }

      list.innerHTML = '<div class="text-center">Geçmiş tedarikçiler yükleniyor...</div>';

      const url = '/satinalma-sys/talep_ajax.php?action=get_product_suppliers&product_name=' + encodeURIComponent(productName);

      fetch(url)
        .then(r => r.json())
        .then(data => {
          console.log('get_product_suppliers response:', data);

          if (data.error) throw new Error(data.error);

          supplierData = data.suppliers || [];
          currentQuotes = [];
          renderHistoricalSuppliers(supplierData, data.historical_count || 0);
        })
        .catch(err => {
          console.error('Historical suppliers load error:', err);
          list.innerHTML = '<div class="alert alert-danger">Hata: ' + err.message + '</div>';
        });
    }
    // TEDARİKÇİLERİ RENDER ETME
    function renderSuppliers(suppliers, quotes, selectedQuote) {
      const list = document.getElementById('supplierList');
      if (!list) return;

      if (!suppliers || suppliers.length === 0) {
        list.innerHTML = '<div class="text-center text-muted">Tedarikçi yok</div>';
        return;
      }

      let html = '';

      if (selectedQuote) {
        html += '<div class="alert alert-success mb-3">';
        html += '<strong>Seçili:</strong> ' + (selectedQuote.supplier_name || '');
        const selSymbol = selectedQuote.currency === 'USD' ? '$' : (selectedQuote.currency === 'EUR' ? '€' : '₺');
        html += '<br><small>Fiyat: ' + selSymbol + parseFloat(selectedQuote.price || 0).toFixed(2) + '</small>';
        html += '</div>';
      }

      suppliers.forEach(s => {
        if (!s || !s.id) return;

        const quote = quotes.find(q => q && q.supplier_id == s.id);
        const hasQuote = !!quote;
        const isSelected = selectedQuote && selectedQuote.supplier_id == s.id;

        html += '<div class="supplier-item ' + (hasQuote ? 'has-quote' : '') + ' ' + (isSelected ? 'selected' : '') + '">';
        html += '<div class="supplier-item-header">';
        html += '<div class="supplier-name">' + (s.name || 'İsimsiz');
        if (isSelected) html += ' <span style="color: #28a745;">✓</span>';
        html += '</div>';
        if (hasQuote) {
          const qSymbol = quote.currency === 'USD' ? '$' : (quote.currency === 'EUR' ? '€' : '₺');
          html += '<div class="supplier-price">' + qSymbol + parseFloat(quote.price || 0).toFixed(2) + '</div>';
        }
        html += '</div>';
        html += '<div class="supplier-details">';
        html += '<div>' + (s.phone || '-') + '</div>';
        html += '<div>' + (s.contact_person || '-') + '</div>';
        html += '</div>';

        if (hasQuote) {
          html += '<div class="d-flex gap-2 mt-2">';
          html += '<button type="button" class="btn btn-' + (isSelected ? 'success' : 'primary') + ' btn-sm flex-fill" ';
          html += 'onclick="selectSupplier(' + s.id + ', \'' + (s.name || '').replace(/'/g, "\\'") + '\', true)">';
          html += (isSelected ? '✓ SEÇİLİ' : '🎯Seç');
          html += '</button>';
          html += '<button type="button" class="btn btn-outline btn-sm" ';
          html += 'onclick="openQuoteModal(' + s.id + ', \'' + (s.name || '').replace(/'/g, "\\'") + '\')">';
          html += 'Düzenle</button>';
          html += '</div>';
        } else {
          html += '<button type="button" class="btn btn-primary btn-sm mt-2 w-100" ';
          html += 'onclick="openQuoteModal(' + s.id + ', \'' + (s.name || '').replace(/'/g, "\\'") + '\')">';
          html += '💰 Teklif Gir</button>';
        }

        html += '</div>';
      });

      list.innerHTML = html;
    }

    function renderHistoricalSuppliers(suppliers, historicalCount) {
      const list = document.getElementById('supplierList');
      if (!list) return;

      if (!suppliers || suppliers.length === 0) {
        list.innerHTML = '<div class="text-center text-muted">Tedarikçi bulunamadı</div>';
        return;
      }

      // EN DÜŞÜK FİYATI BUL
      let lowestPrice = null;
      suppliers.forEach(s => {
        if (s.has_history == 1 && s.avg_price) {
          const price = parseFloat(s.avg_price);
          if (lowestPrice === null || price < lowestPrice) {
            lowestPrice = price;
          }
        }
      });

      let html = '';

      // Bilgilendirme mesajı
      if (historicalCount > 0) {
        html += '<div class="alert alert-info mb-3">';
        html += '<strong>📊 Bu ürün için ' + historicalCount + ' tedarikçiden geçmiş teklif bulundu</strong><br>';
        html += '<small>Geçmişi olan firmalar ⭐ ile işaretlidir | ';
        if (lowestPrice) {
          html += '<span style="color:#28a745;font-weight:600;">En düşük: ₺' + lowestPrice.toFixed(2) + '</span>'; // Geçmiş veriler TRY
        }
        html += '</small>';
        html += '</div>';
      }

      // FİLTRE BUTONU
      html += '<div class="mb-3">';
      html += '<label class="filter-checkbox">';
      html += '<input type="checkbox" id="filterHistoricalOnly" style="margin-right:8px;">';
      html += '<span>Sadece geçmişi olanları göster</span>';
      html += '</label>';
      html += '</div>';

      // Tedarikçileri listele
      suppliers.forEach(s => {
        if (!s || !s.id) return;

        const hasHistory = parseInt(s.has_history) === 1;
        const avgPrice = s.avg_price ? parseFloat(s.avg_price) : null;
        const lastDate = s.last_quote_date ? new Date(s.last_quote_date).toLocaleDateString('tr-TR') : null;
        const quoteCount = s.quote_count ? parseInt(s.quote_count) : 0;

        // EN İYİ FİYAT KONTROLÜ
        const isBestPrice = avgPrice && lowestPrice && Math.abs(avgPrice - lowestPrice) < 0.01;

        // FİLTRE İÇİN DATA ATTRIBUTE
        const dataAttr = hasHistory ? 'data-has-history="1"' : 'data-has-history="0"';

        html += '<div class="supplier-item' + (hasHistory ? ' has-history' : '') + '" ' + dataAttr + '>';

        // Başlık
        html += '<div class="supplier-item-header">';
        html += '<div class="supplier-name">';
        html += (s.name || 'İsimsiz');
        if (hasHistory) html += ' <span style="color: #ffc107; font-size: 1.2em;">⭐</span>';
        html += '</div>';

        if (hasHistory && avgPrice) {
          // EN İYİ FİYAT YEŞİL RENK
          const priceStyle = isBestPrice ?
            'color:#28a745;font-weight:700;font-size:1.3rem;' :
            '';
          html += '<div class="supplier-price" style="' + priceStyle + '">';
          if (isBestPrice) html += '✓ ';
          html += 'Ort: ₺' + avgPrice.toFixed(2);
          html += '</div>';
        }
        html += '</div>';

        // İletişim bilgileri
        html += '<div class="supplier-details">';
        html += '<div>📞 ' + (s.phone || '-') + '</div>';
        html += '<div>👤 ' + (s.contact_person || '-') + '</div>';
        html += '</div>';

        // Geçmiş bilgi kutusu
        if (hasHistory && (avgPrice || lastDate || quoteCount > 0)) {
          // EN İYİ FİYAT İÇİN YEŞİL KUTU
          const boxStyle = isBestPrice ?
            'background:#d4edda;border-left:3px solid #28a745;' :
            'background:#fff3cd;border-left:3px solid #ffc107;';

          html += '<div style="margin-top:8px;padding:10px;border-radius:4px;' + boxStyle + '">';
          html += '<small style="font-weight:600;color:' + (isBestPrice ? '#155724' : '#856404') + ';">';
          html += (isBestPrice ? '🏆 EN İYİ FİYAT' : '📋 Geçmiş Teklif Bilgisi') + ':</small><br>';
          if (avgPrice) html += '<small><strong>Ortalama Fiyat:</strong> ₺' + avgPrice.toFixed(2) + '</small><br>';
          if (lastDate) html += '<small><strong>Son Tarih:</strong> ' + lastDate + '</small><br>';
          if (quoteCount > 0) html += '<small><strong>Toplam:</strong> ' + quoteCount + ' teklif</small>';
          html += '</div>';
        }

        // Teklif gir butonu
        html += '<button type="button" class="btn btn-primary btn-sm mt-2 w-100" ';
        html += 'onclick="openQuoteModal(' + s.id + ', \'' + (s.name || '').replace(/'/g, "\\'") + '\')">';
        html += '💰 Teklif Gir</button>';

        html += '</div>';
      });

      list.innerHTML = html;

      // FİLTRE EVENT LİSTENER
      const filterCheckbox = document.getElementById('filterHistoricalOnly');
      if (filterCheckbox) {
        filterCheckbox.addEventListener('change', function() {
          const showOnlyHistorical = this.checked;
          document.querySelectorAll('#supplierList .supplier-item').forEach(item => {
            const hasHistory = item.getAttribute('data-has-history') === '1';
            item.style.display = (showOnlyHistorical && !hasHistory) ? 'none' : 'block';
          });
        });
      }
    }

    // BİLDİRİM SİSTEMİ
    window.showNotification = function(msg, type) {
      const n = document.createElement('div');
      n.className = 'alert alert-' + type;
      n.textContent = msg;
      n.style.cssText = 'position:fixed;top:20px;right:20px;z-index:10000;min-width:300px;opacity:0;transition:all 0.3s;box-shadow:0 4px 12px rgba(0,0,0,0.15)';
      document.body.appendChild(n);
      setTimeout(() => n.style.opacity = '1', 10);
      setTimeout(() => {
        n.style.opacity = '0';
        setTimeout(() => n.remove(), 300);
      }, 3000);
    };

    // EVENT LISTENERS - DOM YÜKLENDİKTEN SONRA
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', initEventListeners);
    } else {
      initEventListeners();
    }


    // AUTOCOMPLETE FONKSİYONU
    function setupProductAutocomplete(input) {
      if (!input) return;

      let timeout = null;
      let suggestionBox = null;

      input.addEventListener('input', function(e) {
        clearTimeout(timeout);
        const term = e.target.value.trim();

        if (suggestionBox) {
          suggestionBox.remove();
          suggestionBox = null;
        }

        if (term.length < 2) return;

        timeout = setTimeout(() => {
          fetch(`/satinalma-sys/talep_ajax.php?action=search_products&term=${encodeURIComponent(term)}`)
            .then(r => r.json())
            .then(products => {
              if (!products || products.length === 0) return;

              suggestionBox = document.createElement('div');
              suggestionBox.style.cssText = 'position:absolute;background:white;border:2px solid #007bff;border-radius:6px;max-height:200px;overflow-y:auto;z-index:1000;box-shadow:0 4px 12px rgba(0,0,0,0.15);';

              const rect = input.getBoundingClientRect();
              suggestionBox.style.top = (rect.bottom + window.scrollY) + 'px';
              suggestionBox.style.left = rect.left + 'px';
              suggestionBox.style.width = rect.width + 'px';

              products.forEach(product => {
                const item = document.createElement('div');
                item.textContent = product;
                item.style.cssText = 'padding:10px;cursor:pointer;border-bottom:1px solid #eee;';
                item.addEventListener('mouseenter', () => item.style.background = '#f0f8ff');
                item.addEventListener('mouseleave', () => item.style.background = 'white');
                item.addEventListener('click', () => {
                  input.value = product;
                  suggestionBox.remove();
                  suggestionBox = null;

                  // Geçmiş tedarikçileri getir
                  showProductHistory(product, input.closest('.product-row'));
                });
                suggestionBox.appendChild(item);
              });

              document.body.appendChild(suggestionBox);
            })
            .catch(err => console.error('Autocomplete error:', err));
        }, 300);
      });

      document.addEventListener('click', function(e) {
        if (suggestionBox && !suggestionBox.contains(e.target) && e.target !== input) {
          suggestionBox.remove();
          suggestionBox = null;
        }
      });
    }

    function showProductHistory(productName, row) {
      if (!row) return;

      fetch(`/satinalma-sys/talep_ajax.php?action=get_product_suppliers&product_name=${encodeURIComponent(productName)}`)
        .then(r => r.json())
        .then(data => {
          if (data.success && data.historical_count > 0) {
            window.showNotification(`"${productName}" için ${data.historical_count} tedarikçiden geçmiş teklif bulundu`, 'info');
          }
        })
        .catch(err => console.error('Product history error:', err));
    }
    window.toggleApproval = function(btn) {
      const itemId = btn.dataset.itemId;
      const currentState = btn.classList.contains('approved');

      btn.disabled = true;
      btn.innerHTML = '⏳ İşleniyor...';

      const formData = new FormData();
      formData.append('item_id', itemId);
      formData.append('approved', currentState ? '0' : '1');

      fetch('/satinalma-sys/talep_ajax.php?action=toggle_approval', {
          method: 'POST',
          body: formData
        })
        .then(r => r.json())
        .then(data => {
          if (data.success) {
            btn.classList.toggle('approved');
            btn.innerHTML = data.approved ? '✔ Onaylandı' : '⏳ Bekliyor';
            window.showNotification('Onay güncellendi', 'success');
          } else {
            throw new Error(data.error || 'Hata oluştu');
          }
        })
        .catch(err => {
          window.showNotification('Hata: ' + err.message, 'danger');
          btn.innerHTML = currentState ? '✔ Onaylandı' : '⏳ Bekliyor';
        })
        .finally(() => {
          btn.disabled = false;
        });
    };

    function initEventListeners() {
      // Sayfa yüklendiğinde mevcut ürünlerin geçmiş tedarikçilerini yükle
      document.querySelectorAll('.product-row').forEach(row => {
        const productInput = row.querySelector('input[name="urun[]"]');
        const productName = productInput ? productInput.value.trim() : '';

        if (productName) {
          // Geçmiş tedarikçileri sessizce yükle (bildirim gösterme)
          fetch(`/satinalma-sys/talep_ajax.php?action=get_product_suppliers&product_name=${encodeURIComponent(productName)}`)
            .then(r => r.json())
            .then(data => {
              if (data.success && data.historical_count > 0) {
                // Badge ekle veya güncelle
                const btn = row.querySelector('.tedarikci-sec-btn');
                if (btn && !btn.querySelector('.history-badge')) {
                  const badge = document.createElement('span');
                  badge.className = 'badge history-badge';
                  badge.style.cssText = 'background:#ffc107;color:#000;font-size:0.7rem;padding:2px 6px;border-radius:10px;margin-left:5px;';
                  badge.textContent = '⭐' + data.historical_count;
                  btn.appendChild(badge);
                }
              }
            })
            .catch(err => console.error('Product history check error:', err));
        }
      });
      document.addEventListener('click', function(e) {
        if (e.target && e.target.classList && e.target.classList.contains('modal')) {
          closeAllModals();
        }
      });

      document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
          closeAllModals();
        }
      });

      document.addEventListener('submit', function(e) {
        if (e.target && e.target.id === 'supplierForm') {
          e.preventDefault();
          const form = e.target;
          const formData = new FormData(form);
          const submitBtn = form.querySelector('button[type="submit"]');
          const originalText = submitBtn ? submitBtn.innerHTML : '';
          if (submitBtn) {
            submitBtn.innerHTML = '⏳ Kaydediliyor...';
            submitBtn.disabled = true;
          }

          fetch('/satinalma-sys/talep_ajax.php?action=add_supplier', {
              method: 'POST',
              body: formData
            })
            .then(r => r.json())
            .then(data => {
              if (data.success) {
                window.showNotification('Yeni tedarikçi eklendi', 'success');
                form.reset();
                setTimeout(() => {
                  if (data.supplier_id) {
                    loadSuppliers(currentProductName);
                    window.switchTab('existing');
                  }
                }, 1000);
              } else {
                throw new Error(data.error || 'Kayıt yapılamadı');
              }
            })
            .catch(err => window.showNotification('Hata: ' + err.message, 'danger'))
            .finally(() => {
              if (submitBtn) {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
              }
            });
        }

        if (e.target && e.target.id === 'quoteForm') {
          e.preventDefault();
          const form = e.target;

          const priceEl = document.getElementById('quotePrice');
          const paymentTermEl = document.getElementById('paymentTerm');
          const price = priceEl ? priceEl.value : '';
          const paymentTerm = paymentTermEl ? paymentTermEl.value : '';

          if (!price || parseFloat(price) <= 0) {
            window.showNotification('Geçerli bir fiyat giriniz', 'danger');
            return;
          }

          if (!paymentTerm) {
            window.showNotification('Ödeme koşulu seçiniz', 'danger');
            return;
          }

          const formData = new FormData(form);
          formData.append('product_name', currentProductName);

          const submitBtn = form.querySelector('button[type="submit"]');
          const originalText = submitBtn ? submitBtn.innerHTML : '';
          if (submitBtn) {
            submitBtn.innerHTML = '⏳ Kaydediliyor...';
            submitBtn.disabled = true;
          }

          fetch('/satinalma-sys/talep_ajax.php?action=save_quote', {
              method: 'POST',
              body: formData
            })
            .then(r => r.json())
            .then(data => {
              if (data.success) {
                window.showNotification('Teklif kaydedildi', 'success');

                // ÖNEMLİ: Eğer yeni item oluşturulduysa, currentItemId'yi güncelle
                if (data.item_id) {
                  const oldItemId = currentItemId;
                  currentItemId = data.item_id;

                  // Sayfadaki data-item-id'yi güncelle
                  const rows = document.querySelectorAll('.product-row');
                  rows.forEach(row => {
                    const rowItemId = row.getAttribute('data-item-id');
                    if (rowItemId == oldItemId || String(rowItemId).startsWith('new_')) {
                      // Ürün adını kontrol et
                      const productInput = row.querySelector('input[name="urun[]"]');
                      if (productInput && productInput.value.trim() === currentProductName) {
                        console.log('Updating row item_id from', rowItemId, 'to', data.item_id);
                        row.setAttribute('data-item-id', data.item_id);
                      }
                    }
                  });
                }

                window.closeQuoteModal();

                // Modal'ı tekrar yükle
                setTimeout(() => {
                  loadSuppliers(currentProductName);
                }, 300);
              } else {
                throw new Error(data.error || 'Teklif kaydedilemedi');
              }
            })
            .catch(err => window.showNotification('Hata: ' + err.message, 'danger'))
            .finally(() => {
              if (submitBtn) {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
              }
            });
        }
      });

      document.addEventListener('input', function(e) {
        if (e.target && e.target.id === 'supplierSearch') {
          const searchTerm = e.target.value.toLowerCase();
          document.querySelectorAll('.supplier-item').forEach(item => {
            const name = (item.querySelector('.supplier-name')?.textContent || '').toLowerCase();
            const details = (item.querySelector('.supplier-details')?.textContent || '').toLowerCase();
            item.style.display = (name.includes(searchTerm) || details.includes(searchTerm)) ? 'block' : 'none';
          });
        }
      });
      // Mevcut ürün inputlarına autocomplete ekle
      document.querySelectorAll('input[name="urun[]"]').forEach(input => {
        setupProductAutocomplete(input);
      });
    }

    console.log('All functions exported to window - ready!');
  })();
</script>

<!--========STYLE KISMI========-->
<style>
  :root {
    --primary-color: #007bff;
    --success-color: #28a745;
    --danger-color: #dc3545;
    --warning-color: #ffc107;
    --info-color: #17a2b8;
    --light-color: #f8f9fa;
    --dark-color: #343a40;
    --border-color: #dee2e6;
    --shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    --border-radius: 8px;
  }

  * {
    box-sizing: border-box;
  }

  body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    line-height: 1.6;
    margin: 0;
    padding: 20px;
    background-color: #f5f6fa;
  }

  .container {
    max-width: 1600px;
    margin: 0 auto;
    padding: 0 15px;
  }

  .form-section {
    background: white;
    border-radius: var(--border-radius);
    box-shadow: var(--shadow);
    padding: 25px;
    margin-bottom: 20px;
  }

  .section-title {
    color: var(--dark-color);
    margin: 0 0 20px 0;
    font-size: 1.25rem;
    font-weight: 600;
    padding-bottom: 10px;
    border-bottom: 2px solid var(--primary-color);
  }

  .form-grid {
    display: grid;
    gap: 20px;
    margin-bottom: 20px;
  }

  .grid-3 {
    grid-template-columns: repeat(3, 1fr);
  }

  .grid-4 {
    grid-template-columns: repeat(4, 1fr);
  }

  .grid-2 {
    grid-template-columns: repeat(2, 1fr);
  }

  .form-field {
    display: flex;
    flex-direction: column;
  }

  .form-field label {
    font-weight: 600;
    color: var(--dark-color);
    margin-bottom: 5px;
    font-size: 0.9rem;
  }

  .form-control {
    padding: 10px 12px;
    border: 2px solid var(--border-color);
    border-radius: 6px;
    font-size: 14px;
    transition: all 0.3s ease;
    background: white;
  }

  .form-control:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
  }

  .form-control:read-only {
    background-color: #e9ecef;
    cursor: not-allowed;
  }

  .product-row {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr 1fr 1fr auto auto auto;
    gap: 15px;
    align-items: end;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 8px;
    margin-bottom: 15px;
    border-left: 4px solid var(--primary-color);
    position: relative;
  }

  .product-row:hover {
    background: #e9ecef;
    transition: background 0.2s ease;
  }

  .product-status {
    position: absolute;
    top: 8px;
    right: 8px;
    font-size: 0.75rem;
    padding: 2px 8px;
    border-radius: 12px;
    font-weight: 600;
  }

  .status-beklemede {
    background: #fff3cd;
    color: #856404;
  }

  .status-teklifbekleniyor {
    background: #d1ecf1;
    color: #0c5460;
  }

  .status-teklifalindi {
    background: #cce5ff;
    color: #004085;
  }

  .status-siparisverildi {
    background: #d4edda;
    color: #155724;
  }

  .status-teslimedildi {
    background: #d4edda;
    color: #155724;
  }

  .supplier-info {
    grid-column: span 8;
    margin-top: 10px;
    padding: 10px;
    background: white;
    border-radius: 6px;
    border: 1px solid #e9ecef;
    display: none;
  }

  .supplier-info.active {
    display: block;
  }

  .supplier-summary {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.85rem;
    color: #6c757d;
    margin-top: 5px;
  }

  .btn {
    padding: 8px 16px;
    border: 2px solid transparent;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    transition: all 0.3s ease;
    min-height: 38px;
    background: none;
  }

  .btn-primary {
    background: var(--primary-color);
    color: white;
    border-color: var(--primary-color);
  }

  .btn-primary:hover {
    background: #0056b3;
    border-color: #0056b3;
    transform: translateY(-1px);
  }

  .btn-success {
    background: var(--success-color);
    color: white;
    border-color: var(--success-color);
  }

  .btn-success:hover {
    background: #218838;
    border-color: #218838;
  }

  .btn-danger {
    background: var(--danger-color);
    color: white;
    border-color: var(--danger-color);
  }

  .btn-danger:hover {
    background: #c82333;
    border-color: #c82333;
  }

  .btn-outline {
    background: transparent;
    color: var(--primary-color);
    border-color: var(--primary-color);
  }

  .btn-outline:hover {
    background: var(--primary-color);
    color: white;
  }

  .btn-sm {
    padding: 6px 12px;
    font-size: 12px;
    min-height: 32px;
  }

  .btn-icon {
    padding: 6px 8px;
    min-width: 36px;
  }

  .modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 9999;
    backdrop-filter: blur(3px);
  }

  .modal.show {
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .modal-dialog {
    background: white;
    border-radius: var(--border-radius);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
    max-width: 800px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
    animation: modalSlideIn 0.3s ease;
  }

  @keyframes modalSlideIn {
    from {
      opacity: 0;
      transform: translateY(-50px);
    }

    to {
      opacity: 1;
      transform: translateY(0);
    }
  }

  .modal-header {
    padding: 20px 25px;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #f8f9fa;
  }

  .modal-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--dark-color);
    margin: 0;
  }

  .modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #6c757d;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
  }

  .modal-close:hover {
    background: #e9ecef;
    color: var(--danger-color);
  }

  .modal-body {
    padding: 25px;
  }

  .supplier-tabs {
    display: flex;
    margin-bottom: 20px;
    border-bottom: 1px solid #e9ecef;
  }

  .supplier-tab {
    padding: 10px 20px;
    border: none;
    background: none;
    cursor: pointer;
    color: #6c757d;
    font-weight: 500;
    border-bottom: 3px solid transparent;
  }

  .supplier-tab.active {
    color: var(--primary-color);
    border-bottom-color: var(--primary-color);
  }

  .supplier-list {
    max-height: 400px;
    overflow-y: auto;
  }

  .supplier-item {
    background: white;
    border: 2px solid var(--border-color);
    border-radius: 6px;
    padding: 15px;
    margin-bottom: 10px;
    cursor: pointer;
    transition: all 0.3s ease;
  }

  .supplier-item:hover {
    border-color: var(--primary-color);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  }

  .supplier-item.selected {
    border-color: #28a745;
    background: #d4edda;
    border-left: 4px solid #28a745;
  }

  .supplier-item.has-quote {
    border-left: 4px solid var(--info-color);
  }

  .supplier-item-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
  }

  .supplier-name {
    font-weight: 600;
    color: var(--dark-color);
    font-size: 1.1rem;
  }

  .supplier-price {
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--success-color);
  }

  .supplier-details {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    font-size: 0.85rem;
    color: #6c757d;
  }

  .new-supplier-form {
    background: #e8f4fd;
    padding: 20px;
    border-radius: 8px;
    margin-top: 15px;
    display: none;
  }

  .new-supplier-form.show {
    display: block;
  }

  .alert {
    padding: 12px 16px;
    margin: 10px 0;
    border-radius: 6px;
    font-weight: 500;
  }

  .alert-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
  }

  .alert-danger {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
  }

  .alert-warning {
    background: #fff3cd;
    color: #856404;
    border: 1px solid #ffeeba;
  }

  .alert-info {
    background: #d1ecf1;
    color: #0c5460;
    border: 1px solid #bee5eb;
  }

  .text-center {
    text-align: center;
  }

  .text-muted {
    color: #6c757d;
  }

  .text-success {
    color: #28a745;
  }

  .d-flex {
    display: flex;
  }

  .gap-2 {
    gap: 0.5rem;
  }

  .gap-3 {
    gap: 1rem;
  }

  .justify-content-between {
    justify-content: space-between;
  }

  .align-items-center {
    align-items: center;
  }

  .mb-2 {
    margin-bottom: 0.5rem;
  }

  .mb-3 {
    margin-bottom: 1rem;
  }

  .mt-2 {
    margin-top: 0.5rem;
  }

  .mt-3 {
    margin-top: 1rem;
  }

  .w-100 {
    width: 100%;
  }

  .flex-fill {
    flex: 1;
  }

  @media (max-width: 1200px) {
    .product-row {
      grid-template-columns: 1fr;
      gap: 10px;
    }

    .supplier-info {
      grid-column: span 1;
    }
  }

  @media (max-width: 768px) {
    .container {
      padding: 0 10px;
    }

    body {
      padding: 10px;
    }

    .grid-3,
    .grid-4 {
      grid-template-columns: 1fr;
    }

    .grid-2 {
      grid-template-columns: 1fr;
    }

    .form-section {
      padding: 15px;
    }

    .modal-dialog {
      width: 95%;
      margin: 10px;
    }

    .modal-body,
    .modal-header {
      padding: 15px;
    }

    .supplier-details {
      grid-template-columns: 1fr;
    }

    .supplier-item.has-history {
      border-left: 4px solid #ffc107 !important;
      background: #fffbf0;
    }

    .supplier-item.has-history:hover {
      background: #fff8e1;
    }

    /* Filtre checkbox stili */
    .filter-checkbox {
      display: inline-flex;
      align-items: center;
      padding: 10px 15px;
      background: #f8f9fa;
      border-radius: 6px;
      cursor: pointer;
      user-select: none;
      transition: background 0.2s;
      gap: 8px;
    }

    .filter-checkbox:hover {
      background: #e9ecef;
    }

    .filter-checkbox input[type="checkbox"] {
      width: 18px;
      height: 18px;
      cursor: pointer;
      margin: 0;
    }

    .filter-checkbox span {
      font-weight: 500;
      color: #495057;
      white-space: nowrap;
    }

    .approval-btn {
      width: 100%;
      background: #ffc107;
      color: #000;
      border: 2px solid #ffc107;
      font-weight: 600;
      transition: all 0.3s ease;
    }

    .approval-btn.approved {
      background: #28a745;
      color: white;
      border-color: #28a745;
    }

    .approval-btn:hover {
      opacity: 0.9;
      transform: translateY(-1px);
    }

    .inline-approval {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .inline-approval label {
      margin: 0;
      font-weight: bold;
    }

    .inline-approval .approval-btn {
      min-width: 120px;
    }

    /* En iyi fiyat için animasyon */
    @keyframes bestPricePulse {

      0%,
      100% {
        transform: scale(1);
      }

      50% {
        transform: scale(1.05);
      }
    }

    .supplier-item.has-history .supplier-price[style*="color:#28a745"] {
      animation: bestPricePulse 2s infinite;
    }
  }
</style>

<div class="container fade-in">
  <div class="form-section">
    <h2 class="section-title">📋Satın Alma Formu (Düzenle)</h2>

    <form method="post" id="mainForm">
      <!-- TEMEL BİLGİLER -->
      <div class="form-grid grid-3">
        <div class="form-field">
          <label>🔖Satın Alma Kodu (REN)</label>
          <input type="text" class="form-control" readonly value="<?= h($row['order_code'] ?? '') ?>">
        </div>
        <div class="form-field">
          <label>📅Talep Tarihi</label>
          <input type="date" name="talep_tarihi" class="form-control" value="<?= h($row['talep_tarihi'] ?? '') ?>">
        </div>
        <div class="form-field">
          <label>🗂️Proje İsmi</label>
          <?php
          $orders = [];
          try {
            $st = $pdo->prepare("SELECT order_code, proje_adi FROM orders ORDER BY id DESC");
            $st->execute();
            $orders = $st->fetchAll(PDO::FETCH_ASSOC);
          } catch (Exception $e) {
            $orders = [];
          }

          $current_proje = $row['proje_ismi'] ?? '';
          ?>
          <select name="proje_ismi" class="form-control" required>
            <option value="">— Seçiniz —</option>
            <?php foreach ($orders as $order):
              $proje = trim($order['proje_adi'] ?? '');
              $code = trim($order['order_code'] ?? '');
              if ($proje === '') continue;
              $label = $code ? "$code - $proje" : $proje;
              $selected = ($current_proje === $proje) ? 'selected' : '';
            ?>
              <option value="<?= h($proje) ?>" <?= $selected ?>><?= h($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <!-- ÜRÜN LİSTESİ -->
      <h3 class="section-title">🛒Ürün Listesi</h3>
      <div id="productList">
        <?php
        $units = [
          "adet" => "Adet",
          "takim" => "Takım",
          "cift" => "Çift",
          "paket" => "Paket",
          "kutu" => "Kutu",
          "koli" => "Koli",
          "kg" => "Kg",
          "g" => "G",
          "m" => "M",
          "cm" => "Cm",
          "mm" => "Mm",
          "m2" => "M²",
          "m3" => "M³",
          "lt" => "Lt",
          "ml" => "Ml"
        ];

        $items_to_show = !empty($existing_items) ? $existing_items : [
          ['id' => null, 'urun' => $row['urun'] ?? '', 'miktar' => $row['miktar'] ?? '', 'birim' => $row['birim'] ?? '', 'birim_fiyat' => $row['birim_fiyat'] ?? '', 'durum' => 'Beklemede', 'quote_count' => 0, 'best_price' => null, 'selected_supplier' => null]
        ];

        // Mevcut kodun yerine:
        foreach ($items_to_show as $index => $item):
          $item_id = $item['id'] ?? 0;
          $durum = $item['durum'] ?? 'Beklemede';

          // GÜVENLİ DÖNÜŞÜM
          $quote_count = isset($item['quote_count']) ? (int)$item['quote_count'] : 0;
          $best_price = isset($item['best_price']) ? $item['best_price'] : null;
          $selected_supplier = isset($item['selected_supplier']) ? $item['selected_supplier'] : null;
          $selected_price = isset($item['selected_price']) ? $item['selected_price'] : null;
          $selected_supplier_id = isset($item['selected_supplier_id']) ? $item['selected_supplier_id'] : null;
          $quoted_suppliers = isset($item['quoted_suppliers']) ? $item['quoted_suppliers'] : '';

          $status_class = 'status-' . strtolower(str_replace([' ', 'ı', 'ş', 'ğ', 'ü', 'ö', 'ç'], ['', 'i', 's', 'g', 'u', 'o', 'c'], $durum));
        ?>
          <div class="product-row slide-up" data-row="<?= $index ?>" data-item-id="<?= $item_id ?>">
            <div class="product-status <?= $status_class ?>">
              <?= h($durum) ?>
            </div>

            <div class="form-field">
              <label>📦 Ürün</label>
              <input type="text" name="urun[]" class="form-control" value="<?= h($item['urun'] ?? '') ?>" placeholder="Ürün adını girin">
            </div>
            <div class="form-field">
              <label>🔢 Miktar</label>
              <input type="number" step="0.01" name="miktar[]" class="form-control" value="<?= h($item['miktar'] ?? '') ?>" placeholder="0">
            </div>
            <div class="form-field">
              <label>📏 Birim</label>
              <select name="birim[]" class="form-control">
                <option value="">Seçiniz</option>
                <?php foreach ($units as $val => $label):
                  $selected = (strtolower($item['birim'] ?? '') === $val) ? 'selected' : '';
                ?>
                  <option value="<?= $val ?>" <?= $selected ?>><?= $label ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-field">
              <label>💰 Birim Fiyat</label>
              <div style="display: flex; gap: 8px; align-items: center;">
                <input type="number" step="0.25" name="birim_fiyat[]" class="form-control" value="<?= h($item['birim_fiyat'] ?? '') ?>" placeholder="0.00" readonly>
                <span class="badge" style="background: #7ba05b; color: white; padding: 8px 12px; font-size: 0.9rem; border-radius: 4px; min-width: 30px; text-align: center;">
                  <?php
                  $currency_symbol = '₺';
                  if (isset($item['selected_currency'])) {
                    $currency_symbol = $item['selected_currency'] === 'USD' ? '$' : ($item['selected_currency'] === 'EUR' ? '€' : '₺');
                  }
                  echo $currency_symbol;
                  ?>
                </span>
              </div>
            </div>
            <div class="form-field">
              <label>📊 Durum</label>
              <select name="item_durum[]" class="form-control">
                <option value="Beklemede" <?= $durum === 'Beklemede' ? 'selected' : '' ?>>Beklemede</option>
                <option value="Teklif Bekleniyor" <?= $durum === 'Teklif Bekleniyor' ? 'selected' : '' ?>>Teklif Bekleniyor</option>
                <option value="Teklif Alındı" <?= $durum === 'Teklif Alındı' ? 'selected' : '' ?>>Teklif Alındı</option>
                <option value="Sipariş Verildi" <?= $durum === 'Sipariş Verildi' ? 'selected' : '' ?>>Sipariş Verildi</option>
                <option value="Teslim Edildi" <?= $durum === 'Teslim Edildi' ? 'selected' : '' ?>>Teslim Edildi</option>
              </select>
            </div>

            <button type="button" class="btn btn-primary btn-sm tedarikci-sec-btn"
              onclick="openSupplierModalFromRow(this)">
              🏢 Tedarikçi Seç
              <?php if (isset($quote_count) && $quote_count > 0): ?>
                <span class="badge" style="background: #17a2b8; color: white; font-size: 0.7rem; padding: 2px 6px; border-radius: 10px; margin-left: 5px;">
                  <?= $quote_count ?>
                </span>
              <?php endif; ?>
              <?php if ($selected_supplier): ?>
                <span class="badge" style="background: #28a745; color: white; font-size: 0.7rem; padding: 2px 6px; border-radius: 10px; margin-left: 5px;">
                  ✓
                </span>
              <?php endif; ?>
            </button>
            <button type="button" class="btn btn-outline btn-sm detay-btn" onclick="toggleSupplierInfo(this)">
              📋 Detay
            </button>
            <button type="button" class="btn btn-danger btn-sm btn-icon sil-btn" onclick="removeProductRow(this)" title="Satırı Sil">
              🗑️
            </button>
            <div class="form-field">
              <div style="display: flex; align-items: center; gap: 10px;">
                <label style="margin: 0;">✅ Son Onay</label>
                <button type="button"
                  class="btn btn-sm approval-btn <?= isset($item['son_onay']) && $item['son_onay'] == 1 ? 'approved' : '' ?>"
                  data-item-id="<?= $item_id ?>"
                  onclick="toggleApproval(this)">
                  <?= isset($item['son_onay']) && $item['son_onay'] == 1 ? '✔ Onaylandı' : '⏳ Bekliyor' ?>
                </button>
              </div>
            </div>

            <div class="supplier-info">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <strong>Tedarikçi Bilgileri</strong>
                <?php if ($best_price): ?>
                  <?php
                  $best_currency = $item['best_price_currency'] ?? 'TRY';
                  $best_symbol = $best_currency === 'USD' ? '$' : ($best_currency === 'EUR' ? '€' : '₺');
                  ?>
                  <span class="text-success font-weight-bold">En İyi Fiyat: <?= $best_symbol ?><?= number_format((float)$best_price, 2) ?></span>
                <?php endif; ?>
              </div>
              <div class="supplier-summary">
                <span>
                  <strong>Seçilen Tedarikçi:</strong>
                  <?php if ($selected_supplier): ?>
                    <span class="text-success">✓ <?= h($selected_supplier) ?></span>
                    <?php if ($selected_price): ?>
                      <?php
                      $sel_currency = $item['selected_currency'] ?? 'TRY';
                      $sel_symbol = $sel_currency === 'USD' ? '$' : ($sel_currency === 'EUR' ? '€' : '₺');
                      ?>
                      <span class="text-muted">(<?= $sel_symbol ?><?= number_format((float)$selected_price, 2) ?>)</span>
                    <?php endif; ?>
                  <?php else: ?>
                    <span class="text-muted">Henüz seçilmedi</span>
                  <?php endif; ?>
                </span>
                <span>Toplam Teklif: <strong><?= $quote_count ?></strong></span>
              </div>
              <?php if ($quoted_suppliers): ?>
                <div class="mt-2" style="font-size: 0.85rem; color: #6c757d;">
                  <strong>Teklif Veren Firmalar:</strong> <?= count(array_filter(explode(',', $quoted_suppliers))) ?> adet
                </div>
              <?php endif; ?>

              <!-- Seçili Tedarikçi Detayları -->
              <?php if ($selected_supplier_id && $item_id > 0): ?>
                <?php
                try {
                  $stmt = $pdo->prepare("
                    SELECT sq.*, s.name as supplier_name, s.contact_person, s.phone, s.email
                    FROM satinalma_quotes sq
                    JOIN suppliers s ON sq.supplier_id = s.id
                    WHERE sq.order_item_id = ? AND sq.selected = 1
                    LIMIT 1
                  ");
                  $stmt->execute([$item_id]);
                  $selected_quote_detail = $stmt->fetch(PDO::FETCH_ASSOC);
                } catch (Exception $e) {
                  $selected_quote_detail = null;
                }
                ?>

                <?php if ($selected_quote_detail): ?>
                  <div class="mt-3 p-3" style="background: #d4edda; border-radius: 6px; border-left: 4px solid #28a745;">
                    <h6 class="mb-2">✅ Seçili Tedarikçi Detayları:</h6>
                    <div class="row">
                      <div class="col-md-6">
                        <small><strong>Firma:</strong> <?= h($selected_quote_detail['supplier_name']) ?></small><br>
                        <small><strong>Fiyat:</strong>
                          <?php
                          $currency_symbol = $selected_quote_detail['currency'] === 'USD' ? '$' : ($selected_quote_detail['currency'] === 'EUR' ? '€' : '₺');
                          echo $currency_symbol . number_format((float)$selected_quote_detail['price'], 2);
                          ?>
                        </small><br>
                        <small><strong>Teslimat:</strong> <?= $selected_quote_detail['delivery_days'] ? $selected_quote_detail['delivery_days'] . ' gün' : 'Belirtilmemiş' ?></small><br>
                        <small><strong>Gönderim:</strong> <?= h($selected_quote_detail['shipping_type'] ?? 'Belirtilmemiş') ?></small>
                      </div>
                      <div class="col-md-6">
                        <small><strong>Ödeme:</strong> <?= h($selected_quote_detail['payment_term'] ?? 'Belirtilmemiş') ?></small><br>
                        <small><strong>Teklif Tarihi:</strong> <?= $selected_quote_detail['quote_date'] ? date('d.m.Y', strtotime($selected_quote_detail['quote_date'])) : 'Belirtilmemiş' ?></small>
                      </div>
                    </div>
                    <?php if ($selected_quote_detail['note']): ?>
                      <div class="mt-2">
                        <small><strong>Not:</strong> <?= h($selected_quote_detail['note']) ?></small>
                      </div>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- ÜRÜN EKLE BUTONU -->
      <div class="d-flex gap-2 mb-3">
        <button type="button" class="btn btn-primary" onclick="addProductRow()">
          ➕ Yeni Ürün Ekle
        </button>
      </div>

      <!-- DURUM ve TARİHLER -->
      <div class="form-grid grid-4">
        <div class="form-field">
          <label>📊 Genel Durum</label>
          <select name="durum" class="form-control">
            <?php
            $durumlar = ['Beklemede', 'Onaylandı', 'Sipariş edildi', 'Tamamlandı'];
            $current_durum = $row['durum'] ?? 'Beklemede';
            ?>
            <?php foreach ($durumlar as $durum): ?>
              <option value="<?= h($durum) ?>" <?= ($current_durum === $durum) ? 'selected' : '' ?>>
                <?= h($durum) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-field">
          <label>✅ Onay Tarihi</label>
          <input type="date" name="onay_tarihi" class="form-control" value="<?= h($row['onay_tarihi'] ?? '') ?>">
        </div>
        <div class="form-field">
          <label>📤 Sipariş Verildiği Tarih</label>
          <input type="date" name="verildigi_tarih" class="form-control" value="<?= h($row['verildigi_tarih'] ?? '') ?>">
        </div>
        <div class="form-field">
          <label>⏰ Termin Tarihi</label>
          <input type="date" name="termin_tarihi" class="form-control" value="<?= h($row['termin_tarihi'] ?? '') ?>">
        </div>
      </div>

      <div class="form-grid grid-2">
        <div class="form-field">
          <label>📦 Teslim Tarihi</label>
          <input type="date" name="teslim_tarihi" class="form-control" value="<?= h($row['teslim_tarihi'] ?? '') ?>">
        </div>
      </div>

      <!-- FORM BUTONLARI -->
      <div class="d-flex gap-3 mt-3">
        <button type="submit" class="btn btn-success">
          💾 Kaydet ve Güncelle
        </button>
        <a href="/satinalma-sys/talepler.php" class="btn btn-outline">
          ❌ İptal
        </a>
      </div>
    </form>
  </div>
</div>

<!-- TEDARİKÇİ SEÇİM MODALI -->
<div id="supplierModal" class="modal">
  <div class="modal-dialog">
    <div class="modal-header">
      <h3 class="modal-title">Tedarikçi Seç - <span id="currentProductName"></span></h3>
      <button type="button" class="modal-close" onclick="closeSupplierModal()">&times;</button>
    </div>
    <div class="modal-body">
      <div id="supplierNotification"></div>

      <!-- TAB MENÜ -->
      <div class="supplier-tabs">
        <button type="button" class="supplier-tab active" onclick="switchTab('existing')">
          📋 Mevcut Tedarikçiler
        </button>
        <button type="button" class="supplier-tab" onclick="switchTab('new')">
          ➕ Yeni Tedarikçi Ekle
        </button>
      </div>

      <!-- MEVCUT TEDARİKÇİLER -->
      <div id="existingSuppliers">
        <div class="form-field mb-3">
          <input type="text" id="supplierSearch" class="form-control" placeholder="🔍 Tedarikçi ara...">
        </div>

        <div class="supplier-list" id="supplierList">
          <!-- AJAX ile yüklenecek -->
        </div>
      </div>

      <!-- YENİ TEDARİKÇİ FORMU -->
      <div id="newSupplierForm" class="new-supplier-form">
        <form id="supplierForm">
          <div class="form-grid grid-2">
            <div class="form-field">
              <label>🏢 Tedarikçi Adı *</label>
              <input type="text" id="supplierName" name="supplier_name" class="form-control" required>
            </div>
            <div class="form-field">
              <label>👤 İlgili Kişi</label>
              <input type="text" id="contactPerson" name="contact_person" class="form-control" placeholder="İlgili kişi adı">
            </div>
          </div>

          <div class="form-grid grid-2">
            <div class="form-field">
              <label>📞 Telefon</label>
              <input type="text" id="phone" name="phone" class="form-control" placeholder="Telefon numarası">
            </div>
            <div class="form-field">
              <label>📧 E-posta</label>
              <input type="email" id="email" name="email" class="form-control" placeholder="E-posta adresi">
            </div>
          </div>

          <div class="form-field">
            <label>📍 Adres</label>
            <textarea id="supplierAddress" name="address" class="form-control" rows="2"></textarea>
          </div>

          <div class="d-flex gap-2 justify-content-between mt-3">
            <button type="button" class="btn btn-outline" onclick="switchTab('existing')">⬅️ Geri</button>
            <button type="submit" class="btn btn-success">💾 Kaydet</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- TEKLİF FORMU MODALI -->
<div id="quoteModal" class="modal">
  <div class="modal-dialog">
    <div class="modal-header">
      <h3 class="modal-title">💰 Teklif Gir - <span id="quoteSupplierName"></span></h3>
      <button type="button" class="modal-close" onclick="closeQuoteModal()">&times;</button>
    </div>
    <div class="modal-body">
      <div id="quoteNotification"></div>

      <form id="quoteForm">
        <input type="hidden" id="quoteItemId" name="item_id">
        <input type="hidden" id="quoteSupplierId" name="supplier_id">
        <!--<input type="hidden" id="quoteCurrency" name="currency" value="TRY"> -->
        <input type="hidden" id="quoteDate" name="quote_date">

        <div class="form-grid grid-2">
          <div class="form-field">
            <label>💰 Birim Fiyat *</label>
            <input type="number" id="quotePrice" name="price" step="0.25" class="form-control" required>
          </div>
          <div class="form-field">
            <label>💱 Para Birimi</label>
            <select id="quoteCurrency" name="currency" class="form-control">
              <option value="TRY">₺ TL</option>
              <option value="USD">$ USD</option>
              <option value="EUR">€ EUR</option>
            </select>
          </div>
          <div class="form-field">
            <label>📅 Teslimat Süresi (Gün) *</label>
            <input type="number" id="deliveryDays" name="delivery_days" class="form-control" min="1" placeholder="15" required>
          </div>
        </div>

        <div class="form-field">
          <label>💳 Ödeme Koşulu *</label>
          <select id="paymentTerm" name="payment_term" class="form-control" required>
            <option value="">Seçiniz</option>
            <option value="Peşin">Peşin</option>
            <option value="Havale/EFT">Havale/EFT</option>
            <option value="K. Kartı Tek Çekim">K. Kartı Tek Çekim</option>
            <option value="K. Kartı 2 Taksit">K. Kartı 2 Taksit</option>
            <option value="Çek - 30 Gün">Çek - 30 Gün</option>
            <option value="Çek - 60 Gün">Çek - 60 Gün</option>
            <option value="Çek - 90 Gün">Çek - 90 Gün</option>
            <option value="Çek - 120 Gün">Çek - 120 Gün</option>
          </select>
        </div>

        <div class="form-field">
          <label>🚚 Gönderim Türü *</label>
          <select id="shippingType" name="shipping_type" class="form-control" required>
            <option value="">Seçiniz</option>
            <option value="Ambar">Ambar</option>
            <option value="Kargo">Kargo</option>
          </select>
        </div>

        <div class="form-field">
          <label>📝 Notlar</label>
          <textarea id="quoteNotes" name="notes" class="form-control" rows="3" placeholder="Teklif ile ilgili ek bilgiler..."></textarea>
        </div>

        <div class="d-flex gap-2 justify-content-between mt-3">
          <button type="button" class="btn btn-outline" onclick="closeQuoteModal()">✖ İptal</button>
          <button type="submit" class="btn btn-success">💾 Teklifi Kaydet</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ÜRÜN SATIRI TEMPLATE -->
<template id="productRowTemplate">
  <div class="product-row slide-up" data-item-id="0">
    <div class="product-status status-beklemede">Beklemede</div>

    <div class="form-field">
      <label>📦 Ürün</label>
      <input type="text" name="urun[]" class="form-control" placeholder="Ürün adını girin">
    </div>
    <div class="form-field">
      <label>🔢 Miktar</label>
      <input type="number" step="0.01" name="miktar[]" class="form-control" placeholder="0">
    </div>
    <div class="form-field">
      <label>📏 Birim</label>
      <select name="birim[]" class="form-control">
        <option value="">Seçiniz</option>
        <?php foreach ($units as $val => $label): ?>
          <option value="<?= $val ?>"><?= $label ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-field">
      <label>💰 Birim Fiyat (₺)</label>
      <input type="number" step="0.25" name="birim_fiyat[]" class="form-control" placeholder="0.00">
    </div>
    <div class="form-field">
      <label>📊 Durum</label>
      <select name="item_durum[]" class="form-control">
        <option value="Beklemede" selected>Beklemede</option>
        <option value="Teklif Bekleniyor">Teklif Bekleniyor</option>
        <option value="Teklif Alındı">Teklif Alındı</option>
        <option value="Sipariş Verildi">Sipariş Verildi</option>
        <option value="Teslim Edildi">Teslim Edildi</option>
      </select>
    </div>
    <div class="form-field">
      <label>✅ Son Onay</label>
      <button type="button"
        class="btn btn-sm approval-btn <?= $item['son_onay'] ? 'approved' : '' ?>"
        data-item-id="<?= $item_id ?>"
        onclick="toggleApproval(this)">
        <?= $item['son_onay'] ? '✔ Onaylandı' : '⏳ Bekliyor' ?>
      </button>
    </div>

    <!--Template içindeki tedarikçi seç butonunu bulun ve şöyle değiştirin:-->
    <button type="button" class="btn btn-primary btn-sm"
      onclick="openSupplierModalFromRow(this)">
      🏢 Tedarikçi Seç
    </button>
    <button type="button" class="btn btn-outline btn-sm" onclick="toggleSupplierInfo(this)">
      📋 Detay
    </button>
    <button type="button" class="btn btn-danger btn-sm btn-icon" onclick="removeProductRow(this)" title="Satırı Sil">
      🗑️
    </button>

    <div class="supplier-info">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <strong>Tedarikçi Bilgileri</strong>
      </div>
      <div class="supplier-summary">
        <span><strong>Seçilen Tedarikçi:</strong> <span class="text-muted">Henüz seçilmedi</span></span>
        <span>Toplam Teklif: <strong>0</strong></span>
      </div>
    </div>
  </div>
</template>


<?php
include('../includes/footer.php');
?>