<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\{
	ResultsRetrieve,
	ResultsUpdate
};
use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\ResultItem;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\ResultsSet;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\BaseScanActionVO;
use FernleafSystems\Wordpress\Services\Services;

abstract class Base extends ExecOnceModConsumer {

	const SCAN_SLUG = '';

	/**
	 * @var BaseScanActionVO
	 */
	private $scanActionVO;

	private static $resultsCounts = [];

	public function __construct() {
	}

	protected function canRun() :bool {
		return $this->isReady();
	}

	protected function run() {
		add_action(
			$this->getCon()->prefix( 'ondemand_scan_'.$this->getSlug() ),
			function () {
				/** @var HackGuard\ModCon $mod */
				$mod = $this->getMod();
				$mod->getScanQueueController()->startScans( [ $this->getSlug() ] );
			}
		);
		add_filter( $this->getCon()->prefix( 'admin_bar_menu_items' ), [ $this, 'addAdminMenuBarItem' ], 100 );
	}

	public function addAdminMenuBarItem( array $items ) :array {
		$problems = $this->countScanProblems();
		if ( $problems > 0 ) {
			$items[] = [
				'id'       => $this->getCon()->prefix( 'problems-'.$this->getSlug() ),
				'title'    => $this->getScanName()
							  .sprintf( '<div class="wp-core-ui wp-ui-notification shield-counter"><span aria-hidden="true">%s</span></div>', $problems ),
				'href'     => $this->getCon()->getModule_Insights()->getUrl_ScansResults(),
				'warnings' => $problems
			];
		}
		return $items;
	}

	public function cleanStalesResults() {
		foreach ( $this->getAllResults()->getItems() as $item ) {
			$this->cleanStaleResultItem( $item );
		}
	}

	public function createFileDownloadLink( int $recordID ) :string {
		return $this->getMod()->createFileDownloadLink( 'scan_file', [ 'rid' => $recordID ] );
	}

	public function countScanProblems() :int {
		if ( !isset( self::$resultsCounts[ $this->getSlug() ] ) ) {
			if ( $this->isRestricted() ) {
				$count = 0;
			}
			else {
				$count = ( new ResultsRetrieve() )
					->setScanController( $this )
					->setMod( $this->getMod() )
					->count( false );
			}
			self::$resultsCounts[ $this->getSlug() ] = $count;
		}
		return self::$resultsCounts[ $this->getSlug() ];
	}

	public function getScanHasProblem() :bool {
		return $this->countScanProblems() > 0;
	}

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
		if ( $this->isRestricted() ) {
			$results = $this->getNewResultsSet();
		}
		else {
			$results = ( new ResultsRetrieve() )
				->setMod( $this->getMod() )
				->setScanController( $this )
				->retrieveForAutoRepair();
		}
		return $results;
	}

	/**
	 * @return Scans\Base\ResultsSet|mixed
	 */
	public function getAllResults() {
		if ( $this->isRestricted() ) {
			$results = $this->getNewResultsSet();
		}
		else {
			$results = ( new ResultsRetrieve() )
				->setMod( $this->getMod() )
				->setScanController( $this )
				->retrieveLatest( true );
		}
		return $results;
	}

	/**
	 * @return Scans\Base\ResultsSet|mixed
	 */
	public function getResultsForDisplay() {
		if ( $this->isRestricted() ) {
			$results = $this->getNewResultsSet();
		}
		else {
			$results = ( new ResultsRetrieve() )
				->setMod( $this->getMod() )
				->setScanController( $this )
				->retrieveLatest( false );
		}
		return $results;
	}

	/**
	 * @return Scans\Base\Utilities\ItemActionHandler
	 */
	public function getItemActionHandler() {
		return $this->newItemActionHandler()
					->setMod( $this->getMod() )
					->setScanController( $this );
	}

	/**
	 * @return Scans\Base\Utilities\ItemActionHandler|mixed
	 */
	abstract protected function newItemActionHandler();

	/**
	 * @return BaseScanActionVO|mixed
	 */
	public function getScanActionVO() {
		if ( empty( $this->scanActionVO ) ) {
			$this->scanActionVO = HackGuard\Scan\ScanActionFromSlug::GetAction( $this->getSlug() );
		}
		return $this->scanActionVO;
	}

	public function getScanName() :string {
		/** @var HackGuard\Strings $strings */
		$strings = $this->getMod()->getStrings();
		return $strings->getScanStrings()[ static::SCAN_SLUG ][ 'name' ];
	}

	public function isCronAutoRepair() :bool {
		return false;
	}

	public function canCronAutoDelete() :bool {
		return false;
	}

	abstract public function isEnabled() :bool;

	protected function isPremiumOnly() :bool {
		return true;
	}

	public function isReady() :bool {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		return $mod->isModuleEnabled() && $this->isEnabled() && !$this->isRestricted();
	}

	public function isRestricted() :bool {
		return $this->isPremiumOnly() && !$this->getCon()->isPremiumActive();
	}

	public function resetIgnoreStatus() {
		( new ResultsUpdate() )
			->setMod( $this->getMod() )
			->setScanController( $this )
			->clearIgnored();
	}

	/**
	 * TODO: Make private/protected
	 */
	public function runCronAutoRepair() {
		$results = $this->getItemsToAutoRepair();
		if ( $results->hasItems() ) {
			foreach ( $results->getAllItems() as $item ) {
				try {
					$this->getItemActionHandler()
						 ->setScanItem( $item )
						 ->repair( $this->canCronAutoDelete() );
				}
				catch ( \Exception $e ) {
				}
			}
			$this->cleanStalesResults();
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
			$slug = strtolower( ( new \ReflectionClass( $this ) )->getShortName() );
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
		/** @var ResultItem $item */
		$item = new $class();
		$item->scan = $this->getSlug();
		return $item;
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
		return rtrim( $ns, '\\' ).'\\';
	}

	protected function scheduleOnDemandScan( int $nDelay = 3 ) {
		$sHook = $this->getCon()->prefix( 'ondemand_scan_'.$this->getSlug() );
		if ( !wp_next_scheduled( $sHook ) ) {
			wp_schedule_single_event( Services::Request()->ts() + $nDelay, $sHook );
		}
	}

	/**
	 * @return BaseScanActionVO|mixed
	 */
	abstract public function buildScanAction();

	/**
	 * @return BaseScanActionVO|mixed
	 */
	abstract public function buildScanResult( array $rawResult ) :HackGuard\DB\ResultItems\Ops\Record;

	/**
	 * @deprecated 12.1
	 */
	public function getScanResultsDbHandler() :Databases\Scanner\Handler {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		return $mod->getDbHandler_ScanResults();
	}

	/**
	 * @param ResultItem|mixed $item
	 * @deprecated 12.1
	 */
	public function isResultItemStale( $item ) :bool {
		return false;
	}
}