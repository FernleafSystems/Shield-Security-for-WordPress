<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\StoreAction;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\FindAssetsToSnap;
use FernleafSystems\Wordpress\Services\Core\VOs;
use FernleafSystems\Wordpress\Services\Services;

class ScheduleBuildAll extends BaseBulk {

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

	public function hookBuild() {
		if ( wp_next_scheduled( $this->getCronHook() ) !== false ) {
			add_action( $this->getCronHook(), [ $this, 'build' ] );
		}
	}

	public function schedule() {
		$sHook = $this->getCronHook();
		if ( wp_next_scheduled( $sHook ) === false && count( $this->getAssetsThatNeedBuilt() ) > 0 ) {
			wp_schedule_single_event( Services::Request()->ts() + 30, $sHook );
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

	/**
	 * @return string
	 */
	private function getCronHook() {
		return $this->getCon()->prefix( 'ptg_build_snapshots' );
	}
}