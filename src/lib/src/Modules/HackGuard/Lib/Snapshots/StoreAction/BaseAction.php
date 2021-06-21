<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\StoreAction;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\WpPluginVo;
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\WpThemeVo;

class BaseAction {

	use ModConsumer;

	/**
	 * @var WpPluginVo|WpThemeVo
	 */
	private $oAsset;

	public function __construct() {
	}

	/**
	 * @return WpPluginVo|WpThemeVo
	 */
	public function getAsset() {
		return $this->oAsset;
	}

	/**
	 * @param WpPluginVo|WpThemeVo $oAsset
	 * @return static
	 */
	public function setAsset( $oAsset ) {
		$this->oAsset = $oAsset;
		return $this;
	}

	/**
	 * @return Snapshots\Store
	 * @throws \Exception
	 */
	protected function getNewStore() {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		return ( new Snapshots\Store( $this->getAsset() ) )
			->setWorkingDir( $mod->getPtgSnapsBaseDir() );
	}
}