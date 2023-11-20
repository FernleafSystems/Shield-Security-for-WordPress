<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Reports;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\OffCanvas\FormReportCreate;

class PageReportsView extends \FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender {

	public const SLUG = 'render_page_reports_view';
	public const TEMPLATE = '/wpadmin_pages/insights/reports/page_reports_view.twig';

	protected function getRenderData() :array {
		return [
			'content' => [
				'reports_table' => self::con()->action_router->render( ReportsTable::class ),
			],
			'flags'   => [
				'can_create_report' => self::con()->caps->canReportsLocal(),
			],
			'strings' => [
				'table_title'                => __( 'Security Reports', 'wp-simple-firewall' ),
				'create_custom_report'       => __( 'Create Custom Report', 'wp-simple-firewall' ),
				'custom_reports_unavailable' => __( 'Upgrade To Create Custom Reports', 'wp-simple-firewall' ),
				'view_report'                => __( 'View Report', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'offcanvas_render_slug' => FormReportCreate::SLUG,
			],
		];
	}
}