<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\Build;

use FernleafSystems\Wordpress\Services\Core\VOs\Assets\{
	WpPluginVo,
	WpThemeVo
};
use FernleafSystems\Wordpress\Services\Utilities\Integrations\WpHashes\Hashes;

class BuildHashesFromApi {

	/**
	 * All file keys are their normalised file paths, with the ABSPATH stripped from it.
	 * @param WpPluginVo|WpThemeVo $asset
	 * @return string[] - keys are file paths relative to ABSPATH
	 * @throws \Exception
	 */
	public function build( $asset ) {
		if ( !$asset->isWpOrg() ) {

			$apiSupport = false;

			$apiInfo = ( new Hashes\ApiInfo() )
				->setUseQueryCache( true )
				->getInfo();
			if ( \is_array( $apiInfo ) && !empty( $apiInfo[ 'supported_premium' ] ) ) {
				if ( $asset->asset_type === 'plugin' ) {
					$slug = $asset->slug;
					$file = $asset->file;
					$name = $asset->Name;
					$items = $apiInfo[ 'supported_premium' ][ 'plugins' ];
				}
				else {
					$slug = $asset->stylesheet;
					$file = $asset->stylesheet;
					$name = $asset->wp_theme->get( 'Name' );
					$items = $apiInfo[ 'supported_premium' ][ 'themes' ];
				}

				foreach ( $items as $maybeItem ) {

					if ( $maybeItem[ 'slug' ] == $slug || $maybeItem[ 'name' ] == $name || $maybeItem[ 'file' ] == $file ) {
						$apiSupport = true;
						if ( $asset->asset_type === 'plugin' && empty( $asset->slug ) ) {
							$asset->slug = $maybeItem[ 'slug' ];
						}
						break;
					}
				}
			}

			if ( !$apiSupport ) {
				throw new \Exception( 'Not a WordPress.org asset.' );
			}
		}
		return $this->retrieveForAsset( $asset );
	}

	/**
	 * @param WpPluginVo|WpThemeVo $asset
	 * @return string[]|null
	 * @throws \Exception
	 */
	private function retrieveForAsset( $asset ) :?array {

		if ( $asset->asset_type === 'plugin' ) {
			$hashes = ( new Hashes\Plugin() )
				->setUseQueryCache( true )
				->getHashes( $asset->slug, $asset->Version, 'md5' );
		}
		elseif ( $asset->asset_type === 'theme' ) {
			if ( $asset->is_child ) {
				throw new \Exception( 'Live hashes are not supported for child themes.' );
			}
			$hashes = ( new Hashes\Theme() )
				->setUseQueryCache( true )
				->getHashes( $asset->stylesheet, $asset->version, 'md5' );
		}
		else {
			throw new \Exception( 'Not a supported asset type' );
		}

		return \is_array( $hashes ) ? $hashes : null;
	}
}