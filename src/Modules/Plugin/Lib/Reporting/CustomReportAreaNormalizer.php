<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting;

class CustomReportAreaNormalizer {

	private const FIELD_TO_AREA = [
		'changes_zones'    => Constants::REPORT_AREA_CHANGES,
		'statistics_zones' => Constants::REPORT_AREA_STATS,
		'scans_zones'      => Constants::REPORT_AREA_SCANS,
	];

	private array $allowedAreas;

	/**
	 * @param array<string,list<string>> $allowedAreas
	 */
	public function __construct( array $allowedAreas ) {
		$this->allowedAreas = $allowedAreas;
	}

	/**
	 * @param array<string,mixed> $form
	 * @return array<string,list<string>>
	 */
	public function normalize( array $form ) :array {
		$areas = [];

		foreach ( self::FIELD_TO_AREA as $field => $area ) {
			$selected = $form[ $field ] ?? [];
			if ( !\is_array( $selected ) ) {
				continue;
			}

			$normalized = $this->normalizeSelectedZones(
				$selected,
				\is_array( $this->allowedAreas[ $area ] ?? null ) ? $this->allowedAreas[ $area ] : []
			);
			if ( !empty( $normalized ) ) {
				$areas[ $area ] = $normalized;
			}
		}

		return $areas;
	}

	/**
	 * @param array<mixed> $selected
	 * @param array<mixed> $allowed
	 * @return list<string>
	 */
	private function normalizeSelectedZones( array $selected, array $allowed ) :array {
		$input = \array_map(
			static fn( $value ) :string => \is_scalar( $value ) ? \sanitize_key( (string)$value ) : '',
			$selected
		);

		$normalized = [];
		foreach ( $allowed as $zone ) {
			if ( \is_scalar( $zone ) && \in_array( (string)$zone, $input, true ) ) {
				$normalized[] = (string)$zone;
			}
		}
		return $normalized;
	}
}
