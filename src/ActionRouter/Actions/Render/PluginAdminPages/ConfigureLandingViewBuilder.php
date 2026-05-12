<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

/**
 * @phpstan-import-type ConfigureZoneTileContract from ConfigureZoneTilesBuilder
 * @phpstan-import-type ConfigureLandingTile from ConfigureLandingRenderContracts
 * @phpstan-import-type ConfigureLandingViewData from ConfigureLandingRenderContracts
 * @phpstan-import-type ConfigurePostureSummary from ConfigureLandingRenderContracts
 * @phpstan-import-type DiagnosisContract from ConfigureLandingRenderContracts
 * @phpstan-import-type ZoneCard from ConfigureLandingRenderContracts
 * @phpstan-import-type ZoneSection from ConfigureLandingRenderContracts
 * @phpstan-import-type OperatorChromeStep from OperatorChromeContract
 */
class ConfigureLandingViewBuilder {

	use PluginControllerConsumer;
	use StandardStatusMapping;

	private ConfigureZoneTilesBuilder $tilesBuilder;
	private StatusDetailGroupsBuilder $detailGroupsBuilder;
	private ConfigureZoneDiagnosisBuilder $diagnosisBuilder;
	private BuildConfigurationCoverage $configurationCoverageBuilder;

	public function __construct(
		?ConfigureZoneTilesBuilder $tilesBuilder = null,
		?StatusDetailGroupsBuilder $detailGroupsBuilder = null,
		?ConfigureZoneDiagnosisBuilder $diagnosisBuilder = null,
		?BuildConfigurationCoverage $configurationCoverageBuilder = null
	) {
		$this->tilesBuilder = $tilesBuilder ?? new ConfigureZoneTilesBuilder();
		$this->detailGroupsBuilder = $detailGroupsBuilder ?? new StatusDetailGroupsBuilder();
		$this->diagnosisBuilder = $diagnosisBuilder ?? new ConfigureZoneDiagnosisBuilder();
		$this->configurationCoverageBuilder = $configurationCoverageBuilder ?? new BuildConfigurationCoverage();
	}

	/**
	 * @return ConfigureLandingViewData
	 */
	public function build() :array {
		$tiles = $this->buildLandingTiles();
		$tileLookup = [];
		$diagnoses = [];

		foreach ( $tiles as $zoneTile ) {
			$tileLookup[ $zoneTile[ 'key' ] ] = $zoneTile;
			$diagnoses[ $zoneTile[ 'key' ] ] = $this->diagnosisBuilder->build( $zoneTile );
		}

		$postureSummary = $this->buildPostureSummary( $this->configurationCoverageBuilder->build() );
		$rootStep = $this->buildOperatorRootStep( $postureSummary );

		return [
			'tiles'           => $tiles,
			'tile_lookup'     => $tileLookup,
			'diagnoses'       => $diagnoses,
			'sections'        => $this->buildZoneSections( $tiles, $diagnoses ),
			'posture_summary' => $postureSummary,
			'root_step'       => $rootStep,
			'root_step_json'  => OperatorChromeContract::encodeJson( $rootStep ),
		];
	}

	/**
	 * @return list<ConfigureLandingTile>
	 */
	private function buildLandingTiles() :array {
		return \array_map(
			fn( array $zoneTile ) :array => $this->attachDetailGroups( $zoneTile ),
			$this->tilesBuilder->build()
		);
	}

	/**
	 * @param ConfigureZoneTileContract $zoneTile
	 * @return ConfigureLandingTile
	 */
	private function attachDetailGroups( array $zoneTile ) :array {
		$zoneTile[ 'panel' ][ 'detail_groups' ] = $this->detailGroupsBuilder->buildForConfigure(
			\array_values( $zoneTile[ 'panel' ][ 'rows' ] ?? [] )
		);
		return $zoneTile;
	}

	/**
	 * @param list<ConfigureLandingTile> $tiles
	 * @param array<string,DiagnosisContract> $diagnoses
	 * @return list<ZoneSection>
	 */
	private function buildZoneSections( array $tiles, array $diagnoses ) :array {
		$cardsByBand = [
			'critical' => [],
			'warning'  => [],
			'good'     => [],
			'neutral'  => [],
		];

		foreach ( $tiles as $zoneTile ) {
			$cardsByBand[ $this->normalizeZoneBand( $zoneTile[ 'status' ] ) ][] = $this->buildZoneCard(
				$zoneTile,
				$diagnoses[ $zoneTile[ 'key' ] ]
			);
		}

		$sections = [
			[
				'key'   => 'critical',
				'cards' => $cardsByBand[ 'critical' ],
			],
			[
				'key'   => 'warning',
				'cards' => $cardsByBand[ 'warning' ],
			],
			[
				'key'   => 'general',
				'cards' => $cardsByBand[ 'neutral' ],
			],
			[
				'key'   => 'healthy',
				'cards' => $cardsByBand[ 'good' ],
			],
		];

		return \array_values( \array_filter(
			$sections,
			static fn( array $section ) :bool => !empty( $section[ 'cards' ] )
		) );
	}

	/**
	 * @param ConfigureLandingTile $zoneTile
	 * @param DiagnosisContract $diagnosis
	 * @return ZoneCard
	 */
	private function buildZoneCard( array $zoneTile, array $diagnosis ) :array {
		return [
			'key'            => $zoneTile[ 'key' ],
			'label'          => $zoneTile[ 'label' ],
			'icon_class'     => $zoneTile[ 'icon_class' ],
			'status'         => $zoneTile[ 'status' ],
			'status_label'   => $zoneTile[ 'status_label' ],
			'summary'        => $zoneTile[ 'summary' ],
			'selection_json' => $diagnosis[ 'zone_selection_json' ],
			'is_disabled'    => $zoneTile[ 'is_disabled' ],
		];
	}

	private function normalizeZoneBand( string $status ) :string {
		return \in_array( $status, [ 'critical', 'warning', 'good', 'neutral' ], true )
			? $status
			: 'good';
	}

	/**
	 * @param array{
	 *   severity:'good'|'warning'|'critical',
	 *   percentage:int,
	 *   controls:array{total:int,good:int,warning:int,critical:int},
	 *   zones:array{total:int,good:int,warning:int,critical:int}
	 * } $postureSource
	 * @return ConfigurePostureSummary
	 */
	private function buildPostureSummary( array $postureSource ) :array {
		$posturePercentage = $postureSource[ 'percentage' ];
		$postureStatus = $postureSource[ 'severity' ];

		return [
			'status'     => $postureStatus,
			'chip_label' => $this->standardStatusLabel( $postureStatus ),
			'icon_class' => $this->standardStatusIconClass( $postureStatus ),
			'eyebrow'    => __( 'Configuration Coverage', 'wp-simple-firewall' ),
			'summary'    => $this->buildPostureSummaryText( $postureSource ),
			'meter'      => [
				'percentage'      => $posturePercentage,
				'status'          => $postureStatus,
				'aria_label'      => __( 'Configuration Coverage', 'wp-simple-firewall' ),
				'aria_value_text' => \sprintf( '%s%%', $posturePercentage ),
			],
		];
	}

	/**
	 * @param array{
	 *   percentage:int,
	 *   zones:array{total:int,good:int,warning:int,critical:int}
	 * } $postureSource
	 */
	private function buildPostureSummaryText( array $postureSource ) :string {
		$zoneCounts = $postureSource[ 'zones' ];
		return \sprintf(
			__( '%1$s%% - %2$s - %3$s - %4$s', 'wp-simple-firewall' ),
			$postureSource[ 'percentage' ],
			\sprintf( _n( '%s critical zone', '%s critical zones', $zoneCounts[ 'critical' ], 'wp-simple-firewall' ), $zoneCounts[ 'critical' ] ),
			\sprintf( _n( '%s zone needs review', '%s zones need review', $zoneCounts[ 'warning' ], 'wp-simple-firewall' ), $zoneCounts[ 'warning' ] ),
			\sprintf( _n( '%s zone ready', '%s zones ready', $zoneCounts[ 'good' ], 'wp-simple-firewall' ), $zoneCounts[ 'good' ] )
		);
	}

	/**
	 * @param ConfigurePostureSummary $posture
	 * @return OperatorChromeStep
	 */
	private function buildOperatorRootStep( array $posture ) :array {
		return OperatorChromeContract::normalizeStep( [
			'breadcrumb_label' => __( 'Configure', 'wp-simple-firewall' ),
			'title'            => __( 'Configure', 'wp-simple-firewall' ),
			'summary'          => $posture[ 'summary' ],
			'focus'            => $posture[ 'chip_label' ],
			'next_step'        => __( 'Open a zone to review findings and move into focused settings changes.', 'wp-simple-firewall' ),
			'icon_class'       => self::con()->svgs->iconClass( 'gear' ),
			'badge'            => \sprintf( '%s%%', $posture[ 'meter' ][ 'percentage' ] ),
			'badge_status'     => $posture[ 'status' ],
			'color_key'        => 'configure',
		] );
	}
}
