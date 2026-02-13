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

		$loginCount = $eventSelector->clearWheres()->sumEvent( 'login_block' );
		$botCount = $eventSelector->clearWheres()->sumEventsLike( 'bottrack_' );
		$offenseCount = $eventSelector->clearWheres()->sumEvent( 'ip_offense' );
		$killCount = $eventSelector->clearWheres()->sumEvent( 'conn_kill' );
		$ipBlockedCount = $ipRuleSelect->filterByTypes( [
			IpRulesDB\Handler::T_AUTO_BLOCK,
			IpRulesDB\Handler::T_MANUAL_BLOCK
		] )->count();
		$commentCount = $eventSelector->clearWheres()->sumEvents( [
			'spam_block_bot',
			'spam_block_human',
		] );

		$statsData = [
			'login'          => [
				'id'           => 'login_block',
				'title'        => __( 'Login Blocks', 'wp-simple-firewall' ),
				'val'          => sprintf( '%s: %s', __( 'Lifetime Total', 'wp-simple-firewall' ),
					\number_format( $loginCount ) ),
				'val_number'   => \number_format( $loginCount ),
				'status_class' => 'good',
				'tooltip_p'    => __( 'Total login attempts blocked.', 'wp-simple-firewall' ),
			],
			'bot_blocks'     => [
				'id'           => 'bot_blocks',
				'title'        => __( 'Bot Detection', 'wp-simple-firewall' ),
				'val'          => sprintf( '%s: %s', __( 'Lifetime Total', 'wp-simple-firewall' ),
					\number_format( $botCount ) ),
				'val_number'   => \number_format( $botCount ),
				'status_class' => 'good',
				'tooltip_p'    => __( 'Total requests identified as bots.', 'wp-simple-firewall' ),
			],
			'transgressions' => [
				'id'           => 'ip_offense',
				'title'        => __( 'Offenses', 'wp-simple-firewall' ),
				'val'          => sprintf( '%s: %s', __( 'Lifetime Total', 'wp-simple-firewall' ),
					\number_format( $offenseCount ) ),
				'val_number'   => \number_format( $offenseCount ),
				'status_class' => 'warning',
				'tooltip_p'    => __( 'Total offenses against the site.', 'wp-simple-firewall' ),
			],
			'conn_kills'     => [
				'id'           => 'conn_kill',
				'title'        => __( 'Connection Killed', 'wp-simple-firewall' ),
				'val'          => sprintf( '%s: %s', __( 'Lifetime Total', 'wp-simple-firewall' ),
					\number_format( $killCount ) ),
				'val_number'   => \number_format( $killCount ),
				'status_class' => 'critical',
				'tooltip_p'    => __( 'Total connections blocked/killed after too many offenses.', 'wp-simple-firewall' ),
			],
			'ip_blocked'     => [
				'id'           => 'ip_blocked',
				'title'        => __( 'IP Blocked', 'wp-simple-firewall' ),
				'val'          => sprintf( '%s: %s', __( 'Now', 'wp-simple-firewall' ),
					\number_format( $ipBlockedCount ) ),
				'val_number'   => \number_format( $ipBlockedCount ),
				'status_class' => 'warning',
				'tooltip_p'    => __( 'IP address exceeds offense limit and is blocked.', 'wp-simple-firewall' ),
			],
			'comments'       => [
				'id'           => 'comment_block',
				'title'        => __( 'Comment Blocks', 'wp-simple-firewall' ),
				'val'          => sprintf( '%s: %s', __( 'Lifetime Total', 'wp-simple-firewall' ),
					\number_format( $commentCount ) ),
				'val_number'   => \number_format( $commentCount ),
				'status_class' => 'info',
				'tooltip_p'    => __( 'Total SPAM comments blocked.', 'wp-simple-firewall' ),
			],
		];

		foreach ( $statsData as $key => $statData ) {
			$subtitle = sprintf( __( '7 %s', 'wp-simple-firewall' ), __( 'days', 'wp-simple-firewall' ) );
			$statsData[ $key ][ 'title_sub' ] = $subtitle;
			/* translators: %1$s: title, %2$s: subtitle */
			$statsData[ $key ][ 'tooltip_chart' ] = sprintf( __( '%1$s: %2$s.', 'wp-simple-firewall' ), __( 'Stats', 'wp-simple-firewall' ), $subtitle );
		}

		return [
			'vars' => [
				'stats' => $statsData,
			],
		];
	}
}
