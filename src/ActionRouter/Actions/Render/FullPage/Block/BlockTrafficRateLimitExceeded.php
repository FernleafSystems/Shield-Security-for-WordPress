<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\FullPage\Block;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\CommonDisplayStrings;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\ByPassIpBlock;

class BlockTrafficRateLimitExceeded extends BaseBlock {

	use ByPassIpBlock;
	use BlockRecoveryRenderContracts;

	public const SLUG = 'render_block_traffic_rate_limited_exceeded';
	public const TEMPLATE = '/pages/block/block_page_traffic_rate_limit_exceeded.twig';

	protected function getRenderData() :array {
		$autoRecovery = $this->buildBlockRecoveryActionContract( $this->getBlockRecoveryPageKey(), 'auto-recover' );

		return [
			'strings' => [
				'page_title'    => sprintf( '%s | %s', CommonDisplayStrings::get( 'access_restricted_label' ), self::con()->labels->Name ),
				'title'         => CommonDisplayStrings::get( 'access_restricted_label' ),
				'subtitle'      => __( 'There have been too many requests from your IP address.', 'wp-simple-firewall' ),
				'contact_admin' => __( 'Please contact the site administrator if you need further guidance.', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'recovery' => $this->buildBlockRecoveryContract( $this->getBlockRecoveryPageKey(), [
					$this->buildBlockRecoveryCandidate(
						$autoRecovery,
						$this->renderAutoUnblock( $autoRecovery )
					),
				] ),
			],
		];
	}

	protected function getBlockRecoveryPageKey() :string {
		return 'traffic-rate-limit';
	}

	protected function renderAutoUnblock( array $recovery ) :string {
		return self::con()->action_router->render( Components\AutoUnblockShield::class, [
			'vars' => [
				'recovery' => $recovery,
			],
		] );
	}

	protected function getRestrictionDetailsBlurb() :array {
		return [
			__( "You have exceeded the site's traffic rate limiting parameters.", 'wp-simple-firewall' ),
			__( "If you continue to exceed the rate limits, your IP address may be completely blocked from further access.", 'wp-simple-firewall' ),
		];
	}
}
