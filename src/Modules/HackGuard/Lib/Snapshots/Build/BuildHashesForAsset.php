<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\Build;

use FernleafSystems\Wordpress\Services\Core\VOs\Assets;
use FernleafSystems\Wordpress\Services\Services;

class BuildHashesForAsset {

	private string $hashAlgo = 'md5';

	/**
	 * All file keys are their normalised file paths, with the ABSPATH stripped from it.
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
		return empty( $this->hashAlgo ) ? 'md5' : $this->hashAlgo;
	}

	public function setHashAlgo( string $hashAlgo ) :self {
		$this->hashAlgo = $hashAlgo;
		return $this;
	}
}
