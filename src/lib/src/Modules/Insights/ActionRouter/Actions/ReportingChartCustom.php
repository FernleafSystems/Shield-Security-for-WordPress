<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Lib\Request\FormParams;

class ReportingChartCustom extends ReportingBase {

	const SLUG = 'render_chart_custom';

	/**
	 * @inheritDoc
	 */
	protected function exec() {
		$this->renderChart( FormParams::Retrieve() );
	}
}