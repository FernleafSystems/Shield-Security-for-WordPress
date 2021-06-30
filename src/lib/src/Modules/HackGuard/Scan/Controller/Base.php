<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\BaseResultItem;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\BaseResultsSet;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\BaseScanActionVO;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\Table\BaseEntryFormatter;
use FernleafSystems\Wordpress\Services\Services;

abstract class Base extends ExecOnceModConsumer {

	const SCAN_SLUG = '';

	/**
	 * @var BaseScanActionVO
	 */
	private $oScanActionVO;

	/**
	 * Base constructor.
	 * see dynamic constructors: features/hack_protect.php
	 */
	public function __construct() {
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
	}

	public function cleanStalesResults() {
		$results = ( new HackGuard\Scan\Results\ResultsRetrieve() )
			->setScanController( $this )
			->retrieve();
		foreach ( $results->getItems() as $item ) {
			if ( !$this->isResultItemStale( $item ) ) {
				$results->removeItemByHash( $item->hash );
			}
		}
		( new HackGuard\Scan\Results\ResultsDelete() )
			->setScanController( $this )
			->delete( $results );
	}

	public function createFileDownloadLink( Databases\Scanner\EntryVO $entry ) :string {
		return $this->getMod()->createFileDownloadLink( 'scan_file', [ 'rid' => $entry->id ] );
	}

	public function getLastScanAt() :int {
		/** @var Databases\Events\Select $sel */
		$sel = $this->getCon()
					->getModule_Events()
					->getDbHandler_Events()
					->getQuerySelector();
		$entry = $sel->getLatestForEvent( $this->getSlug().'_scan_run' );
		return ( $entry instanceof Databases\Events\EntryVO ) ? (int)$entry->created_at : 0;
	}

	public function getScanHasProblem() :bool {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var Databases\Scanner\Select $sel */
		$sel = $mod->getDbHandler_ScanResults()->getQuerySelector();
		return $sel->filterByNotIgnored()
				   ->filterByScan( $this->getSlug() )
				   ->count() > 0;
	}

	/**
	 * @param BaseResultItem|mixed $item
	 * @return bool
	 */
	abstract protected function isResultItemStale( $item ) :bool;

	/**
	 * @param int|string $itemID
	 * @param string     $action
	 * @return bool
	 */
	public function executeItemAction( int $itemID, string $action ) :bool {
		$success = false;

		if ( is_numeric( $itemID ) ) {
			/** @var Databases\Scanner\EntryVO $entry */
			$entry = $this->getScanResultsDbHandler()
						  ->getQuerySelector()
						  ->byId( $itemID );
			if ( empty( $entry ) ) {
				throw new \Exception( 'Item could not be found.' );
			}

			$entry = ( new HackGuard\Scan\Results\ConvertBetweenTypes() )
				->setScanController( $this )
				->convertVoToResultItem( $entry );

			$success = $this->getItemActionHandler()
							->setScanItem( $entry )
							->process( $action );
		}

		return $success;
	}

	/**
	 * @return Scans\Base\BaseResultsSet|mixed
	 */
	protected function getItemsToAutoRepair() {
		/** @var Databases\Scanner\Select $sel */
		$sel = $this->getScanResultsDbHandler()->getQuerySelector();
		$sel->filterByScan( $this->getSlug() )
			->filterByNotIgnored();
		return ( new HackGuard\Scan\Results\ConvertBetweenTypes() )
			->setScanController( $this )
			->fromVOsToResultsSet( $sel->query() );
	}

	/**
	 * @return bool
	 */
	public function updateAllAsNotified() {
		/** @var Databases\Scanner\Update $updater */
		$updater = $this->getScanResultsDbHandler()->getQueryUpdater();
		return $updater->setAllNotifiedForScan( $this->getSlug() );
	}

	/**
	 * @param bool $bIncludeIgnored
	 * @return Scans\Base\BaseResultsSet|mixed
	 */
	public function getAllResults( $bIncludeIgnored = false ) {
		/** @var Databases\Scanner\Select $sel */
		$sel = $this->getScanResultsDbHandler()->getQuerySelector();
		$sel->filterByScan( $this->getSlug() );
		if ( !$bIncludeIgnored ) {
			$sel->filterByNotIgnored();
		}
		return ( new HackGuard\Scan\Results\ConvertBetweenTypes() )
			->setScanController( $this )
			->fromVOsToResultsSet( $sel->query() );
	}

	/**
	 * @return Scans\Base\Utilities\ItemActionHandler
	 */
	protected function getItemActionHandler() {
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
		if ( !$this->oScanActionVO instanceof BaseScanActionVO ) {
			$this->oScanActionVO = HackGuard\Scan\ScanActionFromSlug::GetAction( $this->getSlug() );
		}
		return $this->oScanActionVO;
	}

	public function getScanName() :string {
		/** @var HackGuard\Strings $strings */
		$strings = $this->getMod()->getStrings();
		return $strings->getScanNames()[ static::SCAN_SLUG ];
	}

	public function isCronAutoRepair() :bool {
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

	/**
	 * @return $this
	 */
	public function resetIgnoreStatus() {
		/** @var Databases\Scanner\Update $oUpd */
		$oUpd = $this->getScanResultsDbHandler()->getQueryUpdater();
		$oUpd->clearIgnoredAtForScan( $this->getSlug() );
		return $this;
	}

	/**
	 * @return $this
	 */
	public function resetNotifiedStatus() {
		/** @var Databases\Scanner\Update $oUpd */
		$oUpd = $this->getScanResultsDbHandler()->getQueryUpdater();
		$oUpd->clearNotifiedAtForScan( $this->getSlug() );
		return $this;
	}

	/**
	 * TODO: Make private/protected
	 */
	public function runCronAutoRepair() {
		$results = $this->getItemsToAutoRepair();
		if ( $results->hasItems() ) {
			foreach ( $results->getAllItems() as $oItem ) {
				try {
					$this->getItemActionHandler()
						 ->setScanItem( $oItem )
						 ->repair();
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
		( new HackGuard\Scan\Results\ResultsDelete() )
			->setScanController( $this )
			->deleteAllForScan();
		return $this;
	}

	public function getScanResultsDbHandler() :Databases\Scanner\Handler {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		return $mod->getDbHandler_ScanResults();
	}

	public function getSlug() :string {
		try {
			$slug = strtolower( ( new \ReflectionClass( $this ) )->getShortName() );
		}
		catch ( \ReflectionException $e ) {
			$slug = '';
		}
		return $slug;
	}

	/**
	 * @return BaseResultItem|mixed
	 */
	public function getNewResultItem() {
		$class = $this->getScanNamespace().'ResultItem';
		return new $class();
	}

	/**
	 * @return BaseResultsSet|mixed
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
		return $formatter->setScanController( $this )
						 ->setMod( $this->getMod() )
						 ->setScanActionVO( $this->getScanActionVO() );
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
}