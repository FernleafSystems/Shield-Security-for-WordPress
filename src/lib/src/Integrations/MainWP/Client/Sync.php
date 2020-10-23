<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Integrations\MainWP\Client;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class Sync {

	use PluginControllerConsumer;

	public function run() :array {
		$con = $this->getCon();
		$data = [
			'sync_at'    => Services::Request()->ts(),
			'version'    => $con->getVersion(),
			'has_update' => Services::WpPlugins()->isUpdateAvailable( $con->getPluginBaseFile() ),
		];
		return $data;
	}
}