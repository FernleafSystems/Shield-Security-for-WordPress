<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Lib\Reports\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Reporting;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\Components\Reports\ReportsBuilderAlerts;

class BuilderAlerts extends BaseBuilder {

	/**
	 * @return string[]
	 */
	protected function gather() :array {
		$reports = [];
		foreach ( $this->getCon()->modules as $mod ) {
			$repCon = $mod->getReportingHandler();
			if ( $repCon instanceof Reporting ) {
				foreach ( $repCon->getAlertReporters() as $reporter ) {
					$reports = array_merge(
						$reports,
						$reporter->setReport( $this->rep )->build()
					);
				}
			}
		}
		return $reports;
	}

	protected function render( array $gathered ) :string {
		return $this->getCon()
					->getModule_Insights()
					->getActionRouter()
					->render(
						ReportsBuilderAlerts::SLUG,
						[
							'vars'    => [
								'alerts' => $gathered
							],
						]
					);
	}
}