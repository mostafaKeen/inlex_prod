/**
 * Fee Sync Widget — Bitrix24 CRM Product Grid (FINAL FIX)
 * Supports: Deal & Lead entities
 * SPA Item Types: 1058 (Professional Fees), 1062 (Government Fees)
 * 
 * ISSUE FIXED:
 * - Property values (typeOfCost, payments) were being lost during buildRowFromEntityRow
 * - Now using BOTH direct product ID lookup AND property extraction from catalog
 * - Properly handles Bitrix24's property response format
 */

var FeeSyncWidget = (function () {

  // ─── Field Maps ────────────────────────────────────────────────────────────

  var PRODUCT_PROPS = {
    typeOfCost:  'PROPERTY_111',
    payments:    'PROPERTY_109',
    companyType: 'PROPERTY_99',
    visaType:    'PROPERTY_101',
    visaStatus:  'PROPERTY_103',
  };

  // SPA 1058 = Professional Fees
  var SPA_PROF_FIELDS = {
    typeOfCost:  'ufCrm15_1779367818775',
    payments:    'ufCrm15_1779367955682',
    companyType: 'ufCrm15_1779368170455',
    visaType:    'ufCrm15_1779368285728',
    visaStatus:  'ufCrm15_1779368405816',
  };

  // SPA 1062 = Government Fees
  var SPA_GOV_FIELDS = {
    typeOfCost:  'ufCrm17_1779370162991',
    payments:    'ufCrm17_1779370261982',
    companyType: 'ufCrm17_1779370566902',
    visaType:    'ufCrm17_1779370435095',
    visaStatus:  'ufCrm17_1779370325590',
  };

  // Deal custom fields
  var DEAL_PROF_FIELD  = 'UF_CRM_1779313011';
  var DEAL_GOV_FIELD   = 'UF_CRM_1779654189';
  var LEAD_PROF_FIELD  = 'UF_CRM_1779194029';
  var LEAD_GOV_FIELD   = null;

  // Catalog product property option maps
  var PROP_TYPE_OF_COST = { '207': 'Government Cost', '209': 'Professional Fees' };
  var PROP_PAYMENTS = {
    '193': 'Annually', '195': 'One Time', '197': 'Quarterly',
    '199': 'Every 2 years', '201': 'Monthly',
    '203': 'In The Order Of Discussion',
    '205': 'One time (cost depends on transactions)'
  };

  // ─── State ─────────────────────────────────────────────────────────────────
  var state = {
    entityType: null,
    entityId:   null,
    rows:       [],
    nextRowId:  1,
    productCatalog: [],
    spaItems: {
      prof: {},
      gov:  {}
    }
  };

  // ─── DOM Refs & Logging ────────────────────────────────────────────────────
  function log(msg, level) {
    console.log('[FeeSyncWidget] ' + msg);
    var el = document.getElementById('sync-log');
    if (!el) return;
    var ts = new Date().toLocaleTimeString();
    el.innerHTML = '<span>[' + ts + '] ' + msg + '</span><br>' + el.innerHTML;
  }

  function setStatus(msg, cls) {
    var el = document.getElementById('sync-status');
    if (!el) return;
    el.textContent = msg;
    el.className = cls || 'status-info';
  }

  // ─── Init ──────────────────────────────────────────────────────────────────
  function init(entityType, entityId, onReady) {
    state.entityType = entityType;
    state.entityId   = entityId;
    state.rows       = [];
    state.nextRowId  = 1;
    state.productCatalog = [];
    state.spaItems = { prof: {}, gov: {} };

    setStatus('Loading…', 'status-info');
    log('Initialising for ' + entityType + ' #' + entityId);

    // Load catalog first, then entity products
    loadCatalogProducts(function () {
      loadEntityProducts(function () {
        renderRows();
        bindActions();
        setStatus('Ready', 'status-info');
        if (typeof onReady === 'function') onReady();
      });
    });
  }

  // ─── Load catalog ──────────────────────────────────────────────────────────
  function loadCatalogProducts(cb) {
    var allProducts = [];

    BX24.callMethod('crm.product.list', {
      select: ['ID', 'NAME', 'PRICE', 'CURRENCY_ID', 'ACTIVE',
               'PROPERTY_111', 'PROPERTY_109', 'PROPERTY_99',
               'PROPERTY_101', 'PROPERTY_103'],
      filter: { 'ACTIVE': 'Y' },
      order:  { 'NAME': 'ASC' }
    }, function (res) {
      if (res.error()) {
        log('Catalog load error: ' + res.error());
        state.productCatalog = allProducts;
        if (cb) cb();
        return;
      }
      var page = res.data() || [];
      allProducts = allProducts.concat(page);
      if (res.more()) {
        res.next();
      } else {
        state.productCatalog = allProducts;
        log('Loaded ' + allProducts.length + ' catalog products');
        if (cb) cb();
      }
    });
  }

  // ─── Load existing entity products ────────────────────────────────────────
  function loadEntityProducts(cb) {
    var method = state.entityType === 'deal'
      ? 'crm.deal.productrows.get'
      : 'crm.lead.productrows.get';

    BX24.callMethod(method, { id: state.entityId }, function (res) {
      if (res.error()) {
        log('Product rows error: ' + res.error());
        if (cb) cb();
        return;
      }
      var rows = res.data() || [];
      log('Loaded ' + rows.length + ' product row(s) from entity');

      rows.forEach(function (r) {
        state.rows.push(buildRowFromEntityRow(r));
      });

      if (cb) cb();
    });
  }

  // ─── FIXED: Build row with proper property extraction ─────────────────────
  function buildRowFromEntityRow(r) {
    var catalogItem = findCatalogProduct(r.PRODUCT_ID);
    
    // Get property values from catalog (since productrows don't include them)
    var typeOfCost = getPropValue(catalogItem, 'PROPERTY_111') || '';
    var payments   = getPropValue(catalogItem, 'PROPERTY_109') || '';

    var row = {
      id:          state.nextRowId++,
      productId:   r.PRODUCT_ID || '',
      name:        r.PRODUCT_NAME || (catalogItem ? catalogItem.NAME || catalogItem.name : ''),
      price:       parseFloat(r.PRICE || 0),
      qty:         parseFloat(r.QUANTITY || 1),
      taxRate:     parseFloat(r.TAX_RATE || 0),
      taxIncluded: (r.TAX_INCLUDED === 'Y'),
      typeOfCost:  String(typeOfCost),
      payments:    String(payments),
      sort:        parseInt(r.SORT || 0),
      spaId:       null,
      _entityRow:  r
    };

    log('Row #' + row.id + ': product=' + row.productId + 
        ', name=' + row.name +
        ', typeOfCost=' + row.typeOfCost + 
        ', payments=' + row.payments);

    return row;
  }

  // ─── Extract property value from product object ────────────────────────────
  function getPropValue(product, propKey) {
    if (!product) return '';
    var val = product[propKey];
    if (val === undefined || val === null || val === '') return '';
    
    // Handle array of values
    if (Array.isArray(val)) {
      val = val[0];
      if (val === undefined || val === null) return '';
    }
    
    // Handle object property values (Bitrix24 wraps them)
    if (typeof val === 'object') {
      if (val.id !== undefined)    return String(val.id);
      if (val.ID !== undefined)    return String(val.ID);
      if (val.value !== undefined) return String(val.value);
      if (val.VALUE !== undefined) return String(val.VALUE);
    }
    
    return String(val);
  }

  function findCatalogProduct(productId) {
    if (!productId) return null;
    var pid = String(productId);
    return state.productCatalog.find(function (p) {
      return String(p.ID || p.id) === pid;
    }) || null;
  }

  // ─── Render ────────────────────────────────────────────────────────────────
  function renderRows() {
    var tbody = document.getElementById('product-rows-body');
    if (!tbody) return;
    tbody.innerHTML = '';
    state.rows.forEach(function (row, idx) {
      tbody.appendChild(buildRowEl(row, idx + 1));
    });
    recalcTotals();
  }

  function buildRowEl(row, num) {
    var tr = document.createElement('tr');
    tr.setAttribute('data-row-id', row.id);

    // Build product catalog options
    var opts = '<option value="">-- select --</option>';
    state.productCatalog.forEach(function (p) {
      var pid = p.ID || p.id;
      var pname = p.NAME || p.name || '';
      var sel = String(row.productId) === String(pid) ? ' selected' : '';
      opts += '<option value="' + pid + '"' + sel + '>' + escHtml(pname) + '</option>';
    });

    // Type of cost options
    var typeOpts = buildEnumOpts([
      { id: '207', label: 'Government Cost' },
      { id: '209', label: 'Professional Fees' }
    ], row.typeOfCost);

    // Payments options
    var payOpts = buildEnumOpts([
      { id: '193', label: 'Annually' },
      { id: '195', label: 'One Time' },
      { id: '197', label: 'Quarterly' },
      { id: '199', label: 'Every 2 years' },
      { id: '201', label: 'Monthly' },
      { id: '203', label: 'In The Order Of Discussion' },
      { id: '205', label: 'One time (variable)' }
    ], row.payments);

    var amount = (row.price * row.qty).toFixed(2);

    tr.innerHTML = [
      '<td>',
        '<div class="row-number">',
          '<span class="drag-handle">⠿</span>',
          '<span>' + num + '</span>',
        '</div>',
      '</td>',
      '<td>',
        '<select class="input-bx select-bx js-product-select" style="min-width:160px">',
          opts,
        '</select>',
        '<input type="text" class="input-bx js-product-name" placeholder="Product name"',
          ' value="' + escHtml(row.name) + '" style="margin-top:4px">',
      '</td>',
      '<td>',
        '<div class="img-placeholder" title="Image">+</div>',
      '</td>',
      '<td>',
        '<select class="input-bx select-bx js-type-of-cost">',
          typeOpts,
        '</select>',
      '</td>',
      '<td>',
        '<div class="input-bx-wrapper">',
          '<input type="number" class="input-bx input-bx-with-suffix js-price" min="0" step="0.01"',
            ' value="' + row.price + '">',
          '<span class="input-bx-suffix">Dh</span>',
        '</div>',
      '</td>',
      '<td>',
        '<select class="input-bx select-bx js-payments">',
          payOpts,
        '</select>',
      '</td>',
      '<td>',
        '<div class="input-bx-wrapper">',
          '<input type="number" class="input-bx input-bx-with-suffix js-tax" min="0" max="100" step="0.01"',
            ' value="' + row.taxRate + '">',
          '<span class="input-bx-suffix">%</span>',
        '</div>',
      '</td>',
      '<td>',
        '<div class="input-bx-wrapper">',
          '<span class="input-bx-suffix" style="right:auto;left:10px;pointer-events:none">Dh</span>',
          '<input type="number" class="input-bx js-amount" readonly',
            ' value="' + amount + '" style="padding-left:32px;background:#f7f9fa">',
        '</div>',
      '</td>',
      '<td>',
        '<button class="btn-delete js-delete-row" title="Remove">✕</button>',
      '</td>'
    ].join('');

    // Bind row-level events
    tr.querySelector('.js-product-select').addEventListener('change', function (e) {
      onProductSelect(row.id, e.target.value);
    });
    tr.querySelector('.js-product-name').addEventListener('input', function (e) {
      updateRowField(row.id, 'name', e.target.value);
    });
    tr.querySelector('.js-type-of-cost').addEventListener('change', function (e) {
      updateRowField(row.id, 'typeOfCost', e.target.value);
    });
    tr.querySelector('.js-payments').addEventListener('change', function (e) {
      updateRowField(row.id, 'payments', e.target.value);
    });
    tr.querySelector('.js-price').addEventListener('input', function (e) {
      updateRowField(row.id, 'price', parseFloat(e.target.value) || 0);
      recalcRowAmount(row.id);
    });
    tr.querySelector('.js-tax').addEventListener('input', function (e) {
      updateRowField(row.id, 'taxRate', parseFloat(e.target.value) || 0);
      recalcRowAmount(row.id);
    });
    tr.querySelector('.js-delete-row').addEventListener('click', function () {
      deleteRow(row.id);
    });

    return tr;
  }

  function buildEnumOpts(items, currentVal) {
    var html = '<option value="">-- select --</option>';
    
    // Normalize current value
    var normalizedCurrent = String(currentVal || '').trim();
    
    items.forEach(function (item) {
      var itemId = String(item.id).trim();
      var itemLabel = String(item.label).trim();
      
      // Match by ID (most reliable)
      var isSelected = (itemId === normalizedCurrent);
      
      var sel = isSelected ? ' selected' : '';
      html += '<option value="' + itemId + '"' + sel + '>' + escHtml(itemLabel) + '</option>';
    });
    
    return html;
  }

  // ─── Row mutations ─────────────────────────────────────────────────────────
  function onProductSelect(rowId, productId) {
    var catalogItem = findCatalogProduct(productId);
    var row = findRow(rowId);
    if (!row) return;

    row.productId = productId;
    if (catalogItem) {
      row.name       = catalogItem.NAME || catalogItem.name || '';
      row.price      = parseFloat(catalogItem.PRICE || catalogItem.price || 0);
      row.typeOfCost = String(getPropValue(catalogItem, 'PROPERTY_111'));
      row.payments   = String(getPropValue(catalogItem, 'PROPERTY_109'));
    }
    
    var tr = document.querySelector('tr[data-row-id="' + rowId + '"]');
    if (tr) {
      var newTr = buildRowEl(row, getRowIndex(rowId) + 1);
      tr.parentNode.replaceChild(newTr, tr);
    }
    recalcTotals();
  }

  function updateRowField(rowId, field, value) {
    var row = findRow(rowId);
    if (row) row[field] = value;
  }

  function recalcRowAmount(rowId) {
    var row = findRow(rowId);
    if (!row) return;
    var amount = row.price * row.qty;
    var tr = document.querySelector('tr[data-row-id="' + rowId + '"]');
    if (tr) {
      var amtInput = tr.querySelector('.js-amount');
      if (amtInput) amtInput.value = amount.toFixed(2);
    }
    recalcTotals();
  }

  function deleteRow(rowId) {
    state.rows = state.rows.filter(function (r) { return r.id !== rowId; });
    var tr = document.querySelector('tr[data-row-id="' + rowId + '"]');
    if (tr) tr.remove();
    
    var rows = document.querySelectorAll('#product-rows-body tr');
    rows.forEach(function (tr2, i) {
      var numEl = tr2.querySelector('.row-number span:last-child');
      if (numEl) numEl.textContent = i + 1;
    });
    recalcTotals();
  }

  function findRow(rowId) {
    return state.rows.find(function (r) { return r.id === rowId; }) || null;
  }

  function getRowIndex(rowId) {
    return state.rows.findIndex(function (r) { return r.id === rowId; });
  }

  // ─── Add row ───────────────────────────────────────────────────────────────
  function addEmptyRow() {
    var row = {
      id:         state.nextRowId++,
      productId:  '',
      name:       '',
      price:      0,
      qty:        1,
      taxRate:    0,
      taxIncluded: false,
      typeOfCost: '',
      payments:   '',
      sort:       state.rows.length * 10,
      spaId:      null
    };
    state.rows.push(row);
    var tbody = document.getElementById('product-rows-body');
    if (tbody) tbody.appendChild(buildRowEl(row, state.rows.length));
    recalcTotals();
  }

  // ─── Product picker modal ──────────────────────────────────────────────────
  function openProductSelector() {
    showProductPickerModal();
  }

  function showProductPickerModal() {
    var existing = document.getElementById('product-picker-modal');
    if (existing) existing.remove();

    var overlay = document.createElement('div');
    overlay.id = 'product-picker-modal';
    overlay.style.cssText = [
      'position:fixed;top:0;left:0;right:0;bottom:0;',
      'background:rgba(0,0,0,0.45);z-index:9999;',
      'display:flex;align-items:center;justify-content:center;'
    ].join('');

    var modal = document.createElement('div');
    modal.style.cssText = [
      'background:#fff;border-radius:8px;padding:24px;',
      'width:680px;max-width:95vw;max-height:80vh;',
      'display:flex;flex-direction:column;gap:12px;',
      'box-shadow:0 8px 32px rgba(0,0,0,0.18);',
      'font-family:"Open Sans",Arial,sans-serif;font-size:13px;'
    ].join('');

    var header = document.createElement('div');
    header.style.cssText = 'display:flex;justify-content:space-between;align-items:center;';
    header.innerHTML = [
      '<strong style="font-size:15px;color:#333">Select Product from Catalog</strong>',
      '<button id="picker-close" style="background:none;border:none;font-size:20px;cursor:pointer;color:#999">✕</button>'
    ].join('');

    var searchBox = document.createElement('input');
    searchBox.type = 'text';
    searchBox.placeholder = 'Search products…';
    searchBox.className = 'input-bx';

    var filterBar = document.createElement('div');
    filterBar.style.cssText = 'display:flex;gap:8px;';
    filterBar.innerHTML = [
      '<select id="picker-filter-type" class="input-bx select-bx" style="flex:1">',
        '<option value="">All Types of Cost</option>',
        '<option value="207">Government Cost</option>',
        '<option value="209">Professional Fees</option>',
      '</select>',
      '<select id="picker-filter-payments" class="input-bx select-bx" style="flex:1">',
        '<option value="">All Payment Types</option>',
        '<option value="193">Annually</option>',
        '<option value="195">One Time</option>',
        '<option value="197">Quarterly</option>',
        '<option value="199">Every 2 years</option>',
        '<option value="201">Monthly</option>',
      '</select>'
    ].join('');

    var tableWrap = document.createElement('div');
    tableWrap.style.cssText = 'overflow-y:auto;flex:1;border:1px solid #e2e5ec;border-radius:4px;';

    var table = document.createElement('table');
    table.style.cssText = 'width:100%;border-collapse:collapse;font-size:12px;';
    table.innerHTML = [
      '<thead>',
        '<tr style="background:#f7f9fa;position:sticky;top:0">',
          '<th style="padding:8px 10px;text-align:left;color:#828b95;font-weight:600;border-bottom:1px solid #e2e5ec;width:36px"></th>',
          '<th style="padding:8px 10px;text-align:left;color:#828b95;font-weight:600;border-bottom:1px solid #e2e5ec">Name</th>',
          '<th style="padding:8px 10px;text-align:left;color:#828b95;font-weight:600;border-bottom:1px solid #e2e5ec;width:100px">Price</th>',
          '<th style="padding:8px 10px;text-align:left;color:#828b95;font-weight:600;border-bottom:1px solid #e2e5ec;width:130px">Type of Cost</th>',
          '<th style="padding:8px 10px;text-align:left;color:#828b95;font-weight:600;border-bottom:1px solid #e2e5ec;width:100px">Payments</th>',
        '</tr>',
      '</thead>',
      '<tbody id="picker-tbody"></tbody>'
    ].join('');
    tableWrap.appendChild(table);

    var footer = document.createElement('div');
    footer.style.cssText = 'display:flex;justify-content:flex-end;gap:8px;padding-top:8px;border-top:1px solid #e2e5ec;';
    footer.innerHTML = [
      '<button id="picker-add-selected" class="btn-primary-bx" disabled>Add Selected</button>',
      '<button id="picker-cancel" class="btn-secondary-bx">Cancel</button>'
    ].join('');

    modal.appendChild(header);
    modal.appendChild(searchBox);
    modal.appendChild(filterBar);
    modal.appendChild(tableWrap);
    modal.appendChild(footer);
    overlay.appendChild(modal);
    document.body.appendChild(overlay);

    var selected = {};

    function renderPickerRows() {
      var query = searchBox.value.toLowerCase();
      var filterType = document.getElementById('picker-filter-type').value;
      var filterPay  = document.getElementById('picker-filter-payments').value;
      var tbody2 = document.getElementById('picker-tbody');
      tbody2.innerHTML = '';

      var filtered = state.productCatalog.filter(function (p) {
        var name = (p.NAME || p.name || '').toLowerCase();
        var toc  = String(getPropValue(p, 'PROPERTY_111') || '');
        var pay  = String(getPropValue(p, 'PROPERTY_109') || '');
        if (query && name.indexOf(query) === -1) return false;
        if (filterType && toc !== filterType) return false;
        if (filterPay  && pay !== filterPay)  return false;
        return true;
      });

      if (filtered.length === 0) {
        tbody2.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:20px;color:#a8adb2">No products found</td></tr>';
        return;
      }

      filtered.forEach(function (p) {
        var pid  = String(p.ID || p.id);
        var pname = p.NAME || p.name || '';
        var price = parseFloat(p.PRICE || p.price || 0).toFixed(2);
        var toc   = PROP_TYPE_OF_COST[getPropValue(p, 'PROPERTY_111')] || '—';
        var pay   = PROP_PAYMENTS[getPropValue(p, 'PROPERTY_109')] || '—';
        var chk   = selected[pid] ? 'checked' : '';

        var tr2 = document.createElement('tr');
        tr2.style.cssText = 'border-bottom:1px solid #eef2f4;cursor:pointer;' + (selected[pid] ? 'background:#f0f8ff;' : '');
        tr2.innerHTML = [
          '<td style="padding:8px 10px;text-align:center">',
            '<input type="checkbox" ' + chk + ' data-pid="' + pid + '">',
          '</td>',
          '<td style="padding:8px 10px;font-weight:600;color:#333">' + escHtml(pname) + '</td>',
          '<td style="padding:8px 10px;color:#535c69">Dh ' + price + '</td>',
          '<td style="padding:8px 10px;color:#535c69">' + escHtml(toc) + '</td>',
          '<td style="padding:8px 10px;color:#535c69">' + escHtml(pay) + '</td>',
        ].join('');

        tr2.querySelector('input[type=checkbox]').addEventListener('change', function (e) {
          if (e.target.checked) {
            selected[pid] = p;
            tr2.style.background = '#f0f8ff';
          } else {
            delete selected[pid];
            tr2.style.background = '';
          }
          document.getElementById('picker-add-selected').disabled = Object.keys(selected).length === 0;
        });
        tr2.addEventListener('click', function (e) {
          if (e.target.tagName === 'INPUT') return;
          var cb = tr2.querySelector('input[type=checkbox]');
          cb.checked = !cb.checked;
          cb.dispatchEvent(new Event('change'));
        });

        tbody2.appendChild(tr2);
      });
    }

    renderPickerRows();

    searchBox.addEventListener('input', renderPickerRows);
    document.getElementById('picker-filter-type').addEventListener('change', renderPickerRows);
    document.getElementById('picker-filter-payments').addEventListener('change', renderPickerRows);

    document.getElementById('picker-close').addEventListener('click', function () { overlay.remove(); });
    document.getElementById('picker-cancel').addEventListener('click', function () { overlay.remove(); });

    document.getElementById('picker-add-selected').addEventListener('click', function () {
      Object.values(selected).forEach(function (p) {
        var row = {
          id:         state.nextRowId++,
          productId:  String(p.ID || p.id),
          name:       p.NAME || p.name || '',
          price:      parseFloat(p.PRICE || p.price || 0),
          qty:        1,
          taxRate:    0,
          taxIncluded: false,
          typeOfCost: getPropValue(p, 'PROPERTY_111'),
          payments:   getPropValue(p, 'PROPERTY_109'),
          sort:       state.rows.length * 10,
          spaId:      null
        };
        state.rows.push(row);
        var tbody3 = document.getElementById('product-rows-body');
        if (tbody3) tbody3.appendChild(buildRowEl(row, state.rows.length));
      });
      recalcTotals();
      overlay.remove();
    });

    overlay.addEventListener('click', function (e) {
      if (e.target === overlay) overlay.remove();
    });
  }

  // ─── Totals ────────────────────────────────────────────────────────────────
  function recalcTotals() {
    var trs = document.querySelectorAll('#product-rows-body tr');
    trs.forEach(function (tr2) {
      var rowId = parseInt(tr2.getAttribute('data-row-id'));
      var row = findRow(rowId);
      if (!row) return;
      var priceEl = tr2.querySelector('.js-price');
      var taxEl   = tr2.querySelector('.js-tax');
      var nameEl  = tr2.querySelector('.js-product-name');
      var tocEl   = tr2.querySelector('.js-type-of-cost');
      var payEl   = tr2.querySelector('.js-payments');
      var selEl   = tr2.querySelector('.js-product-select');

      if (priceEl) row.price = parseFloat(priceEl.value) || 0;
      if (taxEl)   row.taxRate = parseFloat(taxEl.value) || 0;
      if (nameEl)  row.name = nameEl.value;
      if (tocEl)   row.typeOfCost = tocEl.value;
      if (payEl)   row.payments = payEl.value;
      if (selEl)   row.productId = selEl.value;

      var amt = row.price * row.qty;
      var amtEl = tr2.querySelector('.js-amount');
      if (amtEl) amtEl.value = amt.toFixed(2);
    });

    var raw = 0, taxTotal = 0;
    state.rows.forEach(function (r) {
      var base = r.price * r.qty;
      raw += base;
      taxTotal += base * (r.taxRate / 100);
    });

    setText('total-raw',        'Dh ' + raw.toFixed(2));
    setText('total-before-tax', 'Dh ' + raw.toFixed(2));
    setText('total-tax',        'Dh ' + taxTotal.toFixed(2));
    setText('total-amount',     'Dh ' + (raw + taxTotal).toFixed(2));
  }

  function setText(id, val) {
    var el = document.getElementById(id);
    if (el) el.textContent = val;
  }

  // ─── Save & Sync ───────────────────────────────────────────────────────────
  function saveAndSync() {
    setStatus('Saving…', 'status-info');
    recalcTotals();

    var rows = collectRowData();
    if (rows.length === 0) {
      setStatus('No products to save', 'status-warning');
      return;
    }

    log('Saving ' + rows.length + ' product row(s) to ' + state.entityType + ' #' + state.entityId);

    var method = state.entityType === 'deal'
      ? 'crm.deal.productrows.set'
      : 'crm.lead.productrows.set';

    var productRows = rows.map(function (r, idx) {
      return {
        PRODUCT_ID:   r.productId || 0,
        PRODUCT_NAME: r.name,
        PRICE:        r.price,
        QUANTITY:     r.qty,
        TAX_RATE:     r.taxRate,
        TAX_INCLUDED: r.taxIncluded ? 'Y' : 'N',
        SORT:         (idx + 1) * 10
      };
    });

    BX24.callMethod(method, {
      id:   state.entityId,
      rows: productRows
    }, function (res) {
      if (res.error()) {
        setStatus('Error saving products: ' + res.error(), 'status-danger');
        log('Error: ' + res.error());
        return;
      }

      log('Product rows saved OK');

      var totalAmt = rows.reduce(function (sum, r) { return sum + r.price * r.qty; }, 0);
      updateEntityOpportunity(totalAmt, function () {
        syncSpaItems(rows, function () {
          setStatus('Saved & synced ✓', 'status-success');
          log('All done');
        });
      });
    });
  }

  function collectRowData() {
    recalcTotals();
    return state.rows.filter(function (r) { return r.name || r.productId; });
  }

  function updateEntityOpportunity(amount, cb) {
    var method = state.entityType === 'deal' ? 'crm.deal.update' : 'crm.lead.update';
    BX24.callMethod(method, {
      id:     state.entityId,
      fields: { OPPORTUNITY: amount, IS_MANUAL_OPPORTUNITY: 'Y' }
    }, function (res) {
      if (res.error()) log('Opportunity update error: ' + res.error());
      else log('Opportunity updated to ' + amount.toFixed(2));
      if (cb) cb();
    });
  }

  // ─── SPA Item Sync ─────────────────────────────────────────────────────────
  function syncSpaItems(rows, cb) {
    var profRows = rows.filter(function (r) { return r.typeOfCost === '209'; });
    var govRows  = rows.filter(function (r) { return r.typeOfCost === '207'; });

    log('Syncing ' + profRows.length + ' Prof Fee SPA items, ' + govRows.length + ' Gov Cost SPA items');

    syncSpaGroup(1058, SPA_PROF_FIELDS, DEAL_PROF_FIELD, profRows, function () {
      syncSpaGroup(1062, SPA_GOV_FIELDS, DEAL_GOV_FIELD, govRows, cb);
    });
  }

  function syncSpaGroup(entityTypeId, fieldMap, dealLinkField, rows, cb) {
    if (rows.length === 0) { if (cb) cb(); return; }

    var spaIds = [];
    var pending = rows.length;

    rows.forEach(function (row) {
      var fields = buildSpaFields(row, fieldMap);

      if (row.spaId) {
        BX24.callMethod('crm.item.update', {
          entityTypeId: entityTypeId,
          id:           row.spaId,
          fields:       fields
        }, function (res) {
          if (res.error()) log('SPA update error: ' + res.error());
          else {
            log('SPA item #' + row.spaId + ' updated (type ' + entityTypeId + ')');
            spaIds.push(row.spaId);
          }
          if (--pending === 0) linkSpaToEntity(entityTypeId, spaIds, dealLinkField, cb);
        });
      } else {
        BX24.callMethod('crm.item.add', {
          entityTypeId: entityTypeId,
          fields:       Object.assign({ TITLE: row.name, OPPORTUNITY: row.price }, fields)
        }, function (res) {
          if (res.error()) {
            log('SPA create error: ' + res.error());
          } else {
            var newId = (res.data() && res.data().item) ? res.data().item.id : null;
            if (newId) {
              row.spaId = newId;
              spaIds.push(newId);
              log('SPA item created #' + newId + ' (type ' + entityTypeId + ')');
            }
          }
          if (--pending === 0) linkSpaToEntity(entityTypeId, spaIds, dealLinkField, cb);
        });
      }
    });
  }

  function buildSpaFields(row, fieldMap) {
    var fields = {};
    if (row.typeOfCost) {
      fields[fieldMap.typeOfCost] = mapSpaEnumValue(fieldMap.typeOfCost, PROP_TYPE_OF_COST[row.typeOfCost]);
    }
    if (row.payments) {
      fields[fieldMap.payments] = mapSpaEnumValue(fieldMap.payments, PROP_PAYMENTS[row.payments]);
    }
    return fields;
  }

  function mapSpaEnumValue(fieldKey, labelValue) {
    var spaEnumMaps = {
      'ufCrm15_1779367818775': { 'Professional Fees': '445', 'Government Cost': '447' },
      'ufCrm15_1779367955682': {
        'Annually': '449', 'One Time': '451', 'Quarterly': '453',
        'Every 2 years': '455', 'Monthly': '457',
        'In The Order Of Discussion': '459',
        'One time (the cost depends on the number of transactions)': '461'
      },
      'ufCrm17_1779370162991': { 'Government Cost': '497', 'Professional Fees': '499' },
      'ufCrm17_1779370261982': {
        'Annually': '501', 'One Time': '503', 'Quarterly': '505',
        'Every 2 years': '507', 'Monthly': '509',
        'In The Order Of Discussion': '511',
        'One time (the cost depends on the number of transactions)': '513'
      }
    };
    var map = spaEnumMaps[fieldKey];
    if (!map || !labelValue) return undefined;
    return map[labelValue] || undefined;
  }

  function linkSpaToEntity(entityTypeId, spaIds, dealLinkField, cb) {
    if (!dealLinkField || spaIds.length === 0 || state.entityType !== 'deal') {
      if (cb) cb(); return;
    }
    var updateFields = {};
    updateFields[dealLinkField] = spaIds;
    BX24.callMethod('crm.deal.update', {
      id:     state.entityId,
      fields: updateFields
    }, function (res) {
      if (res.error()) log('SPA link error: ' + res.error());
      else log('Deal linked to ' + spaIds.length + ' SPA items (type ' + entityTypeId + ')');
      if (cb) cb();
    });
  }

  // ─── Product Edit/Create Modal ─────────────────────────────────────────────
  function showProductEditModal(productId) {
    var product = productId ? findCatalogProduct(productId) : null;
    var isNew = !product;

    var overlay = document.createElement('div');
    overlay.id = 'product-edit-modal';
    overlay.style.cssText = [
      'position:fixed;top:0;left:0;right:0;bottom:0;',
      'background:rgba(0,0,0,0.45);z-index:10000;',
      'display:flex;align-items:center;justify-content:center;'
    ].join('');

    var modal = document.createElement('div');
    modal.style.cssText = [
      'background:#fff;border-radius:8px;padding:24px;',
      'width:520px;max-width:95vw;',
      'box-shadow:0 8px 32px rgba(0,0,0,0.18);',
      'font-family:"Open Sans",Arial,sans-serif;font-size:13px;',
      'display:flex;flex-direction:column;gap:14px;'
    ].join('');

    var title = isNew ? 'Create New Product' : 'Edit Product';

    modal.innerHTML = [
      '<div style="display:flex;justify-content:space-between;align-items:center">',
        '<strong style="font-size:15px;color:#333">' + title + '</strong>',
        '<button id="edit-close" style="background:none;border:none;font-size:20px;cursor:pointer;color:#999">✕</button>',
      '</div>',

      '<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">',

        '<div style="display:flex;flex-direction:column;gap:4px;grid-column:span 2">',
          '<label style="font-weight:600;color:#535c69">Product Name *</label>',
          '<input id="ep-name" class="input-bx" value="' + escHtml(product ? (product.NAME || product.name || '') : '') + '">',
        '</div>',

        '<div style="display:flex;flex-direction:column;gap:4px">',
          '<label style="font-weight:600;color:#535c69">Price (Dh)</label>',
          '<input id="ep-price" type="number" min="0" step="0.01" class="input-bx"',
            ' value="' + (product ? parseFloat(product.PRICE || product.price || 0) : 0) + '">',
        '</div>',

        '<div style="display:flex;flex-direction:column;gap:4px">',
          '<label style="font-weight:600;color:#535c69">Type of Cost</label>',
          '<select id="ep-type-of-cost" class="input-bx select-bx">',
            buildEnumOpts([
              { id: '207', label: 'Government Cost' },
              { id: '209', label: 'Professional Fees' }
            ], product ? getPropValue(product, 'PROPERTY_111') : ''),
          '</select>',
        '</div>',

        '<div style="display:flex;flex-direction:column;gap:4px">',
          '<label style="font-weight:600;color:#535c69">Payments</label>',
          '<select id="ep-payments" class="input-bx select-bx">',
            buildEnumOpts([
              { id: '193', label: 'Annually' },
              { id: '195', label: 'One Time' },
              { id: '197', label: 'Quarterly' },
              { id: '199', label: 'Every 2 years' },
              { id: '201', label: 'Monthly' },
              { id: '203', label: 'In The Order Of Discussion' },
              { id: '205', label: 'One time (variable)' }
            ], product ? getPropValue(product, 'PROPERTY_109') : ''),
          '</select>',
        '</div>',

        '<div style="display:flex;flex-direction:column;gap:4px">',
          '<label style="font-weight:600;color:#535c69">Company Application Type</label>',
          '<select id="ep-company-type" class="input-bx select-bx">',
            buildEnumOpts([
              { id: '155', label: 'Mainland LLC' },
              { id: '157', label: 'Free Zone' },
              { id: '159', label: 'Branch DET' },
              { id: '161', label: 'Branch FZ' },
              { id: '163', label: 'Representative Office DET' },
              { id: '165', label: 'Representative Office FZ' },
              { id: '167', label: 'Freelance License' }
            ], product ? getPropValue(product, 'PROPERTY_99') : ''),
          '</select>',
        '</div>',

        '<div style="display:flex;flex-direction:column;gap:4px">',
          '<label style="font-weight:600;color:#535c69">Visa Type</label>',
          '<select id="ep-visa-type" class="input-bx select-bx">',
            buildEnumOpts([
              { id: '171', label: 'Investor Visa' },
              { id: '173', label: 'Employment Visa' },
              { id: '175', label: 'Golden Visa' },
              { id: '177', label: 'Property Visa' },
              { id: '179', label: 'Talent Visa' },
              { id: '181', label: 'Influencer Visa' },
              { id: '183', label: 'Family Visa' }
            ], product ? getPropValue(product, 'PROPERTY_101') : ''),
          '</select>',
        '</div>',

        '<div style="display:flex;flex-direction:column;gap:4px">',
          '<label style="font-weight:600;color:#535c69">Visa Status</label>',
          '<select id="ep-visa-status" class="input-bx select-bx">',
            buildEnumOpts([
              { id: '187', label: 'New' },
              { id: '189', label: 'Renewal' },
              { id: '191', label: 'Not Specified' }
            ], product ? getPropValue(product, 'PROPERTY_103') : ''),
          '</select>',
        '</div>',

      '</div>',

      '<div style="display:flex;justify-content:flex-end;gap:8px;padding-top:8px;border-top:1px solid #e2e5ec">',
        '<button id="ep-save" class="btn-primary-bx">' + (isNew ? 'Create Product' : 'Save Changes') + '</button>',
        '<button id="ep-cancel" class="btn-secondary-bx">Cancel</button>',
      '</div>'
    ].join('');

    overlay.appendChild(modal);
    document.body.appendChild(overlay);

    document.getElementById('edit-close').addEventListener('click', function () { overlay.remove(); });
    document.getElementById('ep-cancel').addEventListener('click', function () { overlay.remove(); });
    overlay.addEventListener('click', function (e) { if (e.target === overlay) overlay.remove(); });

    document.getElementById('ep-save').addEventListener('click', function () {
      var name = document.getElementById('ep-name').value.trim();
      if (!name) { alert('Product name is required'); return; }

      var fields = {
        NAME:        name,
        PRICE:       parseFloat(document.getElementById('ep-price').value) || 0,
        CURRENCY_ID: 'AED',
        ACTIVE:      'Y'
      };

      var propFields = {};
      var toc  = document.getElementById('ep-type-of-cost').value;
      var pay  = document.getElementById('ep-payments').value;
      var ct   = document.getElementById('ep-company-type').value;
      var vt   = document.getElementById('ep-visa-type').value;
      var vs   = document.getElementById('ep-visa-status').value;

      if (toc) propFields['PROPERTY_111'] = toc;
      if (pay) propFields['PROPERTY_109'] = pay;
      if (ct)  propFields['PROPERTY_99']  = ct;
      if (vt)  propFields['PROPERTY_101'] = vt;
      if (vs)  propFields['PROPERTY_103'] = vs;

      var btn = document.getElementById('ep-save');
      btn.disabled = true;
      btn.textContent = 'Saving…';

      if (isNew) {
        BX24.callMethod('crm.product.add', { fields: Object.assign(fields, propFields) }, function (res) {
          if (res.error()) {
            alert('Error creating product: ' + res.error());
            btn.disabled = false;
            btn.textContent = 'Create Product';
            return;
          }
          var newId = res.data();
          log('Product created #' + newId);
          loadCatalogProducts(function () {
            overlay.remove();
            var newP = findCatalogProduct(newId);
            if (newP) {
              var row = {
                id: state.nextRowId++,
                productId: String(newId),
                name: name,
                price: fields.PRICE,
                qty: 1,
                taxRate: 0,
                taxIncluded: false,
                typeOfCost: toc,
                payments: pay,
                sort: state.rows.length * 10,
                spaId: null
              };
              state.rows.push(row);
              var tbody4 = document.getElementById('product-rows-body');
              if (tbody4) tbody4.appendChild(buildRowEl(row, state.rows.length));
              recalcTotals();
            }
          });
        });
      } else {
        BX24.callMethod('crm.product.update', {
          id:     productId,
          fields: Object.assign(fields, propFields)
        }, function (res) {
          if (res.error()) {
            alert('Error updating product: ' + res.error());
            btn.disabled = false;
            btn.textContent = 'Save Changes';
            return;
          }
          log('Product #' + productId + ' updated');
          loadCatalogProducts(function () {
            overlay.remove();
          });
        });
      }
    });
  }

  // ─── Bind top-level actions ────────────────────────────────────────────────
  function bindActions() {
    function rebind(id, handler) {
      var el = document.getElementById(id);
      if (!el) return;
      var clone = el.cloneNode(true);
      el.parentNode.replaceChild(clone, el);
      clone.addEventListener('click', handler);
    }

    rebind('btn-add-product',    addEmptyRow);
    rebind('btn-select-product', openProductSelector);
    rebind('btn-save',           saveAndSync);
    rebind('btn-edit-product',   function () {
      var activeSelect = document.querySelector('#product-rows-body tr:focus-within .js-product-select')
                      || document.querySelector('#product-rows-body .js-product-select');
      var pid = activeSelect ? activeSelect.value : null;
      showProductEditModal(pid || null);
    });
  }

  // ─── Helpers ───────────────────────────────────────────────────────────────
  function escHtml(s) {
    return String(s || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  // ─── Public API ────────────────────────────────────────────────────────────
  return {
    init:               init,
    addRow:             addEmptyRow,
    openSelector:       openProductSelector,
    saveAndSync:        saveAndSync,
    showProductModal:   showProductEditModal
  };

})();