<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Ufc;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Helpers\StandardDirectoryIterator;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class Scanner
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Ufc
 */
class Scanner {

	/**
	 * @var array
	 */
	protected $aExclusions;

	/**
	 * @var array
	 */
	protected $aDirFileTypes;

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
				/**
				 * The filter handles the bulk of the file inclusions and exclusions
				 * We can set the types (extensions) of the files to include
				 * useful for the upload directory where we're only interested in JS and PHP
				 * The filter will also be responsible (in this case) for filtering out
				 * WP Core files from the collection of files to be assessed
				 */
				$oDirIt = StandardDirectoryIterator::create( $sDir, 0, $this->getFileTypesForDir( $sDir ), true );
			}
			catch ( \Exception $oE ) {
				continue;
			}

			foreach ( $oDirIt as $oFsItem ) {
				/** @var \SplFileInfo $oFsItem */
				$sFullPath = $oFsItem->getPathname();

				$oResultItem = new ResultItem();
				$oResultItem->path_full = wp_normalize_path( $sFullPath );
				$oResultItem->path_fragment = $oHashes->getFileFragment( $sFullPath );
				if ( !$this->isExcluded( $sFullPath ) ) {
					$oResultSet->addItem( $oResultItem );
				};
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

			if ( preg_match( '/^#(.+)#[a-z]*$/i', $sExclusion, $aMatches ) ) { // it's regex
				$bExcluded = @preg_match( stripslashes( $sExclusion ), $sFilePath );
			}
			else {
				$sExclusion = wp_normalize_path( $sExclusion );
				if ( strpos( $sExclusion, '/' ) === false ) { // filename only
					$bExcluded = ( $sFileName == $sExclusion );
				}
				else {
					$bExcluded = strpos( $sFilePath, $sExclusion );
				}
			}

			if ( $bExcluded ) {
				break;
			}
		}
		return (bool)$bExcluded;
	}

	/**
	 * @param string $sDir
	 * @return $this
	 */
	public function addScanDirector( $sDir ) {
		$aDirs = $this->getScanDirectories();
		$aDirs[] = $sDir;
		$this->aScanDirectories = $aDirs;
		return $this;
	}

	/**
	 * @return array
	 */
	protected function getDirFileTypes() {
		if ( !is_array( $this->aDirFileTypes ) ) {
			$this->aDirFileTypes = array();
		}
		return $this->aDirFileTypes;
	}

	/**
	 * @return array
	 */
	public function getExclusions() {
		return is_array( $this->aExclusions ) ? $this->aExclusions : array();
	}

	/**
	 * @param string $sDir
	 * @return array
	 */
	public function getFileTypesForDir( $sDir ) {
		$aEx = $this->getDirFileTypes();
		return isset( $aEx[ $sDir ] ) ? $aEx[ $sDir ] : array();
	}

	/**
	 * @return array
	 */
	public function getScanDirectories() {
		if ( empty( $this->aScanDirectories ) ) {
			$this->aScanDirectories = [
				path_join( ABSPATH, 'wp-admin' ),
				path_join( ABSPATH, 'wp-includes' )
			];
		}
		return $this->aScanDirectories;
	}

	/**
	 * @param string $sDir
	 * @param array  $aTypes
	 * @return $this
	 */
	public function addDirSpecificFileTypes( $sDir, $aTypes ) {
		$aEx = $this->getDirFileTypes();
		$aEx[ $sDir ] = $aTypes;
		$this->aDirFileTypes = $aEx;
		return $this;
	}

	/**
	 * @param array $aExclusions
	 * @return $this
	 */
	public function setExclusions( $aExclusions ) {
		$this->aExclusions = $aExclusions;
		return $this;
	}
}