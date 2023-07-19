<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Retrieve\{
	RetrieveCount,
	RetrieveItems
};
use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\BaseScanActionVO;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\ResultItem;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\ResultsSet;
use FernleafSystems\Wordpress\Services\Services;

abstract class Base {

	use ExecOnce;
	use HackGuard\ModConsumer;

	public const SCAN_SLUG = '';

	/**
	 * @var BaseScanActionVO
	 */
	private $scanActionVO;

	protected $latestResults;

	private static $resultsCounts = [];

	public function __construct() {
	}

	protected function canRun() :bool {
		return $this->isReady();
	}

	protected function run() {
		add_action(
			$this->con()->prefix( 'ondemand_scan_'.$this->getSlug() ),
			function () {
				$this->mod()->getScansCon()->startNewScans( [ $this->getSlug() ] );
			}
		);
	}

	public function getAdminMenuItems() :array {
		return [];
	}

	public function getQueueGroupSize() :int {
		return 1;
	}

	public function cleanStalesResults() {
		foreach ( $this->getAllResults()->getItems() as $item ) {
			$this->cleanStaleResultItem( $item );
		}
	}

	public function countScanProblems() :int {
		if ( !isset( self::$resultsCounts[ $this->getSlug() ] ) ) {
			if ( $this->isRestricted() ) {
				$count = 0;
			}
			else {
				$count = ( new RetrieveCount() )
					->setScanController( $this )
					->count();
			}
			self::$resultsCounts[ $this->getSlug() ] = $count;
		}
		return self::$resultsCounts[ $this->getSlug() ];
	}

	public function getScansController() :HackGuard\Scan\ScansController {
		return $this->mod()->getScansCon();
	}

	/**
	 * @param ResultItem $item
	 */
	public function cleanStaleResultItem( $item ) {
		return true;
	}

	/**
	 * @param ResultItem $item
	 * @throws \Exception
	 */
	public function executeItemAction( $item, string $action ) :bool {
		return $this->getItemActionHandler()
					->setScanItem( $item )
					->process( $action );
	}

	/**
	 * @return Scans\Base\ResultsSet|mixed
	 */
	protected function getItemsToAutoRepair() {
		if ( $this->isRestricted() || !$this->isCronAutoRepair() ) {
			$results = $this->getNewResultsSet();
		}
		else {
			$results = ( new RetrieveItems() )
				->setScanController( $this )
				->retrieveForAutoRepair();
		}
		return $results;
	}

	/**
	 * @return Scans\Base\ResultsSet|mixed
	 */
	public function getAllResults() {
		if ( !isset( $this->latestResults ) ) {
			$this->latestResults = $this->getNewResultsSet();
			if ( !$this->isRestricted() ) {
				try {
					$this->latestResults = ( new RetrieveItems() )
						->setScanController( $this )
						->retrieveLatest();
				}
				catch ( \Exception $e ) {
				}
			}
		}
		return $this->latestResults;
	}

	/**
	 * @return Scans\Base\ResultsSet|mixed
	 */
	public function getResultsForDisplay() {
		return $this->getAllResults()->getNotIgnored();
	}

	/**
	 * @return Scans\Base\Utilities\ItemActionHandler
	 */
	public function getItemActionHandler() {
		return $this->newItemActionHandler()->setScanController( $this );
	}

	/**
	 * @return Scans\Base\Utilities\ItemActionHandler|mixed
	 */
	abstract protected function newItemActionHandler();

	/**
	 * @return Scans\Afs\ScanActionVO|Scans\Apc\ScanActionVO|BaseScanActionVO|Scans\Wpv\ScanActionVO|null
	 */
	public function getScanActionVO() {
		return $this->scanActionVO ?? $this->scanActionVO = HackGuard\Scan\ScanActionFromSlug::GetAction( $this->getSlug() );
	}

	public function getScanName() :string {
		/** @var HackGuard\Strings $strings */
		$strings = $this->mod()->getStrings();
		return $strings->getScanStrings()[ $this->getSlug() ][ 'name' ];
	}

	public function isCronAutoRepair() :bool {
		return false;
	}

	public function isEnabled() :bool {
		return false;
	}

	protected function isPremiumOnly() :bool {
		return true;
	}

	public function isReady() :bool {
		return $this->mod()->isModuleEnabled() && $this->isEnabled() && !$this->isRestricted();
	}

	public function isRestricted() :bool {
		return $this->isPremiumOnly() && !$this->con()->isPremiumActive();
	}

	/**
	 * TODO: Make private/protected
	 */
	public function runCronAutoRepair() {
		foreach ( $this->getItemsToAutoRepair()->getAllItems() as $item ) {
			try {
				$this->getItemActionHandler()
					 ->setScanItem( $item )
					 ->repair();
			}
			catch ( \Exception $e ) {
			}
		}
	}

	/**
	 * @return $this
	 */
	public function purge() {
		// TODO
		return $this;
	}

	public function getSlug() :string {
		try {
			$slug = \strtolower( ( new \ReflectionClass( $this ) )->getShortName() );
		}
		catch ( \Exception $e ) {
			$slug = '';
		}
		return $slug;
	}

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
			$ns = ( new \ReflectionClass( $this->getScanActionVO() ) )->getNamespaceName();
		}
		catch ( \Exception $e ) {
			$ns = __NAMESPACE__;
		}
		return \rtrim( $ns, '\\' ).'\\';
	}

	protected function scheduleOnDemandScan() {
		$hook = $this->con()->prefix( 'ondemand_scan_'.$this->getSlug() );
		if ( !wp_next_scheduled( $hook ) ) {
			wp_schedule_single_event( Services::Request()->ts() + 10, $hook );
		}
	}

	/**
	 * @return BaseScanActionVO|mixed
	 */
	abstract public function buildScanAction();

	abstract public function buildScanResult( array $rawResult ) :HackGuard\DB\ResultItems\Ops\Record;
}