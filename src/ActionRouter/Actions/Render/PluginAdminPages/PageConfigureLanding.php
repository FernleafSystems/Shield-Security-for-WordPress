<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Meters\MeterCard;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\{
	BuildMeter,
	Component\Base as MeterComponent,
	Handler as MeterHandler,
	Meter\MeterOverallConfig,
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

	protected function getLandingContent() :array {
		return [
			'hero_meter' => self::con()->action_router->render( MeterCard::class, [
				'meter_slug'    => MeterSummary::SLUG,
				'meter_channel' => MeterComponent::CHANNEL_CONFIG,
				'is_hero'       => true,
			] ),
		];
	}

	protected function getLandingVars() :array {
		return [
			'posture_summary' => $this->buildPostureSummary( $this->getMeterTrafficCounts() ),
			'zone_tiles'      => $this->getConfigureZoneTiles(),
		];
	}

	protected function getLandingStrings() :array {
		return [
			'posture_title'  => __( 'Configuration Posture', 'wp-simple-firewall' ),
			'zones_title'    => __( 'Security Zones', 'wp-simple-firewall' ),
			'zones_subtitle' => __( 'Jump directly to a security zone to review and adjust settings.', 'wp-simple-firewall' ),
		];
	}

	protected function getConfigureMeterSlugs() :array {
		return \array_values( \array_diff(
			\array_keys( MeterHandler::METERS ),
			[
				MeterSummary::SLUG,
				MeterOverallConfig::SLUG,
			]
		) );
	}

	protected function getMeterDataForSlug( string $meterSlug ) :array {
		return $this->getMeterHandler()->getMeter( $meterSlug, true, MeterComponent::CHANNEL_CONFIG );
	}

	private function getMeterHandler() :MeterHandler {
		if ( $this->meterHandler === null ) {
			$this->meterHandler = new MeterHandler();
		}
		return $this->meterHandler;
	}

	private function getMeterTrafficCounts() :array {
		$trafficCounts = $this->buildEmptyTrafficCounts();
		foreach ( $this->getConfigureMeterSlugs() as $meterSlug ) {
			$meterData = $this->getMeterDataForSlug( $meterSlug );
			$traffic = BuildMeter::trafficFromPercentage( (int)( $meterData[ 'totals' ][ 'percentage' ] ?? 0 ) );
			if ( isset( $trafficCounts[ $traffic ] ) ) {
				$trafficCounts[ $traffic ]++;
			}
		}
		return $trafficCounts;
	}

	private function buildPostureSummary( array $meterTrafficCounts ) :string {
		$summaryParts = [];
		$criticalCount = (int)( $meterTrafficCounts[ 'critical' ] ?? 0 );
		$warningCount = (int)( $meterTrafficCounts[ 'warning' ] ?? 0 );

		if ( $criticalCount > 0 ) {
			$summaryParts[] = sprintf(
				_n( '%s critical area', '%s critical areas', $criticalCount, 'wp-simple-firewall' ),
				$criticalCount
			);
		}
		if ( $warningCount > 0 ) {
			$summaryParts[] = sprintf(
				_n( '%s area needs work', '%s areas need work', $warningCount, 'wp-simple-firewall' ),
				$warningCount
			);
		}

		if ( empty( $summaryParts ) ) {
			$summaryParts[] = __( 'All configuration areas look good', 'wp-simple-firewall' );
		}
		return implode( ', ', $summaryParts );
	}

	private function buildEmptyTrafficCounts() :array {
		return [
			'good'     => 0,
			'warning'  => 0,
			'critical' => 0,
		];
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
