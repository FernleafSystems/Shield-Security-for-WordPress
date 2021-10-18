<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\Utilities;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Common\ScanItemConsumer;

abstract class RepairItemBase {

	use ModConsumer;
	use ScanItemConsumer;

	/**
	 * @return bool
	 * @throws \Exception
	 */
	abstract public function repairItem() :bool;

	/**
	 * @return bool
	 * @throws \Exception
	 */
	abstract public function canRepair() :bool;
}