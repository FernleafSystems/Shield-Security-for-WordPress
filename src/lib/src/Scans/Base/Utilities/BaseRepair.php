<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\Utilities;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\BaseResultItem;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\BaseResultsSet;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Common\ScanItemConsumer;

/**
 * Class BaseRepair
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Base
 */
abstract class BaseRepair {

	use ScanItemConsumer;

	/**
	 * @var bool
	 */
	private $bAllowDelete = false;

	/**
	 * @var bool
	 */
	private $bIsManualAction = false;

	/**
	 * @return bool
	 */
	public function isAllowDelete() {
		return (bool)$this->bAllowDelete;
	}

	/**
	 * @return bool
	 */
	public function isManualAction() {
		return (bool)$this->bIsManualAction;
	}

	/**
	 * @param bool $bAllowDelete
	 * @return $this
	 */
	public function setAllowDelete( $bAllowDelete ) {
		$this->bAllowDelete = $bAllowDelete;
		return $this;
	}

	/**
	 * @param bool $bManual
	 * @return $this
	 */
	public function setIsManualAction( $bManual ) {
		$this->bIsManualAction = $bManual;
		return $this;
	}

	/**
	 * @param BaseResultsSet $oResults
	 */
	public function repairResultsSet( $oResults ) {
		foreach ( $oResults->getItems() as $oItem ) {
			try {
				/** @var BaseResultItem $oItem */
				$this->setScanItem( $oItem )
					 ->repairItem();
			}
			catch ( \Exception $oE ) {
			}
		}
	}

	/**
	 * @return bool
	 * @throws \Exception
	 */
	abstract public function repairItem();

	/**
	 * @return bool
	 * @throws \Exception
	 */
	public function canRepair() {
		return false;
	}
}