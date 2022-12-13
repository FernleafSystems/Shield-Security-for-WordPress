<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\FullPage\Block\Components;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\IpAutoUnblockShieldVisitor;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\AutoUnblock\AutoUnblockVisitor;
use FernleafSystems\Wordpress\Services\Services;

class AutoUnblockShield extends Base {

	public const SLUG = 'render_autounblock_shield';
	public const TEMPLATE = '/pages/block/autorecover.twig';

	protected function getRenderData() :array {
		$con = $this->getCon();
		return [
			'flags'   => [
				'is_available' => ( new AutoUnblockVisitor() )
					->setMod( $this->primary_mod )
					->isUnblockAvailable()
			],
			'hrefs'   => [
				'home' => Services::WpGeneral()->getHomeUrl( '/' )
			],
			'vars'    => [
				'unblock_nonce' => ActionData::Build( IpAutoUnblockShieldVisitor::SLUG.'-'.$con->this_req->ip ),
			],
			'strings' => [
				'title'   => __( 'Auto-Unblock Your IP', 'wp-simple-firewall' ),
				'you_can' => __( 'You can automatically unblock your IP address by clicking the button below.', 'wp-simple-firewall' ),
				'button'  => __( 'Unblock My IP Address', 'wp-simple-firewall' ),
			],
		];
	}
}