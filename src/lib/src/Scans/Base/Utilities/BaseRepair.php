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
	private $allowDelete = false;

	public function isAllowDelete() :bool {
		return $this->allowDelete;
	}

	/**
	 * @param bool $allowDelete
	 * @return $this
	 */
	public function setAllowDelete( bool $allowDelete ) {
		$this->allowDelete = $allowDelete;
		return $this;
	}

	/**
	 * @return bool
	 * @throws \Exception
	 */
	abstract public function repairItem() :bool;

	/**
	 * @return bool
	 * @throws \Exception
	 */
	public function canRepair() :bool {
		return false;
	}
}