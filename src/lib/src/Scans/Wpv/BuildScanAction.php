<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Wpv;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;

class BuildScanAction extends Base\BuildScanAction {

	protected function buildItems() {
		$this->getScanActionVO()->items = ( new BuildScanItems() )
			->setMod( $this->getScanController()->getMod() )
			->run();
	}
}