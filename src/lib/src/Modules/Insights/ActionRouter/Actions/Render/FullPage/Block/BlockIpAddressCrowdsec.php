<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\FullPage\Block;

class BlockIpAddressCrowdsec extends BlockIpAddressShield {

	const SLUG = 'render_block_ip_address_crowdsec';
	const TEMPLATE = '/pages/block/block_page_ip_crowdsec.twig';

	protected function getRestrictionDetailsBlurb() :array {
		return [
			'crowdsec'  => __( "In collaboration with CrowdSec and their crowd-sourced IP reputation data, your IP address has been identified as malicious.", 'wp-simple-firewall' ),
			'no_access' => __( "All access to this website is therefore restricted.", 'wp-simple-firewall' )
						   .' '.__( "Please take a moment to review your options below.", 'wp-simple-firewall' ),
		];
	}

	protected function renderAutoUnblock() :string {
		return $this->getCon()
					->getModule_Insights()
					->getActionRouter()
					->render( Components\AutoUnblockCrowdsec::SLUG );
	}

	protected function renderEmailMagicLinkContent() :string {
		return '';
	}
}