<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\AnyUserAuthRequired;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\{
	BuildMeter,
	Component\Base as MeterComponent,
	Handler,
	Meter\MeterSummary
};
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Options\Transient;

class WpDashboardSummary extends \FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender {

	use AnyUserAuthRequired;

	public const SLUG = 'render_dashboard_widget';
	public const TEMPLATE = '/admin/admin_dashboard_widget_v2.twig';
	private const VARS_CACHE_KEY = 'dashboard-widget-v3-vars';

	protected function getRenderData() :array {
		$con = self::con();
		$vars = $this->getVars( $this->isRefreshRequested() );
		$vars[ 'generated_at' ] = Services::Request()
							  ->carbon()
							  ->setTimestamp( $vars[ 'generated_at' ] )
							  ->diffForHumans();
		return [
			'hrefs'   => [
				'overview' => $con->plugin_urls->adminHome(),
				'scans'    => $con->plugin_urls->adminTopNav( PluginNavs::NAV_SCANS, PluginNavs::SUBNAV_SCANS_RESULTS ),
			],
			'flags'   => [
				'show_internal_links' => $con->isPluginAdmin()
			],
			'strings' => [
				'title'              => sprintf( '%s: %s', $con->labels->Name, __( 'Overview', 'wp-simple-firewall' ) ),
				'needs_attention'    => __( 'Action Required', 'wp-simple-firewall' ),
				'all_clear'          => __( 'All Clear', 'wp-simple-firewall' ),
				'no_issues'          => __( 'No security issues currently need attention.', 'wp-simple-firewall' ),
				'config_posture'     => __( 'Configuration Posture', 'wp-simple-firewall' ),
				'action_items'       => __( 'Action Items', 'wp-simple-firewall' ),
				'view_actions'       => __( 'View Actions', 'wp-simple-firewall' ),
				'view_posture'       => __( 'View Posture', 'wp-simple-firewall' ),
				'go_to_dashboard'    => __( 'Go to Shield Dashboard', 'wp-simple-firewall' ),
				'last_scan'          => __( 'Last scan', 'wp-simple-firewall' ),
				'critical'           => __( 'Critical', 'wp-simple-firewall' ),
				'needs_work'         => __( 'Needs Work', 'wp-simple-firewall' ),
				'good'               => __( 'Good', 'wp-simple-firewall' ),
				'refresh'            => __( 'Refresh', 'wp-simple-firewall' ),
			],
			'vars'    => $vars,
		];
	}

	private function getVars( bool $refresh ) :array {
		$con = self::con();
		$provider = new AttentionItemsProvider();
		$cacheKey = $con->prefix( self::VARS_CACHE_KEY );
		$vars = Transient::Get( $cacheKey );
		if ( $refresh || empty( $vars ) ) {
			$configProgress = ( new Handler() )->getMeter(
				MeterSummary::SLUG,
				false,
				MeterComponent::CHANNEL_CONFIG
			);
			$configTraffic = BuildMeter::trafficFromPercentage(
				(int)( $configProgress[ 'totals' ][ 'percentage' ] ?? 0 )
			);
			$actionItems = $provider->buildActionItems();
			$actionTotal = \count( $actionItems );
			$actionTraffic = $this->resolveActionTraffic( $actionItems );

			$latestScanAt = $provider->getLatestCompletedScanTimestamp( $con->comps->scans->getScanSlugs() );

			$vars = [
				'generated_at'    => Services::Request()->ts(),
				'config_progress' => $configProgress,
				'config_traffic'  => $configTraffic,
				'action_total'    => $actionTotal,
				'action_traffic'  => $actionTraffic,
				'is_all_clear'    => $actionTotal === 0,
				'last_scan_human' => $latestScanAt > 0
					? Services::Request()->carbon( true )->setTimestamp( $latestScanAt )->diffForHumans()
					: '',
			];
			Transient::Set( $cacheKey, $vars, 30 );
		}

		return $vars;
	}

	/**
	 * @param array<int, array<string, mixed>> $actionItems
	 */
	private function resolveActionTraffic( array $actionItems ) :string {
		$severity = \strtolower( \trim( (string)( $actionItems[ 0 ][ 'severity' ] ?? 'good' ) ) );
		return \in_array( $severity, [ 'good', 'warning', 'critical' ], true ) ? $severity : 'warning';
	}

	private function isRefreshRequested() :bool {
		return \filter_var( $this->action_data[ 'refresh' ] ?? false, \FILTER_VALIDATE_BOOLEAN );
	}
}
