<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Events;

class UI extends BaseShield\UI {

	public function renderSummaryStats() :string {
		$con = $this->getCon();
		/** @var Databases\Events\Select $oSelEvents */
		$oSelEvents = $con->getModule_Events()
						  ->getDbHandler_Events()
						  ->getQuerySelector();

		/** @var Databases\IPs\Select $oSelectIp */
		$oSelectIp = $con->getModule_IPs()
						 ->getDbHandler_IPs()
						 ->getQuerySelector();

		$aStatsData = [
			'login'          => [
				'id'        => 'login_block',
				'title'     => __( 'Login Blocks', 'wp-simple-firewall' ),
				'val'       => sprintf( '%s: %s', __( 'Lifetime Total' ),
					$oSelEvents->clearWheres()->sumEvent( 'login_block' ) ),
				'tooltip_p' => __( 'Total login attempts blocked.', 'wp-simple-firewall' ),
			],
			//			'firewall'       => [
			//				'id'      => 'firewall_block',
			//				'title'   => __( 'Firewall Blocks', 'wp-simple-firewall' ),
			//				'val'     => $oSelEvents->clearWheres()->sumEvent( 'firewall_block' ),
			//				'tooltip' => __( 'Total requests blocked by firewall rules.', 'wp-simple-firewall' )
			//			],
			'bot_blocks'     => [
				'id'        => 'bot_blocks',
				'title'     => __( 'Bot Detection', 'wp-simple-firewall' ),
				'val'       => sprintf( '%s: %s', __( 'Lifetime Total' ),
					$oSelEvents->clearWheres()->sumEventsLike( 'bottrack_' ) ),
				'tooltip_p' => __( 'Total requests identified as bots.', 'wp-simple-firewall' ),
			],
			'comments'       => [
				'id'        => 'comment_block',
				'title'     => __( 'Comment Blocks', 'wp-simple-firewall' ),
				'val'       => sprintf( '%s: %s', __( 'Lifetime Total' ),
					$oSelEvents->clearWheres()->sumEvents( [
						'spam_block_bot',
						'spam_block_human',
						'spam_block_recaptcha'
					] ) ),
				'tooltip_p' => __( 'Total SPAM comments blocked.', 'wp-simple-firewall' ),
			],
			'transgressions' => [
				'id'        => 'ip_offense',
				'title'     => __( 'Offenses', 'wp-simple-firewall' ),
				'val'       => sprintf( '%s: %s', __( 'Lifetime Total' ),
					$oSelEvents->clearWheres()->sumEvent( 'ip_offense' ) ),
				'tooltip_p' => __( 'Total offenses against the site.', 'wp-simple-firewall' ),
			],
			'conn_kills'     => [
				'id'        => 'conn_kill',
				'title'     => __( 'Connection Killed', 'wp-simple-firewall' ),
				'val'       => sprintf( '%s: %s', __( 'Lifetime Total' ),
					$oSelEvents->clearWheres()->sumEvent( 'conn_kill' ) ),
				'tooltip_p' => __( 'Total connections blocked/killed after too many offenses.', 'wp-simple-firewall' ),
			],
			'ip_blocked'     => [
				'id'        => 'ip_blocked',
				'title'     => __( 'IP Blocked', 'wp-simple-firewall' ),
				'val'       => sprintf( '%s: %s', __( 'Now' ),
					$oSelectIp->filterByBlacklist()->count()
				),
				'tooltip_p' => __( 'IP address exceeds offense limit and is blocked.', 'wp-simple-firewall' ),
			],
		];

		foreach ( $aStatsData as $sKey => $sStatData ) {
			$sSub = sprintf( __( 'previous %s %s', 'wp-simple-firewall' ), 7, __( 'days', 'wp-simple-firewall' ) );
			$aStatsData[ $sKey ][ 'title_sub' ] = $sSub;
			$aStatsData[ $sKey ][ 'tooltip_chart' ] = sprintf( '%s: %s.', __( 'Stats', 'wp-simple-firewall' ), $sSub );
		}

		return $this->getMod()
					->renderTemplate(
						'/wpadmin_pages/insights/reports/summary_stats.twig',
						[
							'ajax'    => [
								'render_chart_post' => $con->getModule_Events()->getAjaxActionData( 'render_chart_post', true ),
							],
							'vars'    => [
								'stats' => $aStatsData,
							],
						],
						true
					);
	}

	public function buildInsightsVars() :array {
		$oEvtsMod = $this->getCon()->getModule_Events();
		/** @var Events\Strings $oStrs */
		$oStrs = $oEvtsMod->getStrings();
		$aEvtNames = $oStrs->getEventNames();

		return [
			'ajax'    => [
				'render_chart' => $oEvtsMod->getAjaxActionData( 'render_chart', true ),
			],
			'content' => [
				'summary_stats' => $this->renderSummaryStats(),
			],
			'flags'   => [],
			'strings' => [
			],
			'vars'    => [
				'events_options' => array_intersect_key(
					$aEvtNames,
					array_flip(
						[
							'ip_offense',
							'conn_kill',
							'firewall_block',
						]
					)
				)
			],
		];
	}
}