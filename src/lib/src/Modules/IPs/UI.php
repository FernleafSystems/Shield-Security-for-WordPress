<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\IpRules\Ops\Handler;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\NotBot\TestNotBotLoading;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Ops\RetrieveIpsForLists;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\IpRules\ForIpRules;
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
				'ip_review'      => $this->renderIpAnalyse(),
				'table_ip_rules' => $this->renderTable_IpRules(),
			],
			'flags'   => [
				'can_blacklist' => $con->isPremiumActive()
			],
			'strings' => [
				'trans_limit'        => sprintf(
					__( 'Offenses required for IP block: %s', 'wp-simple-firewall' ),
					sprintf( '<a href="%s" target="_blank">%s</a>',
						$mod->getUrl_DirectLinkToOption( 'transgression_limit' ), $opts->getOffenseLimit() )
				),
				'auto_expire'        => sprintf(
					__( 'IPs on block list auto-expire after: %s', 'wp-simple-firewall' ),
					sprintf( '<a href="%s" target="_blank">%s</a>',
						$mod->getUrl_DirectLinkToOption( 'auto_expire' ),
						Services::Request()
								->carbon()
								->addSeconds( $opts->getAutoExpireTime() + 10 )
								->diffForHumans( null, true )
					)
				),
				'crowdsec_decisions' => __( 'CrowdSec Block List', 'wp-simple-firewall' ),
				'title_whitelist'    => __( 'IP Bypass List', 'wp-simple-firewall' ),
				'title_blacklist'    => __( 'IP Block List', 'wp-simple-firewall' ),
				'summary_whitelist'  => sprintf( __( 'IP addresses that are never blocked and bypass all %s rules.', 'wp-simple-firewall' ), $pluginName ),
				'summary_blacklist'  => sprintf( __( 'IP addresses that have tripped %s defenses.', 'wp-simple-firewall' ), $pluginName ),
				'enter_ip_block'     => __( 'Enter IP address to block', 'wp-simple-firewall' ),
				'enter_ip_white'     => __( 'Supply IP address to add to bypass list', 'wp-simple-firewall' ),
				'enter_ip'           => __( 'Enter IP address', 'wp-simple-firewall' ),
				'label_for_ip'       => __( 'Label for IP', 'wp-simple-firewall' ),
				'ip_new'             => __( 'New IP', 'wp-simple-firewall' ),
				'ip_filter'          => __( 'Filter By IP', 'wp-simple-firewall' ),
				'ip_block'           => __( 'Block IP', 'wp-simple-firewall' ),
				'tab_manage_block'   => __( 'Manage Block List', 'wp-simple-firewall' ),
				'tab_manage_bypass'  => __( 'Manage Bypass List', 'wp-simple-firewall' ),
				'tab_ip_analysis'    => __( 'IP Analysis', 'wp-simple-firewall' ),
				'ip_rules'           => __( 'IP Rules', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'unique_ips_black' => ( new RetrieveIpsForLists() )
					->setMod( $this->getMod() )
					->black(),
				'unique_ips_white' => ( new RetrieveIpsForLists() )
					->setMod( $this->getMod() )
					->white()
			],
		];
	}

	public function renderForm_IpAdd() :string {
		$con = $this->getCon();
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var Options $opts */
		$opts = $this->getOptions();
		return $mod->renderTemplate( '/components/forms/ip_rule_add.twig', [
			'ajax'    => [
				'table_action' => $mod->getAjaxActionData( 'iprulestable_action', true ),
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
				'ip_address_help'         => __( 'IPv4 or Ipv6; CIDR ranges only.', 'wp-simple-firewall' ),
				'add_rule'                => __( 'Add New IP Rule', 'wp-simple-firewall' ),
				'confirm'                 => __( "I fully understand the significance of this action", 'wp-simple-firewall' ),
			],
			'vars'    => [
				'blacklist' => Handler::T_MANUAL_BLACK,
				'whitelist' => Handler::T_MANUAL_WHITE,
			],
		] );
	}

	public function renderTable_IpRules() :string {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var Options $opts */
		$opts = $this->getOptions();
		return $mod->renderTemplate( '/wpadmin_pages/insights/ips/ip_rules.twig', [
			'ajax'    => [
				'table_action' => $mod->getAjaxActionData( 'iprulestable_action', true ),
			],
			'flags'   => [
				'is_enabled' => $opts->isEnabledCrowdSecAutoBlock(),
			],
			'hrefs'   => [
				'please_enable' => $mod->getUrl_DirectLinkToOption( 'cs_block' ),
			],
			'strings' => [
			],
			'vars'    => [
				'datatables_init' => ( new ForIpRules() )
					->setMod( $this->getMod() )
					->build()
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

	private function renderIpAnalyse() :string {
		$mod = $this->getMod();
		return $mod->renderTemplate( '/wpadmin_pages/insights/ips/ip_analyse/index.twig', [
			'ajax'    => [
				'ip_analyse_build'  => $mod->getAjaxActionData( 'ip_analyse_build', true ),
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
		] );
	}
}