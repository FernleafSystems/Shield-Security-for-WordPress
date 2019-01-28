<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Ptg\ScanResults;

use FernleafSystems\Wordpress\Plugin\Shield\Scans;

class Clean extends Scans\Base\ScanResults\Clean {

	/**
	 * @return $this
	 */
	public function forPlugins() {
		/** @var Scans\Ptg\ResultsSet $oRs */
		$oRs = $this->getWorkingResultsSet();
		return $this->deleteResults( $oRs->getResultsForPluginsContext() );
	}

	/**
	 * @return $this
	 */
	public function forThemes() {
		/** @var Scans\Ptg\ResultsSet $oRs */
		$oRs = $this->getWorkingResultsSet();
		return $this->deleteResults( $oRs->getResultsForThemesContext() );
	}
}