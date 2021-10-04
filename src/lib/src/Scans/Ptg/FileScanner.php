<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Ptg;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Hashes\{
	Exceptions,
	Query
};
use FernleafSystems\Wordpress\Services\Core\VOs\Assets;
use FernleafSystems\Wordpress\Services\Utilities\WpOrg\Plugin;
use FernleafSystems\Wordpress\Services\Utilities\WpOrg\Theme;

class FileScanner extends Shield\Scans\Base\Files\BaseFileScanner {

	/**
	 * @param string $fullPath - in this case it's relative to ABSPATH
	 * @return ResultItem|null
	 */
	public function scan( string $fullPath ) {
		$item = null;
		try {
			$item = $this->scanPath( $fullPath );
		}
		catch ( \Exception $e ) {
			error_log( $e->getMessage() );
		}
		return $item;
	}

	/**
	 * @param string $fullPath
	 * @return Assets\WpPluginVo|Assets\WpThemeVo
	 * @throws \Exception
	 */
	private function getAssetFromPath( string $fullPath ) {
		$asset = ( new Plugin\Files() )->findPluginFromFile( $fullPath );
		if ( empty( $asset ) ) {
			$asset = ( new Theme\Files() )->findThemeFromFile( $fullPath );
		}
		if ( empty( $asset ) ) {
			throw new \Exception( sprintf( 'Could not load asset for: %s', $fullPath ) );
		}
		return $asset;
	}

	/**
	 * @param string $fullPath
	 * @return ResultItem|null
	 * @throws Exceptions\AssetHashesNotFound
	 * @throws Exceptions\NoneAssetFileException
	 * @throws \Exception
	 */
	private function scanPath( string $fullPath ) {
		$item = null;
		try {
			$verified = ( new Query() )
				->setMod( $this->getMod() )
				->verifyHash( $fullPath );
			if ( !$verified ) {
				$item = $this->getNewItem( $this->getAssetFromPath( $fullPath ), $fullPath );
				$item->is_different = true;
			}
		}
		catch ( Exceptions\UnrecognisedAssetFile $naf ) {
			$item = $this->getNewItem( $this->getAssetFromPath( $fullPath ), $fullPath );
			$item->is_unrecognised = true;
		}

		return $item;
	}

	/**
	 * @param Assets\WpPluginVo|Assets\WpThemeVo $asset
	 * @param string                             $fullPath
	 * @return ResultItem
	 */
	private function getNewItem( $asset, string $fullPath ) :ResultItem {
		/** @var ResultItem $item */
		$item = $this->getScanController()->getNewResultItem();
		$item->path_full = $fullPath;
		$item->path_fragment = str_replace( wp_normalize_path( ABSPATH ), '', $fullPath );
		$item->is_unrecognised = false;
		$item->is_different = false;
		$item->is_missing = false;
		$item->context = ( $asset instanceof Assets\WpPluginVo ) ? 'plugins' : 'themes';
		$item->slug = ( $asset instanceof Assets\WpPluginVo ) ? $asset->file : $asset->stylesheet;
		return $item;
	}
}