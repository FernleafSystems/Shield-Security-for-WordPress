<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModCon;

class BuildScanAction {

	use Shield\Modules\ModConsumer;

	/**
	 * @param string $slug
	 * @return Shield\Scans\Base\BaseScanActionVO|mixed
	 * @throws \Exception
	 */
	public function build( string $slug ) {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		$action = $mod->getScanCon( $slug )->getScanActionVO();

		// Build the action definition:

		$class = $action->getScanNamespace().'BuildScanAction';
		/** @var Shield\Scans\Base\BuildScanAction $builder */
		$builder = new $class();
		$builder->setMod( $mod )
				->setScanActionVO( $action )
				->build();
		return $action;
	}
}
