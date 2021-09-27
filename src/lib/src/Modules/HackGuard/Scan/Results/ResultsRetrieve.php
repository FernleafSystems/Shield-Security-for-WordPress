<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\DB\Scans as ScansDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\DB\ScanResults as ScanResultsDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller\ScanControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;

class ResultsRetrieve {

	use ScanControllerConsumer;

	/**
	 * @return Scans\Base\ResultsSet|mixed
	 */
	public function retrieve( bool $includeIgnored = true ) {
		$scanCon = $this->getScanController();
		/** @var ModCon $mod */
		$mod = $scanCon->getMod();
		/** @var ScansDB\Ops\Select $scansSelector */
		$scansSelector = $mod->getDbH_Scans()->getQuerySelector();

		$latest = $scansSelector->getLatestForScan( $scanCon->getSlug() );

		if ( $scanCon->isRestricted() || empty( $latest ) ) {
			$raw = [];
		}
		else {
			/** @var ScanResultsDB\Ops\Select $selector */
			$selector = $mod->getDbH_ScanResults()->getQuerySelector();
			$selector->filterByScan( $latest->id );
			if ( !$includeIgnored ) {
				$selector->filterByNotIgnored();
			}
			$raw = $selector->queryWithResult();
		}
		return ( new ConvertBetweenTypes() )
			->setScanController( $scanCon )
			->fromRecordsToResultsSet( $raw );
	}

	public function count() :int {
		$scanCon = $this->getScanController();
		/** @var ModCon $mod */
		$mod = $scanCon->getMod();
		/** @var ScansDB\Ops\Select $scansSelector */
		$scansSelector = $mod->getDbH_Scans()->getQuerySelector();

		$latest = $scansSelector->getLatestForScan( $scanCon->getSlug() );

		if ( $scanCon->isRestricted() || empty( $latest ) ) {
			$count = 0;
		}
		else {
			/** @var ScanResultsDB\Ops\Select $selector */
			$selector = $mod->getDbH_ScanResults()->getQuerySelector();
			$count = $selector->filterByScan( $latest->id )->count();
		}
		return $count;
	}
}
