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
	 * @throws AssetHashesNotFound
	 * @throws NonAssetFileException
	 * @throws UnrecognisedAssetFile
	 * @throws \Exception
	 */
	public function getHashesForFile( string $path ) :array {

		$vo = $this->findAssetFromPath( $path );

		if ( $vo->asset_type === 'plugin' ) {
			$fragment = ( new Plugin\Files() )->getRelativeFilePathFromItsInstallDir( $path );
		}
		else {
			$fragment = ( new Theme\Files() )->getRelativeFilePathFromItsInstallDir( $path );
		}

		$assetHashes = ( new Retrieve() )->byVO( $vo );
		$hash = $assetHashes[ $fragment ] ?? ( $assetHashes[ \strtolower( $fragment ) ] ?? null );
		if ( empty( $hash ) ) {
			throw new UnrecognisedAssetFile( sprintf( 'No hashes exist for file: %s', $path ) );
		}

		return \is_array( $hash ) ? $hash : [ $hash ];
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
				throw new NonAssetFileException( 'Not a plugin or theme file path' );
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
	public function verifyHash( string $fullPath ) :bool {
		$verified = false;
		$compare = new CompareHash();
		foreach ( $this->getHashesForFile( $fullPath ) as $hash ) {
			if ( $compare->isEqualFile( $fullPath, $hash ) ) {
				$verified = true;
				break;
			}
		}
		return $verified;
	}
}