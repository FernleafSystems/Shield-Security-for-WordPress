<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\Components\Reports;

class PageReports extends BasePluginAdminPage {

	const SLUG = 'admin_plugin_page_reports';
	const PRIMARY_MOD = 'reporting';
	const TEMPLATE = '/wpadmin_pages/insights/reports/index.twig';

	protected function getRenderData() :array {
		$AR = $this->getCon()
				   ->getModule_Insights()
				   ->getActionRouter();
		return [
			'content' => [
				'summary_stats' => $AR->render( Reports\ChartsSummary::SLUG ),
				'custom_chart'  => $AR->render( Reports\ChartsCustom::SLUG ),
			],
		];
	}
}