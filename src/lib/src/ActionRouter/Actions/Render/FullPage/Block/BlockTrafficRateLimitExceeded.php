<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\FullPage\Block;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\ByPassIpBlock;

class BlockTrafficRateLimitExceeded extends BaseBlock {

	use ByPassIpBlock;

	public const SLUG = 'render_block_traffic_rate_limited_exceeded';
	public const TEMPLATE = '/pages/block/block_page_traffic_rate_limit_exceeded.twig';

	protected function getRenderData() :array {

		return [
			'strings' => [
				'page_title'    => sprintf( '%s | %s', __( 'Access Restricted', 'wp-simple-firewall' ), self::con()->labels->Name ),
				'title'         => __( 'Access Restricted', 'wp-simple-firewall' ),
				'subtitle'      => __( 'There have been too many requests from your IP address.', 'wp-simple-firewall' ),
				'contact_admin' => __( 'Please contact the site administrator if you need further guidance.', 'wp-simple-firewall' ),
			],
		];
	}

	protected function renderAutoUnblock() :string {
		return self::con()->action_router->render( Components\AutoUnblockShield::class );
	}

	protected function getRestrictionDetailsBlurb() :array {
		return [
			__( "You have exceeded the site's traffic rate limiting parameters.", 'wp-simple-firewall' ),
			__( "If you continue to exceed the rate limits, your IP address may be completely blocked from further access.", 'wp-simple-firewall' ),
		];
	}
}