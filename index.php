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

		/* ── Container ─────────────────────────────────────────── */
		.widget-container {
			background: #ffffff;
			border-radius: 8px;
			box-shadow: 0 2px 6px rgba(0,0,0,0.04);
			padding: 20px;
			border: 1px solid #e2e5ec;
			max-width: 1300px;
			margin: 0 auto;
			position: relative;
		}

		/* ── Header ────────────────────────────────────────────── */
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

		/* ── Action bar ────────────────────────────────────────── */
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

		/* ── Buttons ───────────────────────────────────────────── */
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

		.btn-primary-bx:hover {
			background-color: #0066cc;
			box-shadow: 0 2px 8px rgba(0, 128, 255, 0.2);
		}

		.btn-primary-bx:active {
			background-color: #0055aa;
			transform: scale(0.98);
		}

		.btn-primary-bx:disabled {
			background-color: #c6cdd3;
			cursor: not-allowed;
			opacity: 0.6;
		}

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

		.btn-secondary-bx:hover {
			background-color: #f5f7f8;
			border-color: #a8adb2;
			box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06);
		}

		.btn-secondary-bx:active {
			background-color: #eef2f4;
			transform: scale(0.98);
		}

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
			box-shadow: 0 2px 6px rgba(47, 198, 246, 0.15);
		}

		.btn-save-sync:hover {
			background: linear-gradient(135deg, #18b0e0 0%, #0a95cc 100%);
			box-shadow: 0 4px 12px rgba(47, 198, 246, 0.25);
			transform: translateY(-1px);
		}

		.btn-save-sync:active {
			transform: translateY(0);
		}

		.btn-save-sync:disabled {
			opacity: 0.6;
			cursor: not-allowed;
		}

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

		.btn-delete:hover {
			color: #ff5050;
			background: #fff0f0;
		}

		/* ── Inputs ────────────────────────────────────────────── */
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

		.input-bx:focus {
			border-color: #0080ff;
			box-shadow: 0 0 0 3px rgba(0, 128, 255, 0.1);
		}

		.input-bx-wrapper {
			position: relative;
			display: flex;
			align-items: center;
		}

		.input-bx-suffix {
			position: absolute;
			right: 8px;
			color: #a8adb2;
			pointer-events: none;
			font-size: 12px;
		}

		.input-bx-with-suffix {
			padding-right: 30px !important;
		}

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

		/* ── Product table ─────────────────────────────────────── */
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
			margin-bottom: 0;
		}

		.product-table {
			width: 100%;
			border-collapse: collapse;
			text-align: left;
			min-width: 900px;
		}

		.product-table thead tr {
			background-color: #f7f9fa;
		}

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

		.product-table tbody tr {
			transition: all 0.15s ease;
			background-color: #fff;
		}

		.product-table tbody tr:hover {
			background-color: #fafbfc;
			box-shadow: inset 0 0 0 1px #f0f2f5;
		}

		.row-number {
			color: #a8adb2;
			display: flex;
			align-items: center;
			gap: 6px;
			font-weight: 600;
			font-size: 12px;
		}

		.drag-handle {
			cursor: grab;
			color: #d0d4da;
			font-size: 16px;
			user-select: none;
			transition: color 0.2s ease;
		}

		tr:hover .drag-handle {
			color: #0080ff;
		}

		.drag-handle:active {
			cursor: grabbing;
		}

		/* ── Totals ────────────────────────────────────────────── */
		.totals-container {
			display: flex;
			justify-content: flex-end;
			margin-top: 24px;
		}

		.totals-box {
			width: 340px;
			background: linear-gradient(135deg, #f7f9fa 0%, #f0f2f5 100%);
			border: 1px solid #e2e5ec;
			border-radius: 6px;
			padding: 16px;
		}

		.total-row {
			display: flex;
			justify-content: space-between;
			align-items: center;
			padding: 8px 0;
			color: #828b95;
			font-size: 13px;
		}

		.total-row strong {
			color: #535c69;
			font-weight: 600;
		}

		.total-row.grand-total {
			border-top: 2px solid #e2e5ec;
			margin-top: 10px;
			padding-top: 14px;
			color: #222;
			font-size: 15px;
			font-weight: 700;
		}

		.total-row.grand-total strong {
			color: #0080ff;
			font-size: 17px;
		}

		/* ── Status ────────────────────────────────────────────── */
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

		.status-left {
			display: flex;
			flex-direction: column;
			gap: 8px;
			flex: 1;
			min-width: 0;
		}

		#sync-status {
			display: inline-block;
			font-weight: 600;
			padding: 6px 12px;
			border-radius: 4px;
			font-size: 12px;
			animation: fadeIn 0.3s ease;
		}

		.status-info {
			color: #0066cc;
			background: #e5f2ff;
		}

		.status-success {
			color: #155724;
			background: #d4edda;
		}

		.status-warning {
			color: #856404;
			background: #fff3cd;
		}

		.status-danger {
			color: #721c24;
			background: #f8d7da;
		}

		@keyframes fadeIn {
			from {
				opacity: 0;
				transform: translateY(-4px);
			}
			to {
				opacity: 1;
				transform: translateY(0);
			}
		}

		/* ── Loading overlay ───────────────────────────────────── */
		#initial-loading {
			display: none;
			position: absolute;
			inset: 0;
			background: rgba(255, 255, 255, 0.9);
			border-radius: 8px;
			align-items: center;
			justify-content: center;
			z-index: 50;
		}

		#initial-loading.visible {
			display: flex;
		}

		.loading-spinner {
			display: flex;
			flex-direction: column;
			align-items: center;
			gap: 16px;
		}

		.spinner {
			width: 32px;
			height: 32px;
			border: 3px solid #e2e5ec;
			border-top-color: #0080ff;
			border-radius: 50%;
			animation: spin 0.8s linear infinite;
		}

		.loading-text {
			font-weight: 600;
			color: #535c69;
			font-size: 14px;
		}

		@keyframes spin {
			to {
				transform: rotate(360deg);
			}
		}

		/* ── Save Modal Overlay ────────────────────────────────– */
		#save-modal-overlay {
			display: none;
			position: fixed;
			top: 0;
			left: 0;
			right: 0;
			bottom: 0;
			background: rgba(0, 0, 0, 0.4);
			z-index: 9999;
			align-items: center;
			justify-content: center;
		}

		#save-modal-overlay.visible {
			display: flex;
		}

		.save-modal {
			background: #fff;
			border-radius: 8px;
			padding: 48px 40px;
			text-align: center;
			max-width: 360px;
			box-shadow: 0 16px 48px rgba(0, 0, 0, 0.15);
			animation: slideUp 0.3s ease;
		}

		@keyframes slideUp {
			from {
				opacity: 0;
				transform: translateY(16px);
			}
			to {
				opacity: 1;
				transform: translateY(0);
			}
		}

		.save-modal-icon {
			font-size: 48px;
			margin-bottom: 16px;
			display: block;
		}

		.save-modal-icon.saving {
			animation: spin 1s linear infinite;
		}

		.save-modal-icon.success {
			animation: popIn 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
		}

		@keyframes popIn {
			0% {
				opacity: 0;
				transform: scale(0.3);
			}
			100% {
				opacity: 1;
				transform: scale(1);
			}
		}

		.save-modal-title {
			font-size: 16px;
			font-weight: 700;
			color: #222;
			margin: 16px 0 8px 0;
		}

		.save-modal-message {
			font-size: 13px;
			color: #828b95;
			margin: 0;
			line-height: 1.5;
		}

		.save-modal-details {
			margin-top: 20px;
			padding-top: 20px;
			border-top: 1px solid #e2e5ec;
			text-align: left;
		}

		.detail-row {
			display: flex;
			justify-content: space-between;
			padding: 6px 0;
			font-size: 12px;
			color: #828b95;
		}

		.detail-row strong {
			color: #535c69;
		}

		/* ── Responsive ────────────────────────────────────────– */
		@media (max-width: 1024px) {
			.widget-container {
				padding: 16px;
			}

			.action-bar {
				flex-direction: column;
				align-items: flex-start;
			}

			.left-actions {
				width: 100%;
			}

			.btn-primary-bx,
			.btn-secondary-bx {
				flex: 1;
			}

			.totals-container {
				margin-top: 16px;
			}
		}

		@media (max-width: 600px) {
			body {
				padding: 8px;
			}

			.widget-container {
				padding: 12px;
			}

			.widget-title {
				font-size: 16px;
			}

			.action-bar {
				gap: 8px;
			}

			.btn-primary-bx,
			.btn-secondary-bx,
			.btn-save-sync {
				padding: 6px 12px;
				font-size: 12px;
			}

			.product-table th,
			.product-table td {
				padding: 8px;
				font-size: 11px;
			}

			.totals-box {
				width: 100%;
			}
		}
	</style>
</head>
<body>
	<div class="widget-container">

		<!-- Initial Loading Overlay -->
		<div id="initial-loading">
			<div class="loading-spinner">
				<div class="spinner"></div>
				<div class="loading-text">Preparing widget…</div>
			</div>
		</div>

		<!-- Save Modal Overlay -->
		<div id="save-modal-overlay">
			<div class="save-modal">
				<span id="save-modal-icon" class="save-modal-icon saving">⚙️</span>
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
				<button id="btn-add-product" class="btn-primary-bx">+ Add Product</button>
				<button id="btn-select-product" class="btn-secondary-bx">Select from Catalog</button>
				<button id="btn-edit-product" class="btn-secondary-bx">Create New Product</button>
			</div>
			<button id="btn-save" class="btn-save-sync">💾 Save & Sync</button>
		</div>

		<!-- Product Table -->
		<div class="section-label">Products (Drag to reorder)</div>
		<div class="table-responsive">
			<table class="product-table">
				<thead>
					<tr>
						<th style="width:50px;">#</th>
						<th>Product Name</th>
						<th style="width:44px;"></th>
						<th style="width:170px;">Type of Cost</th>
						<th style="width:140px;">Price</th>
						<th style="width:140px;">Payments</th>
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
				<div class="total-row">
					<span>Subtotal (no tax):</span>
					<strong id="total-raw">Dh 0.00</strong>
				</div>
				<div class="total-row">
					<span>Delivery:</span>
					<strong>Dh 0.00</strong>
				</div>
				<div class="total-row">
					<span>Discount:</span>
					<strong>Dh 0.00</strong>
				</div>
				<div class="total-row">
					<span>Total before tax:</span>
					<strong id="total-before-tax">Dh 0.00</strong>
				</div>
				<div class="total-row">
					<span>Tax total:</span>
					<strong id="total-tax">Dh 0.00</strong>
				</div>
				<div class="total-row grand-total">
					<span>Total amount:</span>
					<strong id="total-amount">Dh 0.00</strong>
				</div>
			</div>
		</div>

		<!-- Status Footer -->
		<div class="status-footer">
			<div class="status-left">
				<div id="sync-status" class="status-info">✓ Ready to save</div>
			</div>
		</div>

	</div>

	<!-- Main Script -->
	<script>
		var FeeSyncWidget = (function () {

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

			function setStatus(msg, cls) {
				var el = document.getElementById('sync-status');
				if (!el) return;
				el.textContent = msg;
				el.className = cls || 'status-info';
			}

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

				setStatus('✓ Ready to save', 'status-info');

				fetchIblockId(function () {
					loadCatalogProducts(function () {
						loadEntityProducts(function () {
							renderRows();
							bindActions();
							if (typeof onReady === 'function') onReady();
						});
					});
				});
			}

			function fetchIblockId(cb) {
				BX24.callMethod('catalog.catalog.list', { select: ['ID', 'IBLOCK_TYPE_ID'] }, function (res) {
					if (!res.error()) {
						var catalogs = res.data() || [];
						var catalog = catalogs[0] || null;
						if (catalog) {
							state.iblockId = catalog.id || catalog.ID || null;
						}
					}
					if (cb) cb();
				});
			}

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
						if (cb) cb();
					}
				});
			}

			function loadEntityProducts(cb) {
				var method = state.entityType === 'deal'
					? 'crm.deal.productrows.get'
					: 'crm.lead.productrows.get';

				BX24.callMethod(method, { id: state.entityId }, function (res) {
					if (res.error()) {
						if (cb) cb();
						return;
					}
					var rows = res.data() || [];
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
					sort:        parseInt(r.SORT || 0),
					spaId:       null,
					_entityRow:  r
				};
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
					'<td></td>',
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
					var draggedRowObj = state.rows.find(function (r) { return r.id === state.draggedRow; });
					if (!draggedRowObj) return;
					state.rows.splice(state.draggedIndex, 1);
					state.rows.splice(index, 0, draggedRowObj);
					tr.style.borderTop = '';
					renderRows();
				});

				return tr;
			}

			function buildEnumOpts(items, currentVal) {
				var html = '<option value="">-- select --</option>';
				var normalizedCurrent = String(currentVal || '').trim();
				items.forEach(function (item) {
					var sel = (String(item.id).trim() === normalizedCurrent) ? ' selected' : '';
					html += '<option value="' + item.id + '"' + sel + '>' + escHtml(item.label) + '</option>';
				});
				return html;
			}

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

			function addEmptyRow() {
				var row = {
					id: state.nextRowId++, productId: '', name: '',
					price: 0, qty: 1, taxRate: 0, taxIncluded: false,
					typeOfCost: '', payments: '',
					sort: state.rows.length * 10, spaId: null
				};
				state.rows.push(row);
				var tbody = document.getElementById('product-rows-body');
				if (tbody) tbody.appendChild(buildRowEl(row, state.rows.length, state.rows.length - 1));
				recalcTotals();
			}

			function recalcTotals() {
				var trs = document.querySelectorAll('#product-rows-body tr');
				trs.forEach(function (tr2) {
					var rowId = parseInt(tr2.getAttribute('data-row-id'));
					var row = findRow(rowId);
					if (!row) return;

					var priceEl = tr2.querySelector('.js-price');
					var taxEl = tr2.querySelector('.js-tax');
					var nameEl = tr2.querySelector('.js-product-name');
					var tocEl = tr2.querySelector('.js-type-of-cost');
					var payEl = tr2.querySelector('.js-payments');

					if (priceEl) row.price = parseFloat(priceEl.value) || 0;
					if (taxEl) row.taxRate = parseFloat(taxEl.value) || 0;
					if (nameEl) row.name = nameEl.value;
					if (tocEl && tocEl.value) row.typeOfCost = tocEl.value;
					if (payEl && payEl.value) row.payments = payEl.value;

					var amtEl = tr2.querySelector('.js-amount');
					if (amtEl) amtEl.value = (row.price * row.qty).toFixed(2);
				});

				var raw = 0, taxTotal = 0;
				state.rows.forEach(function (r) {
					var base = r.price * r.qty;
					raw += base;
					taxTotal += base * (r.taxRate / 100);
				});

				setText('total-raw', 'Dh ' + raw.toFixed(2));
				setText('total-before-tax', 'Dh ' + raw.toFixed(2));
				setText('total-tax', 'Dh ' + taxTotal.toFixed(2));
				setText('total-amount', 'Dh ' + (raw + taxTotal).toFixed(2));
			}

			function setText(id, val) {
				var el = document.getElementById(id);
				if (el) el.textContent = val;
			}

			function saveAndSync() {
				recalcTotals();
				var rows = state.rows.filter(function (r) { return r.name || r.productId; });
				
				if (rows.length === 0) {
					setStatus('⚠ No products to save', 'status-warning');
					return;
				}

				showSaveModal();

				var method = state.entityType === 'deal'
					? 'crm.deal.productrows.set'
					: 'crm.lead.productrows.set';

				var productRows = rows.map(function (r, idx) {
					return {
						PRODUCT_ID: r.productId || 0,
						PRODUCT_NAME: r.name,
						PRICE: r.price,
						QUANTITY: r.qty,
						TAX_RATE: r.taxRate,
						TAX_INCLUDED: r.taxIncluded ? 'Y' : 'N',
						SORT: (idx + 1) * 10
					};
				});

				BX24.callMethod(method, { id: state.entityId, rows: productRows }, function (res) {
					if (res.error()) {
						closeSaveModal(false, 'Save failed');
						setStatus('✗ Error saving products', 'status-danger');
						return;
					}
					closeSaveModal(true, rows.length + ' product' + (rows.length !== 1 ? 's' : ''));
					setStatus('✓ Successfully saved', 'status-success');
				});
			}

			function showSaveModal() {
				var overlay = document.getElementById('save-modal-overlay');
				if (overlay) {
					overlay.classList.add('visible');
				}
			}

			function closeSaveModal(success, detail) {
				setTimeout(function () {
					var overlay = document.getElementById('save-modal-overlay');
					var icon = document.getElementById('save-modal-icon');
					var title = document.getElementById('save-modal-title');
					var message = document.getElementById('save-modal-message');
					var detailsDiv = document.getElementById('save-modal-details');

					if (success) {
						icon.textContent = '✓';
						icon.classList.remove('saving');
						icon.classList.add('success');
						title.textContent = 'All Changes Saved!';
						message.textContent = 'Your products have been updated successfully.';
						detailsDiv.innerHTML = '<div class="detail-row"><span>Products saved:</span><strong>' + detail + '</strong></div>';
					} else {
						icon.textContent = '✗';
						icon.classList.remove('saving');
						icon.style.color = '#ff5050';
						title.textContent = detail;
						message.textContent = 'Please try again or contact support.';
						detailsDiv.innerHTML = '';
					}

					setTimeout(function () {
						if (overlay) {
							overlay.classList.remove('visible');
						}
					}, 1800);
				}, 800);
			}

			function bindActions() {
				var btn1 = document.getElementById('btn-add-product');
				var btn2 = document.getElementById('btn-select-product');
				var btn3 = document.getElementById('btn-save');
				if (btn1) btn1.addEventListener('click', addEmptyRow);
				if (btn2) btn2.addEventListener('click', function () { alert('Catalog selector not available in this version. Use "Add Product" to create rows manually.'); });
				if (btn3) btn3.addEventListener('click', saveAndSync);
			}

			function escHtml(s) {
				return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
			}

			return { init: init };
		})();

		// Initialize on BX24 ready
		BX24.init(function () {
			BX24.fitWindow();

			var initialLoading = document.getElementById('initial-loading');
			var entityTypeLabel = document.getElementById('entity-type-label');
			var entityIdLabel = document.getElementById('entity-id-label');

			initialLoading.classList.add('visible');

			setTimeout(function () {
				var info = BX24.placement.info();
				var detectedType = null;
				var detectedId = null;

				if (info && info.placement) {
					if (info.placement.indexOf('DEAL') !== -1) detectedType = 'deal';
					else if (info.placement.indexOf('LEAD') !== -1) detectedType = 'lead';
					if (info.options && info.options.ID) detectedId = parseInt(info.options.ID);
				}

				if (detectedType && detectedId) {
					var typeLabel = detectedType === 'deal' ? 'Deal' : 'Lead';
					entityTypeLabel.textContent = typeLabel;
					entityIdLabel.textContent = '#' + detectedId;

					FeeSyncWidget.init(detectedType, detectedId, function () {
						initialLoading.classList.remove('visible');
					});
				} else {
					initialLoading.classList.remove('visible');
					document.getElementById('sync-status').textContent = '✗ Error: Could not detect Deal/Lead';
					document.getElementById('sync-status').className = 'status-danger';
				}
			}, 600);
		});
	</script>

</body>
</html>