<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Integrations\MainWP\Server\UI\PageRender;

use FernleafSystems\Wordpress\Plugin\Shield\Integrations\MainWP\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Integrations\MainWP\Server\UI\BaseRender;
use FernleafSystems\Wordpress\Services\Services;

class MwpOutOfDate extends BaseRender {

	protected function getData() :array {
		return [
			'strings' => [
				'update'      => __( "The MainWP Security plugin doesn't meet Shield's minimum requirements." ),
				'min_version' => __( 'Minimum required MainWP server version' ),
				'go_here'     => __( 'Go to WordPress Updates' ),
			],
			'hrefs'   => [
				'update' => Services::WpGeneral()->getAdminUrl_Updates()
			],
			'vars'    => [
				'min_version' => Controller::MIN_VERSION_MAINWP
			],
		];
	}

	protected function getTemplateSlug() :string {
		return 'pages/mwp_outofdate';
	}
}