<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\MainWP\ExtPage;

class NotShieldPro extends BaseMWP {

	public const SLUG = 'mainwp_page_not_shield_pro';
	public const TEMPLATE = '/integration/mainwp/pages/mwp_for_pro.twig';

	protected function getRenderData() :array {
		return [
			'strings' => [
				'not_pro' => __( "Sorry, the MainWP server integration is available only for ShieldPRO members." ),
				'go_pro'  => __( 'Upgrade To ShieldPRO' ),
			],
			'hrefs'   => [
				'go_pro' => 'https://shsec.io/mainwpservergopro'
			],
		];
	}
}