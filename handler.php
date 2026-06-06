<?php

/**
 * Event handler for Lead/Deal updates.
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

$entityType = null;
$entityId   = 0;

switch ($event) {
	case 'ONCRMDEALUPDATE':
	case 'ONCRMDEALADD':
		$entityType = 'deal';
		$entityId   = (int)($data['FIELDS']['ID'] ?? $data['ID'] ?? 0);
		break;

	case 'ONCRMLEADUPDATE':
	case 'ONCRMLEADADD':
		$entityType = 'lead';
		$entityId   = (int)($data['FIELDS']['ID'] ?? $data['ID'] ?? 0);
		break;

	case 'ONCRMDYNAMICITEMUPDATE':
		$spaEntityTypeId = (int)($data['FIELDS']['ENTITY_TYPE_ID'] ?? 0);
		$spaItemId       = (int)($data['FIELDS']['ID'] ?? 0);
		if ($spaItemId > 0 && in_array($spaEntityTypeId, [SpaSync::SPA_PROFESSIONAL_FEES, SpaSync::SPA_GOVERNMENT_FEES], true)) {
			$result = ProductSyncService::syncSpaItemToLinkedEntities($spaEntityTypeId, $spaItemId);
			CRest::setLog([
				'event'  => $event,
				'spa'    => "{$spaEntityTypeId}:{$spaItemId}",
				'result' => $result,
			], 'event_sync_spa');
		}
		echo 'OK';
		exit;
}

if ($entityType && $entityId > 0) {
	$result = ProductSyncService::syncEntity($entityType, $entityId);
	CRest::setLog([
		'event'  => $event,
		'entity' => "{$entityType}:{$entityId}",
		'result' => $result,
	], 'event_sync');
}

// Bitrix24 expects a 200 response
echo 'OK';
