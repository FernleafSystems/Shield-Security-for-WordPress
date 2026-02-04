<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\FullPage\Block\Components;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\IpAutoUnblockCrowdsecVisitor;
use FernleafSystems\Wordpress\Services\Services;

class AutoUnblockCrowdsec extends AutoUnblockShield {

	public const SLUG = 'render_autounblock_crowdsec';

	protected function getRenderData() :array {
		return [
			'flags'   => [
				'is_available' => self::con()->comps->opts_lookup->enabledCrowdSecAutoUnblock(),
			],
			'hrefs'   => [
				'home' => Services::WpGeneral()->getHomeUrl( '/' )
			],
			'vars'    => [
				'unblock_nonce' => ActionData::Build( IpAutoUnblockCrowdsecVisitor::class ),
			],
			'strings' => [
				'title'   => __( 'Auto-Unblock Your IP', 'wp-simple-firewall' ),
				'you_can' => __( 'You can automatically unblock your IP address by clicking the button below.', 'wp-simple-firewall' ),
				'button'  => __( 'Unblock My IP Address', 'wp-simple-firewall' ),
			],
		];
	}
}