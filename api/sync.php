<?php

require_once __DIR__ . '/../crest.php';
require_once __DIR__ . '/../lib/ProductSyncService.php';

header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true) ?: $_REQUEST;

SyncLogger::log("API sync.php starting request", [
	'entityType' => $input['entityType'] ?? null,
	'entityId' => $input['entityId'] ?? null,
	'action' => $input['action'] ?? null,
	'has_auth' => isset($input['auth']),
]);

$auth = $input['auth'] ?? null;
if (is_array($auth)) {
	$saved = CRest::setAuth($auth);
	SyncLogger::log("CRest::setAuth completed", ['success' => $saved]);
}

$entityType = $input['entityType'] ?? null;
$entityId   = isset($input['entityId']) ? (int)$input['entityId'] : 0;
$action     = $input['action'] ?? 'sync';

$result = [];

if (!in_array($entityType, ['lead', 'deal'], true) || $entityId <= 0) {
	http_response_code(400);
	$result = ['success' => false, 'error' => 'Invalid entityType or entityId'];
} else {
	switch ($action) {
		case 'sync':
			$result = ProductSyncService::syncEntity($entityType, $entityId);
			break;

		case 'spa_to_product':
			$spaEntityTypeId = isset($input['spaEntityTypeId']) ? (int)$input['spaEntityTypeId'] : 0;
			$spaItemId       = isset($input['spaItemId']) ? (int)$input['spaItemId'] : 0;
			if ($spaEntityTypeId <= 0 || $spaItemId <= 0) {
				http_response_code(400);
				$result = ['success' => false, 'error' => 'Invalid SPA parameters'];
			} else {
				$result = ProductSyncService::syncSpaToProduct($entityType, $entityId, $spaEntityTypeId, $spaItemId);
			}
			break;

		default:
			http_response_code(400);
			$result = ['success' => false, 'error' => 'Unknown action'];
	}
}

$result['debug_logs'] = SyncLogger::$logs;
echo json_encode($result);
exit;
