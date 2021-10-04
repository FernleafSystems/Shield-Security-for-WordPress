<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Wcf;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;

class BuildScanAction extends Base\BuildScanAction {

	protected function buildItems() {
		$this->getScanActionVO()->items = ( new BuildScanItems() )
			->setMod( $this->getScanController()->getMod() )
			->setScanActionVO( $this->getScanActionVO() )
			->run();
	}
}