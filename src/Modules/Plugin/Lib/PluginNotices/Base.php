<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\PluginNotices;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

abstract class Base {

	use PluginControllerConsumer;

	/**
	 * How long after install a notice that only informs or offers holds off.
	 */
	private const NEW_INSTALL_WINDOW = 86400;

	abstract public function check() :?array;

	/**
	 * Whether the plugin was installed within the last day. A notice that only informs or offers holds off for
	 * that long, so that a new install opens on its setup guide and nothing else. A notice of something wrong
	 * never waits.
	 */
	protected function isNewInstall() :bool {
		$installedAt = self::con()->comps->opts_lookup->getInstalledAt();
		return $installedAt > 0 && ( Services::Request()->ts() - $installedAt ) < self::NEW_INSTALL_WINDOW;
	}
}
