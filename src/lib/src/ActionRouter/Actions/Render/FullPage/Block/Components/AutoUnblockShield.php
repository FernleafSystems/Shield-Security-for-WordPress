<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\FullPage\Block\Components;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\IpAutoUnblockShieldVisitor;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\AutoUnblock\AutoUnblockVisitor;
use FernleafSystems\Wordpress\Services\Services;

class AutoUnblockShield extends Base {

	public const SLUG = 'render_autounblock_shield';
	public const TEMPLATE = '/pages/block/autorecover.twig';

	protected function getRenderData() :array {
		return [
			'flags'   => [
				'is_available' => ( new AutoUnblockVisitor() )->isUnblockAvailable()
			],
			'hrefs'   => [
				'home' => Services::WpGeneral()->getHomeUrl( '/' )
			],
			'vars'    => [
				'unblock_nonce' => ActionData::Build( IpAutoUnblockShieldVisitor::class ),
			],
			'strings' => [
				'title'   => __( 'Auto-Unblock Your IP', 'wp-simple-firewall' ),
				'you_can' => __( 'You can automatically unblock your IP address by clicking the button below.', 'wp-simple-firewall' ),
				'button'  => __( 'Unblock My IP Address', 'wp-simple-firewall' ),
			],
		];
	}
}