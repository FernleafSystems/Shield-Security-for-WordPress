<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal;

use FernleafSystems\Wordpress\Plugin\Shield;

class BuildScanAction extends Shield\Scans\Base\BaseBuildScanAction {

	protected function buildItems() {
		$items = ( new BuildFileMap() )
			->setMod( $this->getMod() )
			->setScanActionVO( $this->getScanActionVO() )
			->build();
		asort( $items );
		$this->getScanActionVO()->items = $items;
	}

	protected function setCustomFields() {
		/** @var ScanActionVO $action */
		$action = $this->getScanActionVO();
		$action->file_exts = [ 'php', 'php5', 'php7' ];
	}
}