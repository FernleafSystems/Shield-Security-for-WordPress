<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\WpCli\ModuleStandard;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

class WpCli {

	use ModConsumer;
	use \FernleafSystems\Utilities\Logic\OneTimeExecute;

	protected function run() {
		try {
			foreach ( $this->getAllCmdHandlers() as $oHandler ) {
				$oHandler->setMod( $this->getMod() )->execute();
			}
		}
		catch ( \Exception $oE ) {
		}
	}

	/**
	 * @return WpCli[]
	 */
	protected function getAllCmdHandlers() {
		return array_merge(
			[ new ModuleStandard() ],
			$this->getCmdHandlers()
		);
	}

	/**
	 * @return WpCli[]
	 */
	protected function getCmdHandlers() {
		return [];
	}
}