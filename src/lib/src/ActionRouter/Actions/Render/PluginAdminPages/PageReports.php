<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Reports;

class PageReports extends BasePluginAdminPage {

	public const SLUG = 'admin_plugin_page_reports';
	public const PRIMARY_MOD = 'reporting';
	public const TEMPLATE = '/wpadmin_pages/insights/reports/index.twig';

	protected function getRenderData() :array {
		$AR = $this->getCon()->action_router;
		return [
			'content' => [
				'summary_stats' => $AR->render( Reports\ChartsSummary::SLUG ),
				'custom_chart'  => $AR->render( Reports\ChartsCustom::SLUG ),
			],
		];
	}
}