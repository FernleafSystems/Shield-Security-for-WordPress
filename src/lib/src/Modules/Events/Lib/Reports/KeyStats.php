<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Events\Lib\Reports;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Lib\Reports\BaseReporter;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\Components\Reports;

class KeyStats extends BaseReporter {

	public function build() :array {
		$rep = $this->getReport();
		return [
			$this->getCon()
				 ->getModule_Insights()
				 ->getActionRouter()
				 ->render( Reports\KeyStats::SLUG, [
					 'interval_start_at' => $rep->interval_start_at,
					 'interval_end_at'   => $rep->interval_end_at,
				 ] )
		];
	}
}