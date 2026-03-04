<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\{
	BuildMeter,
	Component\Base as MeterComponent,
	Handler as MeterHandler,
	Meter\MeterSummary
};

class PageConfigureLanding extends PageModeLandingBase {

	public const SLUG = 'plugin_admin_page_configure_landing';
	public const TEMPLATE = '/wpadmin/plugin_pages/inner/configure_landing.twig';
	private ?MeterHandler $meterHandler = null;

	/**
	 * @var list<array{
	 *   key:string,
	 *   panel_target:string,
	 *   is_enabled:bool,
	 *   is_disabled:bool,
	 *   label:string,
	 *   icon_class:string,
	 *   status:string,
	 *   status_label:string,
	 *   stat_line:string,
	 *   settings_href:string,
	 *   settings_label:string,
	 *   panel:array{
	 *     title:string,
	 *     status:string,
	 *     status_label:string,
	 *     components:list<array{
	 *       title:string,
	 *       status:string,
	 *       status_label:string,
	 *       note:string
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
		$posturePercentage = $this->getPosturePercentage();
		$postureStatus = BuildMeter::trafficFromPercentage( $posturePercentage );

		return [
			'posture_status'     => $postureStatus,
			'posture_percentage' => $posturePercentage,
			'posture_label'      => $this->buildPostureLabel( $postureStatus ),
			'posture_icon_class' => $this->buildPostureIconClass( $postureStatus ),
			'posture_summary'    => $this->buildPostureSummary(
				$posturePercentage,
				$this->getZoneStatusCounts( $zoneTiles )
			),
			'zone_tiles'         => $zoneTiles,
		];
	}

	protected function getLandingStrings() :array {
		return [
			'posture_title'  => __( 'Configuration Posture', 'wp-simple-firewall' ),
			'zones_title'    => __( 'Security Zones', 'wp-simple-firewall' ),
			'zones_subtitle' => __( 'Jump directly to a security zone to review and adjust settings.', 'wp-simple-firewall' ),
		];
	}

	protected function getSummaryMeterData() :array {
		return $this->getMeterHandler()->getMeter( MeterSummary::SLUG, true, MeterComponent::CHANNEL_CONFIG );
	}

	private function getPosturePercentage() :int {
		return max( 0, min(
			100,
			(int)( $this->getSummaryMeterData()[ 'totals' ][ 'percentage' ] ?? 0 )
		) );
	}

	private function getMeterHandler() :MeterHandler {
		if ( $this->meterHandler === null ) {
			$this->meterHandler = new MeterHandler();
		}
		return $this->meterHandler;
	}

	/**
	 * @param list<array{status:string}> $zoneTiles
	 * @return array{good:int,warning:int,critical:int}
	 */
	private function getZoneStatusCounts( array $zoneTiles ) :array {
		$counts = [
			'good'     => 0,
			'warning'  => 0,
			'critical' => 0,
		];

		foreach ( $zoneTiles as $zoneTile ) {
			$status = $zoneTile[ 'status' ] ?? '';
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
		switch ( $postureStatus ) {
			case 'critical':
				return __( 'Critical', 'wp-simple-firewall' );
			case 'warning':
				return __( 'Warning', 'wp-simple-firewall' );
			case 'good':
			default:
				return __( 'Good', 'wp-simple-firewall' );
		}
	}

	private function buildPostureIconClass( string $postureStatus ) :string {
		switch ( $postureStatus ) {
			case 'critical':
				$icon = 'x-circle-fill';
				break;
			case 'warning':
				$icon = 'exclamation-circle-fill';
				break;
			case 'good':
			default:
				$icon = 'check-circle-fill';
				break;
		}
		return $this->buildLandingIconClass( $icon );
	}

	/**
	 * @return list<array{
	 *   key:string,
	 *   panel_target:string,
	 *   is_enabled:bool,
	 *   is_disabled:bool,
	 *   label:string,
	 *   icon_class:string,
	 *   status:string,
	 *   status_label:string,
	 *   stat_line:string,
	 *   settings_href:string,
	 *   settings_label:string,
	 *   panel:array{
	 *     title:string,
	 *     status:string,
	 *     status_label:string,
	 *     components:list<array{
	 *       title:string,
	 *       status:string,
	 *       status_label:string,
	 *       note:string
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
