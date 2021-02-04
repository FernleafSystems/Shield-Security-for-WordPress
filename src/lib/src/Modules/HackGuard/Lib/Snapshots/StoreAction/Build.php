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
		$asset = $this->getAsset();
		try {
			$hashes = ( new Snapshots\Build\BuildHashesFromApi() )->build( $asset );
		}
		catch ( \Exception $e ) {
		}

		$aMeta = $this->generateMeta();
		if ( empty( $hashes ) ) {
			$hashes = ( new Snapshots\Build\BuildHashesForAsset() )
				->setHashAlgo( 'md5' )
				->build( $asset );
			$aMeta[ 'live_hashes' ] = false;
		}
		else {
			$aMeta[ 'live_hashes' ] = true;
		}

		if ( !empty( $hashes ) ) {
			$oStore = ( new CreateNew() )
				->setMod( $this->getMod() )
				->setAsset( $asset )
				->run();
			$oStore->setSnapData( $hashes )
				   ->setSnapMeta( $aMeta )
				   ->save();
		}
	}

	/**
	 * @return array
	 */
	private function generateMeta() {
		$asset = $this->getAsset();
		$meta = [
			'ts'           => Services::Request()->ts(),
			'snap_version' => $this->getCon()->getVersion(),
		];
		$meta[ 'unique_id' ] = ( $asset instanceof WpPluginVo ) ?
			$asset->file
			: $asset->stylesheet;
		$meta[ 'name' ] = ( $asset instanceof WpPluginVo ) ?
			$asset->Name
			: $asset->wp_theme->get( 'Name' );
		$meta[ 'version' ] = $asset->version;
		return $meta;
	}
}