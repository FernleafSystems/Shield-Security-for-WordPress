<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\StoreAction;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Hashes\{
	FileHashAlgorithm,
	NormalizeHashMap
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\Build\BuildHashesForAsset;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\Build\BuildHashesFromApi;

class Build extends BaseAction {

	/**
	 * @throws \Exception
	 */
	public function run( string $algorithm = 'md5' ) {
		FileHashAlgorithm::validate( $algorithm );
		$asset = $this->getAsset();
		$normaliser = new NormalizeHashMap();
		$hashes = [];
		try {
			$hashes = ( new BuildHashesFromApi() )->build( $asset, $algorithm );
		}
		catch ( \Exception $e ) {
		}

		$meta = $this->generateMeta( $algorithm );
		if ( empty( $hashes ) ) {
			$hashes = ( new BuildHashesForAsset() )
				->setHashAlgo( $algorithm )
				->build( $asset );
			$hashes = $normaliser->toScalarMapForAlgorithm( $hashes, $algorithm );
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
