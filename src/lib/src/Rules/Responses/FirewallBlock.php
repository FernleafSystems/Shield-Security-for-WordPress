<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

use FernleafSystems\Wordpress\Plugin\Shield\Blocks\RenderBlockPages\RenderBlockFirewall;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Firewall\Options;
use FernleafSystems\Wordpress\Services\Services;

class FirewallBlock extends Base {

	const SLUG = 'firewall_block';

	protected function execResponse() :bool {
		$this->runBlock();
		return true;
	}

	private function runBlock() {
		$mod = $this->getCon()->getModule_Firewall();

		$this->preBlock();

		remove_filter( 'wp_robots', 'wp_robots_noindex_search' );
		remove_filter( 'wp_robots', 'wp_robots_noindex_embeds' );
		Services::WpGeneral()->turnOffCache();
		nocache_headers();

		switch ( $mod->getBlockResponse() ) {
			case 'redirect_die':
				Services::WpGeneral()->wpDie( 'Firewall Triggered' );
				break;
			case 'redirect_die_message':
				( new RenderBlockFirewall() )
					->setMod( $mod )
					->setAuxData( $this->getConsolidatedConditionMeta() )
					->display();
				break;
			case 'redirect_home':
				Services::Response()->redirectToHome();
				break;
			case 'redirect_404':
				Services::Response()->sendApache404();
				break;
			default:
				break;
		}
		die();
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
	}

	private function sendBlockEmail() :bool {
		$ip = Services::IP()->getRequestIp();

		$resultData = $this->getConsolidatedConditionMeta();

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
						__( 'Firewall Rule', 'wp-simple-firewall' )      => $this->getCon()
																				 ->getModule_Firewall()
																				 ->getStrings()
																				 ->getOptionStrings( 'block_'.$resultData[ 'match_category' ] )[ 'name' ] ?? 'No name',
						__( 'Firewall Pattern', 'wp-simple-firewall' )   => $resultData[ 'match_pattern' ] ?? 'Unavailable',
						__( 'Request Path', 'wp-simple-firewall' )       => Services::Request()->getPath(),
						__( 'Parameter Name', 'wp-simple-firewall' )     => $resultData[ 'match_request_param' ] ?? 'Unavailable',
						__( 'Parameter Value', 'wp-simple-firewall' )    => $resultData[ 'match_request_value' ] ?? 'Unavailable',
					]
				]
			]
		);
	}
}