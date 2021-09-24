<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;

use FernleafSystems\Utilities\Data\Adapter\DynProperties;

/**
 * Class ScanActionVO
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Base
 * @property string   $scan
 * @property int      $created_at
 * @property int      $started_at
 * @property int      $finished_at
 * @property string[] $items
 * @property array[]  $results
 * @property int      $usleep
 */
abstract class BaseScanActionVO {

	use DynProperties;

	const QUEUE_GROUP_SIZE_LIMIT = 1;
	const DEFAULT_SLEEP_SECONDS = 0;

	/**
	 * @return ResultItem|mixed
	 */
	public function getNewResultItem() {
		$class = $this->getScanNamespace().'ResultItem';
		return new $class();
	}

	/**
	 * @return ResultsSet|mixed
	 */
	public function getNewResultsSet() {
		$class = $this->getScanNamespace().'ResultsSet';
		return new $class();
	}

	public function getScanNamespace() :string {
		try {
			$namespace = ( new \ReflectionClass( $this ) )->getNamespaceName();
		}
		catch ( \Exception $e ) {
			$namespace = __NAMESPACE__;
		}
		return rtrim( $namespace, '\\' ).'\\';
	}
}