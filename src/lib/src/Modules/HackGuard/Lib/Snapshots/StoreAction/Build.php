<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\StoreAction;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots;
use FernleafSystems\Wordpress\Services\Core\VOs\WpPluginVo;
use FernleafSystems\Wordpress\Services\Services;

class Build extends BaseAction {

	/**
	 * @throws \Exception
	 */
	public function run() {
		$oAsset = $this->getAsset();
		try {
			$aHashes = ( new Snapshots\Build\BuildHashesFromApi() )->build( $oAsset );
		}
		catch ( \Exception $oE ) {
		}

		$aMeta = $this->generateMeta();
		if ( empty( $aHashes ) ) {
			$aHashes = ( new Snapshots\Build\BuildHashesForAsset() )
				->setHashAlgo( 'md5' )
				->build( $oAsset );
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
				->setAsset( $oAsset )
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
		$oAsset = $this->getAsset();
		$aMeta = [
			'ts'           => Services::Request()->ts(),
			'snap_version' => $this->getCon()->getVersion(),
		];
		$aMeta[ 'unique_id' ] = ( $oAsset instanceof WpPluginVo ) ?
			$oAsset->file
			: $oAsset->stylesheet;
		$aMeta[ 'name' ] = ( $oAsset instanceof WpPluginVo ) ?
			$oAsset->Name
			: $oAsset->wp_theme->get( 'Name' );
		$aMeta[ 'version' ] = $oAsset->version;
		return $aMeta;
	}
}