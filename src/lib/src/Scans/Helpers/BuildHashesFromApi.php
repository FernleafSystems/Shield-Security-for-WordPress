<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Helpers;

use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\WpOrg\Plugin\Hashes;

/**
 * Class BuildHashesFromDir
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Ptg
 */
class BuildHashesFromApi {

	/**
	 * @var int
	 */
	protected $nDepth = 0;

	/**
	 * @var string[]
	 */
	protected $aFileExts = [];

	/**
	 * All file keys are their normalised file paths, with the ABSPATH stripped from it.
	 * @param string $sPluginFile
	 * @return string[]
	 */
	public function build( $sPluginFile ) {
		$oWpPlugins = Services::WpPlugins();
		$oPluginVo = $oWpPlugins->getPluginAsVo( $sPluginFile );

		$sInstallDir = $oWpPlugins->getInstallationDir( $sPluginFile );

		$aHashes = ( new Hashes() )
			->getHashes( $oPluginVo->slug, $oPluginVo->Version, 'md5' );

		$aSnaps = [];
		$oDP = Services::Data();
		$sPathTrim = wp_normalize_path( ABSPATH );
		foreach ( $aHashes as $sFile => $sHash ) {
			if ( in_array( $oDP->getExtension( $sFile ), $this->aFileExts )
				 && ( $this->nDepth == 0 || substr_count( $sFile, '/' ) < $this->nDepth ) ) {
				$aSnaps[ str_replace( $sPathTrim, '', wp_normalize_path( path_join( $sInstallDir, $sFile ) ) ) ] = $sHash;
			}
		}
		return $aSnaps;
	}

	/**
	 * @param int $nDepth
	 * @return $this
	 */
	public function setDepth( $nDepth ) {
		$this->nDepth = max( 0, (int)$nDepth );
		return $this;
	}

	/**
	 * @param string[] $aExts
	 * @return $this
	 */
	public function setFileExts( $aExts ) {
		$this->aFileExts = $aExts;
		return $this;
	}
}