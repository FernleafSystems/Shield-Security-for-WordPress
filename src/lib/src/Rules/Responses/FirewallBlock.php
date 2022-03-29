<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Firewall\Options;
use FernleafSystems\Wordpress\Services\Services;

class FirewallBlock extends Base {

	const SLUG = 'firewall_block';

	protected function execResponse() :bool {
		$mod = $this->getCon()->getModule_Firewall();

		$this->preBlock();

		remove_filter( 'wp_robots', 'wp_robots_noindex_search' );
		remove_filter( 'wp_robots', 'wp_robots_noindex_embeds' );
		header( 'Cache-Control: no-store, no-cache' );

		switch ( $mod->getBlockResponse() ) {
			case 'redirect_die':
				Services::WpGeneral()->wpDie( 'Firewall Triggered' );
				break;
			case 'redirect_die_message':
				Services::WpGeneral()->wpDie( implode( ' ', $this->getFirewallDieMessage() ) );
				break;
			case 'redirect_home':
				Services::Response()->redirectToHome();
				break;
			case 'redirect_404':
				Services::WpGeneral()->turnOffCache();
				Services::Response()->sendApache404();
				break;
			default:
				break;
		}
		die();
	}

	private function getFirewallDieMessage() :array {
		$mod = $this->getCon()->getModule_Firewall();
		$default = __( "Something in the request URL or Form data triggered the firewall.", 'wp-simple-firewall' );
		$customMessage = $mod->getTextOpt( 'text_firewalldie' );

		$messages = apply_filters(
			'shield/firewall_die_message',
			[
				empty( $customMessage ) ? $default : $customMessage,
			]
		);
		return is_array( $messages ) ? $messages : [ $default ];
	}

	private function preBlock() {
		$mod = $this->getCon()->getModule_Firewall();
		/** @var Options $opts */
		$opts = $mod->getOptions();
		if ( $opts->isSendBlockEmail() ) {
			$this->getCon()->fireEvent(
				$this->sendBlockEmail() ? 'fw_email_success' : 'fw_email_fail',
				[ 'audit_params' => [ 'to' => $mod->getPluginReportEmail() ] ]
			);
		}
//		$this->getCon()->fireEvent( 'firewall_block', [ 'audit_params' => $this->getResult()->get_error_data() ] );
	}

	private function sendBlockEmail() :bool {
		$ip = Services::IP()->getRequestIp();

		$mod = $this->getCon()->getModule_Firewall();
		return $mod->getEmailProcessor()->sendEmailWithTemplate(
			'/email/firewall_block.twig',
			$mod->getPluginReportEmail(),
			__( 'Firewall Block Alert', 'wp-simple-firewall' ),
			[
				'strings' => [
					'shield_blocked'  => sprintf( __( '%s Firewall has blocked a request to your WordPress site.', 'wp-simple-firewall' ),
						$this->getCon()->getHumanName() ),
					'details_below'   => __( 'Details for the request are given below:', 'wp-simple-firewall' ),
					'details'         => __( 'Request Details', 'wp-simple-firewall' ),
					'ip_lookup'       => __( 'IP Address Lookup' ),
					'this_is_info'    => __( 'This is for informational purposes only.' ),
					'already_blocked' => sprintf( __( '%s has already taken the necessary action of blocking the request.' ),
						$this->getCon()->getHumanName() ),
				],
				'hrefs'   => [
					'ip_lookup' => add_query_arg( [ 'ip' => $ip ], 'https://shsec.io/botornot' )
				],
				'vars'    => [
					'req_details' => [
						__( 'Visitor IP Address', 'wp-simple-firewall' ) => $ip,
						__( 'Firewall Rule', 'wp-simple-firewall' )      => $resultData[ 'name' ] ?? 'No name',
						__( 'Request Path', 'wp-simple-firewall' )       => Services::Request()->getPath(),
						__( 'Request Parameter', 'wp-simple-firewall' )  => $resultData[ 'param' ] ?? 'No param',
						__( 'Request Value', 'wp-simple-firewall' )      => $resultData[ 'value' ] ?? 'No value',
					]
				]
			]
		);
	}
}