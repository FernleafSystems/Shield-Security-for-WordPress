<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Ops\RetrieveIpsForLists;
use FernleafSystems\Wordpress\Services\Services;

class UI extends Base\ShieldUI {

	public function buildInsightsVars() :array {
		$con = $this->getCon();
		/** @var \ICWP_WPSF_FeatureHandler_Ips $mod */
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
					__( 'Black listed IPs auto-expire after: %s', 'wp-simple-firewall' ),
					sprintf( '<a href="%s" target="_blank">%s</a>',
						$mod->getUrl_DirectLinkToOption( 'auto_expire' ),
						Services::Request()
								->carbon()
								->addSeconds( $opts->getAutoExpireTime() + 10 )
								->diffForHumans( null, true )
					)
				),
				'title_whitelist'   => __( 'IP By-Pass List', 'wp-simple-firewall' ),
				'title_blacklist'   => __( 'IP Block List', 'wp-simple-firewall' ),
				'summary_whitelist' => sprintf( __( 'IP addresses that are never blocked and by-pass all %s rules.', 'wp-simple-firewall' ), $pluginName ),
				'summary_blacklist' => sprintf( __( 'IP addresses that have tripped %s defenses.', 'wp-simple-firewall' ), $pluginName ),
				'enter_ip_block'    => __( 'Enter IP address to block', 'wp-simple-firewall' ),
				'enter_ip_white'    => __( 'Enter IP address to whitelist', 'wp-simple-firewall' ),
				'enter_ip'          => __( 'Enter IP address', 'wp-simple-firewall' ),
				'label_for_ip'      => __( 'Label for IP', 'wp-simple-firewall' ),
				'ip_new'            => __( 'New IP', 'wp-simple-firewall' ),
				'ip_filter'         => __( 'Filter By IP', 'wp-simple-firewall' ),
				'ip_block'          => __( 'Block IP', 'wp-simple-firewall' ),
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

	protected function getSectionWarnings( string $section ) :array {
		$aWarnings = [];

		/** @var Options $opts */
		$opts = $this->getOptions();

		switch ( $section ) {

			case 'section_auto_black_list':
				if ( !$opts->isEnabledAutoBlackList() ) {
					$aWarnings[] = sprintf( '%s: %s', __( 'Note', 'wp-simple-firewall' ), __( "IP blocking is turned-off because the offenses limit is set to 0.", 'wp-simple-firewall' ) );
				}
				break;

			case 'section_behaviours':
			case 'section_probes':
			case 'section_logins':
				if ( !$opts->isEnabledAutoBlackList() ) {
					$aWarnings[] = __( "Since the offenses limit is set to 0, these options have no effect.", 'wp-simple-firewall' );
				}

				if ( $section == 'section_behaviours' && strlen( Services::Request()->getUserAgent() ) == 0 ) {
					$aWarnings[] = __( "Your User Agent appears to be empty. We recommend not turning on this option.", 'wp-simple-firewall' );
				}
				break;
		}

		return $aWarnings;
	}
}