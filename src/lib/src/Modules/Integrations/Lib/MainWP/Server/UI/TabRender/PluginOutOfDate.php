<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Server\UI\TabRender;

use FernleafSystems\Wordpress\Services\Services;

class PluginOutOfDate extends BaseTab {

	protected function getPageSpecificData() :array {
		return [
			'strings' => [
				'update'  => __( 'The Shield Security plugin on this site needs to be upgraded.' ),
				'go_here' => __( 'Go to WordPress Updates' )
			],
			'hrefs'   => [
				'update' => Services::WpGeneral()->getAdminUrl_Updates()
			],
		];
	}

	protected function getTemplateSlug() :string {
		return 'pages/shield_outofdate';
	}
}