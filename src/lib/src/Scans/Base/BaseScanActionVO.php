<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;

use FernleafSystems\Utilities\Data\Adapter\StdClassAdapter;

/**
 * Class ScanActionVO
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Base
 * @property string   $id
 * @property int      $ts_init
 * @property int      $ts_start
 * @property int      $ts_finish
 * @property bool     $is_async
 * @property bool     $is_cron
 * @property bool     $is_items_built
 * @property int      $processed_items
 * @property int      $total_scan_items
 * @property int      $item_processing_limit
 * @property string   $tmp_dir
 * @property string[] $scan_items
 * @property array[]  $results
 */
class BaseScanActionVO {

	use StdClassAdapter;

	/**
	 * @return BaseResultItem|mixed
	 */
	public function getNewResultItem() {
		$sClass = $this->getScanNamespace().'\\ResultItem';
		return new $sClass();
	}

	/**
	 * @return BaseResultsSet|mixed
	 */
	public function getNewResultsSet() {
		$sClass = $this->getScanNamespace().'\\ResultsSet';
		return new $sClass();
	}

	/**
	 * @return string
	 */
	public function getScanNamespace() {
		try {
			$sName = ( new \ReflectionClass( $this ) )->getNamespaceName();
		}
		catch ( \Exception $oE ) {
			$sName = __NAMESPACE__;
		}
		return $sName;
	}
}