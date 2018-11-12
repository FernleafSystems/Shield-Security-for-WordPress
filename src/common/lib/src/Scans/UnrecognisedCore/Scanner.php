<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\UnrecognisedCore;

use FernleafSystems\Wordpress\Services\Services;

/**
 * Class Scanner
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\UnrecognisedCore
 */
class Scanner {

	/**
	 * @var array
	 */
	protected $aExclusions;

	/**
	 * @var array
	 */
	protected $aScanDirectories;

	/**
	 * @return ResultsSet
	 */
	public function run() {
		$oResultSet = new ResultsSet();

		$oHashes = Services::CoreFileHashes();
		if ( !$oHashes->isReady() ) {
			return $oResultSet;
		}

		foreach ( $this->getScanDirectories() as $sDir ) {

			try {
				$oRecursiveIterator = new \RecursiveIteratorIterator(
					new ScannerRecursiveFilterIterator( new \RecursiveDirectoryIterator( $sDir ) )
				);
			}
			catch ( \Exception $oE ) {
			}

			foreach ( $oRecursiveIterator as $oFsItem ) {
				/** @var \SplFileInfo $oFsItem */
				$sFullPath = $oFsItem->getPathname();
				if ( !$oHashes->isCoreFile( $sFullPath ) ) {
					$oResultItem = new ResultItem();
					$oResultItem->path_full = wp_normalize_path( $sFullPath );
					$oResultItem->path_fragment = $oHashes->getFileFragment( $sFullPath );
					$oResultItem->is_excluded = $this->isExcluded( $sFullPath );
					$oResultSet->addItem( $oResultItem );
				}
			}
		}

		return $oResultSet;
	}

	/**
	 * @param string $sFullPath
	 * @return bool
	 */
	protected function isExcluded( $sFullPath ) {

		$sFilePath = wp_normalize_path( $sFullPath );
		$sFileName = basename( $sFilePath );

		$bExcluded = false;

		foreach ( $this->getExclusions() as $sExclusion ) {
			$sExclusion = wp_normalize_path( $sExclusion );

			if ( preg_match( '/^#(.+)#$/', $sExclusion, $aMatches ) ) { // it's regex
				$bExcluded = @preg_match( stripslashes( $sExclusion ), $sFilePath );
			}
			else if ( strpos( $sExclusion, '/' ) === false ) { // filename only
				$bExcluded = ( $sFileName == $sExclusion );
			}
			else {
				$bExcluded = strpos( $sFilePath, $sExclusion );
			}

			if ( $bExcluded ) {
				break;
			}
		}
		return $bExcluded;
	}

	/**
	 * @return array
	 */
	public function getExclusions() {
		return is_array( $this->aExclusions ) ? $this->aExclusions : array();
	}

	/**
	 * @return array
	 */
	public function getScanDirectories() {
		if ( empty( $this->aScanDirectories ) ) {
			$this->aScanDirectories = array();
		}
		return array_merge(
			[ path_join( ABSPATH, 'wp-admin' ), path_join( ABSPATH, 'wp-includes' ) ],
			$this->aScanDirectories
		);
	}

	/**
	 * @param array $aExclusions
	 * @return $this
	 */
	public function setExclusions( $aExclusions ) {
		$this->aExclusions = $aExclusions;
		return $this;
	}

	/**
	 * @param array $aScanDirectories
	 * @return $this
	 */
	public function setScanDirectories( $aScanDirectories ) {
		$this->aScanDirectories = $aScanDirectories;
		return $this;
	}
}