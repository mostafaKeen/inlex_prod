<?php

require_once __DIR__ . '/FieldMapper.php';

class SpaSync
{
	const SPA_PROFESSIONAL_FEES = 1058;
	const SPA_GOVERNMENT_FEES   = 1062;

	const COST_TYPE_GOVERNMENT    = 'government cost';
	const COST_TYPE_PROFESSIONAL  = 'professional cost';

	/** Product property label → SPA field labels */
	const FIELD_SYNC_MAP = [
		'Type of Cost'              => ['Cost Type', 'Type of Cost'],
		'Payments'                  => ['Payment Type', 'Payments'],
		'Visa Type'                 => ['Visa Type'],
		'Visa Status'               => ['Visa Status'],
		'Company Application Type'  => ['Company Application Type'],
	];

	/**
	 * Determine SPA entity type from cost type value.
	 */
	public static function resolveSpaEntityTypeId(?string $costType): ?int
	{
		if ($costType === null || $costType === '') {
			return null;
		}
		$normalized = mb_strtolower(trim($costType));
		if (str_contains($normalized, 'government')) {
			return self::SPA_GOVERNMENT_FEES;
		}
		if (str_contains($normalized, 'professional')) {
			return self::SPA_PROFESSIONAL_FEES;
		}
		return null;
	}

	/**
	 * Find SPA item by exact title (product name).
	 */
	public static function findSpaItemByName(int $entityTypeId, string $productName): ?array
	{
		$result = CRest::call('crm.item.list', [
			'entityTypeId' => $entityTypeId,
			'filter'       => ['=title' => $productName],
			'select'       => ['id', 'title', 'opportunity', '*'],
		]);

		if (!empty($result['error']) || empty($result['result']['items'])) {
			return null;
		}
		return $result['result']['items'][0];
	}

	/**
	 * Build SPA fields payload from product row and catalog product data.
	 */
	public static function buildSpaFields(
		array $productRow,
		?array $catalogProduct,
		array $productPropertyMap,
		array $spaFieldMap
	): array {
		$fields = [];

		$titleField = FieldMapper::resolveSpaField($spaFieldMap, ['title', 'name']);
		$fields[$titleField ?: 'title'] = $productRow['productName'] ?? '';

		$price = (float)($productRow['price'] ?? 0);
		$quantity = (float)($productRow['quantity'] ?? 1);
		$amount = $price * $quantity;

		$priceField = FieldMapper::resolveSpaField($spaFieldMap, ['Price', 'Amount', 'opportunity']);
		$fields[$priceField ?: 'opportunity'] = $amount;

		$qtyField = FieldMapper::resolveSpaField($spaFieldMap, ['Quantity', 'Qty']);
		if ($qtyField) {
			$fields[$qtyField] = $quantity;
		}

		if ($catalogProduct) {
			foreach (self::FIELD_SYNC_MAP as $productPropLabel => $spaLabels) {
				$propCode = $productPropertyMap[mb_strtolower($productPropLabel)] ?? null;
				if (!$propCode) {
					continue;
				}
				$value = self::extractCatalogPropertyValue($catalogProduct, $propCode);
				if ($value === null) {
					continue;
				}
				$spaField = FieldMapper::resolveSpaField($spaFieldMap, $spaLabels);
				if ($spaField) {
					$fields[$spaField] = $value;
				}
			}
		}

		return $fields;
	}

	public static function extractCatalogPropertyValue(array $catalogProduct, string $propCode)
	{
		if (!isset($catalogProduct[$propCode])) {
			return null;
		}
		$prop = $catalogProduct[$propCode];
		if (is_array($prop)) {
			if (isset($prop['value'])) {
				return $prop['value'];
			}
			if (isset($prop[0]['value'])) {
				return $prop[0]['value'];
			}
		}
		return $prop;
	}

	/**
	 * Discover catalog product properties: label → property code (propertyXXX).
	 */
	public static function discoverProductPropertyMap(): array
	{
		$result = CRest::call('catalog.productProperty.list', [
			'select' => ['id', 'code', 'name'],
		]);

		$map = [];
		if (!empty($result['result']['productProperties'])) {
			foreach ($result['result']['productProperties'] as $prop) {
				$code = 'property' . $prop['id'];
				$map[mb_strtolower(trim($prop['name']))] = $code;
			}
		}
		return $map;
	}

	public static function getCatalogProduct(int $productId): ?array
	{
		if ($productId <= 0) {
			return null;
		}
		$result = CRest::call('catalog.product.get', ['id' => $productId]);
		if (!empty($result['error']) || empty($result['result']['product'])) {
			return null;
		}
		return $result['result']['product'];
	}

	public static function getCostTypeFromProduct(?array $catalogProduct, array $productPropertyMap): ?string
	{
		if (!$catalogProduct) {
			return null;
		}
		$propCode = $productPropertyMap[mb_strtolower('Type of Cost')] ?? null;
		if (!$propCode) {
			return null;
		}
		return self::extractCatalogPropertyValue($catalogProduct, $propCode);
	}

	public static function createSpaItem(int $entityTypeId, array $fields): ?int
	{
		$result = CRest::call('crm.item.add', [
			'entityTypeId'       => $entityTypeId,
			'fields'             => $fields,
			'useOriginalUfNames' => 'Y',
		]);
		if (!empty($result['error']) || empty($result['result']['item']['id'])) {
			CRest::setLog($result, 'spa_create_error');
			return null;
		}
		return (int)$result['result']['item']['id'];
	}

	public static function updateSpaItem(int $entityTypeId, int $itemId, array $fields): bool
	{
		$result = CRest::call('crm.item.update', [
			'entityTypeId'       => $entityTypeId,
			'id'                 => $itemId,
			'fields'             => $fields,
			'useOriginalUfNames' => 'Y',
		]);
		if (!empty($result['error'])) {
			CRest::setLog($result, 'spa_update_error');
			return false;
		}
		return true;
	}

	public static function deleteSpaItem(int $entityTypeId, int $itemId): bool
	{
		$result = CRest::call('crm.item.delete', [
			'entityTypeId' => $entityTypeId,
			'id'           => $itemId,
		]);
		if (!empty($result['error'])) {
			CRest::setLog($result, 'spa_delete_error');
			return false;
		}
		return true;
	}

	/**
	 * Check whether SPA item is linked from any Lead/Deal fee field.
	 */
	public static function isSpaItemReferencedElsewhere(int $spaEntityTypeId, int $spaItemId, int $excludeEntityTypeId, int $excludeEntityId): bool
	{
		foreach (['lead', 'deal'] as $entityType) {
			$entityTypeId = $entityType === 'lead' ? 1 : 2;
			$feeFields = FieldMapper::discoverFeeLinkFields($entityType);
			foreach ($feeFields as $fieldCode) {
				if (!$fieldCode) {
					continue;
				}
				$result = CRest::call('crm.item.list', [
					'entityTypeId'       => $entityTypeId,
					'filter'             => ['@' . $fieldCode => [$spaItemId]],
					'select'             => ['id', $fieldCode],
					'useOriginalUfNames' => 'Y',
				]);
				if (empty($result['result']['items'])) {
					continue;
				}
				foreach ($result['result']['items'] as $item) {
					if ($entityTypeId === $excludeEntityTypeId && (int)$item['id'] === $excludeEntityId) {
						continue;
					}
					return true;
				}
			}
		}
		return false;
	}

	public static function normalizeLinkedIds($value): array
	{
		if ($value === null || $value === '' || $value === false) {
			return [];
		}
		if (!is_array($value)) {
			return [(int)$value];
		}
		return array_map('intval', array_filter($value));
	}
}
