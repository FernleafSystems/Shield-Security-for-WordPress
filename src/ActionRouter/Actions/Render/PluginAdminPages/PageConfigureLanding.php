<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Meters\MeterCard;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Zones\ZoneRenderDataBuilder;
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
	 *   slug:string,
	 *   label:string,
	 *   icon_class:string,
	 *   href:string
	 * }>|null
	 */
	private ?array $zoneLinksCache = null;

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

	protected function getLandingTiles() :array {
		return \array_map(
			function ( array $zone ) :array {
				return [
					'key'          => $zone[ 'slug' ],
					'panel_target' => $zone[ 'slug' ],
					'is_enabled'   => true,
					'is_disabled'  => false,
				];
			},
			$this->getZoneLinks()
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
			'zone_links'      => $this->getZoneLinks(),
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
	 *   slug:string,
	 *   label:string,
	 *   icon_class:string,
	 *   href:string
	 * }>
	 */
	private function getZoneLinks() :array {
		if ( $this->zoneLinksCache === null ) {
			$this->zoneLinksCache = ( new ZoneRenderDataBuilder() )->getZoneLinks();
		}
		return $this->zoneLinksCache;
	}
}
