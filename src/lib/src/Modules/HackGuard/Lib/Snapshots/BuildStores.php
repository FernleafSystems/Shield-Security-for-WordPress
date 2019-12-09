<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Core\VOs\WpPluginVo;
use FernleafSystems\Wordpress\Services\Core\VOs\WpThemeVo;

class BuildStores {

	use ModConsumer;

	/**
	 * @var WpPluginVo|WpThemeVo
	 */
	private $oAsset;

	/**
	 * @throws \Exception
	 */
	public function build() {
		foreach ( ( new FindAssetsToSnap() )->setMod( $this->getMod() )->run() as $oAsset ) {
			( new BuildStore( $oAsset ) )
				->setMod( $this->getMod() )
				->build();
		}
	}
}