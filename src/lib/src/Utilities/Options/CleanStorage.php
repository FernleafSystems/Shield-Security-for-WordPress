<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Utilities\Options;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class CleanStorage {

	use PluginControllerConsumer;

	public function run() {
		foreach ( $this->con()->modules as $mod ) {
			$opts = $mod->getOptions();
			foreach ( \array_keys( $opts->getAllOptionsValues() ) as $optKey ) {
				if ( !$opts->isValidOptionKey( $optKey ) ) {
					$opts->unsetOpt( $optKey );
				}
			}
			$mod->saveModOptions();
		}
	}
}