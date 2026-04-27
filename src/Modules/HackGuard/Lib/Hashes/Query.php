<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Hashes;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Hashes\Exceptions\{
	AssetHashesNotFound,
	NonAssetFileException,
	UnrecognisedAssetFile
};
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\{
	WpPluginVo,
	WpThemeVo
};
use FernleafSystems\Wordpress\Services\Utilities\File\Compare\CompareHash;
use FernleafSystems\Wordpress\Services\Utilities\WpOrg\{
	Plugin,
	Theme
};

class Query {

	/**
	 * @return array{hashes:array<int, string>, trusted_source:bool, asset_type:string, asset_key:string}
	 * @throws AssetHashesNotFound
	 * @throws NonAssetFileException
	 * @throws UnrecognisedAssetFile
	 * @throws \Exception
	 */
	public function getHashDataForFile( string $path ) :array {

		$vo = $this->findAssetFromPath( $path );

		if ( $vo->asset_type === 'plugin' ) {
			$fragment = ( new Plugin\Files() )->getRelativeFilePathFromItsInstallDir( $path );
		}
		else {
			$fragment = ( new Theme\Files() )->getRelativeFilePathFromItsInstallDir( $path );
		}

		$hashSource = ( new Retrieve() )->byVOWithSource( $vo );
		$hash = $hashSource[ 'hashes' ][ $fragment ] ?? ( $hashSource[ 'hashes' ][ \strtolower( $fragment ) ] ?? null );
		if ( empty( $hash ) ) {
			throw new UnrecognisedAssetFile( sprintf( __( 'No hashes exist for file: %s', 'wp-simple-firewall' ), $path ) );
		}

		return [
			'hashes'         => \is_array( $hash ) ? $hash : [ $hash ],
			'trusted_source' => $hashSource[ 'trusted_source' ],
			'asset_type'     => (string)$vo->asset_type,
			'asset_key'      => (string)$vo->unique_id,
		];
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
	 * @return WpPluginVo|WpThemeVo
	 * @throws NonAssetFileException
	 */
	private function findAssetFromPath( string $path ) {
		$vo = ( new Plugin\Files() )->findPluginFromFile( $path );
		if ( empty( $vo ) ) {
			$vo = ( new Theme\Files() )->findThemeFromFile( $path );
		if ( empty( $vo ) ) {
				throw new NonAssetFileException( __( 'Not a plugin or theme file path.', 'wp-simple-firewall' ) );
			}
		}
		return $vo;
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
		$verified = false;
		$hashData = $this->getHashDataForFile( $fullPath );
		$compare = new CompareHash();
		foreach ( $hashData[ 'hashes' ] as $hash ) {
			if ( $compare->isEqualFile( $fullPath, $hash ) ) {
				$verified = true;
				break;
			}
		}

		return new HashVerificationResult(
			$verified,
			$verified && $hashData[ 'trusted_source' ],
			$hashData[ 'asset_type' ],
			$hashData[ 'asset_key' ]
		);
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
