<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

class ReportingChartSummary extends ReportingChartBase {

	public const SLUG = 'render_chart_summary';

	protected function exec() {
		$this->renderChart( $this->action_data );
	}
}