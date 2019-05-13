<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Helpers;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\WpOrg\Plugins;

/**
 * Class Scanner
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal
 */
class Scanner {

	/**
	 * @var string[]
	 */
	private $aMalSigs;

	/**
	 * @return ResultsSet
	 */
	public function run() {
		$oFs = Services::WpFs();
		$oResultSet = new ResultsSet();

		try {
			/**
			 * The filter handles the bulk of the file inclusions and exclusions
			 * We can set the types (extensions) of the files to include
			 * useful for the upload directory where we're only interested in JS and PHP
			 * The filter will also be responsible (in this case) for filtering out
			 * WP Core files from the collection of files to be assessed
			 */
			$oDirIt = Helpers\StandardDirectoryIterator::create( ABSPATH, 0, [ 'php', 'php5', 'js' ], false );

			$aSigs = $this->getMalSigs();
			foreach ( $oDirIt as $oFsItem ) {
				/** @var \SplFileInfo $oFsItem */
				$sFullPath = wp_normalize_path( $oFsItem->getPathname() );

				$sContent = $oFs->getFileContent( $sFullPath );
				if ( !empty( $sContent ) ) {
					foreach ( $aSigs as $sSig ) {
						if ( strpos( $sContent, $sSig ) !== false ) {

							if ( $this->canExcludeFile( $sFullPath ) ) {
								continue;
							}

							$oResultItem = new ResultItem();
							$oResultItem->path_full = wp_normalize_path( $sFullPath );
							$oResultItem->path_fragment = str_replace( wp_normalize_path( ABSPATH ), '', $oResultItem->path_full );
							$oResultItem->is_mal = true;
							$oResultItem->mal_sig = base64_encode( $sSig );
							$oResultSet->addItem( $oResultItem );
							break;
						}
					}
				}
			}
		}
		catch ( \Exception $oE ) {
			error_log(
				sprintf( 'Shield file scanner attempted to read directory but there was error: "%s".', $oE->getMessage() )
			);
		}

		return $oResultSet;
	}

	/**
	 * @param string $sFullPath - normalized
	 * @return bool
	 */
	private function canExcludeFile( $sFullPath ) {
		return $this->isValidCoreFile( $sFullPath ) || $this->isValidPluginFile( $sFullPath );
	}

	/**
	 * @param string $sFullPath - normalized
	 * @return bool
	 */
	private function isValidPluginFile( $sFullPath ) {
		$bCanExclude = false;

		$sPluginsDir = wp_normalize_path( WP_PLUGIN_DIR );
		$oWpPlugins = Services::WpPlugins();
		$oWpFs = Services::WpFs();

		if ( strpos( $sFullPath, $sPluginsDir ) === 0 ) {

			$sFragment = ltrim( str_replace( $sPluginsDir, '', $sFullPath ), '/' );
			$aParts = explode( '/', $sFragment );
			$sDir = array_shift( $aParts );
			$sRemainder = implode( '/', $aParts );

			foreach ( $oWpPlugins->getInstalledPluginFiles() as $sPluginFile ) {
				if ( $sDir == dirname( $sPluginFile ) ) {
					$oThePlugin = $oWpPlugins->getPluginAsVo( $sPluginFile );
					try {
						$sTmpFile = ( new Plugins() )
							->setWorkingSlug( $oThePlugin->slug )
							->fileFromVersion( $oThePlugin->Version, $sRemainder );
						if ( $oWpFs->exists( $sTmpFile ) && md5_file( $sTmpFile ) === md5_file( $sFullPath ) ) {
							$bCanExclude = true;
						}
						$oWpFs->deleteFile( $sTmpFile );
					}
					catch ( \Exception $oE ) {
					}
					break;
				}
			}
		}

		return $bCanExclude;
	}

	/**
	 * @param string $sFullPath
	 * @return bool
	 */
	private function isValidCoreFile( $sFullPath ) {
		$oCoreHashes = Services::CoreFileHashes();
		$sCoreHash = $oCoreHashes->getFileHash( $sFullPath );
		return ( !empty( $sCoreHash ) && $sCoreHash === md5_file( $sFullPath ) );
	}

	/**
	 * @return string[]
	 */
	public function getMalSigs() {
		return $this->aMalSigs;
	}

	/**
	 * @param string[] $sFilePathMalSigs
	 * @return $this
	 */
	public function setMalSigs( $sFilePathMalSigs ) {
		$this->aMalSigs = $sFilePathMalSigs;
		return $this;
	}
}