<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	Constants
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\BuildZonePosture;

class PageConfigureLanding extends PageModeLandingBase {

	use StandardStatusMapping;

	public const SLUG = 'plugin_admin_page_configure_landing';
	public const TEMPLATE = '/wpadmin/plugin_pages/inner/configure_landing.twig';
	/**
	 * @var list<array{
	 *   key:string,
	 *   panel_target:string,
	 *   is_enabled:bool,
	 *   is_disabled:bool,
	 *   include_in_posture:bool,
	 *   label:string,
	 *   icon_class:string,
	 *   status:string,
	 *   status_label:string,
	 *   stat_line:string,
	 *   settings_href:string,
	 *   settings_label:string,
	 *   settings_action:array<string,mixed>,
	 *   panel:array{
	 *     title:string,
	 *     status:string,
	 *     status_label:string,
	 *     components:list<array{
	 *       title:string,
	 *       status:string,
	 *       status_label:string,
	 *       note:string,
	 *       explanations:list<string>,
	 *       config_action:array<string,mixed>
	 *     }>
	 *   }
	 * }>|null
	 */
	private ?array $configureZoneTilesCache = null;

	protected function getLandingTitle() :string {
		return __( 'Configure', 'wp-simple-firewall' );
	}

	protected function getLandingSubtitle() :string {
		return __( 'Check posture and jump to core security configuration areas.', 'wp-simple-firewall' );
	}

	protected function getLandingIcon() :string {
		return 'gear';
	}

	protected function getLandingMode() :string {
		return PluginNavs::MODE_CONFIGURE;
	}

	protected function isLandingInteractive() :bool {
		return true;
	}

	/**
	 * @return list<array{
	 *   key:string,
	 *   panel_target:string,
	 *   is_enabled:bool,
	 *   is_disabled:bool
	 * }>
	 */
	protected function getLandingTiles() :array {
		return \array_map(
			function ( array $tile ) :array {
				return [
					'key'          => $tile[ 'key' ],
					'panel_target' => $tile[ 'panel_target' ],
					'is_enabled'   => $tile[ 'is_enabled' ],
					'is_disabled'  => $tile[ 'is_disabled' ],
				];
			},
			$this->getConfigureZoneTiles()
		);
	}

	protected function getLandingVars() :array {
		$zoneTiles = $this->getConfigureZoneTiles();
		$posturePercentage = $this->getZonePosture()[ 'percentage' ];
		$postureStatus = BuildZonePosture::trafficFromPercentage( $posturePercentage );

		return [
			'posture_status'          => $postureStatus,
			'posture_percentage'      => $posturePercentage,
			'posture_label'           => $this->buildPostureLabel( $postureStatus ),
			'posture_icon_class'      => $this->buildPostureIconClass( $postureStatus ),
			'posture_summary'         => $this->buildPostureSummary(
				$posturePercentage,
				$this->getZoneStatusCounts( $zoneTiles )
			),
			'zone_tiles'              => $zoneTiles,
			'configure_render_action' => $this->buildConfigureRenderActionData(),
		];
	}

	protected function getLandingStrings() :array {
		return [
			'posture_title'  => __( 'Configuration Posture', 'wp-simple-firewall' ),
		];
	}

	/**
	 * @param list<array{status:string,include_in_posture?:bool}> $zoneTiles
	 * @return array{good:int,warning:int,critical:int}
	 */
	private function getZoneStatusCounts( array $zoneTiles ) :array {
		$counts = [
			'good'     => 0,
			'warning'  => 0,
			'critical' => 0,
		];

		foreach ( $zoneTiles as $zoneTile ) {
			if ( \array_key_exists( 'include_in_posture', $zoneTile ) && !$zoneTile[ 'include_in_posture' ] ) {
				continue;
			}
			$status = $zoneTile[ 'status' ];
			if ( isset( $counts[ $status ] ) ) {
				$counts[ $status ]++;
			}
		}
		return $counts;
	}

	/**
	 * @param array{good:int,warning:int,critical:int} $zoneStatusCounts
	 */
	private function buildPostureSummary( int $posturePercentage, array $zoneStatusCounts ) :string {
		$criticalCount = $zoneStatusCounts[ 'critical' ];
		$warningCount = $zoneStatusCounts[ 'warning' ];
		$goodCount = $zoneStatusCounts[ 'good' ];

		return sprintf(
			__( '%1$s%% - %2$s - %3$s - %4$s', 'wp-simple-firewall' ),
			$posturePercentage,
			sprintf( _n( '%s critical', '%s critical', $criticalCount, 'wp-simple-firewall' ), $criticalCount ),
			sprintf( _n( '%s needs work', '%s need work', $warningCount, 'wp-simple-firewall' ), $warningCount ),
			sprintf( _n( '%s good', '%s good', $goodCount, 'wp-simple-firewall' ), $goodCount )
		);
	}

	private function buildPostureLabel( string $postureStatus ) :string {
		return $this->standardStatusLabel( $postureStatus );
	}

	private function buildPostureIconClass( string $postureStatus ) :string {
		return $this->standardStatusIconClass( $postureStatus );
	}

	/**
	 * @return array<string,mixed>
	 */
	private function buildConfigureRenderActionData() :array {
		return $this->buildAjaxRenderActionData( self::class, [
			Constants::NAV_ID     => PluginNavs::NAV_ZONES,
			Constants::NAV_SUB_ID => PluginNavs::SUBNAV_ZONES_OVERVIEW,
		] );
	}

	/**
	 * @param class-string<BasePluginAdminPage|PageModeLandingBase> $renderAction
	 * @param array<string,mixed> $auxData
	 * @return array<string,mixed>
	 */
	protected function buildAjaxRenderActionData( string $renderAction, array $auxData = [] ) :array {
		return ActionData::BuildAjaxRender( $renderAction, $auxData );
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
		return ( new BuildZonePosture() )->build();
	}

	/**
	 * @return list<array{
	 *   key:string,
	 *   panel_target:string,
	 *   is_enabled:bool,
	 *   is_disabled:bool,
	 *   include_in_posture:bool,
	 *   label:string,
	 *   icon_class:string,
	 *   status:string,
	 *   status_label:string,
	 *   stat_line:string,
	 *   settings_href:string,
	 *   settings_label:string,
	 *   settings_action:array<string,mixed>,
	 *   panel:array{
	 *     title:string,
	 *     status:string,
	 *     status_label:string,
	 *     components:list<array{
	 *       title:string,
	 *       status:string,
	 *       status_label:string,
	 *       note:string,
	 *       explanations:list<string>,
	 *       config_action:array<string,mixed>
	 *     }>
	 *   }
	 * }>
	 */
	protected function getConfigureZoneTiles() :array {
		if ( $this->configureZoneTilesCache === null ) {
			$this->configureZoneTilesCache = ( new ConfigureZoneTilesBuilder() )->build();
		}
		return $this->configureZoneTilesCache;
	}
}
