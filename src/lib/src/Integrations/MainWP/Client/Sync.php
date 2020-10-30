<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Integrations\MainWP\Client;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Options;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class Sync {

	use PluginControllerConsumer;

	public function run() :array {
		$con = $this->getCon();
		/** @var Options $pluginOpts */
		$pluginOpts = $con->getModule_Plugin()->getOptions();
		return [
			'meta'    => $this->buildMetaData(),
			'modules' => ( $con->isPremiumActive() && $pluginOpts->isEnabledMainWP() ) ? $this->buildModulesData() : [],
		];
	}

	/**
	 * @return mixed[]
	 */
	private function buildMetaData() :array {
		$con = $this->getCon();
		/** @var Options $pluginOpts */
		$pluginOpts = $con->getModule_Plugin()->getOptions();
		return [
			'is_pro'       => $con->isPremiumActive(),
			'is_mainwp_on' => $con->isPremiumActive() && $pluginOpts->isEnabledMainWP(),
			'installed_at' => $con->getModule_Plugin()->getInstallDate(),
			'sync_at'      => Services::Request()->ts(),
			'version'      => $con->getVersion(),
			'has_update'   => Services::WpPlugins()->isUpdateAvailable( $con->getPluginBaseFile() ),
		];
	}

	/**
	 * @return array[]
	 */
	private function buildModulesData() :array {
		$con = $this->getCon();
		$data = [];
		foreach ( $con->modules as $mod ) {
			$data[ $mod->getSlug() ] = $mod->getMainWpData();
		}
		return $data;
	}
}