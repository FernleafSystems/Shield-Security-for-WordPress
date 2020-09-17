<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Events;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\IPs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\OverviewCards;
use FernleafSystems\Wordpress\Services\Services;

class UI extends Base\ShieldUI {

	public function buildInsightsVars() :array {
		$con = $this->getCon();

		return [
			'vars'    => [
				'insight_stats'  => $this->getStats(),
				'overview_cards' => ( new OverviewCards() )
					->setMod( $this->getMod() )
					->buildForShuffle(),
			],
			'ajax'    => [
				'render_chart_post' => $con->getModule_Events()->getAjaxActionData( 'render_chart_post', true ),
			],
			'hrefs'   => [
				'shield_pro_url'           => 'https://shsec.io/shieldpro',
				'shield_pro_more_info_url' => 'https://shsec.io/shld1',
			],
			'flags'   => [
				'show_ads'              => false,
				'show_standard_options' => false,
				'show_alt_content'      => true,
				'is_pro'                => $con->isPremiumActive(),
			],
			'strings' => [
				'title_security_notices'    => __( 'Security Notices', 'wp-simple-firewall' ),
				'subtitle_security_notices' => __( 'Potential security issues on your site right now', 'wp-simple-firewall' ),
				'configuration_summary'     => __( 'Plugin Configuration Summary', 'wp-simple-firewall' ),
				'click_to_toggle'           => __( 'click to toggle', 'wp-simple-firewall' ),
				'go_to_options'             => sprintf(
					__( 'Go To %s', 'wp-simple-firewall' ),
					__( 'Options' )
				),
				'key'                       => __( 'Key' ),
				'key_positive'              => __( 'Positive Security', 'wp-simple-firewall' ),
				'key_warning'               => __( 'Potential Warning', 'wp-simple-firewall' ),
				'key_danger'                => __( 'Potential Danger', 'wp-simple-firewall' ),
				'key_information'           => __( 'Information', 'wp-simple-firewall' ),
			],
		];
	}

	/**
	 * @return array[]
	 */
	protected function getStats() {
		$con = $this->getCon();
		/** @var Events\Select $oSelEvents */
		$oSelEvents = $con->getModule_Events()
						  ->getDbHandler_Events()
						  ->getQuerySelector();

		/** @var IPs\Select $oSelectIp */
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

		return $aStatsData;
	}
}