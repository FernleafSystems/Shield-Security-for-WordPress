<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

/**
 * @phpstan-import-type ConfigureZoneTileContract from ConfigureZoneTilesBuilder
 * @phpstan-import-type ConfigureLandingTile from ConfigureLandingRenderContracts
 * @phpstan-import-type ConfigureLandingViewData from ConfigureLandingRenderContracts
 * @phpstan-import-type DiagnosisContract from ConfigureLandingRenderContracts
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

		$rootStep = $this->buildOperatorRootStep( $this->configurationCoverageBuilder->build() );

		return [
			'tiles'          => $tiles,
			'tile_lookup'    => $tileLookup,
			'diagnoses'      => $diagnoses,
			'root_step'      => $rootStep,
			'root_step_json' => OperatorChromeContract::encodeJson( $rootStep ),
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
			$zoneTile[ 'panel' ][ 'rows' ]
		);
		return $zoneTile;
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
	 * Configure's step in the breadcrumb carries its configuration coverage.
	 * @param array{
	 *   severity:'good'|'warning'|'critical',
	 *   percentage:int,
	 *   controls:array{total:int,good:int,warning:int,critical:int},
	 *   zones:array{total:int,good:int,warning:int,critical:int}
	 * } $posture
	 * @return OperatorChromeStep
	 */
	private function buildOperatorRootStep( array $posture ) :array {
		return OperatorChromeContract::normalizeStep( [
			'breadcrumb_label' => __( 'Configure', 'wp-simple-firewall' ),
			'title'            => __( 'Configure', 'wp-simple-firewall' ),
			'summary'          => $this->buildPostureSummaryText( $posture ),
			'focus'            => $this->standardStatusLabel( $posture[ 'severity' ] ),
			'next_step'        => __( 'Open a zone to review findings and move into focused settings changes.', 'wp-simple-firewall' ),
			'icon_class'       => self::con()->svgs->iconClass( 'gear' ),
			'badge'            => \sprintf( '%s%%', $posture[ 'percentage' ] ),
			'badge_status'     => $posture[ 'severity' ],
			'color_key'        => 'configure',
		] );
	}
}
