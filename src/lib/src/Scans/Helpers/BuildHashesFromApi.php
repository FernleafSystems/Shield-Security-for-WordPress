<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Helpers;

use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Integrations\WpHashes\Hashes;

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
	 * @return string[] - keys are file paths relative to ABSPATH
	 * @throws \Exception
	 */
	public function build( $sPluginFile ) {
		$oWpPlugins = Services::WpPlugins();
		$oPluginVo = $oWpPlugins->getPluginAsVo( $sPluginFile );

		$sInstallDir = $oWpPlugins->getInstallationDir( $sPluginFile );

		$aHashes = ( new Hashes\Plugin() )
			->getHashes( $oPluginVo->slug, $oPluginVo->Version, 'md5' );
		if ( empty( $aHashes ) ) {
			throw new \Exception( 'Could not retrieve live hashes.' );
		}

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