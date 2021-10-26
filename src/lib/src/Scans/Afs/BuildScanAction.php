<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;

class BuildScanAction extends Base\BuildScanAction {

	protected function buildItems() {
		$items = ( new BuildScanItems() )
			->setMod( $this->getScanController()->getMod() )
			->setScanActionVO( $this->getScanActionVO() )
			->run();
		asort( $items );
		$this->getScanActionVO()->items = $items;
	}

	protected function setCustomFields() {
		/** @var ScanActionVO $action */
		$action = $this->getScanActionVO();
		$action->file_exts = $this->getFileExts();
	}
}