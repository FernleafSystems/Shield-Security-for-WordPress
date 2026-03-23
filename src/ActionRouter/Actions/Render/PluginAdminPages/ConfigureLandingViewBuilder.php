<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\BuildZonePosture;

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
	private BuildZonePosture $zonePostureBuilder;

	public function __construct(
		?ConfigureZoneTilesBuilder $tilesBuilder = null,
		?StatusDetailGroupsBuilder $detailGroupsBuilder = null,
		?ConfigureZoneDiagnosisBuilder $diagnosisBuilder = null,
		?BuildZonePosture $zonePostureBuilder = null
	) {
		$this->tilesBuilder = $tilesBuilder ?? new ConfigureZoneTilesBuilder();
		$this->detailGroupsBuilder = $detailGroupsBuilder ?? new StatusDetailGroupsBuilder();
		$this->diagnosisBuilder = $diagnosisBuilder ?? new ConfigureZoneDiagnosisBuilder();
		$this->zonePostureBuilder = $zonePostureBuilder ?? new BuildZonePosture();
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

		$postureSummary = $this->buildPostureSummary( $tiles, $this->zonePostureBuilder->build() );
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
			\array_values( $zoneTile[ 'panel' ][ 'components' ] ?? [] )
		);
		return $zoneTile;
	}

	/**
	 * @param list<ConfigureLandingTile> $tiles
	 * @param array<string,DiagnosisContract> $diagnoses
	 * @return list<ZoneSection>
	 */
	private function buildZoneSections( array $tiles, array $diagnoses ) :array {
		$sections = [
			[
				'heading'          => __( 'Zones that need attention', 'wp-simple-firewall' ),
				'cards'            => [],
				'collapsible'      => false,
				'disclosure_label' => '',
			],
			[
				'heading'          => __( 'Healthy zones and general controls', 'wp-simple-firewall' ),
				'cards'            => [],
				'collapsible'      => true,
				'disclosure_label' => '',
			],
		];
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

		$sections[ 0 ][ 'cards' ] = \array_merge( $cardsByBand[ 'critical' ], $cardsByBand[ 'warning' ] );
		$sections[ 1 ][ 'cards' ] = $this->generalLast(
			\array_merge( $cardsByBand[ 'good' ], $cardsByBand[ 'neutral' ] )
		);
		$sections[ 1 ][ 'disclosure_label' ] = $this->buildHealthyZoneDisclosureLabel(
			\count( $sections[ 1 ][ 'cards' ] )
		);

		return $sections;
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
			'preview_text'   => $diagnosis[ 'preview_text' ],
			'selection_json' => $diagnosis[ 'zone_selection_json' ],
			'is_disabled'    => $zoneTile[ 'is_disabled' ],
		];
	}

	private function buildHealthyZoneDisclosureLabel( int $count ) :string {
		return \sprintf(
			_n(
				'%s healthy zone and general control',
				'%s healthy zones and general controls',
				$count,
				'wp-simple-firewall'
			),
			$count
		);
	}

	private function normalizeZoneBand( string $status ) :string {
		return \in_array( $status, [ 'critical', 'warning', 'good', 'neutral' ], true )
			? $status
			: 'good';
	}

	/**
	 * @param list<ZoneCard> $cards
	 * @return list<ZoneCard>
	 */
	private function generalLast( array $cards ) :array {
		$general = [];
		$others = [];
		foreach ( $cards as $card ) {
			if ( $card[ 'key' ] === 'general' ) {
				$general[] = $card;
			}
			else {
				$others[] = $card;
			}
		}
		return \array_merge( $others, $general );
	}

	/**
	 * @param list<ConfigureLandingTile> $tiles
	 * @param array{
	 *   components:list<array<string,mixed>>,
	 *   signals:list<array<string,mixed>>,
	 *   totals:array{score:int,max_weight:int,percentage:int,letter_score:string},
	 *   percentage:int,
	 *   severity:string,
	 *   status:string
	 * } $postureSource
	 * @return ConfigurePostureSummary
	 */
	private function buildPostureSummary( array $tiles, array $postureSource ) :array {
		$posturePercentage = $postureSource[ 'percentage' ];
		$postureStatus = BuildZonePosture::trafficFromPercentage( $posturePercentage );

		return [
			'status'     => $postureStatus,
			'chip_label' => $this->standardStatusLabel( $postureStatus ),
			'icon_class' => $this->standardStatusIconClass( $postureStatus ),
			'eyebrow'    => __( 'Configuration Posture', 'wp-simple-firewall' ),
			'summary'    => $this->buildPostureSummaryText( $tiles, $posturePercentage ),
			'meter'      => [
				'percentage'      => $posturePercentage,
				'status'          => $postureStatus,
				'aria_label'      => __( 'Configuration Posture', 'wp-simple-firewall' ),
				'aria_value_text' => \sprintf( '%s%%', $posturePercentage ),
			],
		];
	}

	/**
	 * @param list<ConfigureLandingTile> $tiles
	 */
	private function buildPostureSummaryText( array $tiles, int $posturePercentage ) :string {
		$counts = [
			'good'     => 0,
			'warning'  => 0,
			'critical' => 0,
		];

		foreach ( $tiles as $zoneTile ) {
			if ( !$zoneTile[ 'include_in_posture' ] ) {
				continue;
			}
			if ( isset( $counts[ $zoneTile[ 'status' ] ] ) ) {
				$counts[ $zoneTile[ 'status' ] ]++;
			}
		}

		return \sprintf(
			__( '%1$s%% - %2$s - %3$s - %4$s', 'wp-simple-firewall' ),
			$posturePercentage,
			\sprintf( _n( '%s critical', '%s critical', $counts[ 'critical' ], 'wp-simple-firewall' ), $counts[ 'critical' ] ),
			\sprintf( _n( '%s needs work', '%s need work', $counts[ 'warning' ], 'wp-simple-firewall' ), $counts[ 'warning' ] ),
			\sprintf( _n( '%s good', '%s good', $counts[ 'good' ], 'wp-simple-firewall' ), $counts[ 'good' ] )
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
