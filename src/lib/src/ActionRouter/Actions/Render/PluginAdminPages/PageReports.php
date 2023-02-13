<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Reports;

class PageReports extends BasePluginAdminPage {

	public const SLUG = 'admin_plugin_page_reports';
	public const TEMPLATE = '/wpadmin_pages/plugin_admin/reports.twig';

	protected function getRenderData() :array {
		$AR = $this->getCon()->action_router;
		return [
			'content' => [
				'summary_stats' => $AR->render( Reports\ChartsSummary::SLUG ),
				'custom_chart'  => $AR->render( Reports\ChartsCustom::SLUG ),
			],
			'strings' => [
				'inner_page_title'    => __( 'Simple Reports & Charts', 'wp-simple-firewall' ),
				'inner_page_subtitle' => __( 'Basic charts - this is in beta and will be developed over time.', 'wp-simple-firewall' ),
			],
		];
	}
}