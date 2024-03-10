<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Enum\EnumModules;
use FernleafSystems\Wordpress\Services\Services;

class PageScansHistory extends PageScansBase {

	public const SLUG = 'admin_plugin_page_scans_history';
	public const TEMPLATE = '/wpadmin/plugin_pages/inner/scans_history.twig';

	protected function getPageContextualHrefs() :array {
		return [
			[
				'text' => __( 'Scan Results', 'wp-simple-firewall' ),
				'href' => self::con()->plugin_urls->adminTopNav( PluginNavs::NAV_SCANS, PluginNavs::SUBNAV_SCANS_RESULTS ),
			],
			[
				'text'    => __( 'Configure Scans', 'wp-simple-firewall' ),
				'href'    => '#',
				'classes' => [ 'offcanvas_form_mod_cfg' ],
				'datas'   => [
					'config_item' => EnumModules::SCANS,
				],
			],
		];
	}

	protected function getRenderData() :array {
		return Services::DataManipulation()->mergeArraysRecursive( parent::getRenderData(), [
			'strings' => [
			],
			'vars'    => [
			],
		] );
	}

	protected function getInnerPageTitle() :string {
		return __( 'Scans History', 'wp-simple-firewall' );
	}

	protected function getInnerPageSubTitle() :string {
		return __( 'View details and results for each scan', 'wp-simple-firewall' );
	}
}