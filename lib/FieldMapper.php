<?php

class FieldMapper
{
	const FEE_FIELD_LABELS = [
		'professional' => 'Professional Fees',
		'government'   => 'Government Fees',
	];

	/**
	 * Find a CRM field code by matching listLabel, formLabel, or filterLabel.
	 */
	public static function findFieldByLabel(array $fields, string $label): ?string
	{
		foreach ($fields as $code => $meta) {
			if (!is_array($meta)) {
				continue;
			}
			$labels = array_filter([
				$meta['listLabel'] ?? null,
				$meta['formLabel'] ?? null,
				$meta['filterLabel'] ?? null,
				$meta['title'] ?? null,
			]);
			foreach ($labels as $fieldLabel) {
				if (strcasecmp(trim($fieldLabel), trim($label)) === 0) {
					CRest::setLog([
						'success' => 'Field found by label',
						'searchLabel' => $label,
						'foundCode' => $code,
						'matchedLabel' => $fieldLabel,
					], 'field_discovery');
					return $code;
				}
			}
		}

		CRest::setLog([
			'warning' => 'Field not found by label',
			'searchLabel' => $label,
			'availableFields' => array_keys($fields),
		], 'field_discovery');

		return null;
	}

	/**
	 * Discover Professional Fees and Government Fees field codes for an entity.
	 *
	 * @return array{professional: ?string, government: ?string}
	 */
	public static function discoverFeeLinkFields(string $entityType): array
	{
		$method = $entityType === 'lead' ? 'crm.lead.fields' : 'crm.deal.fields';
		$result = CRest::call($method);

		if (!empty($result['error']) || empty($result['result'])) {
			CRest::setLog([
				'error' => 'Failed to discover fee link fields',
				'entityType' => $entityType,
				'apiError' => $result['error'] ?? null,
			], 'fee_field_discovery_error');
			return ['professional' => null, 'government' => null];
		}

		$professional = self::findFieldByLabel($result['result'], self::FEE_FIELD_LABELS['professional']);
		$government = self::findFieldByLabel($result['result'], self::FEE_FIELD_LABELS['government']);

		CRest::setLog([
			'event' => 'fee_link_fields_discovered',
			'entityType' => $entityType,
			'professional' => $professional,
			'government' => $government,
		], 'fee_field_discovery');

		return [
			'professional' => $professional,
			'government' => $government,
		];
	}

	/**
	 * Build a label → field-code map for SPA entity fields.
	 */
	public static function buildSpaFieldMap(int $entityTypeId): array
	{
		$result = CRest::call('crm.item.fields', [
			'entityTypeId'        => $entityTypeId,
			'useOriginalUfNames'  => 'Y',
		]);

		if (!empty($result['error']) || empty($result['result']['fields'])) {
			CRest::setLog([
				'error' => 'Failed to build SPA field map',
				'entityTypeId' => $entityTypeId,
				'apiError' => $result['error'] ?? null,
			], 'spa_field_map_error');
			return [];
		}

		$map = [];
		foreach ($result['result']['fields'] as $code => $meta) {
			if (!is_array($meta)) {
				continue;
			}
			$labels = array_filter([
				$meta['title'] ?? null,
				$meta['listLabel'] ?? null,
				$meta['formLabel'] ?? null,
			]);
			foreach ($labels as $label) {
				$lowerLabel = mb_strtolower(trim($label));
				$map[$lowerLabel] = $code;
			}
		}

		CRest::setLog([
			'event' => 'spa_field_map_built',
			'entityTypeId' => $entityTypeId,
			'fieldCount' => count($map),
			'fieldMap' => $map,
		], 'spa_field_map_debug');

		return $map;
	}

	/**
	 * Resolve SPA field code by one of several possible labels.
	 */
	public static function resolveSpaField(array $spaFieldMap, array $labels): ?string
	{
		foreach ($labels as $label) {
			$key = mb_strtolower(trim($label));
			if (isset($spaFieldMap[$key])) {
				CRest::setLog([
					'success' => 'SPA field resolved',
					'searchLabel' => $label,
					'resolvedCode' => $spaFieldMap[$key],
				], 'spa_field_resolution');
				return $spaFieldMap[$key];
			}
		}

		CRest::setLog([
			'warning' => 'SPA field not resolved',
			'searchLabels' => $labels,
			'availableFields' => array_keys($spaFieldMap),
		], 'spa_field_resolution');

		return null;
	}
}