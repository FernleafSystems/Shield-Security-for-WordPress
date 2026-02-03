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
				->setHashAlgo( $meta[ 'algo' ] )
				->build( $asset );
			$meta[ 'live_hashes' ] = false;
		}
		else {
			$meta[ 'live_hashes' ] = true;
		}

		if ( !empty( $hashes ) ) {
			( new CreateNew() )
				->setAsset( $asset )
				->run()
				->setSnapData( $hashes )
				->setSnapMeta( $meta )
				->save();
		}
	}

	private function generateMeta() :array {
		$asset = $this->getAsset();
		return [
			'ts'           => Services::Request()->ts(),
			'snap_version' => self::con()->cfg->version(),
			'cs_hashes_at' => 0,
			'unique_id'    => $asset->asset_type === 'plugin' ? $asset->file : $asset->stylesheet,
			'name'         => $asset->asset_type === 'plugin' ? $asset->Name : $asset->wp_theme->get( 'Name' ),
			'version'      => $asset->version,
			'algo'         => 'md5',
		];
	}
}