<?php

require_once __DIR__ . '/../crest.php';
require_once __DIR__ . '/FieldMapper.php';

class EnumMapper {
	private static $directMaps = [
		'ufCrm15_1779367955682' => [ '193' => '449', '195' => '451', '197' => '453', '199' => '455', '201' => '457', '203' => '459', '205' => '461' ],
		'ufCrm17_1779370261982' => [ '193' => '501', '195' => '503', '197' => '505', '199' => '507', '201' => '509', '203' => '511', '205' => '513' ],
		'ufCrm21_1781246319038' => [ '193' => '1467', '195' => '1469', '197' => '1471', '199' => '1473', '201' => '1475', '203' => '1477', '205' => '1479' ],
		'ufCrm23_1781246045913' => [ '193' => '1419', '195' => '1421', '197' => '1423', '199' => '1425', '201' => '1427', '203' => '1429', '205' => '1431' ],
		'ufCrm15_1779367818775' => [ '209' => '445', '207' => '447' ],
		'ufCrm17_1779370162991' => [ '207' => '497', '209' => '499' ]
	];

	public static function map($catalogEnumId, $propCode, $spaFieldCode, $entityTypeId) {
		if ($catalogEnumId === null || $catalogEnumId === '' || !$entityTypeId) {
			return null;
		}
		$key = strval($catalogEnumId);
		if (isset(self::$directMaps[$spaFieldCode][$key])) {
			return self::$directMaps[$spaFieldCode][$key];
		}
		return null;
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

	/** Product property label → SPA field labels */
	const FIELD_SYNC_MAP = [
		'Type of Cost'              => ['Cost Type', 'Type of Cost'],
		'Payments old'              => ['Payment Type', 'Payments'],
		'Visa Type'                 => ['Visa Type'],
		'Visa Status'               => ['Visa Status'],
		'Company Application Type'  => ['Company Application Type'],
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
					$mappedValue = EnumMapper::map($value, $propCode, $spaField, $entityTypeId);
					$fields[$spaField] = ($mappedValue !== null) ? $mappedValue : $value;
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