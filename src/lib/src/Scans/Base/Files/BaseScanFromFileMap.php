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
		$action = $this->getScanActionVO();
		$results = $action->getNewResultsSet();

		if ( is_array( $action->items ) ) {
			foreach ( $action->items as $key => $fullPath ) {
				$item = $this->getFileScanner()->scan( $fullPath );
				if ( $item instanceof Scans\Base\BaseResultItem ) {
					$results->addItem( $item );
				}
			}
		}

		return $results;
	}

	/**
	 * @return BaseFileScanner
	 */
	abstract protected function getFileScanner();
}