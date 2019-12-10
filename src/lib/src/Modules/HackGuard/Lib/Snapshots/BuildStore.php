<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\Build;
use FernleafSystems\Wordpress\Services\Core\VOs\WpPluginVo;
use FernleafSystems\Wordpress\Services\Core\VOs\WpThemeVo;
use FernleafSystems\Wordpress\Services\Services;

class BuildStore {

	use ModConsumer;

	/**
	 * @var WpPluginVo|WpThemeVo
	 */
	private $oAsset;

	/**
	 * Store constructor.
	 * @param WpPluginVo|WpThemeVo $oAsset
	 */
	public function __construct( $oAsset ) {
		$this->oAsset = $oAsset;
	}

	/**
	 * @throws \Exception
	 */
	public function build() {

		try {
			$aHashes = ( new Build\BuildHashesFromApi() )->build( $this->oAsset );
		}
		catch ( \Exception $e ) {
		}

		$aMeta = $this->generateMeta();
		if ( empty( $aHashes ) ) {
			$aHashes = ( new Build\BuildHashesForAsset() )
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
			$oStore = ( new Store( $this->oAsset ) )
				->setStorePath( $oMod->getPtgSnapsBaseDir() );
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
		$aMeta[ 'name' ] = ( $this->oAsset instanceof WpPluginVo ) ?
			$this->oAsset->Name
			: $this->oAsset->wp_theme->get( 'Name' );
		$aMeta[ 'version' ] = ( $this->oAsset instanceof WpPluginVo ) ?
			$this->oAsset->Version
			: $this->oAsset->version;
		return $aMeta;
	}
}