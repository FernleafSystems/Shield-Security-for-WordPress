<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\BuildZonePosture;

/**
 * @phpstan-import-type DetailGroup from StatusDetailGroupsBuilder
 * @phpstan-import-type DiagnosisContract from ConfigureZoneDiagnosisBuilder
 * @phpstan-type ConfigureZoneTile array{
 *   key:string,
 *   panel_target:string,
 *   is_enabled:bool,
 *   is_disabled:bool,
 *   include_in_posture:bool,
 *   label:string,
 *   icon_class:string,
 *   status:string,
 *   status_label:string,
 *   status_icon_class:string,
 *   stat_line:string,
 *   settings_href:string,
 *   settings_label:string,
 *   settings_action:array<string,mixed>,
 *   panel:array{
 *     title:string,
 *     status:string,
 *     status_label:string,
 *     components:list<array<string,mixed>>,
 *     detail_groups:list<DetailGroup>
 *   }
 * }
 * @phpstan-type ZoneCard array{
 *   key:string,
 *   label:string,
 *   icon_class:string,
 *   status:string,
 *   status_label:string,
 *   preview_text:string,
 *   selection_json:string,
 *   is_disabled:bool
 * }
 * @phpstan-type ZoneSection array{
 *   heading:string,
 *   cards:list<ZoneCard>,
 *   collapsible:bool
 * }
 */
trait BuildsConfigureLandingData {

	use StandardStatusMapping;

	private ?array $configureZoneTilesSourceCache = null;
	private ?array $configureLandingTilesCache = null;
	private ?array $configureLandingTileLookupCache = null;
	private ?array $configureZoneDiagnosesCache = null;
	private ?array $configureZoneSectionsCache = null;
	private ?array $configurePostureSummaryCache = null;
	private ?array $zonePostureSourceCache = null;
	private ?string $configureZonesLayerCache = null;

	protected function getRequestedConfigureZoneKey() :string {
		return sanitize_key( $this->getTextInputFromRequestOrActionData( 'zone' ) );
	}

	protected function getValidRequestedConfigureZoneKey() :string {
		$zoneKey = $this->getRequestedConfigureZoneKey();
		return isset( $this->getConfigureLandingTileLookup()[ $zoneKey ] ) ? $zoneKey : '';
	}

	/**
	 * @return list<ConfigureZoneTile>
	 */
	protected function getConfigureZoneTiles() :array {
		if ( $this->configureZoneTilesSourceCache === null ) {
			$this->configureZoneTilesSourceCache = ( new ConfigureZoneTilesBuilder() )->build();
		}

		return $this->configureZoneTilesSourceCache;
	}

	/**
	 * @return array{
	 *   components:list<array<string,mixed>>,
	 *   signals:list<array<string,mixed>>,
	 *   totals:array{score:int,max_weight:int,percentage:int,letter_score:string},
	 *   percentage:int,
	 *   severity:string,
	 *   status:string
	 * }
	 */
	protected function getZonePosture() :array {
		if ( $this->zonePostureSourceCache === null ) {
			$this->zonePostureSourceCache = ( new BuildZonePosture() )->build();
		}

		return $this->zonePostureSourceCache;
	}

	/**
	 * @return list<ConfigureZoneTile>
	 */
	protected function getConfigureLandingTiles() :array {
		if ( $this->configureLandingTilesCache === null ) {
			$this->configureLandingTilesCache = \array_map(
				fn( array $zoneTile ) :array => $this->attachDetailGroups( $zoneTile ),
				$this->getConfigureZoneTiles()
			);
		}

		return $this->configureLandingTilesCache;
	}

	/**
	 * @return array<string,ConfigureZoneTile>
	 */
	protected function getConfigureLandingTileLookup() :array {
		if ( $this->configureLandingTileLookupCache === null ) {
			$this->configureLandingTileLookupCache = [];
			foreach ( $this->getConfigureLandingTiles() as $zoneTile ) {
				$this->configureLandingTileLookupCache[ $zoneTile[ 'key' ] ] = $zoneTile;
			}
		}

		return $this->configureLandingTileLookupCache;
	}

	/**
	 * @return array<string,DiagnosisContract>
	 */
	protected function getConfigureZoneDiagnoses() :array {
		if ( $this->configureZoneDiagnosesCache === null ) {
			$builder = new ConfigureZoneDiagnosisBuilder();
			$this->configureZoneDiagnosesCache = [];
			foreach ( $this->getConfigureLandingTiles() as $zoneTile ) {
				$this->configureZoneDiagnosesCache[ $zoneTile[ 'key' ] ] = $builder->build( $zoneTile );
			}
		}

		return $this->configureZoneDiagnosesCache;
	}

	/**
	 * @return DiagnosisContract
	 */
	protected function getConfigureZoneDiagnosis( string $zoneKey ) :array {
		return $this->getConfigureZoneDiagnoses()[ $zoneKey ];
	}

	/**
	 * @return list<ZoneSection>
	 */
	protected function getConfigureZoneSections() :array {
		if ( $this->configureZoneSectionsCache === null ) {
			$sections = [
				[
					'heading'     => __( 'Zones that need attention', 'wp-simple-firewall' ),
					'cards'       => [],
					'collapsible' => false,
				],
				[
					'heading'     => __( 'Healthy zones and general controls', 'wp-simple-firewall' ),
					'cards'       => [],
					'collapsible' => true,
				],
			];
			$cardsByBand = [
				'critical' => [],
				'warning'  => [],
				'good'     => [],
				'neutral'  => [],
			];

			foreach ( $this->getConfigureLandingTiles() as $zoneTile ) {
				$cardsByBand[ $this->normalizeZoneBand( $zoneTile[ 'status' ] ) ][] = $this->buildZoneCard( $zoneTile );
			}

			$sections[ 0 ][ 'cards' ] = \array_merge( $cardsByBand[ 'critical' ], $cardsByBand[ 'warning' ] );
			$sections[ 1 ][ 'cards' ] = $this->generalLast(
				\array_merge( $cardsByBand[ 'good' ], $cardsByBand[ 'neutral' ] )
			);
			$this->configureZoneSectionsCache = $sections;
		}

		return $this->configureZoneSectionsCache;
	}

	protected function renderConfigureZonesLayer() :string {
		if ( $this->configureZonesLayerCache === null ) {
			$this->configureZonesLayerCache = self::con()->comps->render
				->setTemplate( '/wpadmin/components/configure/layer_zones.twig' )
				->setData( [
					'sections' => $this->getConfigureZoneSections(),
				] )
				->render();
		}

		return $this->configureZonesLayerCache;
	}

	protected function renderConfigureDiagnosisLayer( string $zoneKey ) :string {
		return self::con()->comps->render
			->setTemplate( '/wpadmin/components/configure/layer_diagnosis.twig' )
			->setData( $this->buildConfigureDiagnosisRenderData( $zoneKey ) )
			->render();
	}

	protected function buildConfigureDiagnosisRenderData( string $zoneKey ) :array {
		$diagnosis = $this->getConfigureZoneDiagnosis( $zoneKey );

		return [
			'diagnosis' => $diagnosis,
			'header'             => $diagnosis[ 'header' ],
			'zone_selection'     => $diagnosis[ 'zone_selection' ],
			'editor_selection'   => $diagnosis[ 'editor_selection' ],
		];
	}

	/**
	 * @return array{
	 *   root_step_json:string,
	 *   zones_html:string
	 * }
	 */
	protected function buildConfigureLandingRefresh() :array {
		return [
			'root_step_json' => $this->buildConfigureOperatorRootStepJson(),
			'zones_html'     => $this->renderConfigureZonesLayer(),
		];
	}

	protected function buildConfigureOperatorRootStep() :array {
		$posture = $this->getConfigurePostureSummary();

		return [
			'breadcrumb_label' => __( 'Configure', 'wp-simple-firewall' ),
			'title'            => __( 'Configure', 'wp-simple-firewall' ),
			'summary'          => $posture[ 'summary' ],
			'focus'            => $posture[ 'chip_label' ],
			'next_step'        => __( 'Open a zone to review findings and move into focused settings changes.', 'wp-simple-firewall' ),
			'icon_class'       => self::con()->svgs->iconClass( 'gear' ),
			'badge'            => sprintf( '%s%%', $posture[ 'meter' ][ 'percentage' ] ),
			'badge_status'     => $posture[ 'status' ],
			'color_key'        => 'configure',
		];
	}

	protected function buildConfigureOperatorRootStepJson() :string {
		return (string)( \json_encode( $this->buildConfigureOperatorRootStep() ) ?: '' );
	}

	/**
	 * @return array{
	 *   status:string,
	 *   chip_label:string,
	 *   icon_class:string,
	 *   eyebrow:string,
	 *   summary:string,
	 *   meter:array{
	 *     percentage:int,
	 *     status:string,
	 *     aria_label:string,
	 *     aria_value_text:string
	 *   }
	 * }
	 */
	protected function getConfigurePostureSummary() :array {
		if ( $this->configurePostureSummaryCache === null ) {
			$posturePercentage = $this->getZonePosture()[ 'percentage' ];
			$postureStatus = BuildZonePosture::trafficFromPercentage( $posturePercentage );
			$this->configurePostureSummaryCache = [
				'status'     => $postureStatus,
				'chip_label' => $this->standardStatusLabel( $postureStatus ),
				'icon_class' => $this->standardStatusIconClass( $postureStatus ),
				'eyebrow'    => __( 'Configuration Posture', 'wp-simple-firewall' ),
				'summary'    => $this->buildPostureSummary( $posturePercentage ),
				'meter'      => [
					'percentage'      => $posturePercentage,
					'status'          => $postureStatus,
					'aria_label'      => __( 'Configuration Posture', 'wp-simple-firewall' ),
					'aria_value_text' => sprintf( '%s%%', $posturePercentage ),
				],
			];
		}

		return $this->configurePostureSummaryCache;
	}

	/**
	 * @param ConfigureZoneTile $zoneTile
	 * @return ConfigureZoneTile
	 */
	private function attachDetailGroups( array $zoneTile ) :array {
		$zoneTile[ 'panel' ][ 'detail_groups' ] = ( new StatusDetailGroupsBuilder() )->buildForConfigure(
			\array_values( $zoneTile[ 'panel' ][ 'components' ] ?? [] )
		);
		return $zoneTile;
	}

	/**
	 * @param ConfigureZoneTile $zoneTile
	 * @return ZoneCard
	 */
	private function buildZoneCard( array $zoneTile ) :array {
		$diagnosis = $this->getConfigureZoneDiagnosis( $zoneTile[ 'key' ] );

		return [
			'key'               => $zoneTile[ 'key' ],
			'label'             => $zoneTile[ 'label' ],
			'icon_class'        => $zoneTile[ 'icon_class' ],
			'status'            => $zoneTile[ 'status' ],
			'status_label'      => $zoneTile[ 'status_label' ],
			'preview_text'      => $diagnosis[ 'preview_text' ],
			'selection_json'    => $diagnosis[ 'zone_selection_json' ],
			'is_disabled'       => $zoneTile[ 'is_disabled' ],
		];
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

	private function buildPostureSummary( int $posturePercentage ) :string {
		$counts = [
			'good'     => 0,
			'warning'  => 0,
			'critical' => 0,
		];
		foreach ( $this->getConfigureLandingTiles() as $zoneTile ) {
			if ( !$zoneTile[ 'include_in_posture' ] ) {
				continue;
			}
			if ( isset( $counts[ $zoneTile[ 'status' ] ] ) ) {
				$counts[ $zoneTile[ 'status' ] ]++;
			}
		}

		return sprintf(
			__( '%1$s%% - %2$s - %3$s - %4$s', 'wp-simple-firewall' ),
			$posturePercentage,
			sprintf( _n( '%s critical', '%s critical', $counts[ 'critical' ], 'wp-simple-firewall' ), $counts[ 'critical' ] ),
			sprintf( _n( '%s needs work', '%s need work', $counts[ 'warning' ], 'wp-simple-firewall' ), $counts[ 'warning' ] ),
			sprintf( _n( '%s good', '%s good', $counts[ 'good' ], 'wp-simple-firewall' ), $counts[ 'good' ] )
		);
	}
}
