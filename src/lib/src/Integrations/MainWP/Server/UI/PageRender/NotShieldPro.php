<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Integrations\MainWP\Server\UI\PageRender;

use FernleafSystems\Wordpress\Plugin\Shield\Integrations\MainWP\Server\UI\BaseRender;

class NotShieldPro extends BaseRender {

	protected function getData() :array {
		return [
			'strings' => [
				'not_pro' => __( "Sorry, the MainWP server integration is available only for ShieldPRO clients." ),
				'go_pro' => __( 'Upgrade To ShieldPRO' ),
			],
			'hrefs'   => [
				'go_pro' => 'https://shsec.io/mainwpservergopro'
			],
		];
	}

	protected function getTemplateSlug() :string {
		return 'pages/mwp_for_pro';
	}
}