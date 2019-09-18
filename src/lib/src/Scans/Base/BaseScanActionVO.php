<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;

use FernleafSystems\Utilities\Data\Adapter\StdClassAdapter;

/**
 * Class ScanActionVO
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Base
 * @property string   $scan
 * @property int      $created_at
 * @property int      $started_at
 * @property int      $finished_at
 * @property bool     $is_async
 * @property int      $total_items
 * @property string[] $items
 * @property array[]  $results
 */
class BaseScanActionVO {

	use StdClassAdapter;

	const ITEM_STORAGE_LIMIT = 1;

	/**
	 * @return BaseResultItem|mixed
	 */
	public function getNewResultItem() {
		$sClass = $this->getScanNamespace().'ResultItem';
		return new $sClass();
	}

	/**
	 * @return BaseResultsSet|mixed
	 */
	public function getNewResultsSet() {
		$sClass = $this->getScanNamespace().'ResultsSet';
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
		return rtrim( $sName, '\\' ).'\\';
	}
}