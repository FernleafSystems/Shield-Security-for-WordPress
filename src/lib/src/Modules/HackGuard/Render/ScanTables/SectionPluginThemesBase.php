<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Render\ScanTables;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller\Apc;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller\Wpv;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;

class SectionPluginThemesBase extends SectionBase {

	protected function getVulnerabilities() :Scans\Wpv\ResultsSet {
		if ( !isset( $this->vulnerable ) ) {
			/** @var ModCon $mod */
			$mod = $this->getMod();
			try {
				$this->vulnerable = $mod->getScanCon( Wpv::SCAN_SLUG )->getAllResults();
			}
			catch ( \Exception $e ) {
				$this->vulnerable = new Scans\Wpv\ResultsSet();
			}
		}
		return $this->vulnerable;
	}

	protected function getAbandoned() :Scans\Apc\ResultsSet {
		if ( !isset( $this->abandoned ) ) {
			/** @var ModCon $mod */
			$mod = $this->getMod();
			try {
				$this->abandoned = $mod->getScanCon( Apc::SCAN_SLUG )->getAllResults();
			}
			catch ( \Exception $e ) {
				$this->abandoned = new Scans\Apc\ResultsSet();
			}
		}
		return $this->abandoned;
	}
}