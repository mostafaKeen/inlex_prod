<?php

require_once __DIR__ . '/../crest.php';
require_once __DIR__ . '/../lib/ProductSyncService.php';

header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true) ?: $_REQUEST;

$auth = $input['auth'] ?? null;
if (is_array($auth)) {
	CRest::setAuth($auth);
}

$entityType = $input['entityType'] ?? null;
$entityId   = isset($input['entityId']) ? (int)$input['entityId'] : 0;
$action     = $input['action'] ?? 'sync';

if (!in_array($entityType, ['lead', 'deal'], true) || $entityId <= 0) {
	http_response_code(400);
	echo json_encode(['success' => false, 'error' => 'Invalid entityType or entityId']);
	exit;
}

switch ($action) {
	case 'sync':
		$result = ProductSyncService::syncEntity($entityType, $entityId);
		echo json_encode($result);
		break;

	case 'spa_to_product':
		$spaEntityTypeId = isset($input['spaEntityTypeId']) ? (int)$input['spaEntityTypeId'] : 0;
		$spaItemId       = isset($input['spaItemId']) ? (int)$input['spaItemId'] : 0;
		if ($spaEntityTypeId <= 0 || $spaItemId <= 0) {
			http_response_code(400);
			echo json_encode(['success' => false, 'error' => 'Invalid SPA parameters']);
			exit;
		}
		$result = ProductSyncService::syncSpaToProduct($entityType, $entityId, $spaEntityTypeId, $spaItemId);
		echo json_encode($result);
		break;

	default:
		http_response_code(400);
		echo json_encode(['success' => false, 'error' => 'Unknown action']);
}
