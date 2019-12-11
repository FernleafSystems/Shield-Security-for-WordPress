<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller;

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