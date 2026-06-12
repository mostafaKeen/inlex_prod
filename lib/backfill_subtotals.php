<?php

/**
 * One-time backfill: recalculate Sub_Total_* fields (and resync products)
 * for all existing Leads and Deals.
 *
 * Usage: php backfill_subtotals.php [lead|deal|all]
 */

require_once __DIR__ . '/crest.php';
require_once __DIR__ . '/lib/ProductSyncService.php';
require_once __DIR__ . '/lib/SpaSync.php';
require_once __DIR__ . '/lib/FieldMapper.php';

$target = $argv[1] ?? 'all';
$entityTypes = [];
if ($target === 'all') {
	$entityTypes = ['lead', 'deal'];
} elseif (in_array($target, ['lead', 'deal'], true)) {
	$entityTypes = [$target];
} else {
	fwrite(STDERR, "Invalid argument. Use: lead | deal | all\n");
	exit(1);
}

const PAGE_SIZE = 50;
const SLEEP_MICROSECONDS = 400000; // ~0.4s between entity syncs to respect rate limits

foreach ($entityTypes as $entityType) {
	$entityTypeId = ProductSyncService::ENTITY_TYPE_MAP[$entityType];
	$feeFields = ProductSyncService::FEE_FIELD_MAPS[$entityType];

	echo "=== Backfilling {$entityType}s (entityTypeId {$entityTypeId}) ===\n";

	$start = 0;
	$total = 0;
	$processed = 0;
	$errorsCount = 0;

	do {
		$result = CRest::call('crm.item.list', [
			'entityTypeId'       => $entityTypeId,
			'select'             => array_merge(['id'], array_values($feeFields)),
			'start'              => $start,
			'useOriginalUfNames' => 'Y',
		]);

		if (!empty($result['error'])) {
			fwrite(STDERR, "Error fetching {$entityType} list: " . ($result['error_description'] ?? $result['error']) . "\n");
			break;
		}

		$items = $result['result']['items'] ?? [];
		$total = $result['total'] ?? count($items);

		foreach ($items as $item) {
			$entityId = (int)$item['id'];

			// Only sync entities that have at least one fee field populated
			$hasFeeData = false;
			foreach ($feeFields as $fieldCode) {
				if (!empty($item[$fieldCode])) {
					$hasFeeData = true;
					break;
				}
			}

			// Also check whether the entity actually has product rows attached
			if (!$hasFeeData) {
				$ownerType = ProductSyncService::OWNER_TYPE_MAP[$entityTypeId];
				$rows = ProductSyncService::getProductRows($ownerType, $entityId);
				if (empty($rows)) {
					continue; // nothing to sync / no subtotal to compute
				}
			}

			$syncResult = ProductSyncService::syncEntity($entityType, $entityId);
			$processed++;

			if (!$syncResult['success']) {
				$errorsCount++;
				echo "  [ERROR] {$entityType} #{$entityId}: " . implode('; ', $syncResult['errors']) . "\n";
			} else {
				echo "  [OK] {$entityType} #{$entityId} synced\n";
			}

			usleep(SLEEP_MICROSECONDS);
		}

		$start += PAGE_SIZE;
	} while (isset($result['next']) || $start < $total);

	echo "{$entityType}: processed {$processed}, errors {$errorsCount}\n\n";
}

echo "Backfill complete.\n";