<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Server\UI\PageRender;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Server\UI\BaseRender;
use FernleafSystems\Wordpress\Services\Services;

class PluginOutOfDate extends BaseRender {

	protected function getData() :array {
		return [
			'strings' => [
				'update'  => __( 'The Shield Security plugin on this site needs updated.' ),
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