<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

class ReportingChartSummary extends ReportingBase {

	public const SLUG = 'render_chart_summary';

	protected function exec() {
		 $this->renderChart( $_POST );
	}
}