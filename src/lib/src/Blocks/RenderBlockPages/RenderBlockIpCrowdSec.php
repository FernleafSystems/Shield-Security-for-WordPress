<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Blocks\RenderBlockPages;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;
use FernleafSystems\Wordpress\Services\Services;

class RenderBlockIpCrowdSec extends RenderBlockIP {

	protected function getRestrictionDetailsBlurb() :array {
		return [
			'crowdsec'  => __( "In collaboration with CrowdSec and their crowd-sourced IP reputation data, your IP address has been identified as malicious.", 'wp-simple-firewall' ),
			'no_access' => __( "All access to this website is therefore restricted.", 'wp-simple-firewall' )
						   .' '.__( "Please take a moment to review your options below.", 'wp-simple-firewall' ),
		];
	}

	protected function getTemplateStub() :string {
		return 'block_page_ip_crowdsec';
	}

	protected function renderAutoUnblock() :string {
		/** @var IPs\ModCon $mod */
		$mod = $this->getMod();
		/** @var IPs\Options $opts */
		$opts = $this->getOptions();

		if ( $opts->isEnabledCrowdSecAutoVisitorUnblock() ) {
			$content = $mod->renderTemplate( '/pages/block/autorecover_crowdsec.twig', [
				'hrefs'   => [
					'home' => Services::WpGeneral()->getHomeUrl( '/' )
				],
				'vars'    => [
					'nonce' => $mod->getNonceActionData( 'uau-cs-'.$this->getCon()->this_req->ip ),
				],
				'strings' => [
					'title'   => __( 'Auto-Unblock Your IP', 'wp-simple-firewall' ),
					'you_can' => __( 'You can automatically unblock your IP address by clicking the button below.', 'wp-simple-firewall' ),
					'button'  => __( 'Unblock My IP Address', 'wp-simple-firewall' ),
				],
			] );
		}
		else {
			$content = '';
		}

		return $content;
	}

	protected function renderEmailMagicLinkContent() :string {
		return '';
	}
}