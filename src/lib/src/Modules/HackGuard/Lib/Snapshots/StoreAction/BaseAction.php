<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\StoreAction;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\WpPluginVo;
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\WpThemeVo;

class BaseAction extends Base {

	use ModConsumer;

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
		/** @var ModCon $mod */
		$mod = $this->getMod();
		return ( new Snapshots\Store( $this->getAsset() ) )
			->setWorkingDir( $this->getTempDir() );
	}
}