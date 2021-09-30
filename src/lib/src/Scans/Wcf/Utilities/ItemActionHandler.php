<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Wcf\Utilities;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;

class ItemActionHandler extends Base\Utilities\ItemActionHandler {

	/**
	 * @throws \Exception
	 */
	public function repairDelete() :bool {
		return $this->repair();
	}
}
