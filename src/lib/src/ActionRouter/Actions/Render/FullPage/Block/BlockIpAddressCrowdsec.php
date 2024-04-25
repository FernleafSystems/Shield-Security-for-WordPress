<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\FullPage\Block;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\ByPassIpBlock;

class BlockIpAddressCrowdsec extends BlockIpAddressShield {

	use ByPassIpBlock;

	public const SLUG = 'render_block_ip_address_crowdsec';
	public const TEMPLATE = '/pages/block/block_page_ip_crowdsec.twig';

	protected function getRestrictionDetailsBlurb() :array {
		return [
			'crowdsec'  => __( "In collaboration with CrowdSec and their crowd-sourced IP reputation data, your IP address has been identified as malicious.", 'wp-simple-firewall' ),
			'no_access' => __( "All access to this website is therefore restricted.", 'wp-simple-firewall' )
						   .' '.__( "Please take a moment to review your options below.", 'wp-simple-firewall' ),
		];
	}

	protected function renderAutoUnblock() :string {
		return self::con()->action_router->render( Components\AutoUnblockCrowdsec::class );
	}

	protected function renderEmailMagicLinkContent() :string {
		return '';
	}
}