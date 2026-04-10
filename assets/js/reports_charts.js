/**
 * ==============================================================================
 * RENPLAN ERP - SATIŞ VE FİNANS GRAFİKLERİ (CHART.JS)
 * ==============================================================================
 */
(function() {
  // PHP'den gelen veriyi Global "window" objesinden alıyoruz
  const payload = window.CHART_PAYLOAD;

  if (!payload) return; // Veri yoksa çökmesini engelle

  // 1. CANLI ÜRETİM SAHASINDAKİ MATEMATİKSEL RENK ALGORİTMASI
  // Yan yana gelen dilimler asla birbirine benzemez (+45 derece atlar)
  function generateColors(count, hueStart) {
    let colors = [];
    for (let i = 0; i < count; i++) {
      let hue = (hueStart + (i * 45)) % 360;
      colors.push(`hsl(${hue}, 70%, 55%)`);
    }
    return colors;
  }

  function entriesFrom(group) {
    const items = (payload && payload[group]) || {};
    return Object.entries(items)
      .map(([name, info]) => ({
        name: name,
        val: Number(info.try_val) || 0, // Grafik için TL
        disp_val: Number(info.val) || 0, // Liste için Orijinal Döviz
        cur: info.cur || ''
      }))
      .sort((a, b) => b.val - a.val);
  }

  function symbol(cur) {
    return cur === 'TRY' ? '₺' : (cur === 'USD' ? '$' : (cur === 'EUR' ? '€' : ''));
  }

  function renderPie(canvasId, listId, entries, startHue, isCount = false) {
    const labels = entries.map(e => e.name);
    const data = entries.map(e => e.val);

    const colors = generateColors(labels.length, startHue);

    const ctx = document.getElementById(canvasId)?.getContext('2d');
    if (ctx && labels.length) {
      new Chart(ctx, {
        type: 'doughnut',
        data: {
          labels: labels,
          datasets: [{
            data: data,
            backgroundColor: colors,
            borderWidth: 2,
            borderColor: '#ffffff',
            hoverOffset: 15
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              display: false
            },
            tooltip: {
              backgroundColor: 'rgba(255, 255, 255, 0.95)',
              titleColor: '#1e293b',
              bodyColor: '#1e293b',
              borderColor: '#e2e8f0',
              borderWidth: 1,
              padding: 12,
              callbacks: {
                label: function(context) {
                  let label = context.label || '';
                  let value = context.parsed;

                  let maxLength = 30;
                  if (label.length > maxLength) {
                    label = label.substring(0, maxLength) + '...';
                  }

                  if (isCount) {
                    return ' ' + label + ': ' + value + ' Sipariş';
                  } else {
                    return ' ' + label + ': ₺' + value.toLocaleString('tr-TR', {
                      minimumFractionDigits: 4,
                      maximumFractionDigits: 4
                    });
                  }
                }
              }
            }
          },
          layout: {
            padding: 15
          }
        }
      });
    }

    const ul = document.getElementById(listId);
    if (ul) {
      if (!entries.length) {
        ul.innerHTML = '<li><span class="name">Veri yok</span><span class="val">—</span></li>';
      } else {
        const top5 = entries.slice(0, 5);
        ul.innerHTML = top5.map(it => {
          if (isCount) {
            return `<li><span class="name">${it.name}</span><span class="val" style="color:#10b981;">${it.disp_val} Adet</span></li>`;
          } else {
            return `<li><span class="name">${it.name}</span><span class="val">${it.disp_val.toLocaleString('tr-TR', {
              minimumFractionDigits: 2,
              maximumFractionDigits: 2
            })} ${symbol(it.cur)}</span></li>`;
          }
        }).join('');
      }
    }
  }

  // ================================================
  // ⭐ SATIŞ TEMSİLCİSİ - DİNAMİK SIRALAMA
  // ================================================
  let salespersonChart = null;

  function renderSalespersonChart(sortBy) {
    const enhanced = payload.salesperson_enhanced || {};
    let entries = Object.entries(enhanced).map(([name, data]) => {
      let value = 0;
      let displayValue = 0;
      let suffix = '';

      if (sortBy === 'order_count') {
        value = data.order_count || 0;
        displayValue = value;
        suffix = ' Sipariş';
      } else if (sortBy === 'total_price') {
        value = data.total_price_try || 0;
        displayValue = value;
        suffix = '';
      }
      return {
        name: name,
        value: value,
        displayValue: displayValue,
        suffix: suffix,
        currency: data.original_currency || 'TRY'
      };
    });

    entries.sort((a, b) => b.value - a.value);
    const labels = entries.map(e => e.name);
    const data = entries.map(e => e.value);
    const colors = generateColors(labels.length, 50);

    if (salespersonChart) salespersonChart.destroy();

    const ctx = document.getElementById('pieSalesperson')?.getContext('2d');
    if (ctx && labels.length) {
      salespersonChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
          labels: labels,
          datasets: [{
            data: data,
            backgroundColor: colors,
            borderWidth: 2,
            borderColor: '#ffffff',
            hoverOffset: 15
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              display: false
            },
            tooltip: {
              backgroundColor: 'rgba(255, 255, 255, 0.95)',
              titleColor: '#1e293b',
              bodyColor: '#1e293b',
              borderColor: '#e2e8f0',
              borderWidth: 1,
              padding: 12,
              callbacks: {
                label: function(context) {
                  let label = context.label || '';
                  let value = context.parsed;
                  let entry = entries[context.dataIndex];
                  if (label.length > 30) label = label.substring(0, 30) + '...';
                  if (sortBy === 'total_price') return ' ' + label + ': ₺' + value.toLocaleString('tr-TR', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                  });
                  return ' ' + label + ': ' + value + entry.suffix;
                }
              }
            }
          },
          layout: {
            padding: 15
          }
        }
      });
    }

    const ul = document.getElementById('top5Salesperson');
    if (ul) {
      if (!entries.length) {
        ul.innerHTML = '<li><span class="name">Veri yok</span><span class="val">—</span></li>';
      } else {
        const top5 = entries.slice(0, 5);
        ul.innerHTML = top5.map((it, idx) => {
          let displayText = sortBy === 'total_price' ?
            '₺' + it.displayValue.toLocaleString('tr-TR', {
              minimumFractionDigits: 2,
              maximumFractionDigits: 2
            }) :
            it.displayValue.toLocaleString('tr-TR') + it.suffix;

          let crown = (idx === 0) ? '<span style="margin-right:6px; display:inline-block; transform:scale(1.2);">👑</span>' : '';
          let nameStyle = (idx === 0) ? 'font-weight:700; color:#1e293b;' : '';

          return `<li><span class="name" style="${nameStyle}">${crown}${it.name}</span><span class="val" style="color:#10b981; font-weight:600;">${displayText}</span></li>`;
        }).join('');
      }
    }
  }

  // Olay Dinleyicileri (Event Listeners)
  renderSalespersonChart('order_count');
  document.querySelectorAll('input[name="salesperson_sort"]').forEach(function(radio) {
    radio.addEventListener('change', function() {
      if (this.checked) {
        const infoText = document.getElementById('spPriceInfo');
        if (infoText) {
          infoText.style.display = this.value === 'total_price' ? 'block' : 'none';
        }

        const chartBox = document.querySelector('#pieSalesperson').closest('.chart-box');
        chartBox.style.opacity = '0.3';
        setTimeout(() => {
          renderSalespersonChart(this.value);
          chartBox.style.opacity = '1';
        }, 150);
      }
    });
  });

  renderPie('pieCustomer', 'top5Customer', entriesFrom('customer'), 200, false);
  renderPie('pieProject', 'top5Project', entriesFrom('project'), 280, false);
  renderPie('pieCategory', 'top5Category', entriesFrom('category'), 340, false);

  // ================================================
  // ⭐ SATIŞ TEMSİLCİSİ DETAY ANALİZİ
  // ================================================
  const spDetails = payload.salesperson_details || {};
  const spSelect = document.getElementById('spDetailSelect');

  if (spSelect) {
    const spList = Object.keys(spDetails).sort();
    spList.forEach(sp => {
      let opt = document.createElement('option');
      opt.value = sp;
      opt.textContent = sp;
      spSelect.appendChild(opt);
    });

    const firstRealSp = spList.find(s => s !== 'Belirtilmemiş') || spList[0];
    if (firstRealSp) spSelect.value = firstRealSp;
  }

  let spDetailChart = null;

  function renderSpDetailChart() {
    if (!spSelect) return;
    const selectedSp = spSelect.value;
    const selectedType = document.querySelector('input[name="sp_detail_type"]:checked')?.value;
    const ul = document.getElementById('top5SpDetail');
    const ctx = document.getElementById('pieSpDetail')?.getContext('2d');

    if (!selectedSp || !spDetails[selectedSp]) {
      if (spDetailChart) spDetailChart.destroy();
      if (ul) ul.innerHTML = '<li style="font-size:12px; color:#94a3b8;">Temsilci seçilmedi</li>';
      return;
    }

    function getSymbol(c) {
      return c === 'TRY' ? '₺' : (c === 'USD' ? '$' : (c === 'EUR' ? '€' : ''));
    }

    const dataObj = spDetails[selectedSp][selectedType] || {};
    let entries = Object.entries(dataObj)
      .map(([name, info]) => ({
        name: name,
        val: Number(info.try_val) || 0,
        disp_val: Number(info.val) || 0,
        cur: info.cur || 'TRY'
      }))
      .sort((a, b) => b.val - a.val);

    const labels = entries.map(e => e.name);
    const data = entries.map(e => e.val);

    const colors = generateColors(labels.length, selectedType === 'projects' ? 270 : 330);

    if (spDetailChart) spDetailChart.destroy();

    if (labels.length > 0 && ctx) {
      spDetailChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
          labels: labels,
          datasets: [{
            data: data,
            backgroundColor: colors,
            borderWidth: 2,
            borderColor: '#ffffff',
            hoverOffset: 10
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              display: false
            },
            tooltip: {
              backgroundColor: 'rgba(255, 255, 255, 0.95)',
              titleColor: '#1e293b',
              bodyColor: '#1e293b',
              borderColor: '#e2e8f0',
              borderWidth: 1,
              padding: 12,
              callbacks: {
                label: function(context) {
                  let lbl = context.label || '';
                  if (lbl.length > 30) lbl = lbl.substring(0, 30) + '...';
                  let entry = entries[context.dataIndex];
                  return ' ' + lbl + ': ' + entry.disp_val.toLocaleString('tr-TR', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                  }) + ' ' + getSymbol(entry.cur);
                }
              }
            }
          },
          layout: {
            padding: 10
          }
        }
      });
    }

    if (ul) {
      if (!entries.length) {
        ul.innerHTML = '<li style="font-size:12px; color:#94a3b8;">Bu kriterde kayıt bulunamadı</li>';
      } else {
        ul.innerHTML = entries.slice(0, 5).map(it => {
          let txt = it.disp_val.toLocaleString('tr-TR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
          }) + ' ' + getSymbol(it.cur);
          return `<li style="display:flex; justify-content:space-between; font-size:12px; padding:8px 10px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px;">
                    <span style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:60%; font-weight:600; color:#334155;">${it.name}</span>
                    <span style="color:#10b981; font-weight:800;">${txt}</span>
                </li>`;
        }).join('');
      }
    }
  }

  if (spSelect) {
    spSelect.addEventListener('change', renderSpDetailChart);
    document.querySelectorAll('input[name="sp_detail_type"]').forEach(r => r.addEventListener('change', renderSpDetailChart));
    renderSpDetailChart();
  }

})();