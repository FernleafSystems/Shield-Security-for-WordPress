<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Reports;

class PageReportsView extends \FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender {

	public const SLUG = 'render_page_reports_view';
	public const TEMPLATE = '/wpadmin_pages/insights/reports/page_reports_view.twig';

	protected function getRenderData() :array {
		return [
			'content' => [
				'reports_table' => self::con()->action_router->render( ReportsTable::class ),
			],
			'hrefs'   => [
				'create_custom_report' => self::con()->plugin_urls->offCanvasTrigger( 'renderReportCreate()' ),
			],
			'strings' => [
				'table_title'          => __( 'Security Reports', 'wp-simple-firewall' ),
				'create_custom_report' => __( 'Create Custom Report', 'wp-simple-firewall' ),
				'view_report'          => __( 'View Report', 'wp-simple-firewall' ),
			],
		];
	}
}