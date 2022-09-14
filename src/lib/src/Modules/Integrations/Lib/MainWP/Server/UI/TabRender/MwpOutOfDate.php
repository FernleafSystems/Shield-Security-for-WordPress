<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Server\UI\TabRender;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Controller;
use FernleafSystems\Wordpress\Services\Services;

class MwpOutOfDate extends BaseTab {

	protected function getPageSpecificData() :array {
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