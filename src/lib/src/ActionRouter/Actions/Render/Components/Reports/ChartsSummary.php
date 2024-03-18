<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Reports;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\Event\Ops as EventsDB;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\IpRules\Ops as IpRulesDB;

class ChartsSummary extends Base {

	public const SLUG = 'render_charts_summary';
	public const TEMPLATE = '/wpadmin_pages/insights/reports/charts_summary.twig';

	protected function getRenderData() :array {
		$con = self::con();
		/** @var EventsDB\Select $eventSelector */
		$eventSelector = $con->db_con->events->getQuerySelector();

		/** @var IpRulesDB\Select $ipRuleSelect */
		$ipRuleSelect = $con->db_con->ip_rules->getQuerySelector();

		$statsData = [
			'login'          => [
				'id'        => 'login_block',
				'title'     => __( 'Login Blocks', 'wp-simple-firewall' ),
				'val'       => sprintf( '%s: %s', __( 'Lifetime Total' ),
					\number_format( $eventSelector->clearWheres()->sumEvent( 'login_block' ) ) ),
				'tooltip_p' => __( 'Total login attempts blocked.', 'wp-simple-firewall' ),
			],
			'bot_blocks'     => [
				'id'        => 'bot_blocks',
				'title'     => __( 'Bot Detection', 'wp-simple-firewall' ),
				'val'       => sprintf( '%s: %s', __( 'Lifetime Total' ),
					\number_format( $eventSelector->clearWheres()->sumEventsLike( 'bottrack_' ) ) ),
				'tooltip_p' => __( 'Total requests identified as bots.', 'wp-simple-firewall' ),
			],
			'transgressions' => [
				'id'        => 'ip_offense',
				'title'     => __( 'Offenses', 'wp-simple-firewall' ),
				'val'       => sprintf( '%s: %s', __( 'Lifetime Total' ),
					\number_format( $eventSelector->clearWheres()->sumEvent( 'ip_offense' ) ) ),
				'tooltip_p' => __( 'Total offenses against the site.', 'wp-simple-firewall' ),
			],
			'conn_kills'     => [
				'id'        => 'conn_kill',
				'title'     => __( 'Connection Killed', 'wp-simple-firewall' ),
				'val'       => sprintf( '%s: %s', __( 'Lifetime Total' ),
					\number_format( $eventSelector->clearWheres()->sumEvent( 'conn_kill' ) ) ),
				'tooltip_p' => __( 'Total connections blocked/killed after too many offenses.', 'wp-simple-firewall' ),
			],
			'ip_blocked'     => [
				'id'        => 'ip_blocked',
				'title'     => __( 'IP Blocked', 'wp-simple-firewall' ),
				'val'       => sprintf( '%s: %s', __( 'Now' ),
					\number_format( $ipRuleSelect->filterByTypes( [
						IpRulesDB\Handler::T_AUTO_BLOCK,
						IpRulesDB\Handler::T_MANUAL_BLOCK
					] )->count() )
				),
				'tooltip_p' => __( 'IP address exceeds offense limit and is blocked.', 'wp-simple-firewall' ),
			],
			'comments'       => [
				'id'        => 'comment_block',
				'title'     => __( 'Comment Blocks', 'wp-simple-firewall' ),
				'val'       => sprintf( '%s: %s', __( 'Lifetime Total' ),
					\number_format( $eventSelector->clearWheres()->sumEvents( [
						'spam_block_bot',
						'spam_block_human',
					] ) ) ),
				'tooltip_p' => __( 'Total SPAM comments blocked.', 'wp-simple-firewall' ),
			],
		];

		foreach ( $statsData as $key => $statData ) {
			$subtitle = sprintf( __( '7 %s', 'wp-simple-firewall' ), __( 'days', 'wp-simple-firewall' ) );
			$statsData[ $key ][ 'title_sub' ] = $subtitle;
			$statsData[ $key ][ 'tooltip_chart' ] = sprintf( '%s: %s.', __( 'Stats', 'wp-simple-firewall' ), $subtitle );
		}

		return [
			'vars' => [
				'stats' => $statsData,
			],
		];
	}
}