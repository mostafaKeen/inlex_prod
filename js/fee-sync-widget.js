/**
 * Fee Sync Widget — Bitrix24 CRM Product Grid (v6 - SIMPLIFIED)
 * Supports: Deal & Lead entities
 * SPA Item Types: 1058 (Professional Fees), 1062 (Government Fees)
 *
 * CHANGES IN v6:
 * ─────────────────────────────────────────────────────────────────────────────
 * 1. AUTO-ENTITY DETECTION: No entity selector dropdown. Auto-loads from context.
 * 2. NO PRODUCT SELECTOR: Removed dropdown to pick from catalog per row.
 *    Users edit product name/details directly or use "Add Product" / "Select from Catalog"
 * 3. DRAG-AND-DROP REORDERING: Click-and-drag rows to reorder. Save updates SORT field.
 * ─────────────────────────────────────────────────────────────────────────────
 */

var FeeSyncWidget = (function () {

  // ─── SPA Field Maps ────────────────────────────────────────────────────────

  var SPA_PROF_FIELDS = {
    typeOfCost:  'ufCrm15_1779367818775',
    payments:    'ufCrm15_1779367955682',
    companyType: 'ufCrm15_1779368170455',
    visaType:    'ufCrm15_1779368285728',
    visaStatus:  'ufCrm15_1779368405816',
  };

  var SPA_GOV_FIELDS = {
    typeOfCost:  'ufCrm17_1779370162991',
    payments:    'ufCrm17_1779370261982',
    companyType: 'ufCrm17_1779370566902',
    visaType:    'ufCrm17_1779370435095',
    visaStatus:  'ufCrm17_1779370325590',
  };

  var DEAL_PROF_FIELD = 'UF_CRM_1779313011';
  var DEAL_GOV_FIELD  = 'UF_CRM_1779654189';
  var LEAD_PROF_FIELD = 'UF_CRM_1779194029';
  var LEAD_GOV_FIELD  = null;

  var PROP_TYPE_OF_COST = { '207': 'Government Cost', '209': 'Professional Fees' };
  var PROP_PAYMENTS = {
    '193': 'Annually', '195': 'One Time', '197': 'Quarterly',
    '199': 'Every 2 years', '201': 'Monthly',
    '203': 'In The Order Of Discussion',
    '205': 'One time (cost depends on transactions)'
  };

  // ─── State ─────────────────────────────────────────────────────────────────
  var state = {
    entityType:     null,
    entityId:       null,
    rows:           [],
    nextRowId:      1,
    productCatalog: [],
    iblockId:       null,
    spaItems:       { prof: {}, gov: {} },
    draggedRow:     null,
    draggedIndex:   null
  };

  // ─── Logging ───────────────────────────────────────────────────────────────
  function log(msg) {
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
    state.entityType     = entityType;
    state.entityId       = entityId;
    state.rows           = [];
    state.nextRowId      = 1;
    state.productCatalog = [];
    state.iblockId       = null;
    state.spaItems       = { prof: {}, gov: {} };
    state.draggedRow     = null;
    state.draggedIndex   = null;

    setStatus('Loading…', 'status-info');
    log('Initialising for ' + entityType + ' #' + entityId);

    fetchIblockId(function () {
      loadCatalogProducts(function () {
        loadEntityProducts(function () {
          renderRows();
          bindActions();
          setStatus('Ready', 'status-info');
          if (typeof onReady === 'function') onReady();
        });
      });
    });
  }

  // ─── Fetch iblock ID ────────────────────────────────────────────────────────
  function fetchIblockId(cb) {
    BX24.callMethod('catalog.catalog.list', { select: ['ID', 'IBLOCK_TYPE_ID'] }, function (res) {
      if (res.error()) {
        log('catalog.catalog.list error: ' + res.error());
        if (cb) cb();
        return;
      }
      var catalogs = res.data() || [];
      var catalog = catalogs[0] || null;
      if (catalog) {
        state.iblockId = catalog.id || catalog.ID || null;
        log('Catalog iblockId = ' + state.iblockId);
      }
      if (cb) cb();
    });
  }

  // ─── Load catalog products ──────────────────────────────────────────────────
  function loadCatalogProducts(cb) {
    var allProducts = [];

    BX24.callMethod('crm.product.list', {
      select: ['ID', 'NAME', 'PRICE', 'CURRENCY_ID', 'ACTIVE',
               'PROPERTY_111', 'PROPERTY_109', 'PROPERTY_99',
               'PROPERTY_101', 'PROPERTY_103', 'PROPERTY_119'],
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

  // ─── Load existing entity product rows ────────────────────────────────────
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

  function buildRowFromEntityRow(r) {
    var catalogItem = findCatalogProduct(r.PRODUCT_ID);
    var typeOfCost  = getPropValue(catalogItem, 'PROPERTY_111') || '';
    var payments    = getPropValue(catalogItem, 'PROPERTY_109') || '';
    var option      = getPropValue(catalogItem, 'PROPERTY_119') || '';

    var row = {
      id:          state.nextRowId++,
      productId:   r.PRODUCT_ID || '',
      name:        r.PRODUCT_NAME || (catalogItem ? (catalogItem.NAME || '') : ''),
      price:       parseFloat(r.PRICE    || 0),
      qty:         parseFloat(r.QUANTITY || 1),
      taxRate:     parseFloat(r.TAX_RATE || 0),
      taxIncluded: (r.TAX_INCLUDED === 'Y'),
      typeOfCost:  String(typeOfCost),
      payments:    String(payments),
      option:      String(option),
      sort:        parseInt(r.SORT || 0),
      spaId:       null,
      _entityRow:  r
    };

    log('Row #' + row.id + ': product=' + row.productId +
        ', name=' + row.name +
        ', typeOfCost=' + row.typeOfCost +
        ', payments=' + row.payments +
        ', option=' + row.option);

    return row;
  }

  function getPropValue(product, propKey) {
    if (!product) return '';
    var val = product[propKey];
    if (val === undefined || val === null || val === '') return '';
    if (Array.isArray(val)) {
      val = val[0];
      if (val === undefined || val === null) return '';
    }
    if (typeof val === 'object') {
      if (val.id    !== undefined) return String(val.id);
      if (val.ID    !== undefined) return String(val.ID);
      if (val.VALUE !== undefined) return String(val.VALUE);
      if (val.value !== undefined) return String(val.value);
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
      tbody.appendChild(buildRowEl(row, idx + 1, idx));
    });
    recalcTotals();
  }

  function buildRowEl(row, num, index) {
    var tr = document.createElement('tr');
    tr.setAttribute('data-row-id', row.id);
    tr.setAttribute('data-row-index', index);
    tr.draggable = true;
    tr.style.cursor = 'move';

    var typeOpts = buildEnumOpts([
      { id: '207', label: 'Government Cost' },
      { id: '209', label: 'Professional Fees' }
    ], row.typeOfCost);

    var optOpts = buildEnumOpts([
      { id: '235', label: 'Option 1' },
      { id: '237', label: 'Option 2' }
    ], row.option);

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
      '<td><div class="row-number"><span class="drag-handle">⠿</span><span>' + num + '</span></div></td>',
      '<td><input type="text" class="input-bx js-product-name" placeholder="Product name" value="' + escHtml(row.name) + '" style="width:100%"></td>',
      '<td><select class="input-bx select-bx js-option">' + optOpts + '</select></td>',
      '<td><select class="input-bx select-bx js-type-of-cost">' + typeOpts + '</select></td>',
      '<td><div class="input-bx-wrapper">',
        '<input type="number" class="input-bx input-bx-with-suffix js-price" min="0" step="0.01" value="' + row.price + '">',
        '<span class="input-bx-suffix">Dh</span></div></td>',
      '<td><select class="input-bx select-bx js-payments">' + payOpts + '</select></td>',
      '<td><div class="input-bx-wrapper">',
        '<input type="number" class="input-bx input-bx-with-suffix js-tax" min="0" max="100" step="0.01" value="' + row.taxRate + '">',
        '<span class="input-bx-suffix">%</span></div></td>',
      '<td><div class="input-bx-wrapper">',
        '<span class="input-bx-suffix" style="right:auto;left:10px;pointer-events:none">Dh</span>',
        '<input type="number" class="input-bx js-amount" readonly value="' + amount + '" style="padding-left:32px;background:#f7f9fa">',
      '</div></td>',
      '<td><button class="btn-delete js-delete-row" title="Remove">✕</button></td>'
    ].join('');

    tr.querySelector('.js-product-name').addEventListener('input', function (e) { updateRowField(row.id, 'name', e.target.value); });
    tr.querySelector('.js-option').addEventListener('change', function (e) { updateRowField(row.id, 'option', e.target.value); });
    tr.querySelector('.js-type-of-cost').addEventListener('change', function (e) { updateRowField(row.id, 'typeOfCost', e.target.value); });
    tr.querySelector('.js-payments').addEventListener('change', function (e) { updateRowField(row.id, 'payments', e.target.value); });
    tr.querySelector('.js-price').addEventListener('input', function (e) {
      updateRowField(row.id, 'price', parseFloat(e.target.value) || 0);
      recalcRowAmount(row.id);
    });
    tr.querySelector('.js-tax').addEventListener('input', function (e) {
      updateRowField(row.id, 'taxRate', parseFloat(e.target.value) || 0);
      recalcRowAmount(row.id);
    });
    tr.querySelector('.js-delete-row').addEventListener('click', function () { deleteRow(row.id); });

    // Drag-and-drop events
    tr.addEventListener('dragstart', function (e) {
      state.draggedRow = row.id;
      state.draggedIndex = index;
      tr.style.opacity = '0.6';
      tr.style.background = '#f0f8ff';
      e.dataTransfer.effectAllowed = 'move';
    });

    tr.addEventListener('dragend', function () {
      tr.style.opacity = '1';
      tr.style.background = '';
      state.draggedRow = null;
      state.draggedIndex = null;
      var allTrs = document.querySelectorAll('#product-rows-body tr');
      allTrs.forEach(function (t) {
        t.style.borderTop = '';
      });
    });

    tr.addEventListener('dragover', function (e) {
      e.preventDefault();
      e.dataTransfer.dropEffect = 'move';
      if (state.draggedIndex !== null && state.draggedIndex !== index) {
        tr.style.borderTop = '2px solid #0080ff';
      }
    });

    tr.addEventListener('dragleave', function () {
      tr.style.borderTop = '';
    });

    tr.addEventListener('drop', function (e) {
      e.preventDefault();
      if (state.draggedRow === null || state.draggedIndex === null) return;
      if (state.draggedIndex === index) return;

      // Reorder rows array
      var draggedRowObj = state.rows.find(function (r) { return r.id === state.draggedRow; });
      if (!draggedRowObj) return;

      state.rows.splice(state.draggedIndex, 1);
      state.rows.splice(index, 0, draggedRowObj);

      tr.style.borderTop = '';
      renderRows();
      log('Rows reordered: #' + state.draggedRow + ' moved to position ' + (index + 1));
    });

    return tr;
  }

  function buildEnumOpts(items, currentVal) {
    var html              = '<option value="">-- select --</option>';
    var normalizedCurrent = String(currentVal || '').trim();
    items.forEach(function (item) {
      var sel = (String(item.id).trim() === normalizedCurrent) ? ' selected' : '';
      html += '<option value="' + item.id + '"' + sel + '>' + escHtml(item.label) + '</option>';
    });
    return html;
  }

  // ─── Row mutations ─────────────────────────────────────────────────────────
  function updateRowField(rowId, field, value) {
    var row = findRow(rowId);
    if (row) row[field] = value;
  }

  function recalcRowAmount(rowId) {
    var row = findRow(rowId);
    if (!row) return;
    var tr = document.querySelector('tr[data-row-id="' + rowId + '"]');
    if (tr) {
      var amtInput = tr.querySelector('.js-amount');
      if (amtInput) amtInput.value = (row.price * row.qty).toFixed(2);
    }
    recalcTotals();
  }

  function deleteRow(rowId) {
    state.rows = state.rows.filter(function (r) { return r.id !== rowId; });
    var tr = document.querySelector('tr[data-row-id="' + rowId + '"]');
    if (tr) tr.remove();
    document.querySelectorAll('#product-rows-body tr').forEach(function (tr2, i) {
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

  // ─── Add empty row ─────────────────────────────────────────────────────────
  function addEmptyRow() {
    var row = {
      id: state.nextRowId++, productId: '', name: '',
      price: 0, qty: 1, taxRate: 0, taxIncluded: false,
      typeOfCost: '', payments: '', option: '',
      sort: state.rows.length * 10, spaId: null
    };
    state.rows.push(row);
    var tbody = document.getElementById('product-rows-body');
    if (tbody) tbody.appendChild(buildRowEl(row, state.rows.length, state.rows.length - 1));
    recalcTotals();
  }

  // ─── Product picker modal ──────────────────────────────────────────────────
  function openProductSelector() { showProductPickerModal(); }

  function showProductPickerModal() {
    var existing = document.getElementById('product-picker-modal');
    if (existing) existing.remove();

    var overlay = document.createElement('div');
    overlay.id = 'product-picker-modal';
    overlay.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.45);z-index:9999;display:flex;align-items:center;justify-content:center;';

    var modal = document.createElement('div');
    modal.style.cssText = 'background:#fff;border-radius:8px;padding:24px;width:680px;max-width:95vw;max-height:80vh;display:flex;flex-direction:column;gap:12px;box-shadow:0 8px 32px rgba(0,0,0,0.18);font-family:"Open Sans",Arial,sans-serif;font-size:13px;';

    var header = document.createElement('div');
    header.style.cssText = 'display:flex;justify-content:space-between;align-items:center;';
    header.innerHTML = '<strong style="font-size:15px;color:#333">Select Product from Catalog</strong><button id="picker-close" style="background:none;border:none;font-size:20px;cursor:pointer;color:#999">✕</button>';

    var searchBox = document.createElement('input');
    searchBox.type = 'text'; searchBox.placeholder = 'Search products…'; searchBox.className = 'input-bx';

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
        '<option value="193">Annually</option><option value="195">One Time</option>',
        '<option value="197">Quarterly</option><option value="199">Every 2 years</option>',
        '<option value="201">Monthly</option>',
      '</select>'
    ].join('');

    var tableWrap = document.createElement('div');
    tableWrap.style.cssText = 'overflow-y:auto;flex:1;border:1px solid #e2e5ec;border-radius:4px;';
    tableWrap.innerHTML = [
      '<table style="width:100%;border-collapse:collapse;font-size:12px;">',
        '<thead><tr style="background:#f7f9fa;position:sticky;top:0">',
          '<th style="padding:8px 10px;width:36px;border-bottom:1px solid #e2e5ec"></th>',
          '<th style="padding:8px 10px;text-align:left;color:#828b95;font-weight:600;border-bottom:1px solid #e2e5ec">Name</th>',
          '<th style="padding:8px 10px;text-align:left;color:#828b95;font-weight:600;border-bottom:1px solid #e2e5ec;width:100px">Price</th>',
          '<th style="padding:8px 10px;text-align:left;color:#828b95;font-weight:600;border-bottom:1px solid #e2e5ec;width:130px">Type of Cost</th>',
          '<th style="padding:8px 10px;text-align:left;color:#828b95;font-weight:600;border-bottom:1px solid #e2e5ec;width:100px">Payments</th>',
        '</tr></thead>',
        '<tbody id="picker-tbody"></tbody>',
      '</table>'
    ].join('');

    var footer = document.createElement('div');
    footer.style.cssText = 'display:flex;justify-content:flex-end;gap:8px;padding-top:8px;border-top:1px solid #e2e5ec;';
    footer.innerHTML = '<button id="picker-add-selected" class="btn-primary-bx" disabled>Add Selected</button><button id="picker-cancel" class="btn-secondary-bx">Cancel</button>';

    modal.appendChild(header); modal.appendChild(searchBox); modal.appendChild(filterBar);
    modal.appendChild(tableWrap); modal.appendChild(footer);
    overlay.appendChild(modal);
    document.body.appendChild(overlay);

    var selected = {};

    function renderPickerRows() {
      var query      = searchBox.value.toLowerCase();
      var filterType = document.getElementById('picker-filter-type').value;
      var filterPay  = document.getElementById('picker-filter-payments').value;
      var tbody2     = document.getElementById('picker-tbody');
      tbody2.innerHTML = '';

      var filtered = state.productCatalog.filter(function (p) {
        var name = (p.NAME || p.name || '').toLowerCase();
        var toc  = String(getPropValue(p, 'PROPERTY_111') || '');
        var pay  = String(getPropValue(p, 'PROPERTY_109') || '');
        if (query      && name.indexOf(query) === -1) return false;
        if (filterType && toc !== filterType)         return false;
        if (filterPay  && pay !== filterPay)          return false;
        return true;
      });

      if (filtered.length === 0) {
        tbody2.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:20px;color:#a8adb2">No products found</td></tr>';
        return;
      }

      filtered.forEach(function (p) {
        var pid   = String(p.ID || p.id);
        var pname = p.NAME || p.name || '';
        var price = parseFloat(p.PRICE || p.price || 0).toFixed(2);
        var toc   = PROP_TYPE_OF_COST[getPropValue(p, 'PROPERTY_111')] || '—';
        var pay   = PROP_PAYMENTS[getPropValue(p, 'PROPERTY_109')]     || '—';

        var tr2 = document.createElement('tr');
        tr2.style.cssText = 'border-bottom:1px solid #eef2f4;cursor:pointer;' + (selected[pid] ? 'background:#f0f8ff;' : '');
        tr2.innerHTML = [
          '<td style="padding:8px 10px;text-align:center"><input type="checkbox" ' + (selected[pid] ? 'checked' : '') + ' data-pid="' + pid + '"></td>',
          '<td style="padding:8px 10px;font-weight:600;color:#333">'  + escHtml(pname) + '</td>',
          '<td style="padding:8px 10px;color:#535c69">Dh ' + price    + '</td>',
          '<td style="padding:8px 10px;color:#535c69">' + escHtml(toc) + '</td>',
          '<td style="padding:8px 10px;color:#535c69">' + escHtml(pay) + '</td>',
        ].join('');

        tr2.querySelector('input[type=checkbox]').addEventListener('change', function (e) {
          if (e.target.checked) { selected[pid] = p; tr2.style.background = '#f0f8ff'; }
          else { delete selected[pid]; tr2.style.background = ''; }
          document.getElementById('picker-add-selected').disabled = Object.keys(selected).length === 0;
        });
        tr2.addEventListener('click', function (e) {
          if (e.target.tagName === 'INPUT') return;
          var cb = tr2.querySelector('input[type=checkbox]');
          cb.checked = !cb.checked; cb.dispatchEvent(new Event('change'));
        });
        tbody2.appendChild(tr2);
      });
    }

    renderPickerRows();
    searchBox.addEventListener('input', renderPickerRows);
    document.getElementById('picker-filter-type').addEventListener('change', renderPickerRows);
    document.getElementById('picker-filter-payments').addEventListener('change', renderPickerRows);
    document.getElementById('picker-close').addEventListener('click',  function () { overlay.remove(); });
    document.getElementById('picker-cancel').addEventListener('click', function () { overlay.remove(); });

    document.getElementById('picker-add-selected').addEventListener('click', function () {
      Object.values(selected).forEach(function (p) {
        var row = {
          id: state.nextRowId++, productId: String(p.ID || p.id),
          name:  p.NAME || p.name || '',
          price: parseFloat(p.PRICE || p.price || 0),
          qty: 1, taxRate: 0, taxIncluded: false,
          typeOfCost: getPropValue(p, 'PROPERTY_111'),
          payments:   getPropValue(p, 'PROPERTY_109'),
          option:     getPropValue(p, 'PROPERTY_119'),
          sort: state.rows.length * 10, spaId: null
        };
        state.rows.push(row);
        var tbody3 = document.getElementById('product-rows-body');
        if (tbody3) tbody3.appendChild(buildRowEl(row, state.rows.length, state.rows.length - 1));
      });
      recalcTotals();
      overlay.remove();
    });

    overlay.addEventListener('click', function (e) { if (e.target === overlay) overlay.remove(); });
  }

  // ─── Totals ────────────────────────────────────────────────────────────────
  function recalcTotals() {
    var trs = document.querySelectorAll('#product-rows-body tr');
    trs.forEach(function (tr2) {
      var rowId = parseInt(tr2.getAttribute('data-row-id'));
      var row   = findRow(rowId);
      if (!row) return;

      var priceEl = tr2.querySelector('.js-price');
      var taxEl   = tr2.querySelector('.js-tax');
      var nameEl  = tr2.querySelector('.js-product-name');
      var optEl   = tr2.querySelector('.js-option');
      var tocEl   = tr2.querySelector('.js-type-of-cost');
      var payEl   = tr2.querySelector('.js-payments');

      if (priceEl) row.price     = parseFloat(priceEl.value) || 0;
      if (taxEl)   row.taxRate   = parseFloat(taxEl.value)   || 0;
      if (nameEl)  row.name      = nameEl.value;
      if (optEl && optEl.value) row.option     = optEl.value;
      if (tocEl && tocEl.value) row.typeOfCost = tocEl.value;
      if (payEl && payEl.value) row.payments   = payEl.value;

      var amtEl = tr2.querySelector('.js-amount');
      if (amtEl) amtEl.value = (row.price * row.qty).toFixed(2);
    });

    var raw = 0, taxTotal = 0;
    state.rows.forEach(function (r) {
      var base  = r.price * r.qty;
      raw      += base;
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
    if (rows.length === 0) { setStatus('No products to save', 'status-warning'); return; }

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

    BX24.callMethod(method, { id: state.entityId, rows: productRows }, function (res) {
      if (res.error()) {
        setStatus('Error saving products: ' + res.error(), 'status-danger');
        log('Error: ' + res.error());
        return;
      }
      log('Product rows saved OK (with updated sort order)');

      updateCatalogProducts(rows, function () {
        var totalAmt = rows.reduce(function (sum, r) { return sum + r.price * r.qty; }, 0);
        updateEntityOpportunity(totalAmt, function () {
          syncSpaItems(rows, function () {
            setStatus('Saved & synced ✓', 'status-success');
            log('All done');
          });
        });
      });
    });
  }

  function collectRowData() {
    recalcTotals();
    return state.rows.filter(function (r) { return r.name || r.productId; });
  }

  function updateCatalogProducts(rows, cb) {
    var toUpdate = rows.filter(function (r) {
      return r.productId && (r.typeOfCost || r.payments || r.option);
    });

    if (toUpdate.length === 0) { if (cb) cb(); return; }

    log('Updating ' + toUpdate.length + ' catalog product(s)');
    var pending = toUpdate.length;

    toUpdate.forEach(function (row) {
      BX24.callMethod('catalog.product.get', { id: row.productId }, function (getRes) {
        var fields = {};

        if (!getRes.error()) {
          var prod = getRes.data() && (getRes.data().element || getRes.data());

          if (row.typeOfCost) {
            var toc    = prod ? (prod['property111'] || prod['PROPERTY_111']) : null;
            var tocVid = (toc && toc.valueId) ? toc.valueId : 0;
            fields['property111'] = { value: String(row.typeOfCost), valueId: tocVid };
          }
          if (row.payments) {
            var pay    = prod ? (prod['property109'] || prod['PROPERTY_109']) : null;
            var payVid = (pay && pay.valueId) ? pay.valueId : 0;
            fields['property109'] = { value: String(row.payments), valueId: payVid };
          }
          if (row.option) {
            var opt    = prod ? (prod['property119'] || prod['PROPERTY_119']) : null;
            var optVid = (opt && opt.valueId) ? opt.valueId : 0;
            fields['property119'] = { value: String(row.option), valueId: optVid };
          }
        } else {
          if (row.typeOfCost) fields['property111'] = { value: String(row.typeOfCost), valueId: 0 };
          if (row.payments)   fields['property109'] = { value: String(row.payments),   valueId: 0 };
          if (row.option)     fields['property119'] = { value: String(row.option),     valueId: 0 };
        }

        BX24.callMethod('catalog.product.update', {
          id:     row.productId,
          fields: fields
        }, function (res) {
          if (res.error()) {
            log('Error updating product #' + row.productId + ': ' + res.error());
          } else {
            log('Product #' + row.productId + ' updated');
          }
          if (--pending === 0 && cb) cb();
        });
      });
    });
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

  function syncSpaItems(rows, cb) {
    log('Calling backend SPA Sync API...');
    fetch('api/sync.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        entityType: state.entityType,
        entityId: state.entityId,
        action: 'sync'
      })
    })
    .then(function (res) { return res.json(); })
    .then(function (data) {
      if (data && data.success) {
        log('Backend SPA Sync completed successfully.');
        if (cb) cb();
      } else {
        var errMsg = (data && data.errors && data.errors.join(', ')) || 'Unknown error';
        log('Backend SPA Sync failed: ' + errMsg);
        setStatus('Sync completed with errors: ' + errMsg, 'status-warning');
        if (cb) cb();
      }
    })
    .catch(function (err) {
      log('HTTP error during SPA Sync: ' + err);
      setStatus('HTTP error during sync', 'status-danger');
      if (cb) cb();
    });
  }

  function resolveSpaEntityTypeId(typeOfCost, option) {
    var isGov = (typeOfCost === '207');
    var isProf = (typeOfCost === '209');
    if (!isGov && !isProf) return null;
    var isOption2 = (option === '237' || String(option).toLowerCase() === 'option 2');
    if (isGov) return isOption2 ? 1074 : 1062;
    if (isProf) return isOption2 ? 1070 : 1058;
    return null;
  }

  function getLinkField(spaTypeId) {
    var DEAL_FIELDS = {
      1058: 'UF_CRM_1779313011',
      1062: 'UF_CRM_1779654189',
      1070: 'UF_CRM_6A29D1F63E22F',
      1074: 'UF_CRM_6A29D1F65158B'
    };
    var LEAD_FIELDS = {
      1058: 'UF_CRM_1780911226',
      1062: 'UF_CRM_1780912561',
      1070: 'UF_CRM_1781125540',
      1074: 'UF_CRM_1781125572'
    };
    var map = state.entityType === 'deal' ? DEAL_FIELDS : LEAD_FIELDS;
    return map[spaTypeId] || null;
  }

  function updateEntityStatus(cb) {
    var field = state.entityType === 'lead' ? 'UF_CRM_1781076094241' : 'UF_CRM_6A29D1F62D40F';
    var method = state.entityType === 'deal' ? 'crm.deal.update' : 'crm.lead.update';
    var fields = {};
    fields[field] = 'Done';
    BX24.callMethod(method, { id: state.entityId, fields: fields }, function (res) {
      if (res.error()) {
        log('Error updating status field ' + field + ': ' + res.error());
      } else {
        log('Entity status field ' + field + ' updated to Done');
      }
      if (cb) cb();
    });
  }

  function syncSpaGroup(entityTypeId, fieldMap, linkField, rows, cb) {
    if (!rows || rows.length === 0) { if (cb) cb(); return; }

    var spaIds  = [];
    var pending = rows.length;

    rows.forEach(function (row) {
      var fields = buildSpaFields(row, fieldMap);

      if (row.spaId) {
        BX24.callMethod('crm.item.update', {
          entityTypeId: entityTypeId, id: row.spaId, fields: fields
        }, function (res) {
          if (res.error()) log('SPA update error: ' + res.error());
          else { log('SPA item #' + row.spaId + ' updated'); spaIds.push(row.spaId); }
          if (--pending === 0) linkSpaToEntity(entityTypeId, spaIds, linkField, cb);
        });
      } else {
        BX24.callMethod('crm.item.add', {
          entityTypeId: entityTypeId,
          fields: Object.assign({ TITLE: row.name, OPPORTUNITY: row.price }, fields)
        }, function (res) {
          if (res.error()) {
            log('SPA create error: ' + res.error());
          } else {
            var newId = (res.data() && res.data().item) ? res.data().item.id : null;
            if (newId) { row.spaId = newId; spaIds.push(newId); log('SPA item created #' + newId); }
          }
          if (--pending === 0) linkSpaToEntity(entityTypeId, spaIds, linkField, cb);
        });
      }
    });
  }

  function buildSpaFields(row, fieldMap) {
    var fields = {};
    if (row.typeOfCost && fieldMap.typeOfCost) {
      var tocVal = mapSpaEnumValueById(fieldMap.typeOfCost, row.typeOfCost);
      if (tocVal !== undefined) fields[fieldMap.typeOfCost] = tocVal;
    }
    if (row.payments && fieldMap.payments) {
      var payVal = mapSpaEnumValueById(fieldMap.payments, row.payments);
      if (payVal !== undefined) fields[fieldMap.payments] = payVal;
    }
    return fields;
  }

  function mapSpaEnumValueById(fieldKey, catalogEnumId) {
    var directMaps = {
      'ufCrm15_1779367818775': { '209': '445', '207': '447' },
      'ufCrm15_1779367955682': { '193': '449', '195': '451', '197': '453', '199': '455', '201': '457', '203': '459', '205': '461' },
      'ufCrm17_1779370162991': { '207': '497', '209': '499' },
      'ufCrm17_1779370261982': { '193': '501', '195': '503', '197': '505', '199': '507', '201': '509', '203': '511', '205': '513' }
    };
    var map = directMaps[fieldKey];
    if (!map || !catalogEnumId) return undefined;
    return map[String(catalogEnumId)] || undefined;
  }

  function linkSpaToEntity(entityTypeId, spaIds, linkField, cb) {
    if (!linkField || spaIds.length === 0) { if (cb) cb(); return; }
    var updateFields = {};
    updateFields[linkField] = spaIds;
    var method = state.entityType === 'deal' ? 'crm.deal.update' : 'crm.lead.update';
    BX24.callMethod(method, { id: state.entityId, fields: updateFields }, function (res) {
      if (res.error()) log('SPA link error: ' + res.error());
      else log(state.entityType + ' linked to ' + spaIds.length + ' SPA items');
      if (cb) cb();
    });
  }

  function showProductEditModal(productId) {
    var product = productId ? findCatalogProduct(productId) : null;
    var isNew   = !product;

    var overlay = document.createElement('div');
    overlay.id = 'product-edit-modal';
    overlay.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.45);z-index:10000;display:flex;align-items:center;justify-content:center;';

    var modal = document.createElement('div');
    modal.style.cssText = 'background:#fff;border-radius:8px;padding:24px;width:520px;max-width:95vw;box-shadow:0 8px 32px rgba(0,0,0,0.18);font-family:"Open Sans",Arial,sans-serif;font-size:13px;display:flex;flex-direction:column;gap:14px;';

    modal.innerHTML = [
      '<div style="display:flex;justify-content:space-between;align-items:center">',
        '<strong style="font-size:15px;color:#333">' + (isNew ? 'Create New Product' : 'Edit Product') + '</strong>',
        '<button id="edit-close" style="background:none;border:none;font-size:20px;cursor:pointer;color:#999">✕</button>',
      '</div>',
      '<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">',

        '<div style="display:flex;flex-direction:column;gap:4px;grid-column:span 2">',
          '<label style="font-weight:600;color:#535c69">Product Name *</label>',
          '<input id="ep-name" class="input-bx" value="' + escHtml(product ? (product.NAME || product.name || '') : '') + '">',
        '</div>',

        '<div style="display:flex;flex-direction:column;gap:4px">',
          '<label style="font-weight:600;color:#535c69">Price (Dh)</label>',
          '<input id="ep-price" type="number" min="0" step="0.01" class="input-bx" value="' + (product ? parseFloat(product.PRICE || 0) : 0) + '">',
        '</div>',

        '<div style="display:flex;flex-direction:column;gap:4px">',
          '<label style="font-weight:600;color:#535c69">Option</label>',
          '<select id="ep-option" class="input-bx select-bx">' +
            buildEnumOpts([{id:'235',label:'Option 1'},{id:'237',label:'Option 2'}],
              product ? getPropValue(product, 'PROPERTY_119') : '') +
          '</select>',
        '</div>',

        '<div style="display:flex;flex-direction:column;gap:4px">',
          '<label style="font-weight:600;color:#535c69">Type of Cost</label>',
          '<select id="ep-type-of-cost" class="input-bx select-bx">' +
            buildEnumOpts([{id:'207',label:'Government Cost'},{id:'209',label:'Professional Fees'}],
              product ? getPropValue(product, 'PROPERTY_111') : '') +
          '</select>',
        '</div>',

        '<div style="display:flex;flex-direction:column;gap:4px">',
          '<label style="font-weight:600;color:#535c69">Payments</label>',
          '<select id="ep-payments" class="input-bx select-bx">' +
            buildEnumOpts([
              {id:'193',label:'Annually'},{id:'195',label:'One Time'},{id:'197',label:'Quarterly'},
              {id:'199',label:'Every 2 years'},{id:'201',label:'Monthly'},
              {id:'203',label:'In The Order Of Discussion'},{id:'205',label:'One time (variable)'}
            ], product ? getPropValue(product, 'PROPERTY_109') : '') +
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

      var toc = document.getElementById('ep-type-of-cost').value;
      var pay = document.getElementById('ep-payments').value;
      var opt = document.getElementById('ep-option').value;
      var price = parseFloat(document.getElementById('ep-price').value) || 0;

      var btn = document.getElementById('ep-save');
      btn.disabled = true; btn.textContent = 'Saving…';

      if (isNew) {
        if (!state.iblockId) {
          alert('Cannot create product: catalog iblockId not loaded. Refresh and try again.');
          btn.disabled = false; btn.textContent = 'Create Product';
          return;
        }
        var addFields = { iblockId: state.iblockId, name: name, active: 'Y' };
        if (toc) addFields['property111'] = toc;
        if (pay) addFields['property109'] = pay;
        if (opt) addFields['property119'] = opt;

        BX24.callMethod('catalog.product.add', { fields: addFields }, function (res) {
          if (res.error()) {
            alert('Error creating product: ' + res.error());
            btn.disabled = false; btn.textContent = 'Create Product';
            return;
          }
          var newData = res.data();
          var newId   = newData && (newData.element ? newData.element.id : newData);
          log('Product created #' + newId);

          if (newId && price > 0) {
            BX24.callMethod('catalog.price.add', {
              fields: { productId: newId, catalogGroupId: 1, price: price, currency: 'AED' }
            }, function () {});
          }

          loadCatalogProducts(function () {
            overlay.remove();
            var newP = findCatalogProduct(newId);
            if (newP) {
              var row = {
                id: state.nextRowId++, productId: String(newId), name: name,
                price: price, qty: 1, taxRate: 0, taxIncluded: false,
                typeOfCost: toc, payments: pay, option: opt,
                sort: state.rows.length * 10, spaId: null
              };
              state.rows.push(row);
              var tbody4 = document.getElementById('product-rows-body');
              if (tbody4) tbody4.appendChild(buildRowEl(row, state.rows.length, state.rows.length - 1));
              recalcTotals();
            }
          });
        });
      } else {
        BX24.callMethod('catalog.product.get', { id: productId }, function (getRes) {
          var prod      = (!getRes.error() && getRes.data()) ? (getRes.data().element || getRes.data()) : null;
          var updFields = { name: name, active: 'Y' };

          function getVid(prod, key) {
            if (!prod) return 0;
            var p = prod[key];
            return (p && p.valueId) ? p.valueId : 0;
          }

          if (toc) updFields['property111'] = { value: toc, valueId: getVid(prod, 'property111') };
          if (pay) updFields['property109'] = { value: pay, valueId: getVid(prod, 'property109') };
          if (opt) updFields['property119'] = { value: opt, valueId: getVid(prod, 'property119') };

          BX24.callMethod('catalog.product.update', { id: productId, fields: updFields }, function (res) {
            if (res.error()) {
              alert('Error updating product: ' + res.error());
              btn.disabled = false; btn.textContent = 'Save Changes';
              return;
            }
            if (price > 0) {
              BX24.callMethod('catalog.price.list', { filter: { productId: productId, catalogGroupId: 1 } }, function (pr) {
                var prices = (!pr.error() && pr.data()) ? pr.data() : [];
                if (prices.length > 0) {
                  BX24.callMethod('catalog.price.update', { id: prices[0].id, fields: { price: price, currency: 'AED' } }, function () {});
                } else {
                  BX24.callMethod('catalog.price.add', { fields: { productId: productId, catalogGroupId: 1, price: price, currency: 'AED' } }, function () {});
                }
              });
            }
            log('Product #' + productId + ' updated');
            loadCatalogProducts(function () { overlay.remove(); });
          });
        });
      }
    });
  }

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
    rebind('btn-edit-product', function () {
      showProductEditModal(null);
    });
  }

  function escHtml(s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  return {
    init:             init,
    addRow:           addEmptyRow,
    openSelector:     openProductSelector,
    saveAndSync:      saveAndSync,
    showProductModal: showProductEditModal
  };

})();