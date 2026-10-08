<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\Build;

use FernleafSystems\Wordpress\Services\Core\VOs\Assets;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Hashes\FileHashAlgorithm;

class BuildHashesForAsset {

	private string $hashAlgo = 'md5';

	/**
	 * Keys are relative to the asset directory, or the filename for a single-file plugin.
	 * @param Assets\WpPluginVo|Assets\WpThemeVo $asset
	 * @return string[]
	 */
	public function build( $asset ) :array {
		if ( $asset instanceof Assets\WpPluginVo && \dirname( $asset->file ) === '.' ) {
			$path = \path_join( WP_PLUGIN_DIR, $asset->file );
			if ( !Services::WpFs()->isAccessibleFile( $path ) ) {
				return [];
			}

			$hash = \hash_file( $this->getHashAlgo(), $path );
			return $hash === false ? [] : [ \strtolower( $asset->file ) => $hash ];
		}

		return ( new BuildHashesFromDir() )
			->setHashAlgo( $this->getHashAlgo() )
			->setDepth( 0 )
			->setFileExts( [] )
			->build( $asset->getInstallDir() );
	}

	public function getHashAlgo() :string {
		return $this->hashAlgo;
	}

	public function setHashAlgo( string $hashAlgo ) :self {
		$this->hashAlgo = FileHashAlgorithm::validate( $hashAlgo );
		return $this;
	}
}
