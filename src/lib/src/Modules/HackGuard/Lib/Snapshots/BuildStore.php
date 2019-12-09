<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Helpers\BuildHashesFromApi;
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
		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();
		$oStore = ( new Store( $this->oAsset ) )->setStorePath( $oMod->getPtgSnapsBaseDir() );

		$aHashes = ( new BuildHashesFromApi() )->build( $this->oAsset );
		if ( !empty( $aHashes ) ) {
			$oStore->setSnapData( $aHashes );
			$oStore->setSnapMeta( $this->generateMeta() );
			$oStore->save();
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