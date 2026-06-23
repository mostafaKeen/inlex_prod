<?php

require_once __DIR__ . '/../crest.php';
require_once __DIR__ . '/FieldMapper.php';

class EnumMapper {
	private static $directMaps = [
		'UF_CRM_15_1779367955682' => [ '193' => '449', '195' => '451', '197' => '453', '199' => '455', '201' => '457', '203' => '459', '205' => '461' ],
		'UF_CRM_17_1779370261982' => [ '193' => '501', '195' => '503', '197' => '505', '199' => '507', '201' => '509', '203' => '511', '205' => '513' ],
		'UF_CRM_21_1781246319038' => [ '193' => '1467', '195' => '1469', '197' => '1471', '199' => '1473', '201' => '1475', '203' => '1477', '205' => '1479' ],
		'UF_CRM_23_1781246045913' => [ '193' => '1419', '195' => '1421', '197' => '1423', '199' => '1425', '201' => '1427', '203' => '1429', '205' => '1431' ],
		'UF_CRM_15_1779367818775' => [ '209' => '445', '207' => '447' ],
		'UF_CRM_17_1779370162991' => [ '207' => '497', '209' => '499' ],
		'UF_CRM_21_1781246456038' => [ '1509' => '1509', '1511' => '1511', '1513' => '1513' ],
		'UF_CRM_23_1781246158553' => [ '1447' => '1447', '1449' => '1449', '1451' => '1451' ],
	];

	public static function map($catalogEnumId, $propCode, $spaFieldCode, $entityTypeId) {
		if ($catalogEnumId === null || $catalogEnumId === '' || !$entityTypeId) {
			CRest::setLog([
				'error' => 'Invalid input to EnumMapper::map',
				'catalogEnumId' => $catalogEnumId,
				'propCode' => $propCode,
				'spaFieldCode' => $spaFieldCode,
				'entityTypeId' => $entityTypeId,
			], 'enum_mapper_invalid_input');
			return null;
		}

		$key = strval($catalogEnumId);
		
		if (!isset(self::$directMaps[$spaFieldCode])) {
			CRest::setLog([
				'warning' => 'SPA field code not found in enum maps',
				'spaFieldCode' => $spaFieldCode,
				'catalogEnumId' => $catalogEnumId,
				'availableFields' => array_keys(self::$directMaps),
			], 'enum_mapper_field_not_found');
			return null;
		}

		if (isset(self::$directMaps[$spaFieldCode][$key])) {
			$mapped = self::$directMaps[$spaFieldCode][$key];
			CRest::setLog([
				'info' => 'Enum mapped successfully',
				'catalogId' => $catalogEnumId,
				'spaId' => $mapped,
				'spaFieldCode' => $spaFieldCode,
			], 'enum_mapper_success');
			return $mapped;
		}

		CRest::setLog([
			'warning' => 'No mapping found for enum ID in SPA field',
			'spaFieldCode' => $spaFieldCode,
			'catalogEnumId' => $catalogEnumId,
			'availableMappings' => array_keys(self::$directMaps[$spaFieldCode]),
		], 'enum_mapper_no_mapping');
		return null;
	}

	/**
	 * Get all available enum mappings for a SPA field code
	 */
	public static function getFieldMappings($spaFieldCode) {
		return self::$directMaps[$spaFieldCode] ?? [];
	}
}

class SpaSync
{
	const SPA_PROF_FEES_OPT1 = 1058;
	const SPA_GOV_FEES_OPT1  = 1062;
	const SPA_PROF_FEES_OPT2 = 1070;
	const SPA_GOV_FEES_OPT2  = 1074;

	const SPA_PROFESSIONAL_FEES = 1058;
	const SPA_GOVERNMENT_FEES   = 1062;

	const COST_TYPE_GOVERNMENT    = 'government cost';
	const COST_TYPE_PROFESSIONAL  = 'professional cost';

	/** Sub-total field codes per entity type, keyed by SPA entity type ID */
	const SUBTOTAL_FIELD_MAPS = [
		'deal' => [
			1058 => 'UF_CRM_6A2BBF1079900', // Sub_Total_P_Option1
			1062 => 'UF_CRM_6A2BBF1037949', // Sub_Total_G_Option1
			1070 => 'UF_CRM_6A2BBF108E6DF', // Sub_Total_P_Option2
			1074 => 'UF_CRM_6A2BBF10601D8', // Sub_Total_G_Option2
		],
		'lead' => [
			1058 => 'UF_CRM_1781250868208', // Sub_Total_P_Option1
			1062 => 'UF_CRM_1781250851186', // Sub_Total_G_Option1
			1070 => 'UF_CRM_1781250874422', // Sub_Total_P_Option2
			1074 => 'UF_CRM_1781250858979', // Sub_Total_G_Option2
		],
	];

	/** Total field codes per entity type, keyed by Option 1 and Option 2 */
	const TOTAL_FIELD_MAPS = [
		'deal' => [
			'option1' => 'UF_CRM_6A2FE9D5116B2', // Option 1 - Total
			'option2' => 'UF_CRM_6A2FE9D53394C', // Option 2 - Total
		],
		'lead' => [
			'option1' => 'UF_CRM_1781524857503', // Option 1 - Total
			'option2' => 'UF_CRM_1781524864424', // Option 2 - Total
		],
	];

	/** Product property label → SPA field labels */
	const FIELD_SYNC_MAP = [
		'Type of Cost'              => ['Cost Type', 'Type of Cost'],
		'Payments old'              => ['Payment Type', 'Payments'],
		'Visa Type'                 => ['Visa Type'],
		'Visa Status'               => ['Visa Status'],
		'Company Application Type'  => ['Company Application Type'],
	];

	/**
	 * Map SPA entity type ID to field code for payment syncing
	 */
	const PAYMENT_FIELD_CODES = [
		1058 => 'UF_CRM_15_1779367955682',  // Professional Fees Option 1
		1062 => 'UF_CRM_17_1779370261982',  // Government Fees Option 1
		1070 => 'UF_CRM_21_1781246319038',  // Professional Fees Option 2
		1074 => 'UF_CRM_23_1781246045913',  // Government Fees Option 2
	];

	/**
	 * FIX: Explicit mapping for quantity fields per SPA entity type
	 * These are the field codes for the quantity field in each SPA type.
	 * Used as fallback when label-based resolution fails.
	 */
	const QUANTITY_FIELD_CODES = [
		1058 => 'UF_CRM_15_1782194345',  // Professional Fees Option 1 - quantity
		1062 => 'UF_CRM_17_1782194321',  // Government Fees Option 1 - quantity
		1070 => 'UF_CRM_21_1782194288',  // Professional Fees Option 2 - quantity
		1074 => 'UF_CRM_23_1782194371',  // Government Fees Option 2 - quantity
	];

	/**
	 * Determine SPA entity type from cost type value and option value.
	 */
	public static function resolveSpaEntityTypeId(?string $costType, ?string $option = null): ?int
	{
		if ($costType === null || $costType === '') {
			return null;
		}
		$normalized = mb_strtolower(trim($costType));
		$isGov = str_contains($normalized, 'government') || $normalized === '207';
		$isProf = str_contains($normalized, 'professional') || $normalized === '209';

		if (!$isGov && !$isProf) {
			return null;
		}

		$optionId = $option !== null ? trim($option) : '';
		$isOption2 = ($optionId === '237' || mb_strtolower($optionId) === 'option 2');

		if ($isGov) {
			return $isOption2 ? self::SPA_GOV_FEES_OPT2 : self::SPA_GOV_FEES_OPT1;
		}
		if ($isProf) {
			return $isOption2 ? self::SPA_PROF_FEES_OPT2 : self::SPA_PROF_FEES_OPT1;
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
	 * Find SPA item by External ID (xmlId).
	 */
	public static function findSpaItemByXmlId(int $entityTypeId, string $xmlId): ?array
	{
		$result = CRest::call('crm.item.list', [
			'entityTypeId' => $entityTypeId,
			'filter'       => ['=xmlId' => $xmlId],
			'select'       => ['id', 'title', 'xmlId', 'opportunity', '*'],
		]);

		if (!empty($result['error']) || empty($result['result']['items'])) {
			return null;
		}
		return $result['result']['items'][0];
	}

	/**
	 * Build SPA fields payload from product row and catalog product data.
	 * 
	 * FIX: Now includes quantity field using explicit field code mapping
	 * as fallback if label-based resolution fails.
	 */
	public static function buildSpaFields(
		array $productRow,
		?array $catalogProduct,
		array $productPropertyMap,
		array $spaFieldMap,
		?string $xmlId = null,
		?int $entityTypeId = null
	): array {
		$fields = [];

		CRest::setLog([
			'event' => 'buildSpaFields_start',
			'entityTypeId' => $entityTypeId,
			'productRow' => $productRow,
			'hasCatalogProduct' => !empty($catalogProduct),
		], 'spa_build_fields_debug');

		$titleField = FieldMapper::resolveSpaField($spaFieldMap, ['title', 'name']);
		$fields[$titleField ?: 'title'] = $productRow['productName'] ?? '';

		if ($xmlId !== null && $xmlId !== '') {
			$fields['xmlId'] = $xmlId;
		}

		$price = (float)($productRow['price'] ?? 0);
		$quantity = (float)($productRow['quantity'] ?? 1);
		$amount = $price * $quantity;

		$priceField = FieldMapper::resolveSpaField($spaFieldMap, ['Price', 'Amount', 'opportunity']);
		$fields[$priceField ?: 'opportunity'] = $amount;

		// FIX: Try label-based resolution first, then fall back to explicit field code
		$qtyField = FieldMapper::resolveSpaField($spaFieldMap, ['Quantity', 'Qty']);
		
		// If label resolution fails, use explicit field code mapping
		if (!$qtyField && $entityTypeId && isset(self::QUANTITY_FIELD_CODES[$entityTypeId])) {
			$qtyField = self::QUANTITY_FIELD_CODES[$entityTypeId];
			CRest::setLog([
				'info' => 'Using explicit quantity field code (label resolution failed)',
				'entityTypeId' => $entityTypeId,
				'qtyField' => $qtyField,
			], 'spa_quantity_field_fallback');
		}

		if ($qtyField) {
			$fields[$qtyField] = $quantity;
			CRest::setLog([
				'success' => 'Quantity field set',
				'qtyField' => $qtyField,
				'quantity' => $quantity,
				'entityTypeId' => $entityTypeId,
			], 'spa_quantity_field');
		} else {
			CRest::setLog([
				'warning' => 'Could not resolve quantity field for entity type',
				'entityTypeId' => $entityTypeId,
				'availableFieldsInMap' => count($spaFieldMap),
			], 'spa_quantity_field_error');
		}

		if ($catalogProduct) {
			foreach (self::FIELD_SYNC_MAP as $productPropLabel => $spaLabels) {
				$propCode = $productPropertyMap[mb_strtolower($productPropLabel)] ?? null;
				if (!$propCode) {
					CRest::setLog([
						'warning' => 'Product property not found',
						'propertyLabel' => $productPropLabel,
						'availableProperties' => array_keys($productPropertyMap),
					], 'spa_property_not_found');
					continue;
				}

				$value = self::extractCatalogPropertyValue($catalogProduct, $propCode);
				if ($value === null || $value === '') {
					CRest::setLog([
						'info' => 'Property value is empty',
						'propertyLabel' => $productPropLabel,
						'propCode' => $propCode,
					], 'spa_property_empty');
					continue;
				}

				$spaField = FieldMapper::resolveSpaField($spaFieldMap, $spaLabels);
				if ($spaField) {
					$mappedValue = EnumMapper::map($value, $propCode, $spaField, $entityTypeId);
					
					if ($mappedValue !== null) {
						$fields[$spaField] = $mappedValue;
						CRest::setLog([
							'success' => 'Field mapped',
							'propertyLabel' => $productPropLabel,
							'catalogValue' => $value,
							'spaValue' => $mappedValue,
							'spaField' => $spaField,
						], 'spa_field_mapped');
					} else {
						// Use original value if mapping fails (for non-enum or unmapped values)
						$fields[$spaField] = $value;
						CRest::setLog([
							'warning' => 'Enum mapping failed, using original value',
							'propertyLabel' => $productPropLabel,
							'catalogValue' => $value,
							'spaField' => $spaField,
						], 'spa_field_unmapped_fallback');
					}
				}
			}
		}

		CRest::setLog([
			'event' => 'buildSpaFields_complete',
			'finalFields' => $fields,
		], 'spa_build_fields_debug');

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
			if (isset($prop[0])) {
				return $prop[0];
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

		CRest::setLog([
			'event' => 'product_property_map_discovered',
			'count' => count($map),
			'map' => $map,
		], 'property_discovery_debug');

		return $map;
	}

	public static function getCatalogProduct(int $productId): ?array
	{
		if ($productId <= 0) {
			return null;
		}
		$result = CRest::call('catalog.product.get', ['id' => $productId]);
		if (!empty($result['error']) || empty($result['result']['product'])) {
			CRest::setLog([
				'error' => 'Failed to get catalog product',
				'productId' => $productId,
				'apiError' => $result['error'] ?? null,
			], 'catalog_product_fetch_error');
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

	public static function getOptionFromProduct(?array $catalogProduct, array $productPropertyMap): ?string
	{
		if (!$catalogProduct) {
			return null;
		}
		$propCode = $productPropertyMap[mb_strtolower('Option')] ?? null;
		if (!$propCode) {
			return null;
		}
		return self::extractCatalogPropertyValue($catalogProduct, $propCode);
	}

	public static function createSpaItem(int $entityTypeId, array $fields): ?int
	{
		CRest::setLog([
			'event' => 'spa_item_create_start',
			'entityTypeId' => $entityTypeId,
			'fields' => $fields,
		], 'spa_item_lifecycle');

		$result = CRest::call('crm.item.add', [
			'entityTypeId'       => $entityTypeId,
			'fields'             => $fields,
			'useOriginalUfNames' => 'Y',
		]);

		if (!empty($result['error'])) {
			CRest::setLog([
				'event' => 'spa_item_create_error',
				'entityTypeId' => $entityTypeId,
				'error' => $result['error'],
				'errorDescription' => $result['error_description'] ?? null,
				'sentFields' => $fields,
				'apiResponse' => $result,
			], 'spa_create_error');
			return null;
		}

		if (empty($result['result']['item']['id'])) {
			CRest::setLog([
				'error' => 'SPA item created but no ID returned',
				'result' => $result,
			], 'spa_create_error');
			return null;
		}

		$itemId = (int)$result['result']['item']['id'];
		CRest::setLog([
			'event' => 'spa_item_created',
			'entityTypeId' => $entityTypeId,
			'itemId' => $itemId,
		], 'spa_item_lifecycle');

		return $itemId;
	}

	public static function updateSpaItem(int $entityTypeId, int $itemId, array $fields): bool
	{
		CRest::setLog([
			'event' => 'spa_item_update_start',
			'entityTypeId' => $entityTypeId,
			'itemId' => $itemId,
			'fields' => $fields,
		], 'spa_item_lifecycle');

		$result = CRest::call('crm.item.update', [
			'entityTypeId'       => $entityTypeId,
			'id'                 => $itemId,
			'fields'             => $fields,
			'useOriginalUfNames' => 'Y',
		]);

		if (!empty($result['error'])) {
			CRest::setLog([
				'event' => 'spa_item_update_error',
				'entityTypeId' => $entityTypeId,
				'itemId' => $itemId,
				'error' => $result['error'],
				'errorDescription' => $result['error_description'] ?? null,
				'sentFields' => $fields,
			], 'spa_update_error');
			return false;
		}

		CRest::setLog([
			'event' => 'spa_item_updated',
			'entityTypeId' => $entityTypeId,
			'itemId' => $itemId,
		], 'spa_item_lifecycle');

		return true;
	}

	public static function deleteSpaItem(int $entityTypeId, int $itemId): bool
	{
		CRest::setLog([
			'event' => 'spa_item_delete_start',
			'entityTypeId' => $entityTypeId,
			'itemId' => $itemId,
		], 'spa_item_lifecycle');

		$result = CRest::call('crm.item.delete', [
			'entityTypeId' => $entityTypeId,
			'id'           => $itemId,
		]);

		if (!empty($result['error'])) {
			CRest::setLog([
				'event' => 'spa_item_delete_error',
				'entityTypeId' => $entityTypeId,
				'itemId' => $itemId,
				'error' => $result['error'],
			], 'spa_delete_error');
			return false;
		}

		CRest::setLog([
			'event' => 'spa_item_deleted',
			'entityTypeId' => $entityTypeId,
			'itemId' => $itemId,
		], 'spa_item_lifecycle');

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