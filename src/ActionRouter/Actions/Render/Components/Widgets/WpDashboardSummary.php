<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\AnyUserAuthRequired;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\{
	BuildMeter,
	Handler,
	Meter\MeterSummary
};
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Options\Transient;

class WpDashboardSummary extends \FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender {

	use AnyUserAuthRequired;

	public const SLUG = 'render_dashboard_widget';
	public const TEMPLATE = '/admin/admin_dashboard_widget_v2.twig';
	public const MAX_ATTENTION_ROWS = 3;

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
				'protecting'         => __( 'Protecting', 'wp-simple-firewall' ),
				'go_to_dashboard'    => __( 'Go to Shield Dashboard', 'wp-simple-firewall' ),
				'last_scan'          => __( 'Last scan', 'wp-simple-firewall' ),
				'view_details'       => __( 'View Details', 'wp-simple-firewall' ),
				'critical'           => __( 'Critical', 'wp-simple-firewall' ),
				'needs_work'         => __( 'Needs Work', 'wp-simple-firewall' ),
				'good'               => __( 'Good', 'wp-simple-firewall' ),
				'refresh'            => __( 'Refresh', 'wp-simple-firewall' ),
				'and_more'           => __( 'and %s more', 'wp-simple-firewall' ),
				'score_needs_review' => __( 'Security score needs review.', 'wp-simple-firewall' ),
			],
			'vars'    => $vars,
		];
	}

	private function getVars( bool $refresh ) :array {
		$con = self::con();
		$provider = new AttentionItemsProvider();
		$vars = Transient::Get( $con->prefix( 'dashboard-widget-v2-vars' ) );
		if ( $refresh || empty( $vars ) ) {
			$securityProgress = ( new Handler() )->getMeter( MeterSummary::class );
			$traffic = BuildMeter::trafficFromPercentage(
				(int)( $securityProgress[ 'totals' ][ 'percentage' ] ?? 0 )
			);

			$widgetRows = $provider->buildWidgetRows(
				self::MAX_ATTENTION_ROWS,
				$securityProgress,
				$traffic,
				$con->plugin_urls->adminHome()
			);
			$attentionRows = $widgetRows[ 'items' ] ?? [];
			$attentionTotal = (int)( $widgetRows[ 'total' ] ?? 0 );
			$attentionHidden = (int)( $widgetRows[ 'hidden' ] ?? 0 );

			$latestScanAt = $provider->getLatestCompletedScanTimestamp( $con->comps->scans->getScanSlugs() );

			$vars = [
				'generated_at'      => Services::Request()->ts(),
				'security_progress' => $securityProgress,
				'traffic'           => $traffic,
				'attention_items'   => $attentionRows,
				'attention_total'   => $attentionTotal,
				'attention_hidden'  => $attentionHidden,
				'is_all_clear'      => $attentionTotal === 0 && $traffic === 'good',
				'last_scan_human'   => $latestScanAt > 0
					? Services::Request()->carbon( true )->setTimestamp( $latestScanAt )->diffForHumans()
					: '',
			];
			Transient::Set( $con->prefix( 'dashboard-widget-v2-vars' ), $vars, 30 );
		}

		return $vars;
	}

	private function isRefreshRequested() :bool {
		return \filter_var( $this->action_data[ 'refresh' ] ?? false, \FILTER_VALIDATE_BOOLEAN );
	}
}
