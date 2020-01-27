<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\StoreAction;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\FindAssetsToSnap;
use FernleafSystems\Wordpress\Services\Core\VOs;

/**
 * Class BuildAll
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\StoreAction
 * @deprecated 8.5.2
 */
class BuildAll extends BaseBulk {

	public function build() {
		foreach ( $this->getAssetsThatNeedBuilt() as $oAsset ) {
			try {
				( new Build() )
					->setMod( $this->getMod() )
					->setAsset( $oAsset )
					->run();
			}
			catch ( \Exception $oE ) {
			}
		}
	}

	/**
	 * Only those that don't have a meta file or the versions are different
	 * @return VOs\WpPluginVo[]|VOs\WpThemeVo[]
	 */
	private function getAssetsThatNeedBuilt() {
		return array_filter(
			( new FindAssetsToSnap() )
				->setMod( $this->getMod() )
				->run(),
			function ( $oAsset ) {
				/** @var VOs\WpPluginVo|VOs\WpThemeVo $oAsset */
				try {
					$aMeta = ( new Load() )
						->setMod( $this->getMod() )
						->setAsset( $oAsset )
						->run()
						->getSnapMeta();
				}
				catch ( \Exception $oE ) {
					$aMeta = null;
				}
				return ( empty( $aMeta ) || $oAsset->version !== $aMeta[ 'version' ] );
			}
		);
	}
}