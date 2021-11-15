<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Reports\Query;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\DB\{
	Scans,
	ScanResults
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

class ScanCounts {

	use ModConsumer;

	/**
	 * @return int[] - key is scan slug
	 */
	public function all() :array {
		return array_merge(
			$this->standard(),
			$this->filelocker()
		);
	}

	/**
	 * @return int[]
	 */
	public function filelocker() :array {
		return [
			'filelocker' => count( ( new HackGuard\Lib\FileLocker\Ops\LoadFileLocks() )
				->setMod( $this->getMod() )
				->withProblemsNotNotified() )
		];
	}

	/**
	 * @return int[] - key is scan slug
	 */
	public function standard() :array {
		/** @var HackGuard\ModCon $mod */
		$mod = $this->getMod();

		$counts = [];
		foreach ( $mod->getScansCon()->getAllScanCons() as $scanCon ) {
			$counts[ $scanCon->getSlug() ] = ( new HackGuard\Scan\Results\Retrieve() )
				->setMod( $this->getMod() )
				->setScanController( $scanCon )
				->setAdditionalWheres( [
					"`ri`.notified_at=0",
				] )
				->count();
		}

		return $counts;
	}
}
