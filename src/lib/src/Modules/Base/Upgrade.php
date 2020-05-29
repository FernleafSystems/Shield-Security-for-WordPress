<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

class Upgrade {

	use ModConsumer;
	use \FernleafSystems\Utilities\Logic\OneTimeExecute;

	protected function run() {
		$sBuild = $this->getOptions()->getOpt( 'cfg_build' );
		if ( !empty( $sBuild ) ) {
			$this->upgradeModule( $sBuild );
		}
		$this->upgradeCommon();
	}

	protected function upgradeCommon() {
		$this->getOptions()->setOpt( 'cfg_build', $this->getCon()->getBuild() );
		$this->getMod()->saveModOptions( true );
	}

	/**
	 * @param string $sCurrentBuild
	 */
	protected function upgradeModule( $sCurrentBuild ) {
	}
}