<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\FullPage\Block;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\ByPassIpBlock;

class BlockPageSiteBlockdown extends BaseBlock {

	use ByPassIpBlock;

	public const SLUG = 'render_block_page_site_blockdown';
	public const TEMPLATE = '/pages/block/block_page_site_lockdown.twig';

	protected function getRenderData() :array {
		return [
			'content' => [
			],
			'flags'   => [
			],
			'hrefs'   => [
				'how_to_unblock' => 'https://clk.shldscrty.com/shieldhowtounblock',
			],
			'strings' => [
				'page_title'    => sprintf( '%s | %s', __( 'Site Is Under Lockdown', 'wp-simple-firewall' ), self::con()->labels->Name ),
				'title'         => __( 'Site Is Under Lockdown', 'wp-simple-firewall' ),
				'subtitle'      => __( 'Access to this site has been temporarily restricted.', 'wp-simple-firewall' ),
				'contact_admin' => __( 'Please contact site admin to request access if required.', 'wp-simple-firewall' ),
			],
			'vars'    => [
			]
		];
	}

	protected function getRestrictionDetailsBlurb() :array {
		return [
			'check_back' => __( 'Please check back again shortly, or contact the site administrator for further details.', 'wp-simple-firewall' ),
		];
	}
}