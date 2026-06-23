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

CRest::setLog([
	'event' => 'sync_api_start',
	'entityType' => $input['entityType'] ?? null,
	'entityId' => $input['entityId'] ?? null,
	'action' => $input['action'] ?? null,
	'timestamp' => date('Y-m-d H:i:s'),
], 'sync_api_lifecycle');

$auth = $input['auth'] ?? null;
if (is_array($auth)) {
	CRest::setLog(['auth_provided' => true], 'sync_api_auth');
	$saved = CRest::setAuth($auth);
	SyncLogger::log("CRest::setAuth completed", ['success' => $saved]);
	CRest::setLog([
		'event' => 'auth_set',
		'success' => $saved,
		'domain' => $auth['domain'] ?? null,
	], 'sync_api_auth');
}

$entityType = $input['entityType'] ?? null;
$entityId   = isset($input['entityId']) ? (int)$input['entityId'] : 0;
$action     = $input['action'] ?? 'sync';

$result = [];

if (!in_array($entityType, ['lead', 'deal'], true) || $entityId <= 0) {
	http_response_code(400);
	$result = [
		'success' => false,
		'error' => 'Invalid entityType or entityId',
		'received' => [
			'entityType' => $entityType,
			'entityId' => $entityId,
		],
	];
	CRest::setLog($result, 'sync_api_validation_error');
} else {
	CRest::setLog([
		'event' => 'sync_api_validation_passed',
		'entityType' => $entityType,
		'entityId' => $entityId,
		'action' => $action,
	], 'sync_api_validation');

	switch ($action) {
		case 'sync':
			CRest::setLog([
				'event' => 'sync_api_action_sync_start',
				'entityType' => $entityType,
				'entityId' => $entityId,
			], 'sync_api_actions');

			$result = ProductSyncService::syncEntity($entityType, $entityId);

			CRest::setLog([
				'event' => 'sync_api_action_sync_complete',
				'entityType' => $entityType,
				'entityId' => $entityId,
				'success' => $result['success'] ?? false,
				'actionCount' => count($result['actions'] ?? []),
				'errorCount' => count($result['errors'] ?? []),
			], 'sync_api_actions');
			break;

		case 'clearAll':
			CRest::setLog([
				'event' => 'sync_api_action_clear_all_start',
				'entityType' => $entityType,
				'entityId' => $entityId,
			], 'sync_api_actions');

			$result = ProductSyncService::clearAllSpaItems($entityType, $entityId);

			CRest::setLog([
				'event' => 'sync_api_action_clear_all_complete',
				'entityType' => $entityType,
				'entityId' => $entityId,
				'success' => $result['success'] ?? false,
				'actionCount' => count($result['actions'] ?? []),
			], 'sync_api_actions');
			break;

		case 'spa_to_product':
			$spaEntityTypeId = isset($input['spaEntityTypeId']) ? (int)$input['spaEntityTypeId'] : 0;
			$spaItemId       = isset($input['spaItemId']) ? (int)$input['spaItemId'] : 0;

			if ($spaEntityTypeId <= 0 || $spaItemId <= 0) {
				http_response_code(400);
				$result = [
					'success' => false,
					'error' => 'Invalid SPA parameters',
					'received' => [
						'spaEntityTypeId' => $spaEntityTypeId,
						'spaItemId' => $spaItemId,
					],
				];
				CRest::setLog($result, 'sync_api_spa_params_error');
			} else {
				CRest::setLog([
					'event' => 'sync_api_action_spa_to_product_start',
					'entityType' => $entityType,
					'entityId' => $entityId,
					'spaEntityTypeId' => $spaEntityTypeId,
					'spaItemId' => $spaItemId,
				], 'sync_api_actions');

				$result = ProductSyncService::syncSpaToProduct($entityType, $entityId, $spaEntityTypeId, $spaItemId);

				CRest::setLog([
					'event' => 'sync_api_action_spa_to_product_complete',
					'success' => $result['success'] ?? false,
				], 'sync_api_actions');
			}
			break;

		default:
			http_response_code(400);
			$result = [
				'success' => false,
				'error' => 'Unknown action',
				'received_action' => $action,
				'available_actions' => ['sync', 'clearAll', 'spa_to_product'],
			];
			CRest::setLog($result, 'sync_api_unknown_action');
	}
}

$result['debug_logs'] = SyncLogger::$logs;

CRest::setLog([
	'event' => 'sync_api_complete',
	'success' => $result['success'] ?? false,
	'logCount' => count($result['debug_logs'] ?? []),
], 'sync_api_lifecycle');

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
exit;