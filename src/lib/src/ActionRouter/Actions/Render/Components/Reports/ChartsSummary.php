<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Reports;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\FullPageDisplay\FullPageDisplayDynamic;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\FullPage\Report\SecurityReport;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\ReportingChartSummary;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\Events as EventsDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\IpRules\Ops as IpRulesDB;
use FernleafSystems\Wordpress\Services\Services;

class ChartsSummary extends Base {

	public const SLUG = 'render_charts_summary';
	public const TEMPLATE = '/wpadmin_pages/insights/reports/charts_summary.twig';

	protected function getRenderData() :array {
		$con = $this->con();
		$req = Services::Request();
		/** @var EventsDB\Select $eventSelector */
		$eventSelector = $con->getModule_Events()
							 ->getDbH_Events()
							 ->getQuerySelector();

		/** @var IpRulesDB\Select $ipRuleSelect */
		$ipRuleSelect = $con->getModule_IPs()
							->getDbH_IPRules()
							->getQuerySelector();

		$statsData = [
			'login'          => [
				'id'        => 'login_block',
				'title'     => __( 'Login Blocks', 'wp-simple-firewall' ),
				'val'       => sprintf( '%s: %s', __( 'Lifetime Total' ),
					number_format( $eventSelector->clearWheres()->sumEvent( 'login_block' ) ) ),
				'tooltip_p' => __( 'Total login attempts blocked.', 'wp-simple-firewall' ),
			],
			'bot_blocks'     => [
				'id'        => 'bot_blocks',
				'title'     => __( 'Bot Detection', 'wp-simple-firewall' ),
				'val'       => sprintf( '%s: %s', __( 'Lifetime Total' ),
					number_format( $eventSelector->clearWheres()->sumEventsLike( 'bottrack_' ) ) ),
				'tooltip_p' => __( 'Total requests identified as bots.', 'wp-simple-firewall' ),
			],
			'comments'       => [
				'id'        => 'comment_block',
				'title'     => __( 'Comment Blocks', 'wp-simple-firewall' ),
				'val'       => sprintf( '%s: %s', __( 'Lifetime Total' ),
					number_format( $eventSelector->clearWheres()->sumEvents( [
						'spam_block_bot',
						'spam_block_human',
						'spam_block_recaptcha'
					] ) ) ),
				'tooltip_p' => __( 'Total SPAM comments blocked.', 'wp-simple-firewall' ),
			],
			'transgressions' => [
				'id'        => 'ip_offense',
				'title'     => __( 'Offenses', 'wp-simple-firewall' ),
				'val'       => sprintf( '%s: %s', __( 'Lifetime Total' ),
					number_format( $eventSelector->clearWheres()->sumEvent( 'ip_offense' ) ) ),
				'tooltip_p' => __( 'Total offenses against the site.', 'wp-simple-firewall' ),
			],
			'conn_kills'     => [
				'id'        => 'conn_kill',
				'title'     => __( 'Connection Killed', 'wp-simple-firewall' ),
				'val'       => sprintf( '%s: %s', __( 'Lifetime Total' ),
					number_format( $eventSelector->clearWheres()->sumEvent( 'conn_kill' ) ) ),
				'tooltip_p' => __( 'Total connections blocked/killed after too many offenses.', 'wp-simple-firewall' ),
			],
			'ip_blocked'     => [
				'id'        => 'ip_blocked',
				'title'     => __( 'IP Blocked', 'wp-simple-firewall' ),
				'val'       => sprintf( '%s: %s', __( 'Now' ),
					number_format( $ipRuleSelect->filterByTypes( [
						IpRulesDB\Handler::T_AUTO_BLOCK,
						IpRulesDB\Handler::T_MANUAL_BLOCK
					] )->count() )
				),
				'tooltip_p' => __( 'IP address exceeds offense limit and is blocked.', 'wp-simple-firewall' ),
			],
		];

		foreach ( $statsData as $key => $statData ) {
			$subtitle = sprintf( __( 'previous %s %s', 'wp-simple-firewall' ), 7, __( 'days', 'wp-simple-firewall' ) );
			$statsData[ $key ][ 'title_sub' ] = $subtitle;
			$statsData[ $key ][ 'tooltip_chart' ] = sprintf( '%s: %s.', __( 'Stats', 'wp-simple-firewall' ), $subtitle );
		}

		return [
			'ajax'  => [
				'render_summary_chart' => ActionData::BuildJson( ReportingChartSummary::class ),
			],
			'hrefs' => [
				'report' => $con->plugin_urls->noncedPluginAction( FullPageDisplayDynamic::class, $con->plugin_urls->adminHome(), [
					'render_slug' => SecurityReport::SLUG,
					'render_data' => [
						'interval'       => 'weekly',
						'interval_start' => $req->carbon( true )->subWeek()->timestamp,
						'interval_end'   => $req->carbon( true )->timestamp,
					]
				] ),
			],
			'vars'  => [
				'stats' => $statsData,
			],
		];
	}
}