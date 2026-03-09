<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Services\Services;

class PageScansResults extends PageScansBase {

	public const SLUG = 'admin_plugin_page_scans_results';
	public const TEMPLATE = '/wpadmin/plugin_pages/inner/scan_results.twig';

	protected function getPageContextualHrefs() :array {
		return [
			[
				'title'   => __( 'Results Display Options', 'wp-simple-firewall' ),
				'href'    => 'javascript:{}',
				'classes' => [ 'offcanvas_form_scans_results_options' ],
			],
			[
				'title' => __( 'Run Manual Scan', 'wp-simple-firewall' ),
				'href'  => self::con()->plugin_urls->adminTopNav( PluginNavs::NAV_SCANS, PluginNavs::SUBNAV_SCANS_RUN ),
			],
		];
	}

	protected function getRenderData() :array {
		return Services::DataManipulation()->mergeArraysRecursive(
			parent::getRenderData(),
			$this->buildScansResultsRenderData(),
			[
				'imgs'    => [
					'inner_page_title_icon' => self::con()->svgs->iconClass( 'shield-shaded' ),
				],
				'strings' => [
					'inner_page_title'    => __( 'View Results', 'wp-simple-firewall' ),
					'inner_page_subtitle' => __( 'View and manage all scan results.', 'wp-simple-firewall' ),
				],
			]
		);
	}

	protected function buildScansResultsRenderData() :array {
		return ( new ScansResultsViewBuilder() )->build();
	}
}
