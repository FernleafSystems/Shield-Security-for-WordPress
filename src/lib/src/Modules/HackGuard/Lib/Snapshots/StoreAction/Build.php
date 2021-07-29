<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\StoreAction;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots;
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

		$meta = $this->generateMeta();
		if ( empty( $hashes ) ) {
			$hashes = ( new Snapshots\Build\BuildHashesForAsset() )
				->setHashAlgo( 'md5' )
				->build( $asset );
			$meta[ 'live_hashes' ] = false;
		}
		else {
			$meta[ 'live_hashes' ] = true;
		}

		if ( !empty( $hashes ) ) {
			$store = ( new CreateNew() )
				->setMod( $this->getMod() )
				->setAsset( $asset )
				->run();
			$store->setSnapData( $hashes )
				  ->setSnapMeta( $meta )
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
		$meta[ 'unique_id' ] = $asset->asset_type === 'plugin' ?
			$asset->file
			: $asset->stylesheet;
		$meta[ 'name' ] = $asset->asset_type === 'plugin' ?
			$asset->Name
			: $asset->wp_theme->get( 'Name' );
		$meta[ 'version' ] = $asset->version;
		return $meta;
	}
}