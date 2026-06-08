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
		}

		/* ── Header with entity title ──────────────────────────── */
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
		}
		.entity-info {
			font-size: 12px;
			color: #828b95;
			font-weight: 600;
		}
		.entity-info strong {
			color: #0080ff;
		}

		/* ── Action bar ────────────────────────────────────────── */
		.action-bar {
			display: flex;
			justify-content: space-between;
			align-items: center;
			margin-bottom: 16px;
			gap: 8px;
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
			transition: background-color 0.2s;
			font-size: 13px;
			font-family: inherit;
		}
		.btn-primary-bx:hover  { background-color: #0066cc; }
		.btn-primary-bx:active { background-color: #0055aa; }

		.btn-secondary-bx {
			background-color: #fff;
			color: #535c69;
			border: 1px solid #c6cdd3;
			border-radius: 4px;
			padding: 8px 16px;
			font-weight: 600;
			cursor: pointer;
			transition: all 0.2s;
			font-size: 13px;
			font-family: inherit;
		}
		.btn-secondary-bx:hover  { background-color: #f5f7f8; border-color: #a8adb2; }
		.btn-secondary-bx:active { background-color: #eef2f4; }

		.btn-save-sync {
			background-color: #2fc6f6;
			color: #fff;
			border: none;
			border-radius: 4px;
			padding: 9px 20px;
			font-weight: 700;
			cursor: pointer;
			transition: background-color 0.2s;
			font-size: 13px;
			font-family: inherit;
			white-space: nowrap;
		}
		.btn-save-sync:hover  { background-color: #18b0e0; }
		.btn-save-sync:active { background-color: #009ec8; }

		.btn-delete {
			color: #a8adb2;
			background: transparent;
			border: none;
			cursor: pointer;
			padding: 4px 6px;
			border-radius: 4px;
			font-size: 15px;
			line-height: 1;
		}
		.btn-delete:hover { color: #ff5050; background: #fff0f0; }

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
			transition: border-color 0.2s;
		}
		.input-bx:focus { border-color: #0080ff; }

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
		.input-bx-with-suffix  { padding-right: 30px !important; }

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
		.table-responsive {
			overflow-x: auto;
			margin-bottom: 0;
			border: 1px solid #e2e5ec;
			border-radius: 6px;
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
			padding: 10px 12px;
			font-size: 11px;
			border-bottom: 2px solid #e2e5ec;
			text-transform: uppercase;
			letter-spacing: 0.5px;
			white-space: nowrap;
		}
		.product-table td {
			padding: 10px 12px;
			border-bottom: 1px solid #eef2f4;
			vertical-align: middle;
		}
		.product-table tbody tr {
			transition: background-color 0.15s ease;
		}
		.product-table tbody tr:hover {
			background-color: #fafbfc;
		}
		.product-table tbody tr.dragging {
			opacity: 0.6;
			background: #f0f8ff !important;
		}
		.product-table tbody tr.drag-over {
			border-top: 2px solid #0080ff;
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
			transition: color 0.15s ease;
		}
		tr:hover .drag-handle {
			color: #0080ff;
		}
		.drag-handle:active { cursor: grabbing; }

		/* ── Totals ────────────────────────────────────────────── */
		.totals-container {
			display: flex;
			justify-content: flex-end;
			margin-top: 20px;
		}
		.totals-box {
			width: 340px;
			background: #f7f9fa;
			border: 1px solid #e2e5ec;
			border-radius: 6px;
			padding: 14px 16px;
		}
		.total-row {
			display: flex;
			justify-content: space-between;
			align-items: center;
			padding: 5px 0;
			color: #828b95;
			font-size: 13px;
		}
		.total-row strong { color: #535c69; }
		.total-row.grand-total {
			border-top: 2px solid #e2e5ec;
			margin-top: 8px;
			padding-top: 12px;
			color: #222;
			font-size: 15px;
			font-weight: 700;
		}
		.total-row.grand-total strong { color: #0080ff; font-size: 16px; }

		/* ── Status footer ─────────────────────────────────────── */
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
		.status-left { display: flex; flex-direction: column; gap: 6px; flex: 1; min-width: 0; }

		#sync-status {
			display: inline-block;
			font-weight: 600;
			padding: 5px 12px;
			border-radius: 4px;
			font-size: 12px;
		}
		.status-info    { color: #0066cc; background: #e5f2ff; }
		.status-success { color: #155724; background: #d4edda; }
		.status-warning { color: #856404; background: #fff3cd; }
		.status-danger  { color: #721c24; background: #f8d7da; }

		#sync-log {
			font-size: 11px;
			color: #828b95;
			max-height: 72px;
			overflow-y: auto;
			line-height: 1.5;
		}

		/* ── Loading overlay ───────────────────────────────────── */
		#loading-overlay {
			display: none;
			position: absolute;
			inset: 0;
			background: rgba(255,255,255,0.75);
			border-radius: 8px;
			align-items: center;
			justify-content: center;
			z-index: 100;
			font-weight: 600;
			color: #0080ff;
			font-size: 14px;
			gap: 10px;
		}
		#loading-overlay.visible { display: flex; }
		.spinner {
			width: 18px; height: 18px;
			border: 3px solid #e2e5ec;
			border-top-color: #0080ff;
			border-radius: 50%;
			animation: spin 0.7s linear infinite;
			flex-shrink: 0;
		}
		@keyframes spin { to { transform: rotate(360deg); } }

		/* ── Section divider label ─────────────────────────────── */
		.section-label {
			font-size: 11px;
			font-weight: 700;
			color: #a8adb2;
			text-transform: uppercase;
			letter-spacing: 0.6px;
			margin: 0 0 10px 2px;
		}
	</style>
</head>
<body>
<div class="widget-container" style="position:relative;">

	<!-- Loading overlay (shown while JS fetches data) -->
	<div id="loading-overlay">
		<div class="spinner"></div>
		<span id="loading-text">Loading…</span>
	</div>

	<!-- Header with entity info -->
	<div class="widget-header">
		<div>
			<div class="widget-title">Product Fee Sync</div>
			<div class="entity-info">
				<span id="entity-type-label"></span> <strong id="entity-id-label"></strong>
			</div>
		</div>
	</div>

	<!-- Action bar -->
	<div class="action-bar">
		<div class="left-actions">
			<button id="btn-add-product" class="btn-primary-bx">+ Add Product</button>
			<button id="btn-select-product" class="btn-secondary-bx">Select from Catalog</button>
			<button id="btn-edit-product" class="btn-secondary-bx">Create New Product</button>
		</div>
		<button id="btn-save" class="btn-save-sync">💾 Save & Sync</button>
	</div>

	<!-- Product grid -->
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
			<tbody id="product-rows-body">
				<!-- rows injected by fee-sync-widget.js -->
			</tbody>
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

	<!-- Status footer -->
	<div class="status-footer">
		<div class="status-left">
			<div id="sync-status" class="status-info">Ready</div>
			<div id="sync-log"></div>
		</div>
	</div>

</div><!-- /.widget-container -->

<!-- Load the fixed JavaScript file -->
<script src="js/fee-sync-widget-v6.js"></script>

<script>
BX24.init(function () {
	BX24.fitWindow();

	var loadingEl  = document.getElementById('loading-overlay');
	var loadingTxt = document.getElementById('loading-text');
	var entityTypeLabel = document.getElementById('entity-type-label');
	var entityIdLabel   = document.getElementById('entity-id-label');

	var info = BX24.placement.info();
	var detectedType = null;
	var detectedId   = null;

	// Detect if opened inside a Deal or Lead context
	if (info && info.placement) {
		if (info.placement.indexOf('DEAL') !== -1) detectedType = 'deal';
		else if (info.placement.indexOf('LEAD') !== -1) detectedType = 'lead';
		if (info.options && info.options.ID) detectedId = parseInt(info.options.ID);
	}

	function showLoading(msg) {
		loadingTxt.textContent = msg || 'Loading…';
		loadingEl.classList.add('visible');
	}

	function hideLoading() {
		loadingEl.classList.remove('visible');
	}

	function updateEntityHeader(type, id) {
		var typeLabel = type === 'deal' ? 'Deal' : 'Lead';
		entityTypeLabel.textContent = typeLabel;
		entityIdLabel.textContent = '#' + id;
	}

	// Auto-load if context detected
	if (detectedType && detectedId) {
		updateEntityHeader(detectedType, detectedId);
		showLoading('Loading ' + detectedType + ' #' + detectedId + '…');
		FeeSyncWidget.init(detectedType, detectedId, hideLoading);
	} else {
		// Fallback: show error
		document.getElementById('sync-status').textContent = 'Error: Could not detect Deal/Lead context';
		document.getElementById('sync-status').className = 'status-danger';
		hideLoading();
	}
});
</script>
</body>
</html>