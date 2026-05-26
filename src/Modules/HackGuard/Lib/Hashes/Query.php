<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Hashes;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Hashes\Exceptions\{
	AssetHashesNotFound,
	NonAssetFileException,
	UnrecognisedAssetFile
};

class Query {

	/**
	 * @return array{hashes:array<int, string>, trusted_source:bool, asset_type:string, asset_key:string, asset_version:string, relative_path:string}
	 * @throws AssetHashesNotFound
	 * @throws NonAssetFileException
	 * @throws UnrecognisedAssetFile
	 * @throws \Exception
	 */
	public function getHashDataForFile( string $path ) :array {
		return ( new AssetTrustResolver() )->getHashDataForFile( $path );
	}

	/**
	 * @throws AssetHashesNotFound
	 * @throws NonAssetFileException
	 * @throws UnrecognisedAssetFile
	 * @throws \Exception
	 */
	public function getHashesForFile( string $path ) :array {
		return $this->getHashDataForFile( $path )[ 'hashes' ];
	}

	/**
	 * @throws AssetHashesNotFound
	 * @throws NonAssetFileException
	 * @throws \Exception
	 */
	public function fileExistsInHash( string $path ) :bool {
		try {
			$exists = !empty( $this->getHashesForFile( $path ) );
		}
		catch ( UnrecognisedAssetFile $e ) {
			$exists = false;
		}
		return $exists;
	}

	/**
	 * @throws AssetHashesNotFound
	 * @throws NonAssetFileException
	 * @throws UnrecognisedAssetFile
	 * @throws \InvalidArgumentException
	 */
	public function verifyHashWithSource( string $fullPath ) :HashVerificationResult {
		return ( new AssetTrustResolver() )->verifyPath( $fullPath );
	}

	/**
	 * @throws AssetHashesNotFound
	 * @throws NonAssetFileException
	 * @throws UnrecognisedAssetFile
	 * @throws \InvalidArgumentException
	 */
	public function verifyHash( string $fullPath ) :bool {
		return $this->verifyHashWithSource( $fullPath )->verified;
	}
}
