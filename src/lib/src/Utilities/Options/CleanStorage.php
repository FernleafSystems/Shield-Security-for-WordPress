<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Utilities\Options;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class CleanStorage {

	use PluginControllerConsumer;

	public function run() {
		foreach ( $this->getCon()->modules as $mod ) {
			$oOpts = $mod->getOptions();
			foreach ( array_keys( $oOpts->getAllOptionsValues() ) as $sOptKey ) {
				if ( !$oOpts->isValidOptionKey( $sOptKey ) ) {
					$oOpts->unsetOpt( $sOptKey );
				}
			}
			$mod->saveModOptions();
		}
	}
}