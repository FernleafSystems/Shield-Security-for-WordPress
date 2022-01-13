<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\NotBot\TestNotBotLoading;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Ops\RetrieveIpsForLists;
use FernleafSystems\Wordpress\Services\Services;

class UI extends BaseShield\UI {

	public function buildInsightsVars() :array {
		$con = $this->getCon();
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var Options $opts */
		$opts = $this->getOptions();
		$pluginName = $con->getHumanName();

		return [
			'ajax'    => [
				'render_table_ip' => $mod->getAjaxActionData( 'render_table_ip', true ),
				'item_insert'     => $mod->getAjaxActionData( 'ip_insert', true ),
				'item_delete'     => $mod->getAjaxActionData( 'ip_delete', true ),
			],
			'content' => [
				'ip_review' => $this->renderIpAnalyse()
			],
			'flags'   => [
				'can_blacklist' => $con->isPremiumActive()
			],
			'strings' => [
				'trans_limit'       => sprintf(
					__( 'Offenses required for IP block: %s', 'wp-simple-firewall' ),
					sprintf( '<a href="%s" target="_blank">%s</a>',
						$mod->getUrl_DirectLinkToOption( 'transgression_limit' ), $opts->getOffenseLimit() )
				),
				'auto_expire'       => sprintf(
					__( 'IPs on block list auto-expire after: %s', 'wp-simple-firewall' ),
					sprintf( '<a href="%s" target="_blank">%s</a>',
						$mod->getUrl_DirectLinkToOption( 'auto_expire' ),
						Services::Request()
								->carbon()
								->addSeconds( $opts->getAutoExpireTime() + 10 )
								->diffForHumans( null, true )
					)
				),
				'title_whitelist'   => __( 'IP Bypass List', 'wp-simple-firewall' ),
				'title_blacklist'   => __( 'IP Block List', 'wp-simple-firewall' ),
				'summary_whitelist' => sprintf( __( 'IP addresses that are never blocked and bypass all %s rules.', 'wp-simple-firewall' ), $pluginName ),
				'summary_blacklist' => sprintf( __( 'IP addresses that have tripped %s defenses.', 'wp-simple-firewall' ), $pluginName ),
				'enter_ip_block'    => __( 'Enter IP address to block', 'wp-simple-firewall' ),
				'enter_ip_white'    => __( 'Supply IP address to add to bypass list', 'wp-simple-firewall' ),
				'enter_ip'          => __( 'Enter IP address', 'wp-simple-firewall' ),
				'label_for_ip'      => __( 'Label for IP', 'wp-simple-firewall' ),
				'ip_new'            => __( 'New IP', 'wp-simple-firewall' ),
				'ip_filter'         => __( 'Filter By IP', 'wp-simple-firewall' ),
				'ip_block'          => __( 'Block IP', 'wp-simple-firewall' ),
				'tab_manage_block'  => __( 'Manage Block List', 'wp-simple-firewall' ),
				'tab_manage_bypass' => __( 'Manage Bypass List', 'wp-simple-firewall' ),
				'tab_ip_analysis'   => __( 'IP Analysis', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'unique_ips_black' => ( new RetrieveIpsForLists() )
					->setDbHandler( $mod->getDbHandler_IPs() )
					->black(),
				'unique_ips_white' => ( new RetrieveIpsForLists() )
					->setDbHandler( $mod->getDbHandler_IPs() )
					->white()
			],
		];
	}

	public function getSectionWarnings( string $section ) :array {
		$warnings = [];

		/** @var Options $opts */
		$opts = $this->getOptions();

		switch ( $section ) {

			case 'section_auto_black_list':
				if ( !$opts->isEnabledAutoBlackList() ) {
					$warnings[] = sprintf( '%s: %s', __( 'Note', 'wp-simple-firewall' ), __( "IP blocking is turned-off because the offenses limit is set to 0.", 'wp-simple-firewall' ) );
				}
				break;

			case 'section_antibot':
				if ( !$opts->isEnabledAntiBotEngine() ) {
					$warnings[] = sprintf( '%s: %s', __( 'Important', 'wp-simple-firewall' ),
						sprintf( __( "The AntiBot Detection Engine is disabled when set to a minimum score of %s.", 'wp-simple-firewall' ), '0' ) );
				}
				else {
					$notbotFound = ( new TestNotBotLoading() )
						->setMod( $this->getCon()->getModule_IPs() )
						->test();
					if ( !$notbotFound ) {
						$warnings[] = sprintf( '%s: %s', __( 'Important', 'wp-simple-firewall' ),
							sprintf( __( "Shield couldn't determine whether the NotBot JS was loading correctly on your site.", 'wp-simple-firewall' ), '0' ) );
					}
				}
				break;

			case 'section_behaviours':
			case 'section_probes':
			case 'section_logins':
				if ( !$opts->isEnabledAutoBlackList() ) {
					$warnings[] = __( "Since the offenses limit is set to 0, these options have no effect.", 'wp-simple-firewall' );
				}

				if ( $section == 'section_behaviours' && strlen( Services::Request()->getUserAgent() ) == 0 ) {
					$warnings[] = __( "Your User Agent appears to be empty. We recommend not turning on this option.", 'wp-simple-firewall' );
				}
				break;
		}

		return $warnings;
	}

	private function renderIpAnalyse() :string {
		$mod = $this->getMod();
		return $mod->renderTemplate(
			'/wpadmin_pages/insights/ips/ip_analyse/index.twig',
			[
				'ajax'    => [
					'build_ip_analyse'  => $mod->getAjaxActionData( 'build_ip_analyse', true ),
					'ip_analyse_action' => $mod->getAjaxActionData( 'ip_analyse_action', true ),
					'ip_review_select'  => $mod->getAjaxActionData( 'ip_review_select', true ),
				],
				'strings' => [
					'select_ip'     => __( 'Select IP To Analyse', 'wp-simple-firewall' ),
					'card_title'    => 'IP Analysis',
					'card_summary'  => 'Investigate IP activity on this site',
					'please_select' => 'Please select an IP address.',
				],
				'vars'    => [
					'unique_ips' => []
				]
			],
			true
		);
	}
}