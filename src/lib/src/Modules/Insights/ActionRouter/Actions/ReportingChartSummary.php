<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions;

class ReportingChartSummary extends ReportingBase {

	const SLUG = 'render_chart_summary';

	protected function exec() {
		 $this->renderChart( $_POST );
	}
}