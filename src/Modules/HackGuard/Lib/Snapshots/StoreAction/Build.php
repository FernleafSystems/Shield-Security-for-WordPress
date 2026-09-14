<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\StoreAction;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Hashes\NormalizeHashMap;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\Build\BuildHashesForAsset;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\Build\BuildHashesFromApi;

class Build extends BaseAction {

	/**
	 * @throws \Exception
	 */
	public function run() {
		$asset = $this->getAsset();
		$normaliser = new NormalizeHashMap();
		$hashes = [];
		try {
			$hashes = $normaliser->toScalarMap(
				( new BuildHashesFromApi() )->build( $asset )
			);
		}
		catch ( \Exception $e ) {
		}

		$meta = $this->generateMeta();
		if ( empty( $hashes ) ) {
			$hashes = ( new BuildHashesForAsset() )
				->setHashAlgo( $meta[ 'algo' ] )
				->build( $asset );
			$hashes = $normaliser->toScalarMap( $hashes );
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
}
