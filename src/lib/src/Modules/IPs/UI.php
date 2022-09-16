<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\{
	ActionData,
	Actions
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\IpRules\Ops\Handler;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\NotBot\TestNotBotLoading;
use FernleafSystems\Wordpress\Services\Services;

class UI extends BaseShield\UI {

	public function renderForm_IpAdd() :string {
		$con = $this->getCon();
		/** @var ModCon $mod */
		$mod = $this->getMod();
		return $mod->renderTemplate( '/components/forms/ip_rule_add.twig', [
			'ajax'    => [
				'table_action' => ActionData::BuildJson( Actions\IpRulesTableAction::SLUG ),
			],
			'flags'   => [
				'is_blacklist_allowed' => $con->isPremiumActive(),
			],
			'hrefs'   => [
				'please_enable' => $mod->getUrl_DirectLinkToOption( 'cs_block' ),
			],
			'strings' => [
				'add_to_list_block'       => __( 'Add To Block List', 'wp-simple-firewall' ),
				'add_to_list_block_help'  => __( 'Requests from this IP address will be blocked.', 'wp-simple-firewall' ),
				'add_to_list_bypass'      => __( 'Add To Bypass List', 'wp-simple-firewall' ),
				'add_to_list_bypass_help' => __( 'Requests from this IP address will bypass all security rules.', 'wp-simple-firewall' ),
				'label'                   => __( 'Label For This IP Rule', 'wp-simple-firewall' ),
				'label_help'              => __( 'A helpful label to describe this IP rule.', 'wp-simple-firewall' ),
				'label_help_max'          => sprintf( '%s: %s', __( '255 characters max', 'wp-simple-firewall' ), 'a-z,0-9' ),
				'ip_address'              => __( 'IP Address or IP Range', 'wp-simple-firewall' ),
				'ip_address_help'         => __( 'IPv4 or IPv6; Single Address or CIDR Range', 'wp-simple-firewall' ),
				'add_rule'                => __( 'Add New IP Rule', 'wp-simple-firewall' ),
				'confirm'                 => __( "I fully understand the significance of this action", 'wp-simple-firewall' ),
			],
			'vars'    => [
				'blacklist' => Handler::T_MANUAL_BLOCK,
				'whitelist' => Handler::T_MANUAL_BYPASS,
			],
		] );
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
}