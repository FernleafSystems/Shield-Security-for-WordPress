<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\Files;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;

/**
 * Class BaseScanFromFileMap
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\Files
 */
abstract class BaseScanFromFileMap {

	use ModConsumer;
	use Scans\Common\ScanActionConsumer;

	/**
	 * @return Scans\Base\BaseResultsSet
	 */
	public function run() {
		/** @var Scans\Base\BaseScanActionVO $oAction */
		$oAction = $this->getScanActionVO();
		$oResultSet = $oAction->getNewResultsSet();

		if ( is_array( $oAction->items ) ) {

			foreach ( $oAction->items as $nKey => $sFullPath ) {
				$oItem = $this->getFileScanner()->scan( $sFullPath );
				if ( $oItem instanceof Scans\Base\BaseResultItem ) {
					$oResultSet->addItem( $oItem );
				}
			}
		}

		return $oResultSet;
	}

	/**
	 * @return BaseFileScanner
	 */
	abstract protected function getFileScanner();
}