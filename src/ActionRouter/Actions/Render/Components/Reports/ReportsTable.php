<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Reports;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender;

class ReportsTable extends BaseRender {

	public const SLUG = 'render_reports_table';
	public const TEMPLATE = '/wpadmin/components/reports/table_reports.twig';

	protected function getRenderData() :array {
		return [];
	}
}
