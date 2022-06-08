<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Blocks\RenderBlockPages;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Obfuscate;

class RenderBlockCrowdSecIP extends RenderBlockIP {

	protected function getPageSpecificData() :array {
		$con = $this->getCon();

		return [
			'content' => [
			],
			'flags'   => [
				'has_magiclink'   => !empty( $magicLink ),
				'has_autorecover' => !empty( $autoUnblock ),
			],
			'hrefs'   => [
				'how_to_unblock' => 'https://shsec.io/shieldhowtounblock',
			],
			'strings' => [
				'page_title' => sprintf( '%s | %s', __( 'Access Restricted', 'wp-simple-firewall' ), $con->getHumanName() ),
				'title'      => __( 'Access Restricted', 'wp-simple-firewall' ),
				'subtitle'   => __( 'Access from your IP address has been temporarily restricted.', 'wp-simple-firewall' ),
			],
		];
	}

	protected function getRestrictionDetailsBlurb() :array {
		return [
			'crowdsec'  => __( "In collaboration with CrowdSec and their crowd-sourced IP reputation data, your IP address has been identified as malicious.", 'wp-simple-firewall' ),
			'no_access' => __( "All access to this website is therefore restricted.", 'wp-simple-firewall' )
						   .' '.__( "Please take a moment to review your options below.", 'wp-simple-firewall' ),
		];
	}

	protected function getTemplateStub() :string {
		return 'block_page_crowdsec_ip';
	}

	private function renderAutoUnblock() :string {
		/** @var IPs\ModCon $mod */
		$mod = $this->getMod();
		/** @var IPs\Options $opts */
		$opts = $this->getOptions();

		$ip = Services::IP()->getRequestIp();
		$canAutoRecover = $opts->isEnabledAutoVisitorRecover()
						  && $opts->getCanIpRequestAutoUnblock( $ip );

		$content = '';

		return $content;
	}
}