<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\StoreAction;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\WpPluginVo;
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\WpThemeVo;

class BaseAction {

	use PluginControllerConsumer;

	/**
	 * @var WpPluginVo|WpThemeVo
	 */
	private $asset;

	/**
	 * @return WpPluginVo|WpThemeVo
	 */
	public function getAsset() {
		return $this->asset;
	}

	/**
	 * @param WpPluginVo|WpThemeVo $asset
	 * @return static
	 */
	public function setAsset( $asset ) {
		$this->asset = $asset;
		return $this;
	}

	/**
	 * @throws \Exception
	 */
	protected function getNewStore() :Snapshots\Store {
		return ( new Snapshots\Store( $this->getAsset(), true ) )
			->setWorkingDir( ( new Snapshots\HashesStorageDir() )->getTempDir() );
	}
}