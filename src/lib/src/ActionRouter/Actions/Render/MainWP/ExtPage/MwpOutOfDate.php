<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\MainWP\ExtPage;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Controller;
use FernleafSystems\Wordpress\Services\Services;

class MwpOutOfDate extends BaseSubPage {

	public const SLUG = 'mainwp_page_mwp_outofdate';
	public const TEMPLATE = '/integration/mainwp/pages/mwp_outofdate.twig';

	protected function getRenderData() :array {
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
}