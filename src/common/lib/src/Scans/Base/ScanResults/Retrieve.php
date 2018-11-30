<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\ScanResults;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base\HandlerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner;

class Retrieve {

	use HandlerConsumer;

	/**
	 * @var string
	 */
	private $sScan;

	/**
	 * @return Scanner\EntryVO[]
	 */
	public function forAll() {
		/** @var Scanner\Select $oSel */
		$oSel = $this->getDbHandler()->getQuerySelector();
		return $this->runRetrieve( $oSel );
	}

	/**
	 * @return Scanner\EntryVO[]
	 */
	public function forCron() {
		/** @var Scanner\Select $oSel */
		$oSel = $this->getDbHandler()->getQuerySelector();
		$oSel->filterByNotRecentlyNotified()
			 ->filterByNotIgnored();
		return $this->runRetrieve( $oSel );
	}

	/**
	 * @param Scanner\Select $oSel
	 * @return Scanner\EntryVO[]
	 */
	protected function runRetrieve( $oSel ) {
		/** @var Scanner\EntryVO[] $aVo */
		$aVo = $oSel->filterByScan( $this->getScan() )->query();
		return $aVo;
	}

	/**
	 * @return bool
	 */
	public function getScan() {
		return $this->sScan;
	}

	/**
	 * @param bool $sScan
	 * @return $this
	 */
	public function setScan( $sScan ) {
		$this->sScan = $sScan;
		return $this;
	}
}