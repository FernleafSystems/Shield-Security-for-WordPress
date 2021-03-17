<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Events;

class UI extends BaseShield\UI {

	public function renderSectionCustomChart() :string {

		$eventsMod = $this->getCon()->getModule_Events();
		/** @var Events\Strings $strings */
		$strings = $eventsMod->getStrings();
		$eventNames = $strings->getEventNames();

		/** @var ModCon $mod */
		$mod = $this->getMod();
		return $this->getMod()
					->renderTemplate(
						'/wpadmin_pages/insights/reports/charts_custom.twig',
						[
							'ajax'    => [
								'render_custom_chart' => $mod->getAjaxActionData( 'render_custom_chart', true ),
							],
							'strings' => [
								'select_events'   => __( 'Events', 'wp-simple-firewall' ),
								'select_interval' => __( 'Interval', 'wp-simple-firewall' ),
								'build_chart'     => __( 'Build Chart', 'wp-simple-firewall' ),
							],
							'vars'    => [
								'events'   => $eventNames,
								'interval' => [
									'hourly'  => __( 'Hourly', 'wp-simple-firewall' ),
									'daily'   => __( 'Daily', 'wp-simple-firewall' ),
									'weekly'  => __( 'Weekly', 'wp-simple-firewall' ),
									'monthly' => __( 'Monthly', 'wp-simple-firewall' ),
									'yearly'  => __( 'Yearly', 'wp-simple-firewall' ),
								],
							],
						],
						true
					);
	}

	private function renderSectionSummaryStats() :string {
		$con = $this->getCon();
		/** @var Databases\Events\Select $eventSelector */
		$eventSelector = $con->getModule_Events()
							 ->getDbHandler_Events()
							 ->getQuerySelector();

		/** @var Databases\IPs\Select $oSelectIp */
		$oSelectIp = $con->getModule_IPs()
						 ->getDbHandler_IPs()
						 ->getQuerySelector();

		$statsData = [
			'login'          => [
				'id'        => 'login_block',
				'title'     => __( 'Login Blocks', 'wp-simple-firewall' ),
				'val'       => sprintf( '%s: %s', __( 'Lifetime Total' ),
					number_format( $eventSelector->clearWheres()->sumEvent( 'login_block' ) ) ),
				'tooltip_p' => __( 'Total login attempts blocked.', 'wp-simple-firewall' ),
			],
			//			'firewall'       => [
			//				'id'      => 'firewall_block',
			//				'title'   => __( 'Firewall Blocks', 'wp-simple-firewall' ),
			//				'val'     => $eventSelector->clearWheres()->sumEvent( 'firewall_block' ),
			//				'tooltip' => __( 'Total requests blocked by firewall rules.', 'wp-simple-firewall' )
			//			],
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
					number_format( $oSelectIp->filterByBlacklist()->count() )
				),
				'tooltip_p' => __( 'IP address exceeds offense limit and is blocked.', 'wp-simple-firewall' ),
			],
		];

		foreach ( $statsData as $key => $statData ) {
			$subtitle = sprintf( __( 'previous %s %s', 'wp-simple-firewall' ), 7, __( 'days', 'wp-simple-firewall' ) );
			$statsData[ $key ][ 'title_sub' ] = $subtitle;
			$statsData[ $key ][ 'tooltip_chart' ] = sprintf( '%s: %s.', __( 'Stats', 'wp-simple-firewall' ), $subtitle );
		}

		/** @var ModCon $mod */
		$mod = $this->getMod();
		return $this->getMod()
					->renderTemplate(
						'/wpadmin_pages/insights/reports/charts_summary.twig',
						[
							'ajax' => [
								'render_summary_chart' => $mod->getAjaxActionData( 'render_summary_chart', true ),
							],
							'vars' => [
								'stats' => $statsData,
							],
						],
						true
					);
	}

	public function buildInsightsVars() :array {
		return [
			'content' => [
				'summary_stats' => $this->renderSectionSummaryStats(),
				'custom_chart'  => $this->renderSectionCustomChart(),
			],
			'flags'   => [],
			'strings' => [],
		];
	}
}