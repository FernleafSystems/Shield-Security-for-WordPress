<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Lib\Request\FormParams;

class ReportingChartCustom extends ReportingChartBase {

	public const SLUG = 'render_chart_custom';

	protected function exec() {
		$this->renderChart( FormParams::Retrieve() );
	}
}