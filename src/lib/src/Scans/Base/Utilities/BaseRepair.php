<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\Utilities;

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
	 * @return bool
	 */
	public function isAllowDelete() {
		return (bool)$this->bAllowDelete;
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
	 * @return bool
	 * @throws \Exception
	 */
	abstract public function repairItem();

	/**
	 * @return bool
	 * @throws \Exception
	 */
	public function canRepair() :bool {
		return false;
	}
}