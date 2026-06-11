<?php

require_once __DIR__ . '/SpaSync.php';

class ProductSyncService
{
	const OWNER_TYPE_MAP = [
		1 => 'L',
		2 => 'D',
	];

	const ENTITY_TYPE_MAP = [
		'lead' => 1,
		'deal' => 2,
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
	];

	/**
	 * Run full product → SPA synchronization for a Lead or Deal.
	 *
	 * @return array{success: bool, actions: array, errors: array}
	 */
	public static function syncEntity(string $entityType, int $entityId): array
	{
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

		$ownerType = self::OWNER_TYPE_MAP[$entityTypeId];
		$productRows = self::getProductRows($ownerType, $entityId);

		$entity = self::getEntity($entityTypeId, $entityId);
		if (!$entity) {
			return ['success' => false, 'actions' => [], 'errors' => ['Entity not found']];
		}

		$linkedSpaIds = [];
		foreach ($feeFields as $spaTypeId => $fieldCode) {
			$linkedSpaIds[$spaTypeId] = SpaSync::normalizeLinkedIds($entity[$fieldCode] ?? null);
		}

		$processedSpaIds = [
			1058 => [],
			1062 => [],
			1070 => [],
			1074 => [],
		];
		$productNamesSeen = [];

		foreach ($productRows as $row) {
			$productName = trim($row['productName'] ?? '');
			if ($productName === '') {
				continue;
			}
			$productNamesSeen[] = $productName;

			$catalogProduct = SpaSync::getCatalogProduct((int)($row['productId'] ?? 0));
			$costType = SpaSync::getCostTypeFromProduct($catalogProduct, $productPropertyMap);
			$option = SpaSync::getOptionFromProduct($catalogProduct, $productPropertyMap);
			$spaEntityTypeId = SpaSync::resolveSpaEntityTypeId($costType, $option);

			if (!$spaEntityTypeId) {
				$errors[] = "Product \"{$productName}\": could not determine fee type from cost type \"{$costType}\" and option \"{$option}\"";
				continue;
			}

			$spaFieldMap = $spaFieldMaps[$spaEntityTypeId];
			$spaFields = SpaSync::buildSpaFields($row, $catalogProduct, $productPropertyMap, $spaFieldMap);

			$existing = SpaSync::findSpaItemByName($spaEntityTypeId, $productName);
			if ($existing) {
				$spaItemId = (int)$existing['id'];
				SpaSync::updateSpaItem($spaEntityTypeId, $spaItemId, $spaFields);
				$actions[] = ['action' => 'updated', 'spaType' => $spaEntityTypeId, 'spaId' => $spaItemId, 'product' => $productName];
			} else {
				$spaItemId = SpaSync::createSpaItem($spaEntityTypeId, $spaFields);
				if (!$spaItemId) {
					$errors[] = "Failed to create SPA item for product \"{$productName}\"";
					continue;
				}
				$actions[] = ['action' => 'created', 'spaType' => $spaEntityTypeId, 'spaId' => $spaItemId, 'product' => $productName];
			}

			$processedSpaIds[$spaEntityTypeId][] = $spaItemId;
		}

		// Link SPA items to entity fee fields
		foreach ($feeFields as $spaTypeId => $fieldCode) {
			if (!$fieldCode) {
				continue;
			}
			$newIds = array_values(array_unique($processedSpaIds[$spaTypeId] ?? []));
			$currentIds = $linkedSpaIds[$spaTypeId] ?? [];

			if ($newIds !== $currentIds) {
				self::updateEntityField($entityTypeId, $entityId, $fieldCode, $newIds);
				$actions[] = ['action' => 'linked', 'field' => $fieldCode, 'ids' => $newIds];
			}

			// Remove orphaned SPA items that were linked but product no longer exists
			$orphaned = array_diff($currentIds, $newIds);
			foreach ($orphaned as $orphanId) {
				$orphanItem = self::getSpaItem($spaTypeId, $orphanId);
				$orphanName = $orphanItem['title'] ?? '';

				if ($orphanName !== '' && !in_array($orphanName, $productNamesSeen, true)) {
					if (!SpaSync::isSpaItemReferencedElsewhere($spaTypeId, $orphanId, $entityTypeId, $entityId)) {
						SpaSync::deleteSpaItem($spaTypeId, $orphanId);
						$actions[] = ['action' => 'deleted', 'spaType' => $spaTypeId, 'spaId' => $orphanId, 'product' => $orphanName];
					} else {
						$actions[] = ['action' => 'unlinked', 'spaType' => $spaTypeId, 'spaId' => $orphanId];
					}
				}
			}
		}

		// Set utm_check to Done
		$utmCheckField = $entityType === 'lead' ? 'UF_CRM_1781076094241' : 'UF_CRM_6A29D1F62D40F';
		self::updateEntityField($entityTypeId, $entityId, $utmCheckField, 'Done');
		$actions[] = ['action' => 'updated_status', 'field' => $utmCheckField, 'value' => 'Done'];

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
