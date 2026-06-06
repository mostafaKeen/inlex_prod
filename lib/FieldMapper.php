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
					return $code;
				}
			}
		}
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
			return ['professional' => null, 'government' => null];
		}

		return [
			'professional' => self::findFieldByLabel($result['result'], self::FEE_FIELD_LABELS['professional']),
			'government'   => self::findFieldByLabel($result['result'], self::FEE_FIELD_LABELS['government']),
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
				$map[mb_strtolower(trim($label))] = $code;
			}
		}
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
				return $spaFieldMap[$key];
			}
		}
		return null;
	}
}
