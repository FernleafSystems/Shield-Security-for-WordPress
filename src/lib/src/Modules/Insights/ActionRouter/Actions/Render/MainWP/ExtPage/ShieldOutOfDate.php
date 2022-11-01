<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\MainWP\ExtPage;

use FernleafSystems\Wordpress\Services\Services;

class ShieldOutOfDate extends BaseMWP {

	const SLUG = 'mainwp_page_shield_outofdate';
	const TEMPLATE = '/integration/mainwp/pages/shield_outofdate.twig';

	protected function getRenderData() :array {
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
}