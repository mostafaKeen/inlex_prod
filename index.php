<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Product Fee Sync Grid</title>

	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">

	<script src="//api.bitrix24.com/api/v1/"></script>

	<style>
		*, *::before, *::after { box-sizing: border-box; }

		body {
			font-family: 'Open Sans', Arial, sans-serif;
			background-color: #f0f2f5;
			color: #535c69;
			margin: 0;
			padding: 20px;
			font-size: 13px;
		}

		.widget-container {
			background: #ffffff;
			border-radius: 8px;
			box-shadow: 0 2px 6px rgba(0,0,0,0.04);
			padding: 20px;
			border: 1px solid #e2e5ec;
			max-width: 1400px;
			margin: 0 auto;
			position: relative;
		}

		.widget-header {
			display: flex;
			justify-content: space-between;
			align-items: center;
			margin-bottom: 20px;
			padding-bottom: 16px;
			border-bottom: 1px solid #e2e5ec;
		}

		.widget-title {
			font-size: 18px;
			font-weight: 700;
			color: #222;
			margin: 0;
		}

		.entity-info {
			font-size: 12px;
			color: #828b95;
			font-weight: 600;
		}

		.entity-info strong {
			color: #0080ff;
			font-size: 13px;
		}

		.action-bar {
			display: flex;
			justify-content: space-between;
			align-items: center;
			margin-bottom: 16px;
			gap: 12px;
			flex-wrap: wrap;
		}

		.left-actions {
			display: flex;
			gap: 8px;
			flex-wrap: wrap;
		}

		.btn-primary-bx {
			background-color: #0080ff;
			color: #fff;
			border: none;
			border-radius: 4px;
			padding: 8px 16px;
			font-weight: 600;
			cursor: pointer;
			transition: all 0.2s ease;
			font-size: 13px;
			font-family: inherit;
		}
		.btn-primary-bx:hover { background-color: #0066cc; box-shadow: 0 2px 8px rgba(0,128,255,0.2); }
		.btn-primary-bx:active { background-color: #0055aa; transform: scale(0.98); }
		.btn-primary-bx:disabled { background-color: #c6cdd3; cursor: not-allowed; opacity: 0.6; }

		.btn-secondary-bx {
			background-color: #fff;
			color: #535c69;
			border: 1px solid #c6cdd3;
			border-radius: 4px;
			padding: 8px 16px;
			font-weight: 600;
			cursor: pointer;
			transition: all 0.2s ease;
			font-size: 13px;
			font-family: inherit;
		}
		.btn-secondary-bx:hover { background-color: #f5f7f8; border-color: #a8adb2; }
		.btn-secondary-bx:active { background-color: #eef2f4; transform: scale(0.98); }

		.btn-save-sync {
			background: linear-gradient(135deg, #2fc6f6 0%, #18b0e0 100%);
			color: #fff;
			border: none;
			border-radius: 4px;
			padding: 10px 22px;
			font-weight: 700;
			cursor: pointer;
			transition: all 0.2s ease;
			font-size: 13px;
			font-family: inherit;
			white-space: nowrap;
			box-shadow: 0 2px 6px rgba(47,198,246,0.15);
		}
		.btn-save-sync:hover { background: linear-gradient(135deg,#18b0e0 0%,#0a95cc 100%); box-shadow: 0 4px 12px rgba(47,198,246,0.25); transform: translateY(-1px); }
		.btn-save-sync:active { transform: translateY(0); }
		.btn-save-sync:disabled { opacity: 0.6; cursor: not-allowed; }

		.btn-delete {
			color: #a8adb2;
			background: transparent;
			border: none;
			cursor: pointer;
			padding: 4px 6px;
			border-radius: 4px;
			font-size: 15px;
			line-height: 1;
			transition: all 0.2s ease;
		}
		.btn-delete:hover { color: #ff5050; background: #fff0f0; }

		.input-bx {
			border: 1px solid #c6cdd3;
			border-radius: 4px;
			padding: 6px 10px;
			color: #535c69;
			background-color: #fff;
			width: 100%;
			font-size: 13px;
			font-family: inherit;
			outline: none;
			transition: all 0.2s ease;
		}
		.input-bx:focus { border-color: #0080ff; box-shadow: 0 0 0 3px rgba(0,128,255,0.1); }

		.input-bx-wrapper { position: relative; display: flex; align-items: center; }
		.input-bx-suffix { position: absolute; right: 8px; color: #a8adb2; pointer-events: none; font-size: 12px; }
		.input-bx-with-suffix { padding-right: 30px !important; }

		.select-bx {
			appearance: none;
			-webkit-appearance: none;
			background-image: url("data:image/svg+xml;charset=utf-8,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 4 5'%3E%3Cpath fill='%23535c69' d='M2 0L0 2h4zm0 5L0 3h4z'/%3E%3C/svg%3E");
			background-repeat: no-repeat;
			background-position: right 8px center;
			background-size: 8px 10px;
			padding-right: 26px;
			cursor: pointer;
		}

		.section-label {
			font-size: 11px;
			font-weight: 700;
			color: #a8adb2;
			text-transform: uppercase;
			letter-spacing: 0.6px;
			margin: 16px 0 12px 0;
		}

		.table-responsive {
			overflow-x: auto;
			border: 1px solid #e2e5ec;
			border-radius: 6px;
		}

		.product-table {
			width: 100%;
			border-collapse: collapse;
			text-align: left;
			min-width: 1000px;
		}

		.product-table thead tr { background-color: #f7f9fa; }

		.product-table th {
			color: #828b95;
			font-weight: 600;
			padding: 12px;
			font-size: 11px;
			border-bottom: 2px solid #e2e5ec;
			text-transform: uppercase;
			letter-spacing: 0.5px;
			white-space: nowrap;
		}

		.product-table td {
			padding: 12px;
			border-bottom: 1px solid #eef2f4;
			vertical-align: middle;
		}

		.product-table tbody tr { transition: all 0.15s ease; background-color: #fff; }
		.product-table tbody tr:last-child td { border-bottom: none; }
		.product-table tbody tr:hover { background-color: #fafbfc; }

		.row-number { color: #a8adb2; display: flex; align-items: center; gap: 6px; font-weight: 600; font-size: 12px; }
		.drag-handle { cursor: grab; color: #d0d4da; font-size: 16px; user-select: none; transition: color 0.2s; }
		tr:hover .drag-handle { color: #0080ff; }
		.drag-handle:active { cursor: grabbing; }

		/* Option badge styling */
		.option-badge {
			display: inline-block;
			padding: 2px 7px;
			border-radius: 10px;
			font-size: 11px;
			font-weight: 700;
			white-space: nowrap;
		}
		.option-badge-1 { background: #e5f2ff; color: #0066cc; }
		.option-badge-2 { background: #fff3e0; color: #e65100; }

		.totals-container { display: flex; justify-content: flex-end; margin-top: 24px; }

		.totals-box {
			width: 340px;
			background: linear-gradient(135deg, #f7f9fa 0%, #f0f2f5 100%);
			border: 1px solid #e2e5ec;
			border-radius: 6px;
			padding: 16px;
		}

		.total-row { display: flex; justify-content: space-between; align-items: center; padding: 8px 0; color: #828b95; font-size: 13px; }
		.total-row strong { color: #535c69; font-weight: 600; }

		.total-row.grand-total {
			border-top: 2px solid #e2e5ec;
			margin-top: 10px;
			padding-top: 14px;
			color: #222;
			font-size: 15px;
			font-weight: 700;
		}
		.total-row.grand-total strong { color: #0080ff; font-size: 17px; }

		.status-footer {
			display: flex;
			justify-content: space-between;
			align-items: flex-start;
			margin-top: 20px;
			padding-top: 16px;
			border-top: 1px solid #e2e5ec;
			gap: 16px;
			flex-wrap: wrap;
		}

		.status-left { display: flex; flex-direction: column; gap: 8px; flex: 1; min-width: 0; }

		#sync-status {
			display: inline-block;
			font-weight: 600;
			padding: 6px 12px;
			border-radius: 4px;
			font-size: 12px;
		}

		.status-info    { color: #0066cc; background: #e5f2ff; }
		.status-success { color: #155724; background: #d4edda; }
		.status-warning { color: #856404; background: #fff3cd; }
		.status-danger  { color: #721c24; background: #f8d7da; }

		#initial-loading { display: none; position: absolute; inset: 0; background: rgba(255,255,255,0.9); border-radius: 8px; align-items: center; justify-content: center; z-index: 50; }
		#initial-loading.visible { display: flex; }

		.loading-spinner { display: flex; flex-direction: column; align-items: center; gap: 16px; }
		.spinner { width: 32px; height: 32px; border: 3px solid #e2e5ec; border-top-color: #0080ff; border-radius: 50%; animation: spin 0.8s linear infinite; }
		.loading-text { font-weight: 600; color: #535c69; font-size: 14px; }

		@keyframes spin { to { transform: rotate(360deg); } }

		/* Save modal */
		#save-modal-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.4); z-index: 9999; align-items: center; justify-content: center; }
		#save-modal-overlay.visible { display: flex; }

		.save-modal { background: #fff; border-radius: 8px; padding: 48px 40px; text-align: center; max-width: 360px; box-shadow: 0 16px 48px rgba(0,0,0,0.15); animation: slideUp 0.3s ease; }

		@keyframes slideUp { from { opacity:0; transform: translateY(16px); } to { opacity:1; transform: translateY(0); } }

		.save-modal-icon { font-size: 48px; margin-bottom: 16px; display: block; }
		.save-modal-title { font-size: 16px; font-weight: 700; color: #222; margin: 16px 0 8px 0; }
		.save-modal-message { font-size: 13px; color: #828b95; margin: 0; line-height: 1.5; }
		.save-modal-details { margin-top: 20px; padding-top: 20px; border-top: 1px solid #e2e5ec; text-align: left; }
		.detail-row { display: flex; justify-content: space-between; padding: 6px 0; font-size: 12px; color: #828b95; }
		.detail-row strong { color: #535c69; }

		@media (max-width: 600px) {
			body { padding: 8px; }
			.widget-container { padding: 12px; }
			.totals-box { width: 100%; }
		}
	</style>
</head>
<body>
<div class="widget-container">

	<!-- Initial Loading Overlay -->
	<div id="initial-loading">
		<div class="loading-spinner">
			<div class="spinner"></div>
			<div class="loading-text" id="loading-text">Preparing widget…</div>
		</div>
	</div>

	<!-- Save Modal Overlay -->
	<div id="save-modal-overlay">
		<div class="save-modal">
			<span id="save-modal-icon" class="save-modal-icon">⚙️</span>
			<h3 class="save-modal-title" id="save-modal-title">Saving Changes…</h3>
			<p class="save-modal-message" id="save-modal-message">Please wait while we update your data</p>
			<div class="save-modal-details" id="save-modal-details"></div>
		</div>
	</div>

	<!-- Header -->
	<div class="widget-header">
		<div>
			<h1 class="widget-title">Product Fee Sync</h1>
			<div class="entity-info">
				<span id="entity-type-label"></span>
				<strong id="entity-id-label"></strong>
			</div>
		</div>
	</div>

	<!-- Action Bar -->
	<div class="action-bar">
		<div class="left-actions">
			<button id="btn-select-product" class="btn-secondary-bx">📋 Select from Catalog</button>
			<button id="btn-edit-product"   class="btn-secondary-bx">✏️ Create Product</button>
		</div>
		<button id="btn-save" class="btn-save-sync">💾 Save &amp; Sync</button>
	</div>

	<!-- Product Table -->
	<div class="section-label">Products (Drag to reorder)</div>
	<div class="table-responsive">
		<table class="product-table">
			<thead>
				<tr>
					<th style="width:50px;">#</th>
					<th>Product Name</th>
					<th style="width:140px;">Option</th>
					<th style="width:170px;">Type of Cost</th>
					<th style="width:140px;">Price</th>
					<th style="width:160px;">Payments</th>
					<th style="width:110px;">Tax %</th>
					<th style="width:140px;">Amount</th>
					<th style="width:36px;"></th>
				</tr>
			</thead>
			<tbody id="product-rows-body"></tbody>
		</table>
	</div>

	<!-- Totals -->
	<div class="totals-container">
		<div class="totals-box">
			<div class="total-row"><span>Subtotal (no tax):</span><strong id="total-raw">Dh 0.00</strong></div>
			<div class="total-row"><span>Delivery:</span><strong>Dh 0.00</strong></div>
			<div class="total-row"><span>Discount:</span><strong>Dh 0.00</strong></div>
			<div class="total-row"><span>Total before tax:</span><strong id="total-before-tax">Dh 0.00</strong></div>
			<div class="total-row"><span>Tax total:</span><strong id="total-tax">Dh 0.00</strong></div>
			<div class="total-row grand-total"><span>Total amount:</span><strong id="total-amount">Dh 0.00</strong></div>
		</div>
	</div>

	<!-- Status Footer -->
	<div class="status-footer">
		<div class="status-left">
			<div id="sync-status" class="status-info">✓ Ready to save</div>
		</div>
	</div>

</div>

<script>
var FeeSyncWidget = (function () {

	// ─── SPA Field Maps ───────────────────────────────────────────────────────
	// Type 1058 = Professional Fees Option 1
	// Type 1062 = Government Fees  Option 1
	// Type 1070 = Professional Fees Option 2
	// Type 1074 = Government Fees  Option 2
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

	// Link fields per entity type and SPA type
	var DEAL_LINK_FIELDS = {
		1058: 'UF_CRM_1779313011',
		1062: 'UF_CRM_1779654189',
		1070: 'UF_CRM_6A29D1F63E22F',
		1074: 'UF_CRM_6A29D1F65158B'
	};
	var LEAD_LINK_FIELDS = {
		1058: 'UF_CRM_1780911226',
		1062: 'UF_CRM_1780912561',
		1070: 'UF_CRM_1781125540',
		1074: 'UF_CRM_1781125572'
	};

	// Status field per entity type (set to "Done" after sync)
	var STATUS_FIELDS = {
		deal: 'UF_CRM_6A29D1F62D40F',
		lead: 'UF_CRM_1781076094241'
	};

	var PROP_TYPE_OF_COST = { '207': 'Government Cost', '209': 'Professional Fees' };
	var PROP_PAYMENTS = {
		'193': 'Annually', '195': 'One Time', '197': 'Quarterly',
		'199': 'Every 2 years', '201': 'Monthly',
		'203': 'In The Order Of Discussion',
		'205': 'One time (cost depends on transactions)'
	};
	var PROP_OPTIONS = { '235': 'Option 1', '237': 'Option 2' };

	// ─── State ────────────────────────────────────────────────────────────────
	var state = {
		entityType:     null,
		entityId:       null,
		rows:           [],
		nextRowId:      1,
		productCatalog: [],
		iblockId:       null,
		draggedRow:     null,
		draggedIndex:   null
	};

	// ─── Logging ──────────────────────────────────────────────────────────────
	function log(msg) {
		console.log('[FeeSyncWidget] ' + msg);
	}

	function setStatus(msg, cls) {
		var el = document.getElementById('sync-status');
		if (!el) return;
		el.textContent = msg;
		el.className = cls || 'status-info';
	}

	function setLoadingText(txt) {
		var el = document.getElementById('loading-text');
		if (el) el.textContent = txt;
	}

	// ─── Resolve SPA entity type ID from typeOfCost + option ─────────────────
	function resolveSpaEntityTypeId(typeOfCost, option) {
		var isGov  = (typeOfCost === '207');
		var isProf = (typeOfCost === '209');
		if (!isGov && !isProf) return null;
		var isOption2 = (option === '237' || String(option).toLowerCase() === 'option 2');
		if (isGov)  return isOption2 ? 1074 : 1062;
		if (isProf) return isOption2 ? 1070 : 1058;
		return null;
	}

	// ─── Get link field for current entity type + SPA type ───────────────────
	function getLinkField(spaTypeId) {
		var map = state.entityType === 'deal' ? DEAL_LINK_FIELDS : LEAD_LINK_FIELDS;
		return map[spaTypeId] || null;
	}

	// ─── Init ─────────────────────────────────────────────────────────────────
	function init(entityType, entityId, onReady) {
		state.entityType     = entityType;
		state.entityId       = entityId;
		state.rows           = [];
		state.nextRowId      = 1;
		state.productCatalog = [];
		state.iblockId       = null;
		state.draggedRow     = null;
		state.draggedIndex   = null;

		setStatus('Loading…', 'status-info');
		log('Init for ' + entityType + ' #' + entityId);

		setLoadingText('Loading catalog…');
		fetchIblockId(function () {
			loadCatalogProducts(function () {
				setLoadingText('Loading products…');
				loadEntityProducts(function () {
					renderRows();
					bindActions();
					setStatus('✓ Ready to save', 'status-info');
					if (typeof onReady === 'function') onReady();
				});
			});
		});
	}

	// ─── Fetch iblock ID ──────────────────────────────────────────────────────
	function extractCatalogs(data) {
		if (!data) return [];
		if (Array.isArray(data)) return data;
		if (data.catalogs && Array.isArray(data.catalogs)) return data.catalogs;
		return [];
	}

	function pickIblockId(catalogs) {
		if (!catalogs || catalogs.length === 0) return null;
		var main = null;
		for (var i = 0; i < catalogs.length; i++) {
			var c = catalogs[i];
			if (!c.productIblockId || c.productIblockId === 0) { main = c; break; }
		}
		if (!main) main = catalogs[0];
		return main ? (main.iblockId || main.id || main.ID || null) : null;
	}

	function fetchIblockId(cb) {
		BX24.callMethod('catalog.catalog.list', { select: ['id', 'iblockId', 'iblockTypeId', 'productIblockId'] }, function (res) {
			if (!res.error()) {
				var catalogs = extractCatalogs(res.data());
				var id = pickIblockId(catalogs);
				if (id) {
					state.iblockId = id;
					log('iblockId = ' + state.iblockId);
				} else {
					log('catalog.catalog.list returned no usable catalog');
				}
			} else {
				log('catalog.catalog.list error: ' + res.error());
			}
			if (cb) cb();
		});
	}

	// ─── Load catalog products ────────────────────────────────────────────────
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

	// ─── Load entity product rows ─────────────────────────────────────────────
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
			log('Loaded ' + rows.length + ' product row(s)');
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

		log('Row #' + row.id + ': name=' + row.name + ', typeOfCost=' + row.typeOfCost + ', option=' + row.option);
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

	// ─── Render ───────────────────────────────────────────────────────────────
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

		var amount = (row.price * row.qty * (1 + row.taxRate / 100)).toFixed(2);

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

		// Live event bindings
		tr.querySelector('.js-product-name').addEventListener('input', function (e) {
			updateRowField(row.id, 'name', e.target.value);
		});
		tr.querySelector('.js-option').addEventListener('change', function (e) {
			updateRowField(row.id, 'option', e.target.value);
			log('Row ' + row.id + ' option → ' + e.target.value);
		});
		tr.querySelector('.js-type-of-cost').addEventListener('change', function (e) {
			updateRowField(row.id, 'typeOfCost', e.target.value);
			log('Row ' + row.id + ' typeOfCost → ' + e.target.value);
		});
		tr.querySelector('.js-payments').addEventListener('change', function (e) {
			updateRowField(row.id, 'payments', e.target.value);
			log('Row ' + row.id + ' payments → ' + e.target.value);
		});
		tr.querySelector('.js-price').addEventListener('input', function (e) {
			updateRowField(row.id, 'price', parseFloat(e.target.value) || 0);
			recalcRowAmount(row.id);
		});
		tr.querySelector('.js-tax').addEventListener('input', function (e) {
			updateRowField(row.id, 'taxRate', parseFloat(e.target.value) || 0);
			recalcRowAmount(row.id);
		});
		tr.querySelector('.js-delete-row').addEventListener('click', function () { deleteRow(row.id); });

		// Drag & drop
		tr.addEventListener('dragstart', function (e) {
			state.draggedRow   = row.id;
			state.draggedIndex = index;
			tr.style.opacity   = '0.6';
			tr.style.background = '#f0f8ff';
			e.dataTransfer.effectAllowed = 'move';
		});
		tr.addEventListener('dragend', function () {
			tr.style.opacity = '1';
			tr.style.background = '';
			state.draggedRow   = null;
			state.draggedIndex = null;
			document.querySelectorAll('#product-rows-body tr').forEach(function (t) { t.style.borderTop = ''; });
		});
		tr.addEventListener('dragover', function (e) {
			e.preventDefault();
			e.dataTransfer.dropEffect = 'move';
			if (state.draggedIndex !== null && state.draggedIndex !== index) {
				tr.style.borderTop = '2px solid #0080ff';
			}
		});
		tr.addEventListener('dragleave', function () { tr.style.borderTop = ''; });
		tr.addEventListener('drop', function (e) {
			e.preventDefault();
			if (state.draggedRow === null || state.draggedIndex === null) return;
			if (state.draggedIndex === index) return;
			var obj = state.rows.find(function (r) { return r.id === state.draggedRow; });
			if (!obj) return;
			state.rows.splice(state.draggedIndex, 1);
			state.rows.splice(index, 0, obj);
			tr.style.borderTop = '';
			renderRows();
			log('Reordered: row #' + state.draggedRow + ' → position ' + (index + 1));
		});

		return tr;
	}

	function buildEnumOpts(items, currentVal) {
		var html = '<option value="">-- select --</option>';
		var norm = String(currentVal || '').trim();
		items.forEach(function (item) {
			var sel = (String(item.id).trim() === norm) ? ' selected' : '';
			html += '<option value="' + item.id + '"' + sel + '>' + escHtml(item.label) + '</option>';
		});
		return html;
	}

	// ─── Row helpers ──────────────────────────────────────────────────────────
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
			if (amtInput) amtInput.value = (row.price * row.qty * (1 + row.taxRate / 100)).toFixed(2);
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

	// ─── Totals ───────────────────────────────────────────────────────────────
	function recalcTotals() {
		var trs = document.querySelectorAll('#product-rows-body tr');
		trs.forEach(function (tr2) {
			var rowId = parseInt(tr2.getAttribute('data-row-id'));
			var row   = findRow(rowId);
			if (!row) return;

			var priceEl = tr2.querySelector('.js-price');
			var taxEl   = tr2.querySelector('.js-tax');
			var nameEl  = tr2.querySelector('.js-product-name');
			var tocEl   = tr2.querySelector('.js-type-of-cost');
			var payEl   = tr2.querySelector('.js-payments');
			var optEl   = tr2.querySelector('.js-option');

			if (priceEl) row.price   = parseFloat(priceEl.value) || 0;
			if (taxEl)   row.taxRate = parseFloat(taxEl.value)   || 0;
			if (nameEl)  row.name    = nameEl.value;
			// Only overwrite when user has actually chosen a value
			if (tocEl && tocEl.value !== '') row.typeOfCost = tocEl.value;
			if (payEl && payEl.value !== '') row.payments   = payEl.value;
			if (optEl && optEl.value !== '') row.option     = optEl.value;

			var amtEl = tr2.querySelector('.js-amount');
			if (amtEl) amtEl.value = (row.price * row.qty * (1 + row.taxRate / 100)).toFixed(2);
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

	// ─── Product Picker Modal ─────────────────────────────────────────────────
	function showProductPickerModal() {
		var existing = document.getElementById('product-picker-modal');
		if (existing) existing.remove();

		var overlay = document.createElement('div');
		overlay.id = 'product-picker-modal';
		overlay.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.45);z-index:9999;display:flex;align-items:center;justify-content:center;';

		var modal = document.createElement('div');
		modal.style.cssText = 'background:#fff;border-radius:8px;padding:24px;width:760px;max-width:95vw;max-height:82vh;display:flex;flex-direction:column;gap:12px;box-shadow:0 8px 32px rgba(0,0,0,0.18);font-family:"Open Sans",Arial,sans-serif;font-size:13px;';

		modal.innerHTML = [
			'<div style="display:flex;justify-content:space-between;align-items:center">',
				'<strong style="font-size:15px;color:#333">Select Products from Catalog</strong>',
				'<button id="picker-close" style="background:none;border:none;font-size:20px;cursor:pointer;color:#999">✕</button>',
			'</div>',
			'<div style="display:flex;gap:8px;flex-wrap:wrap">',
				'<input id="picker-search" type="text" placeholder="Search products…" class="input-bx" style="flex:2;min-width:160px">',
				'<select id="picker-filter-type" class="input-bx select-bx" style="flex:1;min-width:140px">',
					'<option value="">All Types</option>',
					'<option value="207">Government Cost</option>',
					'<option value="209">Professional Fees</option>',
				'</select>',
				'<select id="picker-filter-option" class="input-bx select-bx" style="flex:1;min-width:120px">',
					'<option value="">All Options</option>',
					'<option value="235">Option 1</option>',
					'<option value="237">Option 2</option>',
				'</select>',
				'<select id="picker-filter-payments" class="input-bx select-bx" style="flex:1;min-width:140px">',
					'<option value="">All Payments</option>',
					'<option value="193">Annually</option><option value="195">One Time</option>',
					'<option value="197">Quarterly</option><option value="199">Every 2 years</option>',
					'<option value="201">Monthly</option>',
				'</select>',
			'</div>',
			'<div id="picker-table-wrap" style="overflow-y:auto;flex:1;border:1px solid #e2e5ec;border-radius:4px;">',
				'<table style="width:100%;border-collapse:collapse;font-size:12px;">',
					'<thead><tr style="background:#f7f9fa;position:sticky;top:0;z-index:1">',
						'<th style="padding:8px 10px;width:36px;border-bottom:1px solid #e2e5ec"><input type="checkbox" id="picker-check-all"></th>',
						'<th style="padding:8px 10px;text-align:left;color:#828b95;font-weight:600;border-bottom:1px solid #e2e5ec">Name</th>',
						'<th style="padding:8px 10px;text-align:left;color:#828b95;font-weight:600;border-bottom:1px solid #e2e5ec;width:90px">Price</th>',
						'<th style="padding:8px 10px;text-align:left;color:#828b95;font-weight:600;border-bottom:1px solid #e2e5ec;width:80px">Option</th>',
						'<th style="padding:8px 10px;text-align:left;color:#828b95;font-weight:600;border-bottom:1px solid #e2e5ec;width:130px">Type of Cost</th>',
						'<th style="padding:8px 10px;text-align:left;color:#828b95;font-weight:600;border-bottom:1px solid #e2e5ec;width:120px">Payments</th>',
					'</tr></thead>',
					'<tbody id="picker-tbody"></tbody>',
				'</table>',
			'</div>',
			'<div style="display:flex;justify-content:space-between;align-items:center;padding-top:8px;border-top:1px solid #e2e5ec">',
				'<span id="picker-count" style="font-size:12px;color:#828b95">0 selected</span>',
				'<div style="display:flex;gap:8px">',
					'<button id="picker-add-selected" class="btn-primary-bx" disabled>Add Selected</button>',
					'<button id="picker-cancel" class="btn-secondary-bx">Cancel</button>',
				'</div>',
			'</div>'
		].join('');

		overlay.appendChild(modal);
		document.body.appendChild(overlay);

		var selected = {};

		function updateCount() {
			var n = Object.keys(selected).length;
			document.getElementById('picker-count').textContent = n + ' selected';
			document.getElementById('picker-add-selected').disabled = (n === 0);
		}

		function renderPickerRows() {
			var query      = document.getElementById('picker-search').value.toLowerCase();
			var filterType = document.getElementById('picker-filter-type').value;
			var filterOpt  = document.getElementById('picker-filter-option').value;
			var filterPay  = document.getElementById('picker-filter-payments').value;
			var tbody2     = document.getElementById('picker-tbody');
			tbody2.innerHTML = '';

			var filtered = state.productCatalog.filter(function (p) {
				var name = (p.NAME || p.name || '').toLowerCase();
				var toc  = String(getPropValue(p, 'PROPERTY_111') || '');
				var pay  = String(getPropValue(p, 'PROPERTY_109') || '');
				var opt  = String(getPropValue(p, 'PROPERTY_119') || '');
				if (query      && name.indexOf(query) === -1) return false;
				if (filterType && toc !== filterType)         return false;
				if (filterOpt  && opt !== filterOpt)          return false;
				if (filterPay  && pay !== filterPay)          return false;
				return true;
			});

			if (filtered.length === 0) {
				tbody2.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:24px;color:#a8adb2">No products found</td></tr>';
				return;
			}

			filtered.forEach(function (p) {
				var pid   = String(p.ID || p.id);
				var pname = p.NAME || p.name || '';
				var price = parseFloat(p.PRICE || p.price || 0).toFixed(2);
				var toc   = PROP_TYPE_OF_COST[getPropValue(p, 'PROPERTY_111')] || '—';
				var pay   = PROP_PAYMENTS[getPropValue(p, 'PROPERTY_109')]     || '—';
				var optId = getPropValue(p, 'PROPERTY_119');
				var optLabel = PROP_OPTIONS[optId] || '—';
				var isSel = !!selected[pid];

				var tr2 = document.createElement('tr');
				tr2.style.cssText = 'border-bottom:1px solid #eef2f4;cursor:pointer;transition:background 0.1s;' + (isSel ? 'background:#f0f8ff;' : '');

				var optBadge = optId === '237'
					? '<span class="option-badge option-badge-2">Opt 2</span>'
					: (optId === '235' ? '<span class="option-badge option-badge-1">Opt 1</span>' : '<span style="color:#a8adb2">—</span>');

				tr2.innerHTML = [
					'<td style="padding:8px 10px;text-align:center"><input type="checkbox" data-pid="' + pid + '"' + (isSel ? ' checked' : '') + '></td>',
					'<td style="padding:8px 10px;font-weight:600;color:#333">' + escHtml(pname) + '</td>',
					'<td style="padding:8px 10px;color:#535c69">Dh ' + price + '</td>',
					'<td style="padding:8px 10px">' + optBadge + '</td>',
					'<td style="padding:8px 10px;color:#535c69">' + escHtml(toc) + '</td>',
					'<td style="padding:8px 10px;color:#535c69">' + escHtml(pay) + '</td>',
				].join('');

				var cb = tr2.querySelector('input[type=checkbox]');
				cb.addEventListener('change', function (e) {
					if (e.target.checked) { selected[pid] = p; tr2.style.background = '#f0f8ff'; }
					else { delete selected[pid]; tr2.style.background = ''; }
					updateCount();
				});
				tr2.addEventListener('click', function (e) {
					if (e.target.tagName === 'INPUT') return;
					cb.checked = !cb.checked;
					cb.dispatchEvent(new Event('change'));
				});
				tbody2.appendChild(tr2);
			});
		}

		renderPickerRows();

		document.getElementById('picker-search').addEventListener('input', renderPickerRows);
		document.getElementById('picker-filter-type').addEventListener('change', renderPickerRows);
		document.getElementById('picker-filter-option').addEventListener('change', renderPickerRows);
		document.getElementById('picker-filter-payments').addEventListener('change', renderPickerRows);

		document.getElementById('picker-check-all').addEventListener('change', function (e) {
			document.querySelectorAll('#picker-tbody input[type=checkbox]').forEach(function (cb) {
				if (cb.checked !== e.target.checked) {
					cb.checked = e.target.checked;
					cb.dispatchEvent(new Event('change'));
				}
			});
		});

		document.getElementById('picker-close').addEventListener('click',  function () { overlay.remove(); });
		document.getElementById('picker-cancel').addEventListener('click', function () { overlay.remove(); });
		overlay.addEventListener('click', function (e) { if (e.target === overlay) overlay.remove(); });

		document.getElementById('picker-add-selected').addEventListener('click', function () {
			Object.values(selected).forEach(function (p) {
				var toc = getPropValue(p, 'PROPERTY_111');
				var pay = getPropValue(p, 'PROPERTY_109');
				var opt = getPropValue(p, 'PROPERTY_119');
				var row = {
					id:          state.nextRowId++,
					productId:   String(p.ID || p.id),
					name:        p.NAME || p.name || '',
					price:       parseFloat(p.PRICE || p.price || 0),
					qty:         1,
					taxRate:     0,
					taxIncluded: false,
					typeOfCost:  toc,
					payments:    pay,
					option:      opt,
					sort:        state.rows.length * 10,
					spaId:       null
				};
				log('Added from catalog: ' + row.name + ', typeOfCost=' + row.typeOfCost + ', option=' + row.option);
				state.rows.push(row);
				var tbody3 = document.getElementById('product-rows-body');
				if (tbody3) tbody3.appendChild(buildRowEl(row, state.rows.length, state.rows.length - 1));
			});
			recalcTotals();
			overlay.remove();
		});
	}

	// ─── Product Edit / Create Modal ──────────────────────────────────────────
	function showProductEditModal(productId) {
		var product = productId ? findCatalogProduct(productId) : null;
		var isNew   = !product;

		var overlay = document.createElement('div');
		overlay.id = 'product-edit-modal';
		overlay.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.45);z-index:10000;display:flex;align-items:center;justify-content:center;';

		var modal = document.createElement('div');
		modal.style.cssText = 'background:#fff;border-radius:8px;padding:24px;width:540px;max-width:95vw;box-shadow:0 8px 32px rgba(0,0,0,0.18);font-family:"Open Sans",Arial,sans-serif;font-size:13px;display:flex;flex-direction:column;gap:14px;';

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

				'<div style="display:flex;flex-direction:column;gap:4px">',
					'<label style="font-weight:600;color:#535c69">Company Application Type</label>',
					'<select id="ep-company-type" class="input-bx select-bx">' +
						buildEnumOpts([
							{id:'155',label:'Mainland LLC'},{id:'157',label:'Free Zone'},{id:'159',label:'Branch DET'},
							{id:'161',label:'Branch FZ'},{id:'163',label:'Representative Office DET'},
							{id:'165',label:'Representative Office FZ'},{id:'167',label:'Freelance License'}
						], product ? getPropValue(product, 'PROPERTY_99') : '') +
					'</select>',
				'</div>',

				'<div style="display:flex;flex-direction:column;gap:4px">',
					'<label style="font-weight:600;color:#535c69">Visa Type</label>',
					'<select id="ep-visa-type" class="input-bx select-bx">' +
						buildEnumOpts([
							{id:'171',label:'Investor Visa'},{id:'173',label:'Employment Visa'},{id:'175',label:'Golden Visa'},
							{id:'177',label:'Property Visa'},{id:'179',label:'Talent Visa'},
							{id:'181',label:'Influencer Visa'},{id:'183',label:'Family Visa'}
						], product ? getPropValue(product, 'PROPERTY_101') : '') +
					'</select>',
				'</div>',

				'<div style="display:flex;flex-direction:column;gap:4px">',
					'<label style="font-weight:600;color:#535c69">Visa Status</label>',
					'<select id="ep-visa-status" class="input-bx select-bx">' +
						buildEnumOpts([
							{id:'187',label:'New'},{id:'189',label:'Renewal'},{id:'191',label:'Not Specified'}
						], product ? getPropValue(product, 'PROPERTY_103') : '') +
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

			var toc   = document.getElementById('ep-type-of-cost').value;
			var pay   = document.getElementById('ep-payments').value;
			var opt   = document.getElementById('ep-option').value;
			var ct    = document.getElementById('ep-company-type').value;
			var vt    = document.getElementById('ep-visa-type').value;
			var vs    = document.getElementById('ep-visa-status').value;
			var price = parseFloat(document.getElementById('ep-price').value) || 0;

			var btn = document.getElementById('ep-save');
			btn.disabled = true; btn.textContent = 'Saving…';

			if (isNew) {
				function doCreate(iblockId) {
					if (!iblockId) {
						alert('Could not load catalog ID from Bitrix24. Please check your app permissions and try again.');
						btn.disabled = false; btn.textContent = 'Create Product';
						return;
					}
					var addFields = { iblockId: iblockId, name: name, active: 'Y' };
					if (toc) addFields['property111'] = toc;
					if (pay) addFields['property109'] = pay;
					if (opt) addFields['property119'] = opt;
					if (ct)  addFields['property99']  = ct;
					if (vt)  addFields['property101'] = vt;
					if (vs)  addFields['property103'] = vs;

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
						});
					});
				}

				if (state.iblockId) {
					doCreate(state.iblockId);
				} else {
					log('iblockId not cached — fetching on-demand…');
					BX24.callMethod('catalog.catalog.list', { select: ['id', 'iblockId', 'iblockTypeId', 'productIblockId'] }, function (res) {
						if (!res.error()) {
							var catalogs = extractCatalogs(res.data());
							var id = pickIblockId(catalogs);
							if (id) { state.iblockId = id; log('iblockId fetched on-demand: ' + id); }
							else    { log('on-demand fetch: no usable catalog'); }
						} else {
							log('catalog.catalog.list error: ' + res.error());
						}
						doCreate(state.iblockId);
					});
				}
				return;

			} else {
				BX24.callMethod('catalog.product.get', { id: productId }, function (getRes) {
					var prod = (!getRes.error() && getRes.data()) ? (getRes.data().element || getRes.data()) : null;

					function getVid(p, key) {
						if (!p) return 0;
						var v = p[key];
						return (v && v.valueId) ? v.valueId : 0;
					}

					var updFields = { name: name, active: 'Y' };
					if (toc) updFields['property111'] = { value: toc, valueId: getVid(prod, 'property111') };
					if (pay) updFields['property109'] = { value: pay, valueId: getVid(prod, 'property109') };
					if (opt) updFields['property119'] = { value: opt, valueId: getVid(prod, 'property119') };
					if (ct)  updFields['property99']  = { value: ct,  valueId: getVid(prod, 'property99')  };
					if (vt)  updFields['property101'] = { value: vt,  valueId: getVid(prod, 'property101') };
					if (vs)  updFields['property103'] = { value: vs,  valueId: getVid(prod, 'property103') };

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

	// ─── Save & Sync ──────────────────────────────────────────────────────────
	function saveAndSync() {
		recalcTotals();
		var rows = state.rows.filter(function (r) { return r.name || r.productId; });

		if (rows.length === 0) {
			setStatus('⚠ No products to save', 'status-warning');
			return;
		}

		console.log('[FeeSyncWidget] Pre-save rows:', JSON.stringify(rows.map(function(r){
			return { id: r.id, name: r.name, typeOfCost: r.typeOfCost, option: r.option, payments: r.payments, price: r.price };
		})));

		showSaveModal();
		log('Saving ' + rows.length + ' row(s) to ' + state.entityType + ' #' + state.entityId);

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
				closeSaveModal(false, 'Save failed');
				setStatus('✗ Error saving products', 'status-danger');
				log('Error: ' + res.error());
				return;
			}
			log('Product rows saved OK');

			updateCatalogProducts(rows, function () {
				var totalAmt = rows.reduce(function (sum, r) { return sum + r.price * r.qty; }, 0);
				updateEntityOpportunity(totalAmt, function () {
					syncSpaItems(rows, function () {
						updateEntityStatus(function () {
							closeSaveModal(true, rows.length + ' product' + (rows.length !== 1 ? 's' : ''));
							setStatus('✓ Saved & synced', 'status-success');
							log('All done');
						});
					});
				});
			});
		});
	}

	function showSaveModal() {
		var overlay = document.getElementById('save-modal-overlay');
		var icon    = document.getElementById('save-modal-icon');
		var title   = document.getElementById('save-modal-title');
		var message = document.getElementById('save-modal-message');
		document.getElementById('save-modal-details').innerHTML = '';
		icon.textContent    = '⚙️';
		icon.style.cssText  = 'font-size:48px;margin-bottom:16px;display:block;animation:spin 1s linear infinite';
		title.textContent   = 'Saving Changes…';
		message.textContent = 'Please wait while we update your data';
		if (overlay) overlay.classList.add('visible');
	}

	function closeSaveModal(success, detail) {
		setTimeout(function () {
			var overlay    = document.getElementById('save-modal-overlay');
			var icon       = document.getElementById('save-modal-icon');
			var title      = document.getElementById('save-modal-title');
			var message    = document.getElementById('save-modal-message');
			var detailsDiv = document.getElementById('save-modal-details');

			icon.style.animation = 'none';
			if (success) {
				icon.textContent    = '✅';
				title.textContent   = 'All Changes Saved!';
				message.textContent = 'Your products have been synced successfully.';
				detailsDiv.innerHTML = '<div class="detail-row"><span>Products saved:</span><strong>' + detail + '</strong></div>';
			} else {
				icon.textContent    = '❌';
				title.textContent   = detail || 'Save Failed';
				message.textContent = 'Please try again or contact support.';
				detailsDiv.innerHTML = '';
			}

			setTimeout(function () {
				if (overlay) overlay.classList.remove('visible');
			}, 2000);
		}, 600);
	}

	// ─── Update catalog products ──────────────────────────────────────────────
	function updateCatalogProducts(rows, cb) {
		var toUpdate = rows.filter(function (r) {
			return r.productId && (r.typeOfCost || r.payments || r.option);
		});

		if (toUpdate.length === 0) { log('No catalog products to update'); if (cb) cb(); return; }
		log('Updating ' + toUpdate.length + ' catalog product(s)');

		var pending = toUpdate.length;

		toUpdate.forEach(function (row) {
			BX24.callMethod('catalog.product.get', { id: row.productId }, function (getRes) {
				var fields = {};
				var prod   = (!getRes.error() && getRes.data()) ? (getRes.data().element || getRes.data()) : null;

				function getVid(p, key) {
					if (!p) return 0;
					var v = p[key];
					return (v && v.valueId) ? v.valueId : 0;
				}

				if (row.typeOfCost) fields['property111'] = { value: String(row.typeOfCost), valueId: getVid(prod, 'property111') };
				if (row.payments)   fields['property109'] = { value: String(row.payments),   valueId: getVid(prod, 'property109') };
				if (row.option)     fields['property119'] = { value: String(row.option),     valueId: getVid(prod, 'property119') };

				log('catalog.product.update #' + row.productId + ' fields=' + JSON.stringify(fields));

				BX24.callMethod('catalog.product.update', { id: row.productId, fields: fields }, function (res) {
					if (res.error()) log('Error updating product #' + row.productId + ': ' + res.error());
					else log('Product #' + row.productId + ' updated OK');
					if (--pending === 0 && cb) cb();
				});
			});
		});
	}

	function updateEntityOpportunity(amount, cb) {
		var method = state.entityType === 'deal' ? 'crm.deal.update' : 'crm.lead.update';
		BX24.callMethod(method, {
			id: state.entityId,
			fields: { OPPORTUNITY: amount, IS_MANUAL_OPPORTUNITY: 'Y' }
		}, function (res) {
			if (res.error()) log('Opportunity update error: ' + res.error());
			else log('Opportunity → ' + amount.toFixed(2));
			if (cb) cb();
		});
	}

	// ─── Set status field to "Done" ───────────────────────────────────────────
	function updateEntityStatus(cb) {
		var field  = STATUS_FIELDS[state.entityType];
		var method = state.entityType === 'deal' ? 'crm.deal.update' : 'crm.lead.update';
		if (!field) { log('No status field for entity type: ' + state.entityType); if (cb) cb(); return; }

		var fields = {};
		fields[field] = 'Done';

		BX24.callMethod(method, { id: state.entityId, fields: fields }, function (res) {
			if (res.error()) log('Error updating status field ' + field + ': ' + res.error());
			else log('Status field ' + field + ' → Done');
			if (cb) cb();
		});
	}

	// ─── SPA Sync ─────────────────────────────────────────────────────────────
	function syncSpaItems(rows, cb) {
		log('Calling backend SPA Sync API...');
		var authData = typeof BX24 !== 'undefined' ? BX24.getAuth() : null;
		var payload = {
			entityType: state.entityType,
			entityId: state.entityId,
			action: 'sync',
			auth: authData
		};
		console.log('[FeeSyncWidget] SPA Sync Request Payload:', payload);
		fetch('api/sync.php', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify(payload)
		})
		.then(function (res) { return res.json(); })
		.then(function (data) {
			console.log('[FeeSyncWidget] SPA Sync Response Data:', data);
			if (data && data.debug_logs) {
				console.log('[FeeSyncWidget] Backend Execution Logs:', data.debug_logs);
			}
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
					else { log('SPA #' + row.spaId + ' updated (type ' + entityTypeId + ')'); spaIds.push(row.spaId); }
					if (--pending === 0) linkSpaToEntity(spaIds, linkField, cb);
				});
			} else {
				var createFields = Object.assign({ TITLE: row.name, OPPORTUNITY: row.price * row.qty }, fields);
				BX24.callMethod('crm.item.add', {
					entityTypeId: entityTypeId,
					fields: createFields
				}, function (res) {
					if (res.error()) {
						log('SPA create error (type ' + entityTypeId + '): ' + res.error());
					} else {
						var newId = (res.data() && res.data().item) ? res.data().item.id : null;
						if (newId) { row.spaId = newId; spaIds.push(newId); log('SPA created #' + newId + ' (type ' + entityTypeId + ')'); }
					}
					if (--pending === 0) linkSpaToEntity(spaIds, linkField, cb);
				});
			}
		});
	}

	// Link collected SPA IDs to the current entity's fee field
	function linkSpaToEntity(spaIds, linkField, cb) {
		if (!linkField || spaIds.length === 0) { if (cb) cb(); return; }

		var method = state.entityType === 'deal' ? 'crm.deal.update' : 'crm.lead.update';
		var updateFields = {};
		updateFields[linkField] = spaIds;

		BX24.callMethod(method, { id: state.entityId, fields: updateFields }, function (res) {
			if (res.error()) log('SPA link error (field ' + linkField + '): ' + res.error());
			else log(state.entityType + ' #' + state.entityId + ' linked to SPA IDs [' + spaIds.join(',') + '] via ' + linkField);
			if (cb) cb();
		});
	}

	function buildSpaFields(row, fieldMap) {
		var fields = {};
		if (row.typeOfCost && fieldMap.typeOfCost) {
			var tocVal = mapSpaEnumValueById(fieldMap.typeOfCost, row.typeOfCost);
			if (tocVal !== undefined) fields[fieldMap.typeOfCost] = tocVal;
			else log('WARN: SPA typeOfCost mapping missing for ' + row.typeOfCost + ' on field ' + fieldMap.typeOfCost);
		}
		if (row.payments && fieldMap.payments) {
			var payVal = mapSpaEnumValueById(fieldMap.payments, row.payments);
			if (payVal !== undefined) fields[fieldMap.payments] = payVal;
			else log('WARN: SPA payments mapping missing for ' + row.payments + ' on field ' + fieldMap.payments);
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

	// ─── Bind actions ─────────────────────────────────────────────────────────
	function bindActions() {
		function rebind(id, handler) {
			var el = document.getElementById(id);
			if (!el) return;
			var clone = el.cloneNode(true);
			el.parentNode.replaceChild(clone, el);
			clone.addEventListener('click', handler);
		}
		rebind('btn-add-product',    addEmptyRow);
		rebind('btn-select-product', showProductPickerModal);
		rebind('btn-save',           saveAndSync);
		rebind('btn-edit-product',   function () { showProductEditModal(null); });
	}

	// ─── Helpers ──────────────────────────────────────────────────────────────
	function escHtml(s) {
		return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
	}

	return { init: init };
})();

// ─── BX24 Bootstrap ──────────────────────────────────────────────────────────
BX24.init(function () {
	BX24.fitWindow();

	var initialLoading  = document.getElementById('initial-loading');
	var entityTypeLabel = document.getElementById('entity-type-label');
	var entityIdLabel   = document.getElementById('entity-id-label');

	initialLoading.classList.add('visible');

	var info = BX24.placement.info();
	var detectedType = null;
	var detectedId   = null;

	if (info && info.placement) {
		if (info.placement.indexOf('DEAL') !== -1)      detectedType = 'deal';
		else if (info.placement.indexOf('LEAD') !== -1) detectedType = 'lead';
		if (info.options && info.options.ID) detectedId = parseInt(info.options.ID);
	}

	if (detectedType && detectedId) {
		entityTypeLabel.textContent = (detectedType === 'deal' ? 'Deal ' : 'Lead ');
		entityIdLabel.textContent   = '#' + detectedId;

		FeeSyncWidget.init(detectedType, detectedId, function () {
			initialLoading.classList.remove('visible');
		});
	} else {
		initialLoading.classList.remove('visible');
		var st = document.getElementById('sync-status');
		st.textContent = '✗ Error: Could not detect Deal/Lead context';
		st.className   = 'status-danger';
	}
});
</script>
</body>
</html>