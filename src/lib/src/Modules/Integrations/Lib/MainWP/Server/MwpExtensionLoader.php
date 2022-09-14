<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Server;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Server\UI\ExtensionSettingsPage;

use function FernleafSystems\Wordpress\Plugin\Shield\Functions\get_plugin;

class MwpExtensionLoader {

	/**
	 * @throws \Exception
	 */
	public function run() {
		( new ExtensionSettingsPage() )
			->setMod( get_plugin()->getController()->getModule_Integrations() )
			->render();
	}
}