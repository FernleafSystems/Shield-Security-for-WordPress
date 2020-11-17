<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Client\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Options;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class Sync {

	use ModConsumer;

	public function run() :array {
		$con = $this->getCon();
		/** @var Options $intOpts */
		$intOpts = $con->getModule_Integrations()->getOptions();
		return [
			'meta'    => $this->buildMetaData(),
			'modules' => ( $con->isPremiumActive() && $intOpts->isEnabledMainWP() ) ? $this->buildModulesData() : [],
		];
	}

	/**
	 * @return mixed[]
	 */
	private function buildMetaData() :array {
		$con = $this->getCon();
		/** @var Options $intOpts */
		$intOpts = $con->getModule_Integrations()->getOptions();
		return [
			'is_pro'       => $con->isPremiumActive(),
			'is_mainwp_on' => $con->isPremiumActive() && $intOpts->isEnabledMainWP(),
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