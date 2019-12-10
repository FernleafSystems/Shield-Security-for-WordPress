<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\Build;

use FernleafSystems\Wordpress\Services\Core\VOs;
use FernleafSystems\Wordpress\Services\Utilities\Integrations\WpHashes\Hashes;

/**
 * Class BuildHashesFromApi
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\Build
 */
class BuildHashesFromApi {

	/**
	 * All file keys are their normalised file paths, with the ABSPATH stripped from it.
	 * @param VOs\WpPluginVo|VOs\WpThemeVo $oAsset
	 * @return string[] - keys are file paths relative to ABSPATH
	 * @throws \Exception
	 */
	public function build( $oAsset ) {
		if ( !$oAsset->isWpOrg() ) {

			$bApiSupport = false;

			$aApiInfo = ( new Hashes\ApiInfo() )
				->setUseQueryCache( true )
				->getInfo();
			if ( is_array( $aApiInfo ) && !empty( $aApiInfo[ 'supported_premium' ] ) ) {
				if ( $oAsset instanceof VOs\WpPluginVo ) {
					$sSlug = $oAsset->slug;
					$sFile = $oAsset->file;
					$sName = $oAsset->Name;
					$aItems = $aApiInfo[ 'supported_premium' ][ 'plugins' ];
				}
				else {
					$sSlug = $oAsset->stylesheet;
					$sFile = $oAsset->stylesheet;
					$sName = $oAsset->wp_theme->get( 'Name' );
					$aItems = $aApiInfo[ 'supported_premium' ][ 'themes' ];
				}

				foreach ( $aItems as $aMaybeItem ) {

					if ( $aMaybeItem[ 'slug' ] == $sSlug
						 || $aMaybeItem[ 'name' ] == $sName || $aMaybeItem[ 'file' ] == $sFile ) {
						$bApiSupport = true;
						if ( $oAsset instanceof VOs\WpPluginVo && empty( $oAsset->slug ) ) {
							$oAsset->slug = $aMaybeItem[ 'slug' ];
						}
						break;
					}
				}
			}

			if ( !$bApiSupport ) {
				throw new \Exception( 'Not a WordPress.org asset.' );
			}
		}
		return $this->retrieveForAsset( $oAsset );
	}

	/**
	 * @param VOs\WpPluginVo|VOs\WpThemeVo $oAsset
	 * @return string[]|null
	 * @throws \Exception
	 */
	private function retrieveForAsset( $oAsset ) {

		if ( $oAsset instanceof VOs\WpPluginVo ) {
			$aHashes = ( new Hashes\Plugin() )
				->setUseQueryCache( true )
				->getHashes( $oAsset->slug, $oAsset->Version, 'md5' );
		}
		elseif ( $oAsset instanceof VOs\WpThemeVo ) {
			if ( $oAsset->is_child ) {
				throw new \Exception( 'Live hashes are not supported for child themes.' );
			}
			$aHashes = ( new Hashes\Theme() )
				->setUseQueryCache( true )
				->getHashes( $oAsset->stylesheet, $oAsset->version, 'md5' );
		}
		else {
			throw new \Exception( 'Not a supported asset type' );
		}

		return $aHashes;
	}
}