<?php

require_once __DIR__ . '/../crest.php';
require_once __DIR__ . '/SpaSync.php';

class ProductSyncService
{
	const OWNER_TYPE_MAP = [
		1 => 'L',
		2 => 'D',
		1086 => 'T43e',
	];

	const ENTITY_TYPE_MAP = [
		'lead' => 1,
		'deal' => 2,
		'onboarding' => 1086,
	];

	const FEE_FIELD_MAPS = [
		'deal' => [
			1058 => 'UF_CRM_1779313011',
			1062 => 'UF_CRM_1779654189',
			1070 => 'UF_CRM_6A29D1F63E22F',
			1074 => 'UF_CRM_6A29D1F65158B',
		],
		'lead' => [
			1058 => 'UF_CRM_1780911226',
			1062 => 'UF_CRM_1780912561',
			1070 => 'UF_CRM_1781125540',
			1074 => 'UF_CRM_1781125572',
		],
		'onboarding' => [
			1058 => 'ufCrm29_1784036735',
			1062 => 'ufCrm29_1784036829',
			1070 => 'ufCrm29_1784036868',
			1074 => 'ufCrm29_1784036903',
		],
	];

	/**
	 * Run full product → SPA synchronization for a Lead or Deal.
	 *
	 * @return array{success: bool, actions: array, errors: array}
	 */
	public static function syncEntity(string $entityType, int $entityId): array
	{
		CRest::setLog([
			'event' => 'sync_entity_start',
			'entityType' => $entityType,
			'entityId' => $entityId,
		], 'sync_entity_lifecycle');

		$entityTypeId = self::ENTITY_TYPE_MAP[$entityType] ?? null;
		if (!$entityTypeId) {
			return ['success' => false, 'actions' => [], 'errors' => ['Unknown entity type']];
		}

		$actions = [];
		$errors  = [];

		$feeFields = self::FEE_FIELD_MAPS[$entityType] ?? [];
		if (empty($feeFields)) {
			$errors[] = 'Could not retrieve Professional Fees or Government Fees CRM fields';
		}

		$productPropertyMap = SpaSync::discoverProductPropertyMap();
		$spaFieldMaps = [
			1058 => FieldMapper::buildSpaFieldMap(1058),
			1062 => FieldMapper::buildSpaFieldMap(1062),
			1070 => FieldMapper::buildSpaFieldMap(1070),
			1074 => FieldMapper::buildSpaFieldMap(1074),
		];

		CRest::setLog([
			'event' => 'field_maps_prepared',
			'productPropertyMapCount' => count($productPropertyMap),
			'spaFieldMapCounts' => [
				1058 => count($spaFieldMaps[1058] ?? []),
				1062 => count($spaFieldMaps[1062] ?? []),
				1070 => count($spaFieldMaps[1070] ?? []),
				1074 => count($spaFieldMaps[1074] ?? []),
			],
		], 'sync_entity_debug');

		$ownerType = self::OWNER_TYPE_MAP[$entityTypeId];
		$productRows = self::getProductRows($ownerType, $entityId);

		CRest::setLog([
			'event' => 'product_rows_fetched',
			'count' => count($productRows),
			'productRows' => $productRows,
		], 'sync_entity_debug');

		$entity = self::getClassicEntity($entityType, $entityId);
		if (!$entity) {
			return ['success' => false, 'actions' => [], 'errors' => ['Entity not found']];
		}

		$currencyId = $entity['CURRENCY_ID'] ?? 'AED';

		$linkedSpaIds = [];
		foreach ($feeFields as $spaTypeId => $fieldCode) {
			$linkedSpaIds[$spaTypeId] = SpaSync::normalizeLinkedIds($entity[$fieldCode] ?? null);
		}

		CRest::setLog([
			'event' => 'linked_spa_ids_extracted',
			'linkedSpaIds' => $linkedSpaIds,
		], 'sync_entity_debug');

		$processedSpaIds = [
			1058 => [],
			1062 => [],
			1070 => [],
			1074 => [],
		];

		$subtotals = [
			1058 => 0.0,
			1062 => 0.0,
			1070 => 0.0,
			1074 => 0.0,
		];

		$totalWithoutTax = 0.0;
		$totalTaxAmount = 0.0;

		foreach ($productRows as $row) {
			$productName = trim($row['productName'] ?? '');
			$productId = (int)($row['productId'] ?? 0);
			if ($productName === '' || $productId <= 0) {
				continue;
			}

			CRest::setLog([
				'event' => 'processing_product_row',
				'productName' => $productName,
				'productId' => $productId,
			], 'product_row_processing');

			$catalogProduct = SpaSync::getCatalogProduct($productId);
			$costType = SpaSync::getCostTypeFromProduct($catalogProduct, $productPropertyMap);
			$option = SpaSync::getOptionFromProduct($catalogProduct, $productPropertyMap);
			$spaEntityTypeId = SpaSync::resolveSpaEntityTypeId($costType, $option);

			CRest::setLog([
				'event' => 'product_analyzed',
				'productName' => $productName,
				'costType' => $costType,
				'option' => $option,
				'spaEntityTypeId' => $spaEntityTypeId,
			], 'product_row_processing');

			if (!$spaEntityTypeId) {
				$errors[] = "Product \"{$productName}\": could not determine fee type from cost type \"{$costType}\" and option \"{$option}\"";
				CRest::setLog([
					'error' => 'Could not resolve SPA entity type',
					'productName' => $productName,
					'costType' => $costType,
					'option' => $option,
				], 'spa_type_resolution_error');
				continue;
			}

			$externalId = "{$entityType}_{$entityId}_{$productId}";
			$spaFieldMap = $spaFieldMaps[$spaEntityTypeId];
			$spaFields = SpaSync::buildSpaFields($row, $catalogProduct, $productPropertyMap, $spaFieldMap, $externalId, $spaEntityTypeId);
			$spaFields['currencyId'] = $currencyId;

			CRest::setLog([
				'event' => 'spa_fields_built',
				'productName' => $productName,
				'spaEntityTypeId' => $spaEntityTypeId,
				'fieldCount' => count($spaFields),
				'fields' => $spaFields,
			], 'spa_field_building');

			// Accumulate sub-total for this fee type/option based on row price x quantity
			$rowPrice = (float)($row['price'] ?? 0);
			$rowQty   = (float)($row['quantity'] ?? 1);
			$rowTaxRate = (float)($row['taxRate'] ?? 0);
			$rowBase = $rowPrice * $rowQty;
			$rowTax = $rowBase * ($rowTaxRate / 100);
			
			$subtotals[$spaEntityTypeId] += $rowBase;
			$totalWithoutTax += $rowBase;
			$totalTaxAmount += $rowTax;

			// Search for existing SPA item with this externalId across all 4 SPA types
			$existing = null;
			$existingSpaTypeId = null;
			foreach ([1058, 1062, 1070, 1074] as $typeId) {
				$item = SpaSync::findSpaItemByXmlId($typeId, $externalId);
				if ($item) {
					$existing = $item;
					$existingSpaTypeId = $typeId;
					break;
				}
			}

			if ($existing) {
				$existingId = (int)$existing['id'];
				if ($existingSpaTypeId === $spaEntityTypeId) {
					// Same type: update
					SpaSync::updateSpaItem($spaEntityTypeId, $existingId, $spaFields);
					$actions[] = ['action' => 'updated', 'spaType' => $spaEntityTypeId, 'spaId' => $existingId, 'product' => $productName];
					$processedSpaIds[$spaEntityTypeId][] = $existingId;
					CRest::setLog([
						'action' => 'updated',
						'spaType' => $spaEntityTypeId,
						'spaId' => $existingId,
						'productName' => $productName,
					], 'spa_item_action');
				} else {
					// Different type: delete old, create new
					SpaSync::deleteSpaItem($existingSpaTypeId, $existingId);
					$actions[] = ['action' => 'deleted_old_for_type_change', 'spaType' => $existingSpaTypeId, 'spaId' => $existingId, 'product' => $productName];

					$spaItemId = SpaSync::createSpaItem($spaEntityTypeId, $spaFields);
					if (!$spaItemId) {
						$errors[] = "Failed to recreate SPA item for product \"{$productName}\" after type change";
						CRest::setLog([
							'error' => 'Failed to recreate SPA item',
							'productName' => $productName,
							'oldType' => $existingSpaTypeId,
							'newType' => $spaEntityTypeId,
						], 'spa_create_error');
						continue;
					}
					$actions[] = ['action' => 'created_new_for_type_change', 'spaType' => $spaEntityTypeId, 'spaId' => $spaItemId, 'product' => $productName];
					$processedSpaIds[$spaEntityTypeId][] = $spaItemId;
					CRest::setLog([
						'action' => 'created_new_for_type_change',
						'spaType' => $spaEntityTypeId,
						'spaId' => $spaItemId,
						'productName' => $productName,
					], 'spa_item_action');
				}
			} else {
				// No existing item: create new
				$spaItemId = SpaSync::createSpaItem($spaEntityTypeId, $spaFields);
				if (!$spaItemId) {
					$errors[] = "Failed to create SPA item for product \"{$productName}\"";
					CRest::setLog([
						'error' => 'Failed to create SPA item',
						'productName' => $productName,
						'spaEntityTypeId' => $spaEntityTypeId,
					], 'spa_create_error');
					continue;
				}
				$actions[] = ['action' => 'created', 'spaType' => $spaEntityTypeId, 'spaId' => $spaItemId, 'product' => $productName];
				$processedSpaIds[$spaEntityTypeId][] = $spaItemId;
				CRest::setLog([
					'action' => 'created',
					'spaType' => $spaEntityTypeId,
					'spaId' => $spaItemId,
					'productName' => $productName,
				], 'spa_item_action');
			}
		}

		// Update Sub_Total_* fields per fee type/option
		// Money-type UF fields require "amount|CURRENCY" format, otherwise
		// crm.item.update silently ignores the value.
		$currencyId = $entity['CURRENCY_ID'] ?? $entity['currencyId'] ?? 'AED';
		$subtotalFields = SpaSync::SUBTOTAL_FIELD_MAPS[$entityType] ?? [];
		foreach ($subtotalFields as $spaTypeId => $fieldCode) {
			if (!$fieldCode) {
				continue;
			}
			$amount = $subtotals[$spaTypeId] ?? 0.0;
			$value = number_format($amount, 2, '.', '') . '|' . $currencyId;

			if ($entityType === 'onboarding') {
				$updateResult = CRest::call('crm.item.update', [
					'entityTypeId' => 1086,
					'id' => $entityId,
					'fields' => [$fieldCode => $value]
				]);
			} else {
				$updateResult = $entityType === 'lead'
					? CRest::call('crm.lead.update', ['id' => $entityId, 'fields' => [$fieldCode => $value], 'params' => ['REGISTER_SONET_EVENT' => 'N']])
					: CRest::call('crm.deal.update', ['id' => $entityId, 'fields' => [$fieldCode => $value], 'params' => ['REGISTER_SONET_EVENT' => 'N']]);
			}
			CRest::setLog([
				'field'    => $fieldCode,
				'spaType'  => $spaTypeId,
				'sentValue'=> $value,
				'response' => $updateResult,
			], 'subtotal_update_debug');

			$actions[] = [
				'action'   => 'updated_subtotal',
				'field'    => $fieldCode,
				'spaType'  => $spaTypeId,
				'value'    => $value,
				'apiError' => $updateResult['error'] ?? null,
			];
		}

		// Update Option Total fields (Option 1 = SPA 1058 + SPA 1062, Option 2 = SPA 1070 + SPA 1074)
		$totalFields = SpaSync::TOTAL_FIELD_MAPS[$entityType] ?? [];
		$optionTotals = [
			'option1' => ($subtotals[1058] ?? 0.0) + ($subtotals[1062] ?? 0.0),
			'option2' => ($subtotals[1070] ?? 0.0) + ($subtotals[1074] ?? 0.0),
		];
		foreach ($totalFields as $optionKey => $fieldCode) {
			if (!$fieldCode) {
				continue;
			}
			$amount = $optionTotals[$optionKey] ?? 0.0;
			$value = number_format($amount, 2, '.', '') . '|' . $currencyId;

			if ($entityType === 'onboarding') {
				$updateResult = CRest::call('crm.item.update', [
					'entityTypeId' => 1086,
					'id' => $entityId,
					'fields' => [$fieldCode => $value]
				]);
			} else {
				$updateResult = $entityType === 'lead'
					? CRest::call('crm.lead.update', ['id' => $entityId, 'fields' => [$fieldCode => $value], 'params' => ['REGISTER_SONET_EVENT' => 'N']])
					: CRest::call('crm.deal.update', ['id' => $entityId, 'fields' => [$fieldCode => $value], 'params' => ['REGISTER_SONET_EVENT' => 'N']]);
			}
			CRest::setLog([
				'field'    => $fieldCode,
				'option'   => $optionKey,
				'sentValue'=> $value,
				'response' => $updateResult,
			], 'option_total_update_debug');

			$actions[] = [
				'action'   => 'updated_option_total',
				'field'    => $fieldCode,
				'option'   => $optionKey,
				'value'    => $value,
				'apiError' => $updateResult['error'] ?? null,
			];
		}

		// Link SPA items to entity fee fields
		foreach ($feeFields as $spaTypeId => $fieldCode) {
			if (!$fieldCode) {
				continue;
			}
			$newIds = array_values(array_unique($processedSpaIds[$spaTypeId] ?? []));
			$currentIds = $linkedSpaIds[$spaTypeId] ?? [];

			CRest::setLog([
				'event' => 'linking_spa_items',
				'spaTypeId' => $spaTypeId,
				'fieldCode' => $fieldCode,
				'newIds' => $newIds,
				'currentIds' => $currentIds,
				'hasChanges' => $newIds !== $currentIds,
			], 'spa_linking_debug');

			if ($newIds !== $currentIds) {
				self::updateClassicEntityField($entityType, $entityId, $fieldCode, $newIds);
				$actions[] = ['action' => 'linked', 'field' => $fieldCode, 'ids' => $newIds];
			}

			// Remove orphaned SPA items that were linked but product no longer exists
			$orphaned = array_diff($currentIds, $newIds);
			foreach ($orphaned as $orphanId) {
				SpaSync::deleteSpaItem($spaTypeId, $orphanId);
				$actions[] = ['action' => 'deleted_orphan', 'spaType' => $spaTypeId, 'spaId' => $orphanId];
				CRest::setLog([
					'action' => 'deleted_orphan',
					'spaType' => $spaTypeId,
					'spaId' => $orphanId,
				], 'spa_item_action');
			}
		}

		// Update opportunity/amount with total including taxes and set currency
		$totalWithTax = $totalWithoutTax + $totalTaxAmount;
		
		if ($entityType === 'onboarding') {
			$updateResult = CRest::call('crm.item.update', [
				'entityTypeId' => 1086,
				'id' => $entityId,
				'fields' => [
					'opportunity' => $totalWithTax,
					'currencyId' => $currencyId
				]
			]);
		} else {
			$updateResult = $entityType === 'lead'
				? CRest::call('crm.lead.update', [
					'id' => $entityId, 
					'fields' => [
						'OPPORTUNITY' => $totalWithTax,
						'CURRENCY_ID' => $currencyId
					], 
					'params' => ['REGISTER_SONET_EVENT' => 'N']
				])
				: CRest::call('crm.deal.update', [
					'id' => $entityId, 
					'fields' => [
						'OPPORTUNITY' => $totalWithTax,
						'CURRENCY_ID' => $currencyId
					], 
					'params' => ['REGISTER_SONET_EVENT' => 'N']
				]);
		}
		
		CRest::setLog([
			'fields'   => ['OPPORTUNITY', 'CURRENCY_ID'],
			'withoutTax' => $totalWithoutTax,
			'taxAmount' => $totalTaxAmount,
			'withTax' => $totalWithTax,
			'currencyId' => $currencyId,
			'response' => $updateResult,
		], 'opportunity_update_debug');

		$actions[] = [
			'action'   => 'updated_opportunity',
			'withoutTax' => $totalWithoutTax,
			'taxAmount' => $totalTaxAmount,
			'withTax' => $totalWithTax,
			'currencyId' => $currencyId,
			'apiError' => $updateResult['error'] ?? null,
		];

		// Set utm_check to Done
		if ($entityType !== 'onboarding') {
			$utmCheckField = $entityType === 'lead' ? 'UF_CRM_1781076094241' : 'UF_CRM_6A29D1F62D40F';
			self::updateClassicEntityField($entityType, $entityId, $utmCheckField, 'Done');
			$actions[] = ['action' => 'updated_status', 'field' => $utmCheckField, 'value' => 'Done'];
		}

		CRest::setLog([
			'event' => 'sync_entity_complete',
			'entityType' => $entityType,
			'entityId' => $entityId,
			'actionCount' => count($actions),
			'errorCount' => count($errors),
			'success' => empty($errors),
		], 'sync_entity_lifecycle');

		return [
			'success' => empty($errors),
			'actions' => $actions,
			'errors'  => $errors,
		];
	}

	/**
	 * Delete ALL linked SPA items from a Lead or Deal, clear the fee link fields,
	 * and zero out the subtotal / total fields.
	 * Called when the user saves with zero products.
	 */
	public static function clearAllSpaItems(string $entityType, int $entityId): array
	{
		CRest::setLog([
			'event' => 'clear_all_spa_start',
			'entityType' => $entityType,
			'entityId' => $entityId,
		], 'clear_all_lifecycle');

		$actions = [];
		$errors  = [];

		$feeFields = self::FEE_FIELD_MAPS[$entityType] ?? [];
		if (empty($feeFields)) {
			return ['success' => false, 'actions' => [], 'errors' => ['No fee field mappings for entity type']];
		}

		$entity = self::getClassicEntity($entityType, $entityId);
		if (!$entity) {
			return ['success' => false, 'actions' => [], 'errors' => ['Entity not found']];
		}

		// Delete all product rows from the Lead/Deal
		$entityTypeId = self::ENTITY_TYPE_MAP[$entityType] ?? null;
		$ownerType = $entityTypeId ? (self::OWNER_TYPE_MAP[$entityTypeId] ?? null) : null;
		if ($ownerType) {
			$productRows = self::getProductRows($ownerType, $entityId);
			foreach ($productRows as $row) {
				$rowId = (int)($row['id'] ?? 0);
				if ($rowId > 0) {
					$delRes = CRest::call('crm.item.productrow.delete', ['id' => $rowId]);
					if (!empty($delRes['error'])) {
						$errors[] = "Failed to delete product row {$rowId}: " . ($delRes['error_description'] ?? $delRes['error']);
					}
					$actions[] = [
						'action' => 'deleted_product_row',
						'rowId' => $rowId,
						'success' => empty($delRes['error']),
					];
				}
			}
		}

		// 1. Delete every linked SPA item and clear the link fields
		foreach ($feeFields as $spaTypeId => $fieldCode) {
			if (!$fieldCode) {
				continue;
			}
			$linkedIds = SpaSync::normalizeLinkedIds($entity[$fieldCode] ?? null);

			CRest::setLog([
				'event' => 'clearing_spa_type',
				'spaTypeId' => $spaTypeId,
				'fieldCode' => $fieldCode,
				'linkedIds' => $linkedIds,
			], 'clear_all_debug');

			foreach ($linkedIds as $spaId) {
				$deleted = SpaSync::deleteSpaItem($spaTypeId, $spaId);
				$actions[] = [
					'action'  => $deleted ? 'deleted' : 'delete_failed',
					'spaType' => $spaTypeId,
					'spaId'   => $spaId,
				];
			}

			// Clear the link field on the entity
			self::updateClassicEntityField($entityType, $entityId, $fieldCode, []);
			$actions[] = ['action' => 'unlinked', 'field' => $fieldCode];
		}

		// 2. Zero out subtotal fields
		$currencyId = $entity['CURRENCY_ID'] ?? $entity['currencyId'] ?? 'AED';
		$zeroValue  = '0.00|' . $currencyId;

		$subtotalFields = SpaSync::SUBTOTAL_FIELD_MAPS[$entityType] ?? [];
		foreach ($subtotalFields as $spaTypeId => $fieldCode) {
			if (!$fieldCode) {
				continue;
			}
			self::updateClassicEntityField($entityType, $entityId, $fieldCode, $zeroValue);
			$actions[] = ['action' => 'zeroed_subtotal', 'field' => $fieldCode];
		}

		// 3. Zero out option total fields
		$totalFields = SpaSync::TOTAL_FIELD_MAPS[$entityType] ?? [];
		foreach ($totalFields as $optionKey => $fieldCode) {
			if (!$fieldCode) {
				continue;
			}
			self::updateClassicEntityField($entityType, $entityId, $fieldCode, $zeroValue);
			$actions[] = ['action' => 'zeroed_total', 'field' => $fieldCode, 'option' => $optionKey];
		}

		// 4. Reset opportunity to 0 and ensure currency is set
		if ($entityType === 'onboarding') {
			$updateResult = CRest::call('crm.item.update', [
				'entityTypeId' => 1086,
				'id' => $entityId,
				'fields' => [
					'opportunity' => 0,
					'currencyId' => $currencyId
				]
			]);
		} else {
			$updateResult = $entityType === 'lead'
				? CRest::call('crm.lead.update', [
					'id' => $entityId, 
					'fields' => [
						'OPPORTUNITY' => 0,
						'CURRENCY_ID' => $currencyId
					], 
					'params' => ['REGISTER_SONET_EVENT' => 'N']
				])
				: CRest::call('crm.deal.update', [
					'id' => $entityId, 
					'fields' => [
						'OPPORTUNITY' => 0,
						'CURRENCY_ID' => $currencyId
					], 
					'params' => ['REGISTER_SONET_EVENT' => 'N']
				]);
		}
		$actions[] = [
			'action' => 'reset_opportunity', 
			'fields' => ['OPPORTUNITY', 'CURRENCY_ID'], 
			'value' => 0,
			'currencyId' => $currencyId
		];

		// 5. Set status field to Done
		if ($entityType !== 'onboarding') {
			$utmCheckField = $entityType === 'lead' ? 'UF_CRM_1781076094241' : 'UF_CRM_6A29D1F62D40F';
			self::updateClassicEntityField($entityType, $entityId, $utmCheckField, 'Done');
			$actions[] = ['action' => 'updated_status', 'field' => $utmCheckField, 'value' => 'Done'];
		}

		CRest::setLog([
			'event' => 'clear_all_spa_complete',
			'entityType' => $entityType,
			'entityId' => $entityId,
			'actionCount' => count($actions),
		], 'clear_all_lifecycle');

		return [
			'success' => true,
			'actions' => $actions,
			'errors'  => $errors,
		];
	}

	/**
	 * Sync all Leads and Deals that reference a specific catalog product.
	 */
	public static function syncAllEntitiesByProduct(int $productId): array
	{
		$actions = [];
		$errors  = [];

		if ($productId <= 0) {
			return ['success' => false, 'actions' => [], 'errors' => ['Invalid product ID']];
		}

		$result = CRest::call('crm.item.productrow.list', [
			'filter' => [
				'=productId' => $productId,
			],
		]);

		if (!empty($result['error'])) {
			CRest::setLog($result, 'productrow_list_by_product_error');
			return ['success' => false, 'actions' => [], 'errors' => [$result['error_description'] ?? 'Error fetching product rows']];
		}

		$productRows = $result['result']['productRows'] ?? [];
		$entitiesToSync = [];

		foreach ($productRows as $row) {
			$ownerType = $row['ownerType'] ?? '';
			$ownerId = (int)($row['ownerId'] ?? 0);

			if ($ownerId <= 0) {
				continue;
			}

			$entityType = null;
			if ($ownerType === 'L') {
				$entityType = 'lead';
			} elseif ($ownerType === 'D') {
				$entityType = 'deal';
			}

			if ($entityType) {
				$key = "{$entityType}:{$ownerId}";
				$entitiesToSync[$key] = [
					'type' => $entityType,
					'id'   => $ownerId,
				];
			}
		}

		foreach ($entitiesToSync as $entityInfo) {
			$syncRes = self::syncEntity($entityInfo['type'], $entityInfo['id']);
			$actions = array_merge($actions, $syncRes['actions'] ?? []);
			$errors  = array_merge($errors, $syncRes['errors'] ?? []);
		}

		return [
			'success' => empty($errors),
			'actions' => $actions,
			'errors'  => $errors,
		];
	}

	public static function getProductRows(string $ownerType, int $entityId): array
	{
		$result = CRest::call('crm.item.productrow.list', [
			'filter' => [
				'=ownerType' => $ownerType,
				'=ownerId'   => $entityId,
			],
		]);

		if (!empty($result['error'])) {
			CRest::setLog($result, 'productrow_list_error');
			return [];
		}
		return $result['result']['productRows'] ?? [];
	}

	/**
	 * Get a Lead or Deal entity using the classic CRM methods.
	 * crm.item.get / entityTypeId 1|2 only works for Universal/SPA-migrated
	 * installs; classic Leads and Deals must use crm.lead.get / crm.deal.get.
	 */
	public static function getClassicEntity(string $entityType, int $entityId): ?array
	{
		if ($entityType === 'onboarding') {
			$result = CRest::call('crm.item.get', [
				'entityTypeId' => 1086,
				'id' => $entityId,
			]);
			if (!empty($result['error']) || empty($result['result']['item'])) {
				CRest::setLog($result, 'classic_entity_get_error');
				return null;
			}
			return $result['result']['item'];
		}

		$method = $entityType === 'lead' ? 'crm.lead.get' : 'crm.deal.get';
		$result = CRest::call($method, [
			'id' => $entityId,
		]);
		if (!empty($result['error']) || empty($result['result'])) {
			CRest::setLog($result, 'classic_entity_get_error');
			return null;
		}
		return $result['result'];
	}

	public static function getEntity(int $entityTypeId, int $entityId): ?array
	{
		$result = CRest::call('crm.item.get', [
			'entityTypeId'       => $entityTypeId,
			'id'                 => $entityId,
			'useOriginalUfNames' => 'Y',
		]);
		if (!empty($result['error']) || empty($result['result']['item'])) {
			return null;
		}
		return $result['result']['item'];
	}

	public static function getSpaItem(int $entityTypeId, int $itemId): ?array
	{
		$result = CRest::call('crm.item.get', [
			'entityTypeId'       => $entityTypeId,
			'id'                 => $itemId,
			'useOriginalUfNames' => 'Y',
		]);
		if (!empty($result['error']) || empty($result['result']['item'])) {
			return null;
		}
		return $result['result']['item'];
	}

	/**
	 * Update fields on a Lead or Deal using classic CRM methods.
	 */
	public static function updateClassicEntityField(string $entityType, int $entityId, string $fieldCode, $values): bool
	{
		if ($entityType === 'onboarding') {
			$result = CRest::call('crm.item.update', [
				'entityTypeId' => 1086,
				'id'     => $entityId,
				'fields' => [$fieldCode => $values],
			]);
			if (!empty($result['error'])) {
				CRest::setLog($result, 'classic_entity_update_error');
				return false;
			}
			return true;
		}

		$method = $entityType === 'lead' ? 'crm.lead.update' : 'crm.deal.update';
		$result = CRest::call($method, [
			'id'     => $entityId,
			'fields' => [$fieldCode => $values],
			'params' => ['REGISTER_SONET_EVENT' => 'N'],
		]);
		if (!empty($result['error'])) {
			CRest::setLog($result, 'classic_entity_update_error');
			return false;
		}
		return true;
	}

	public static function updateEntityField(int $entityTypeId, int $entityId, string $fieldCode, $values): bool
	{
		$result = CRest::call('crm.item.update', [
			'entityTypeId'       => $entityTypeId,
			'id'                 => $entityId,
			'fields'             => [$fieldCode => $values],
			'useOriginalUfNames' => 'Y',
		]);
		if (!empty($result['error'])) {
			CRest::setLog($result, 'entity_update_error');
			return false;
		}
		return true;
	}

	/**
	 * Sync SPA item changes back to the corresponding product row (bidirectional).
	 */
	public static function syncSpaToProduct(string $entityType, int $entityId, int $spaEntityTypeId, int $spaItemId): array
	{
		$entityTypeId = self::ENTITY_TYPE_MAP[$entityType] ?? null;
		if (!$entityTypeId) {
			return ['success' => false, 'error' => 'Unknown entity type'];
		}

		$spaItem = self::getSpaItem($spaEntityTypeId, $spaItemId);
		if (!$spaItem) {
			return ['success' => false, 'error' => 'SPA item not found'];
		}

		$productName = $spaItem['title'] ?? '';
		if ($productName === '') {
			return ['success' => false, 'error' => 'SPA item has no title'];
		}

		$ownerType = self::OWNER_TYPE_MAP[$entityTypeId];
		$productRows = self::getProductRows($ownerType, $entityId);

		$targetRow = null;
		foreach ($productRows as $row) {
			if (strcasecmp(trim($row['productName'] ?? ''), $productName) === 0) {
				$targetRow = $row;
				break;
			}
		}

		$spaFieldMap = FieldMapper::buildSpaFieldMap($spaEntityTypeId);
		$priceField = FieldMapper::resolveSpaField($spaFieldMap, ['Price', 'Amount', 'opportunity']);
		$qtyField   = FieldMapper::resolveSpaField($spaFieldMap, ['Quantity', 'Qty']);

		$amount = (float)($spaItem[$priceField ?: 'opportunity'] ?? 0);
		$quantity = (float)($spaItem[$qtyField] ?? 1);
		if ($quantity <= 0) {
			$quantity = 1;
		}
		$price = $amount / $quantity;

		if ($targetRow) {
			$result = CRest::call('crm.item.productrow.update', [
				'id'     => (int)$targetRow['id'],
				'fields' => [
					'productName' => $productName,
					'price'       => $price,
					'quantity'    => $quantity,
				],
			]);
			if (!empty($result['error'])) {
				return ['success' => false, 'error' => $result['error_description'] ?? $result['error']];
			}
			return ['success' => true, 'action' => 'product_updated', 'productRowId' => $targetRow['id']];
		}

		return ['success' => false, 'error' => 'Matching product row not found'];
	}

	/**
	 * Find Lead/Deal entities that link to a given SPA item and sync SPA → product.
	 */
	public static function syncSpaItemToLinkedEntities(int $spaEntityTypeId, int $spaItemId): array
	{
		$results = [];
		foreach (['lead', 'deal'] as $entityType) {
			$entityTypeId = self::ENTITY_TYPE_MAP[$entityType];
			$feeFields = FieldMapper::discoverFeeLinkFields($entityType);
			$feeKey = $spaEntityTypeId === SpaSync::SPA_PROFESSIONAL_FEES ? 'professional' : 'government';
			$fieldCode = $feeFields[$feeKey] ?? null;
			if (!$fieldCode) {
				continue;
			}

			$listResult = CRest::call('crm.item.list', [
				'entityTypeId'       => $entityTypeId,
				'filter'             => ['@' . $fieldCode => [$spaItemId]],
				'select'             => ['id'],
				'useOriginalUfNames' => 'Y',
			]);

			if (empty($listResult['result']['items'])) {
				continue;
			}

			foreach ($listResult['result']['items'] as $item) {
				$entityId = (int)$item['id'];
				$results[] = self::syncSpaToProduct($entityType, $entityId, $spaEntityTypeId, $spaItemId);
			}
		}
		return $results;
	}
}