<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\MainWP\ExtPage;

class NotShieldPro extends BaseSubPage {

	public const SLUG = 'mainwp_page_not_shield_pro';
	public const TEMPLATE = '/integration/mainwp/pages/mwp_for_pro.twig';

	protected function getRenderData() :array {
		return [
			'strings' => [
				'not_pro' => __( "Sorry, you'll need to upgrade your Shield Security membership to access the MainWP integration." ),
				'go_pro'  => __( 'Upgrade Membership' ),
			],
			'hrefs'   => [
				'go_pro' => 'https://clk.shldscrty.com/mainwpservergopro'
			],
		];
	}
}