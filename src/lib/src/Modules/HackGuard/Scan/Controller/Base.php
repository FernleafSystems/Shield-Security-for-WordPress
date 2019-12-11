<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller;

use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\BaseScanActionVO;

abstract class Base {

	use ModConsumer;

	/**
	 * @var BaseScanActionVO
	 */
	private $oScanActionVO;

	/**
	 * @param int|string $sItemId
	 * @param string     $sAction
	 * @return bool
	 * @throws \Exception
	 */
	public function executeItemAction( $sItemId, $sAction ) {
		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();

		$bSuccess = false;
		if ( is_numeric( $sItemId ) ) {
			/** @var Databases\Scanner\EntryVO $oEntry */
			$oEntry = $oMod->getDbHandler_ScanResults()
						   ->getQuerySelector()
						   ->byId( $sItemId );
			if ( empty( $oEntry ) ) {
				throw new \Exception( 'Item could not be found.' );
			}

			$oItem = ( new HackGuard\Scan\Results\ConvertBetweenTypes() )
				->setScanActionVO( $this->getScanActionVO() )
				->convertVoToResultItem( $oEntry );

			$bSuccess = $this->getItemActionHandler()
							 ->setMod( $this->getMod() )
							 ->setScanActionVO( $this->getScanActionVO() )
							 ->setScanItem( $oItem )
							 ->setDbHandler( $oMod->getDbHandler_ScanResults() )
							 ->handleAction( $sAction );
		}

		return $bSuccess;
	}

	/**
	 * @return Scans\Base\BaseResultsSet|mixed
	 */
	public function getAllResultsForCron() {
		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();
		/** @var Databases\Scanner\Select $oSel */
		$oSel = $oMod->getDbHandler_ScanResults()->getQuerySelector();
		$oSel->filterByScan( $this->getSlug() )
			 ->filterForCron( $oMod->getScanNotificationInterval() );
		return ( new HackGuard\Scan\Results\ConvertBetweenTypes() )
			->setScanActionVO( $this->getScanActionVO() )
			->fromVOsToResultsSet( $oSel->query() );
	}

	/**
	 * @return bool
	 */
	public function updateAllAsNotified() {
		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();
		/** @var Databases\Scanner\Update $oUpd */
		$oUpd = $oMod->getDbHandler_ScanResults()->getQueryUpdater();
		return $oUpd->setAllNotifiedForScan( $this->getSlug() );
	}

	/**
	 * @param bool $bIncludeIgnored
	 * @return Scans\Base\BaseResultsSet|mixed
	 */
	public function getAllResults( $bIncludeIgnored = false ) {
		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();
		/** @var Databases\Scanner\Select $oSel */
		$oSel = $oMod->getDbHandler_ScanResults()->getQuerySelector();
		$oSel->filterByScan( $this->getSlug() );
		if ( !$bIncludeIgnored ) {
			$oSel->filterByNotIgnored();
		}
		return ( new HackGuard\Scan\Results\ConvertBetweenTypes() )
			->setScanActionVO( $this->getScanActionVO() )
			->fromVOsToResultsSet( $oSel->query() );
	}

	/**
	 * @return Scans\Base\Utilities\ItemActionHandler|mixed
	 */
	abstract protected function getItemActionHandler();

	/**
	 * @return BaseScanActionVO|mixed
	 */
	public function getScanActionVO() {
		if ( !$this->oScanActionVO instanceof BaseScanActionVO ) {
			$this->oScanActionVO = ( new HackGuard\Scan\ScanActionFromSlug() )
				->getAction( $this->getSlug() );
		}
		return $this->oScanActionVO;
	}

	/**
	 * @return bool
	 */
	public function isCronAutoRepair() {
		return false;
	}

	/**
	 * @return bool
	 */
	abstract public function isEnabled();

	/**
	 * @return bool
	 */
	protected function isPremiumOnly() {
		return true;
	}

	/**
	 * @return bool
	 */
	public function isScanningAvailable() {
		/** @var HackGuard\Options $oOpts */
		$oOpts = $this->getOptions();
		return !$this->isPremiumOnly() || $oOpts->isPremium();
	}

	/**
	 * @return $this
	 */
	public function resetIgnoreStatus() {
		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();
		/** @var Databases\Scanner\Update $oUpd */
		$oUpd = $oMod->getDbHandler_ScanResults()->getQueryUpdater();
		$oUpd->clearIgnoredAtForScan( $this->getSlug() );
		return $this;
	}

	/**
	 * @return $this
	 */
	public function resetNotifiedStatus() {
		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();
		/** @var Databases\Scanner\Update $oUpd */
		$oUpd = $oMod->getDbHandler_ScanResults()->getQueryUpdater();
		$oUpd->clearNotifiedAtForScan( $this->getSlug() );
		return $this;
	}

	/**
	 * @return $this
	 */
	public function purge() {
		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();
		( new HackGuard\Scan\Results\Clean() )
			->setDbHandler( $oMod->getDbHandler_ScanResults() )
			->setScanActionVO( $this->getScanActionVO() )
			->deleteAllForScan();
		return $this;
	}

	/**
	 * @return string
	 */
	protected function getSlug() {
		try {
			$sSlug = strtolower( ( new \ReflectionClass( $this ) )->getShortName() );
		}
		catch ( \ReflectionException $oRE ) {
			$sSlug = '';
		}
		return $sSlug;
	}
}