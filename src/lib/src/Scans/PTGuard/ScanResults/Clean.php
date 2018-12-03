<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\PTGuard\ScanResults;

use FernleafSystems\Wordpress\Plugin\Shield\Scans;

class Clean extends Scans\Base\ScanResults\Clean {

	/**
	 * @return $this
	 */
	public function forPlugins() {
		/** @var Scans\PTGuard\ResultsSet $oRs */
		$oRs = $this->getWorkingResultsSet();
		return $this->deleteResults( $oRs->getResultsForPluginsContext() );
	}

	/**
	 * @return $this
	 */
	public function forThemes() {
		/** @var Scans\PTGuard\ResultsSet $oRs */
		$oRs = $this->getWorkingResultsSet();
		return $this->deleteResults( $oRs->getResultsForThemesContext() );
	}
}