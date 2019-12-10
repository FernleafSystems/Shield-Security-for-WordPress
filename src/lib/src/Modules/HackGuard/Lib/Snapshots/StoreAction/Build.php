<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\StoreAction;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\Store;
use FernleafSystems\Wordpress\Services\Core\VOs\WpPluginVo;
use FernleafSystems\Wordpress\Services\Core\VOs\WpThemeVo;
use FernleafSystems\Wordpress\Services\Services;

class Build extends Base {

	/**
	 * @var WpPluginVo|WpThemeVo
	 */
	private $oAsset;

	/**
	 * @throws \Exception
	 */
	public function build() {

		try {
			$aHashes = ( new Snapshots\Build\BuildHashesFromApi() )->build( $this->oAsset );
		}
		catch ( \Exception $oE ) {
		}

		$aMeta = $this->generateMeta();
		if ( empty( $aHashes ) ) {
			$aHashes = ( new Snapshots\Build\BuildHashesForAsset() )
				->setHashAlgo( 'md5' )
				->build( $this->oAsset );
			$aMeta[ 'live_hashes' ] = false;
		}
		else {
			$aMeta[ 'live_hashes' ] = true;
		}

		if ( !empty( $aHashes ) ) {
			/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
			$oMod = $this->getMod();
			$oStore = ( new CreateNew() )
				->setMod( $oMod )
				->setAsset( $this->oAsset )
				->run();
			$oStore->setSnapData( $aHashes )
				   ->setSnapMeta( $aMeta )
				   ->save();
		}
	}

	/**
	 * @return array
	 */
	private function generateMeta() {
		$aMeta = [
			'ts'           => Services::Request()->ts(),
			'snap_version' => $this->getCon()->getVersion(),
		];
		$aMeta[ 'unique_id' ] = ( $this->oAsset instanceof WpPluginVo ) ?
			$this->oAsset->file
			: $this->oAsset->stylesheet;
		$aMeta[ 'name' ] = ( $this->oAsset instanceof WpPluginVo ) ?
			$this->oAsset->Name
			: $this->oAsset->wp_theme->get( 'Name' );
		$aMeta[ 'version' ] = ( $this->oAsset instanceof WpPluginVo ) ?
			$this->oAsset->Version
			: $this->oAsset->version;
		return $aMeta;
	}
}