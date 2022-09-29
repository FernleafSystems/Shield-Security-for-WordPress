<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Lib\Reports\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Reporting;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\Components\Reports\ReportsBuilderInfo;

class BuilderInfo extends BaseBuilder {

	/**
	 * @return string[]
	 */
	protected function gather() :array {
		$reports = [];
		foreach ( $this->getCon()->modules as $mod ) {
			$repCon = $mod->getReportingHandler();
			if ( $repCon instanceof Reporting ) {
				foreach ( $repCon->getInfoReporters() as $reporter ) {
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
						ReportsBuilderInfo::SLUG,
						[
							'strings' => [
								'time_interval' => $this->getTimeIntervalForDisplay(),
							],
							'vars'    => [
								'alerts' => $gathered
							],
						]
					);
	}
}