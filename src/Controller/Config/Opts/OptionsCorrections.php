<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Opts;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class OptionsCorrections {

	use PluginControllerConsumer;

	public function run() :void {
	}

	/**
	 * @deprecated 21.3
	 */
	protected function removeDeprecated() {
	}

	/**
	 * @deprecated 21.3
	 */
	protected function removeModuleEnablers() {
	}
}
