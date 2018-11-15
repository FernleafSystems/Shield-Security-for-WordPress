<?php

if ( class_exists( 'ICWP_WPSF_Processor_ScanBase', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/cronbase.php' );

use \FernleafSystems\Wordpress\Plugin\Shield\Scans;

abstract class ICWP_WPSF_Processor_ScanBase extends ICWP_WPSF_Processor_CronBase {

	const SCAN_SLUG = 'base';

	/**
	 * @var ICWP_WPSF_Processor_HackProtect_Scanner
	 */
	protected $oScanner;

	/**
	 */
	public function run() {
		parent::run();
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getMod();
		$this->loadAutoload();
	}

	/**
	 * @return Scans\Base\BaseResultsSet
	 */
	public function doScan() {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getMod();

		$oResults = $this->getScannerResults();
		$this->updateScanResultsStore( $oResults );

		$oFO->setLastScanAt( static::SCAN_SLUG );
		$oResults->hasItems() ?
			$oFO->setLastScanProblemAt( static::SCAN_SLUG )
			: $oFO->clearLastScanProblemAt( static::SCAN_SLUG );

		return $oResults;
	}

	/**
	 * @return Scans\Base\BaseResultsSet
	 */
	protected function getScannerResults() {
		/** @var Scans\Base\BaseResultsSet $oResults */
		return $this->getScanner()->run();
	}

	/**
	 * @return Scans\Base\BaseResultsSet
	 */
	public function doScanAndFullRepair() {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getMod();

		$oResultSet = $this->doScan();
		$this->getRepairer()->repairResultsSet( $oResultSet );
		$oFO->clearLastScanProblemAt( static::SCAN_SLUG );

		return $oResultSet;
	}

	/**
	 * @return mixed
	 */
	abstract protected function getRepairer();

	/**
	 * @return mixed
	 */
	abstract protected function getScanner();

	/**
	 * @param Scans\Base\BaseResultsSet $oNewResults
	 */
	protected function updateScanResultsStore( $oNewResults ) {
		$oNewCopy = clone $oNewResults; // so we don't modify these for later use.
		$oExisting = $this->readScanResultsFromDb();
		$oItemsToDelete = ( new Scans\Base\DiffResultForStorage() )->diff( $oExisting, $oNewCopy );
		$this->deleteResultsSet( $oItemsToDelete );
		$this->storeNewScanResults( $oNewCopy );
		$this->updateExistingScanResults( $oExisting );
	}

	/**
	 * @param Scans\Base\BaseResultsSet $oToDelete
	 */
	protected function deleteResultsSet( $oToDelete ) {
		$oDeleter = $this->getScannerDb()->getQueryDeleter();
		foreach ( $oToDelete->getAllItems() as $oItem ) {
			$oDeleter->reset()
					 ->filterByScan( static::SCAN_SLUG )
					 ->filterByHash( $oItem->hash )
					 ->query();
		}
	}

	/**
	 * @return Scans\Base\BaseResultsSet
	 */
	protected function readScanResultsFromDb() {
		$oSelector = $this->getScannerDb()->getQuerySelector();
		return $this->convertVosToResults( $oSelector->forScan( static::SCAN_SLUG ) );
	}

	/**
	 * @param Scans\Base\BaseResultsSet $oResults
	 */
	protected function storeNewScanResults( $oResults ) {
		$oInsert = $this->getScannerDb()->getQueryInserter();
		foreach ( $this->convertResultsToVos( $oResults ) as $oVo ) {
			$oInsert->insert( $oVo );
		}
	}

	/**
	 * @param Scans\Base\BaseResultsSet $oResults
	 */
	protected function updateExistingScanResults( $oResults ) {
		$oUp = $this->getScannerDb()->getQueryUpdater();
		foreach ( $this->convertResultsToVos( $oResults ) as $oVo ) {
			$oUp->reset()
				->setUpdateData( $oVo->getRawData() )
				->setUpdateWheres(
					[
						'scan' => static::SCAN_SLUG,
						'hash' => $oVo->hash,
					]
				)
				->query();
		}
	}

	/**
	 * @param Scans\Base\BaseResultsSet $oResults
	 * @return \FernleafSystems\Wordpress\Plugin\Shield\Databases\Base\BaseEntryVO[] $aVos
	 */
	abstract protected function convertResultsToVos( $oResults );

	/**
	 * @param \FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner\EntryVO[] $aVos
	 * @return Scans\Base\BaseResultsSet
	 */
	abstract protected function convertVosToResults( $aVos );

	/**
	 * @param \FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner\EntryVO $oVo
	 * @return Scans\Base\BaseResultItem
	 */
	abstract protected function convertVoToResultItem( $oVo );

	/**
	 * @return string
	 */
	public function buildTableScanResults() {
		parse_str( $this->loadRequest()->post( 'filter_params', '' ), $aFilters );
		$aParams = array_intersect_key(
			array_merge( $_POST, array_map( 'trim', $aFilters ) ),
			array_flip( array(
				'paged',
				'order',
				'orderby',
				'fScan',
				'fSlug',
			) )
		);
		return $this->renderTable( $aParams );
	}

	/**
	 * @param $aParams
	 * @return string
	 */
	protected function renderTable( $aParams ) {

		// clean any params of nonsense
		foreach ( $aParams as $sKey => $sValue ) {
			if ( preg_match( '#[^a-z0-9_\s]#i', $sKey ) || preg_match( '#[^a-z0-9._-\s]#i', $sValue ) ) {
				unset( $aParams[ $sKey ] );
			}
		}
		$aParams = array_merge(
			array(
				'orderby' => 'created_at',
				'order'   => 'DESC',
				'paged'   => 1,
				'fScan'   => 'wcf',
				'fSlug'   => '',
			),
			$aParams
		);
		$nPage = (int)$aParams[ 'paged' ];
		$oScanPro = $this->getScannerDb();
		$oSelector = $oScanPro->getQuerySelector()
							  ->setPage( $nPage )
							  ->setOrderBy( $aParams[ 'orderby' ], $aParams[ 'order' ] )
							  ->filterByScan( static::SCAN_SLUG )
							  ->setResultsAsVo( true );
		$aEntries = $this->postSelectEntriesFilter( $oSelector->query(), $aParams );

		if ( empty( $aEntries ) || !is_array( $aEntries ) ) {
			$sRendered = '<div class="alert alert-success m-0">No items discovered</div>';
		}
		else {
			$oTable = $this->getTableRenderer()
						   ->setItemEntries( $this->formatEntriesForDisplay( $aEntries ) )
						   ->setTotalRecords( count( $aEntries ) )
						   ->prepare_items();
			ob_start();
			$oTable->display();
			$sRendered = ob_get_clean();
		}
		return $sRendered;
	}

	/**
	 * @param int|string $sItemId
	 * @param string     $sAction
	 * @return bool
	 * @throws Exception
	 */
	public function executeItemAction( $sItemId, $sAction ) {
		switch ( $sAction ) {
			case 'delete':
				$bSuccess = $this->deleteItem( $sItemId );
				break;

			case 'ignore':
				$bSuccess = $this->ignoreItem( $sItemId );
				break;

			case 'repair':
				$bSuccess = $this->repairItem( $sItemId );
				break;

			default:
				$bSuccess = false;
				break;
		}

		return $bSuccess;
	}

	/**
	 * @param $sItemId
	 * @return bool
	 * @throws Exception
	 */
	protected function deleteItem( $sItemId ) {
		throw new Exception( 'Unsupported Action' );
	}

	/**
	 * @param $sItemId
	 * @return bool
	 * @throws Exception
	 */
	protected function ignoreItem( $sItemId ) {
		throw new Exception( 'Unsupported Action' );
	}

	/**
	 * @param $sItemId
	 * @return bool
	 * @throws Exception
	 */
	protected function repairItem( $sItemId ) {
		throw new Exception( 'Unsupported Action' );
	}

	/**
	 * @param \FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner\EntryVO[] $aEntries
	 * @param array                                                                $aParams
	 * @return \FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner\EntryVO[]
	 */
	protected function postSelectEntriesFilter( $aEntries, $aParams ) {
		return $aEntries;
	}

	/**
	 * @return ScanTableBase
	 */
	protected function getTableRenderer() {
		$this->requireCommonLib( 'Components/Tables/ScanTableBase.php' );
		return new ScanTableBase();
	}

	/**
	 * Move to table
	 * @param \FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner\EntryVO[] $aEntries
	 * @return array
	 * 'path_fragment' => 'File',
	 * 'status'        => 'Status',
	 * 'ignored'       => 'Ignored',
	 */
	abstract protected function formatEntriesForDisplay( $aEntries );

	/**
	 * @return int
	 */
	protected function getCronFrequency() {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getMod();
		return $oFO->getScanFrequency();
	}

	/**
	 * @return ICWP_WPSF_Processor_HackProtect_Scanner
	 */
	public function getScannerDb() {
		return $this->oScanner;
	}

	/**
	 * @param ICWP_WPSF_Processor_HackProtect_Scanner $oScanner
	 * @return $this
	 */
	public function setScannerDb( $oScanner ) {
		$this->oScanner = $oScanner;
		return $this;
	}
}