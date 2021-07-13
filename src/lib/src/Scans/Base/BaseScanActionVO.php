<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;

use FernleafSystems\Utilities\Data\Adapter\DynProperties;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\Table\BaseEntryFormatter;

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

	/**
	 * @return BaseEntryFormatter|mixed
	 */
	public function getTableEntryFormatter() {
		$class = $this->getScanNamespace().'Table\\EntryFormatter';
		/** @var BaseEntryFormatter $formatter */
		$formatter = new $class();
		return $formatter->setScanActionVO( $this );
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