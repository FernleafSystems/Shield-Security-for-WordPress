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
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Zone\Secadmin;

class PageConfigureLanding extends PageModeLandingBase {

	public const SLUG = 'plugin_admin_page_configure_landing';
	public const TEMPLATE = '/wpadmin/plugin_pages/inner/configure_landing.twig';
	private ?array $configureMeterPayload = null;
	private ?MeterHandler $meterHandler = null;

	protected function getLandingTitle() :string {
		return __( 'Configure', 'wp-simple-firewall' );
	}

	protected function getLandingSubtitle() :string {
		return __( 'Check posture and jump to core security configuration areas.', 'wp-simple-firewall' );
	}

	protected function getLandingIcon() :string {
		return 'gear';
	}

	protected function getLandingContent() :array {
		$con = self::con();
		$meterPayload = $this->getConfigureMeterPayload();

		$heroMeter = $con->action_router->render( MeterCard::class, [
			'meter_slug'    => MeterSummary::SLUG,
			'meter_channel' => MeterComponent::CHANNEL_CONFIG,
			'is_hero'       => true,
		] );

		return [
			'hero_meter'           => $heroMeter,
			'overview_meter_cards' => $this->buildOverviewMeterCards( $meterPayload[ 'snapshots' ] ),
		];
	}

	protected function getLandingHrefs() :array {
		$con = self::con();
		return [
			'grades'       => $con->plugin_urls->adminTopNav( PluginNavs::NAV_DASHBOARD, PluginNavs::SUBNAV_DASHBOARD_GRADES ),
			'zones_home'   => $con->plugin_urls->adminTopNav( PluginNavs::NAV_ZONES, Secadmin::Slug() ),
			'rules_manage' => $con->plugin_urls->adminTopNav( PluginNavs::NAV_RULES, PluginNavs::SUBNAV_RULES_MANAGE ),
			'tools_import' => $con->plugin_urls->adminTopNav( PluginNavs::NAV_TOOLS, PluginNavs::SUBNAV_TOOLS_IMPORT ),
		];
	}

	protected function getLandingVars() :array {
		$meterPayload = $this->getConfigureMeterPayload();
		$zoneLinks = ( new ZoneRenderDataBuilder() )->getZoneLinks();
		return [
			'configure_stats' => $this->buildConfigureStats( $meterPayload[ 'traffic_counts' ], \count( $zoneLinks ) ),
			'zone_links'      => $zoneLinks,
		];
	}

	protected function getLandingStrings() :array {
		return [
			'posture_title'     => __( 'Configuration Posture', 'wp-simple-firewall' ),
			'stats_title'       => __( 'Posture Snapshot', 'wp-simple-firewall' ),
			'overview_title'    => __( 'Configuration Areas', 'wp-simple-firewall' ),
			'zones_title'       => __( 'Security Zones', 'wp-simple-firewall' ),
			'zones_subtitle'    => __( 'Jump directly to a security zone to review and adjust settings.', 'wp-simple-firewall' ),
			'quick_links_title' => __( 'Quick Links', 'wp-simple-firewall' ),
			'link_grades'       => __( 'Security Grades', 'wp-simple-firewall' ),
			'link_zones'        => __( 'Security Zones', 'wp-simple-firewall' ),
			'link_rules'        => __( 'Rules Manager', 'wp-simple-firewall' ),
			'link_tools'        => __( 'Import/Export Tool', 'wp-simple-firewall' ),
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

	private function buildOverviewMeterCards( array $meterSnapshots ) :array {
		$cards = [];
		foreach ( $meterSnapshots as $meterSnapshot ) {
			$meterSlug = $meterSnapshot[ 'slug' ];
			$meterData = $meterSnapshot[ 'meter_data' ];
			$traffic = $meterSnapshot[ 'traffic' ];
			$cards[] = [
				'slug'    => $meterSlug,
				'traffic' => $traffic,
				'html'    => self::con()->action_router->render( MeterCard::class, [
					'meter_slug'    => $meterSlug,
					'meter_channel' => MeterComponent::CHANNEL_CONFIG,
					'meter_data'    => $meterData,
				] ),
			];
		}
		return $cards;
	}

	private function getConfigureMeterPayload() :array {
		if ( $this->configureMeterPayload === null ) {
			$snapshots = [];
			$trafficCounts = $this->buildEmptyTrafficCounts();
			foreach ( $this->getConfigureMeterSlugs() as $meterSlug ) {
				$meterData = $this->getMeterDataForSlug( $meterSlug );
				$traffic = BuildMeter::trafficFromPercentage( $meterData[ 'totals' ][ 'percentage' ] );
				$trafficCounts[ $traffic ]++;
				$snapshots[] = [
					'slug'       => $meterSlug,
					'meter_data' => $meterData,
					'traffic'    => $traffic,
				];
			}
			$this->configureMeterPayload = [
				'snapshots'     => $snapshots,
				'traffic_counts' => $trafficCounts,
			];
		}
		return $this->configureMeterPayload;
	}

	private function getMeterHandler() :MeterHandler {
		if ( $this->meterHandler === null ) {
			$this->meterHandler = new MeterHandler();
		}
		return $this->meterHandler;
	}

	private function buildConfigureStats( array $meterTrafficCounts, int $zoneCount ) :array {
		return [
			[
				'name'   => __( 'Good Areas', 'wp-simple-firewall' ),
				'counts' => [ 'lifetime' => $meterTrafficCounts[ 'good' ] ],
			],
			[
				'name'   => __( 'Needs Work', 'wp-simple-firewall' ),
				'counts' => [ 'lifetime' => $meterTrafficCounts[ 'warning' ] ],
			],
			[
				'name'   => __( 'Critical Areas', 'wp-simple-firewall' ),
				'counts' => [ 'lifetime' => $meterTrafficCounts[ 'critical' ] ],
			],
			[
				'name'   => __( 'Security Zones', 'wp-simple-firewall' ),
				'counts' => [ 'lifetime' => $zoneCount ],
			],
		];
	}

	private function buildEmptyTrafficCounts() :array {
		return [
			'good'     => 0,
			'warning'  => 0,
			'critical' => 0,
		];
	}
}
