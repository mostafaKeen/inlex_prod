<?php
$placement = $_REQUEST['PLACEMENT'] ?? '';
$placementOptions = json_decode($_REQUEST['PLACEMENT_OPTIONS'] ?? '{}', true);

$entityId = (int)($placementOptions['ID'] ?? 0);

$entityType = null;
$entityLabel = '';

switch ($placement) {
	case 'CRM_DEAL_DETAIL_TAB':
		$entityType = 'deal';
		$entityLabel = 'Deal';
		break;
	case 'CRM_LEAD_DETAIL_TAB':
		$entityType = 'lead';
		$entityLabel = 'Lead';
		break;
	case 'CRM_DYNAMIC_1086_DETAIL_TAB':
		$entityType = 'onboarding';
		$entityLabel = 'Onboarding';
		break;
}

if (!$entityType || $entityId <= 0):
?>
<html>
<head>
	<meta charset="utf-8">
	<link rel="stylesheet" href="css/app.css">
	<title>Fee SPA Sync</title>
</head>
<body class="container-fluid">
	<div class="alert alert-danger">Unable to determine entity context. Placement: <?=htmlspecialchars($placement)?></div>
</body>
</html>
<?php exit; endif; ?>
<html>
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Product Fee Sync Grid</title>
	
	<!-- Modern Typography -->
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
	
	<script src="//api.bitrix24.com/api/v1/"></script>
	
	<style>
		body {
			font-family: 'Open Sans', Arial, sans-serif;
			background-color: #f0f2f5;
			color: #535c69;
			margin: 0;
			padding: 16px;
			font-size: 13px;
		}

		.widget-container {
			background: #ffffff;
			border-radius: 8px;
			box-shadow: 0 2px 6px rgba(0, 0, 0, 0.04);
			padding: 20px;
			border: 1px solid #e2e5ec;
		}

		/* Top Action Bar */
		.action-bar {
			display: flex;
			justify-content: space-between;
			align-items: center;
			margin-bottom: 20px;
		}

		.left-actions {
			display: flex;
			gap: 10px;
		}

		.btn-primary-bx {
			background-color: #0080ff;
			color: #ffffff;
			border: none;
			border-radius: 4px;
			padding: 8px 16px;
			font-weight: 600;
			cursor: pointer;
			transition: background-color 0.2s;
			font-size: 13px;
		}

		.btn-primary-bx:hover {
			background-color: #0066cc;
		}

		.btn-secondary-bx {
			background-color: #ffffff;
			color: #535c69;
			border: 1px solid #c6cdd3;
			border-radius: 4px;
			padding: 8px 16px;
			font-weight: 600;
			cursor: pointer;
			transition: all 0.2s;
			font-size: 13px;
		}

		.btn-secondary-bx:hover {
			background-color: #f5f7f8;
			border-color: #a8adb2;
		}

		.btn-icon-bx {
			background: transparent;
			border: none;
			color: #a8adb2;
			font-size: 18px;
			cursor: pointer;
			padding: 4px 8px;
			border-radius: 4px;
		}

		.btn-icon-bx:hover {
			background-color: #f5f7f8;
			color: #535c69;
		}

		/* Product Table */
		.table-responsive {
			overflow-x: auto;
			margin-bottom: 25px;
		}

		.product-table {
			width: 100%;
			border-collapse: collapse;
			text-align: left;
		}

		.product-table th {
			background-color: #f7f9fa;
			color: #828b95;
			font-weight: 600;
			padding: 10px 14px;
			font-size: 12px;
			border-bottom: 1px solid #e2e5ec;
			text-transform: uppercase;
			letter-spacing: 0.5px;
		}

		.product-table td {
			padding: 12px 14px;
			border-bottom: 1px solid #eef2f4;
			vertical-align: middle;
		}

		.row-number {
			color: #a8adb2;
			display: flex;
			align-items: center;
			gap: 8px;
			font-weight: 600;
		}

		.drag-handle {
			cursor: grab;
			color: #d0d4da;
		}

		/* Input Fields matching Bitrix24 styling */
		.input-bx {
			border: 1px solid #c6cdd3;
			border-radius: 4px;
			padding: 6px 10px;
			color: #535c69;
			background-color: #ffffff;
			width: 100%;
			font-size: 13px;
			box-sizing: border-box;
			outline: none;
			transition: border-color 0.2s;
		}

		.input-bx:focus {
			border-color: #0080ff;
		}

		.input-bx-wrapper {
			position: relative;
			display: flex;
			align-items: center;
		}

		.input-bx-suffix {
			position: absolute;
			right: 10px;
			color: #a8adb2;
			pointer-events: none;
			font-size: 12px;
		}

		.input-bx-with-suffix {
			padding-right: 32px !important;
		}

		.select-bx {
			appearance: none;
			background-image: url("data:image/svg+xml;charset=utf-8,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 4 5'%3E%3Cpath fill='%23535c69' d='M2 0L0 2h4zm0 5L0 3h4z'/%3E%3C/svg%3E");
			background-repeat: no-repeat;
			background-position: right 10px center;
			background-size: 8px 10px;
			padding-right: 28px;
		}

		.img-placeholder {
			width: 34px;
			height: 34px;
			border: 1px dashed #a8adb2;
			border-radius: 4px;
			display: flex;
			align-items: center;
			justify-content: center;
			color: #a8adb2;
			background: #fdfdfd;
			cursor: pointer;
		}

		.img-placeholder:hover {
			border-color: #0080ff;
			color: #0080ff;
		}

		/* Totals Block */
		.totals-container {
			display: flex;
			justify-content: flex-end;
			margin-top: 20px;
		}

		.totals-box {
			width: 320px;
		}

		.total-row {
			display: flex;
			justify-content: space-between;
			padding: 6px 0;
			color: #828b95;
			font-size: 13px;
		}

		.total-row.grand-total {
			border-top: 1px solid #e2e5ec;
			margin-top: 8px;
			padding-top: 12px;
			color: #333333;
			font-size: 16px;
			font-weight: 700;
		}

		.btn-delete {
			color: #a8adb2;
			background: transparent;
			border: none;
			cursor: pointer;
			padding: 4px;
			border-radius: 4px;
		}

		.btn-delete:hover {
			color: #ff5050;
			background: #fff0f0;
		}

		/* Status Messages */
		.status-footer {
			display: flex;
			justify-content: space-between;
			align-items: center;
			margin-top: 20px;
			padding-top: 15px;
			border-top: 1px solid #e2e5ec;
		}

		#sync-status {
			font-weight: 600;
			padding: 6px 12px;
			border-radius: 4px;
		}

		.status-info { color: #0066cc; background: #e5f2ff; }
		.status-success { color: #155724; background: #d4edda; }
		.status-warning { color: #856404; background: #fff3cd; }
		.status-danger { color: #721c24; background: #f8d7da; }

		#sync-log {
			font-size: 11px;
			color: #828b95;
			max-height: 80px;
			overflow-y: auto;
			margin-top: 8px;
			width: 100%;
		}
	</style>
</head>
<body>
	<div class="widget-container">
		<div class="action-bar">
			<div class="left-actions" style="align-items: center;">
				<button id="btn-add-product" class="btn-primary-bx">Add product</button>
				<button id="btn-select-product" class="btn-secondary-bx">Select product</button>
				<div class="currency-selector" style="display: flex; align-items: center; gap: 8px; margin-left: 10px;">
					<span style="color: #828b95; font-weight: 600;">Currency:</span>
					<select id="entity-currency" class="input-bx select-bx" style="width: 110px; padding: 6px 10px; display: inline-block;">
						<option value="AED">AED (Dh)</option>
						<option value="USD">USD ($)</option>
						<option value="EUR">EUR (€)</option>
					</select>
				</div>
			</div>
			<button class="btn-icon-bx">•••</button>
		</div>

		<div class="table-responsive">
			<table class="product-table">
				<thead>
					<tr>
						<th style="width: 50px;"></th>
						<th>Product</th>
						<th style="width: 60px;"></th>
						<th style="width: 180px;">Type of Cost</th>
						<th style="width: 150px;">Price</th>
						<th style="width: 120px;">Payments</th>
						<th style="width: 130px;">Tax</th>
						<th style="width: 150px;">Amount</th>
						<th style="width: 40px;"></th>
					</tr>
				</thead>
				<tbody id="product-rows-body">
					<!-- Dynamic rows will be inserted here -->
				</tbody>
			</table>
		</div>

		<!-- Additional Option Details -->
		<div style="font-size: 11px; font-weight: 700; color: #a8adb2; text-transform: uppercase; letter-spacing: 0.6px; margin: 16px 0 12px 0;">Options Details</div>
		<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; background: #fff; border: 1px solid #e2e5ec; border-radius: 8px; padding: 20px; margin-top: 10px; margin-bottom: 20px;">
			
			<!-- Option 1 Fields -->
			<div style="display: flex; flex-direction: column; gap: 12px;">
				<h3 style="margin: 0 0 8px 0; color: #0066cc; font-size: 13px; border-bottom: 1px solid #e2e5ec; padding-bottom: 8px;">Option 1 Parameters</h3>
				<div style="display: flex; flex-direction: column; gap: 4px;">
					<label style="font-weight: 600; color: #535c69;">Business Activity</label>
					<input type="text" id="opt1-business-activity" class="input-bx" placeholder="Enter Business Activity">
				</div>
				<div style="display: flex; flex-direction: column; gap: 4px;">
					<label style="font-weight: 600; color: #535c69;">Additional Approval Required</label>
					<input type="text" id="opt1-additional-approval" class="input-bx" placeholder="Enter Additional Approval">
				</div>
				<div style="display: flex; flex-direction: column; gap: 4px;">
					<label style="font-weight: 600; color: #535c69;">Estimated Timeframe</label>
					<input type="text" id="opt1-estimated-timeframe" class="input-bx" placeholder="Enter Estimated Timeframe">
				</div>
			</div>

			<!-- Option 2 Fields -->
			<div style="display: flex; flex-direction: column; gap: 12px;">
				<h3 style="margin: 0 0 8px 0; color: #e65100; font-size: 13px; border-bottom: 1px solid #e2e5ec; padding-bottom: 8px;">Option 2 Parameters</h3>
				<div style="display: flex; flex-direction: column; gap: 4px;">
					<label style="font-weight: 600; color: #535c69;">Business Activity</label>
					<input type="text" id="opt2-business-activity" class="input-bx" placeholder="Enter Business Activity">
				</div>
				<div style="display: flex; flex-direction: column; gap: 4px;">
					<label style="font-weight: 600; color: #535c69;">Additional Approval Required</label>
					<input type="text" id="opt2-additional-approval" class="input-bx" placeholder="Enter Additional Approval">
				</div>
				<div style="display: flex; flex-direction: column; gap: 4px;">
					<label style="font-weight: 600; color: #535c69;">Estimated Timeframe</label>
					<input type="text" id="opt2-estimated-timeframe" class="input-bx" placeholder="Enter Estimated Timeframe">
				</div>
			</div>

		</div>

		<div class="totals-container">
			<div class="totals-box">
				<div class="total-row">
					<span>Total without discounts and taxes:</span>
					<strong id="total-raw">—</strong>
				</div>
				<div class="total-row">
					<span>Delivery price:</span>
					<strong>—</strong>
				</div>
				<div class="total-row">
					<span>Discount amount:</span>
					<strong>—</strong>
				</div>
				<div class="total-row">
					<span>Total before tax:</span>
					<strong id="total-before-tax">—</strong>
				</div>
				<div class="total-row">
					<span>Tax total:</span>
					<strong id="total-tax">—</strong>
				</div>
				<div class="total-row grand-total">
					<span>Total amount:</span>
					<strong id="total-amount">—</strong>
				</div>
			</div>
		</div>

		<div class="status-footer">
			<div>
				<div id="sync-status" class="status-info">Initializing...</div>
				<div id="sync-log"></div>
			</div>
			<button id="btn-save" class="btn-primary-bx" style="background-color: #2fc6f6;">Save & Sync SPA</button>
		</div>
	</div>

	<script src="js/fee-sync-widget.js"></script>
	<script>
		FeeSyncWidget.init('<?=$entityType?>', <?=$entityId?>);
	</script>
</body>
</html>
