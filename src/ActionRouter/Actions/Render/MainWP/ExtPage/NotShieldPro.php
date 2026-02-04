<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\MainWP\ExtPage;

class NotShieldPro extends BaseSubPage {

	public const SLUG = 'mainwp_page_not_shield_pro';
	public const TEMPLATE = '/integration/mainwp/pages/mwp_for_pro.twig';

	protected function getRenderData() :array {
		return [
			'strings' => [
				'not_pro' => sprintf( __( "Sorry, you'll need to upgrade your %s membership to access the MainWP integration.", 'wp-simple-firewall' ), self::con()->labels->Name ),
				'go_pro'  => __( 'Upgrade Membership', 'wp-simple-firewall' ),
			],
			'hrefs'   => [
				'go_pro' => 'https://clk.shldscrty.com/mainwpservergopro'
			],
		];
	}
}