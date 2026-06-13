<?php

/**
 * Event handler for Lead/Deal/SPA updates.
 * Triggers product → SPA synchronization when CRM entities change.
 */

require_once __DIR__ . '/crest.php';
require_once __DIR__ . '/lib/ProductSyncService.php';
require_once __DIR__ . '/lib/SpaSync.php';

$event = $_REQUEST['event'] ?? '';
$data  = $_REQUEST['data'] ?? [];

if (is_string($data)) {
	$data = json_decode($data, true) ?: [];
}

CRest::setLog([
	'event' => 'handler_start',
	'eventType' => $event,
	'hasData' => !empty($data),
], 'handler_lifecycle');

$entityType = null;
$entityId   = 0;

switch ($event) {
	case 'ONCRMDEALUPDATE':
	case 'ONCRMDEALADD':
		$entityType = 'deal';
		$entityId   = (int)($data['FIELDS']['ID'] ?? $data['ID'] ?? 0);
		CRest::setLog([
			'event' => $event,
			'entityType' => $entityType,
			'entityId' => $entityId,
		], 'handler_event_routing');
		break;

	case 'ONCRMLEADUPDATE':
	case 'ONCRMLEADADD':
		$entityType = 'lead';
		$entityId   = (int)($data['FIELDS']['ID'] ?? $data['ID'] ?? 0);
		CRest::setLog([
			'event' => $event,
			'entityType' => $entityType,
			'entityId' => $entityId,
		], 'handler_event_routing');
		break;

	case 'ONCRMPRODUCTUPDATE':
	case 'CATALOG.PRODUCT.ON.UPDATE':
		$productId = (int)($data['FIELDS']['ID'] ?? $data['ID'] ?? 0);
		if ($productId > 0) {
			CRest::setLog([
				'event' => $event,
				'productId' => $productId,
				'status' => 'starting_sync',
			], 'handler_product_update');

			$result = ProductSyncService::syncAllEntitiesByProduct($productId);
			CRest::setLog([
				'event'  => $event,
				'product'=> $productId,
				'result' => $result,
				'status' => 'sync_complete',
			], 'handler_product_update');
		}
		echo 'OK';
		exit;

	case 'ONCRMDYNAMICITEMUPDATE':
		$spaEntityTypeId = (int)($data['FIELDS']['ENTITY_TYPE_ID'] ?? 0);
		$spaItemId       = (int)($data['FIELDS']['ID'] ?? 0);

		CRest::setLog([
			'event' => $event,
			'spaEntityTypeId' => $spaEntityTypeId,
			'spaItemId' => $spaItemId,
		], 'handler_spa_update');

		if ($spaItemId > 0 && in_array($spaEntityTypeId, [SpaSync::SPA_PROFESSIONAL_FEES, SpaSync::SPA_GOVERNMENT_FEES], true)) {
			CRest::setLog([
				'status' => 'syncing_spa_to_entities',
				'spaEntityTypeId' => $spaEntityTypeId,
				'spaItemId' => $spaItemId,
			], 'handler_spa_update');

			$result = ProductSyncService::syncSpaItemToLinkedEntities($spaEntityTypeId, $spaItemId);
			CRest::setLog([
				'event'  => $event,
				'spa'    => "{$spaEntityTypeId}:{$spaItemId}",
				'result' => $result,
				'status' => 'sync_complete',
			], 'handler_spa_update');
		}
		echo 'OK';
		exit;
}

if ($entityType && $entityId > 0) {
	CRest::setLog([
		'status' => 'syncing_entity',
		'entityType' => $entityType,
		'entityId' => $entityId,
	], 'handler_entity_sync');

	$result = ProductSyncService::syncEntity($entityType, $entityId);
	CRest::setLog([
		'event'  => $event,
		'entity' => "{$entityType}:{$entityId}",
		'result' => $result,
		'status' => 'sync_complete',
	], 'handler_entity_sync');
} else {
	CRest::setLog([
		'warning' => 'Unknown or invalid event',
		'event' => $event,
		'entityType' => $entityType,
		'entityId' => $entityId,
	], 'handler_unhandled_event');
}

// Bitrix24 expects a 200 response
echo 'OK';