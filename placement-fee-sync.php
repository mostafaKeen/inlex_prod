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
	<link rel="stylesheet" href="css/app.css">
	<script src="//api.bitrix24.com/api/v1/"></script>
	<script src="js/fee-sync-widget.js"></script>
	<title>Fee SPA Sync</title>
	<style>
		#sync-log {
			max-height: 240px;
			overflow-y: auto;
			font-size: 12px;
			background: #f8f9fa;
			border: 1px solid #dee2e6;
			border-radius: 4px;
			padding: 8px;
		}
		.sync-log-entry { padding: 2px 0; border-bottom: 1px solid #eee; }
		.sync-log-success { color: #155724; }
		.sync-log-warning { color: #856404; }
		.sync-log-danger { color: #721c24; }
		.sync-log-info { color: #0c5460; }
		.sync-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
		.sync-header h4 { margin: 0; }
	</style>
</head>
<body class="container-fluid" style="padding: 12px;">
	<div class="sync-header">
		<h4>Fee SPA Sync</h4>
		<button id="btn-sync" class="btn btn-primary btn-sm">Sync Now</button>
	</div>

	<p class="text-muted" style="margin-bottom: 8px;">
		<?=htmlspecialchars($entityLabel)?> #<?=$entityId?> —
		Products are synchronized to <strong>Professional Fees</strong> (SPA 1058)
		and <strong>Government Fees</strong> (SPA 1062) based on each product's <em>Type of Cost</em>.
	</p>

	<div id="sync-status" class="alert alert-info">Initializing...</div>

	<h5>Activity Log</h5>
	<div id="sync-log"></div>

	<script>
		FeeSyncWidget.init('<?=$entityType?>', <?=$entityId?>);
	</script>
</body>
</html>
