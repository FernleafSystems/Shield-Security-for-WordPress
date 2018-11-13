<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Helpers;

use FernleafSystems\Wordpress\Services\Services;

/**
 * Class ScannerRecursiveFilterIterator
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Helpers
 */
class ScannerRecursiveFilterIterator extends \RecursiveFilterIterator {

	/**
	 * @var string[]
	 */
	protected $aFileExts;

	/**
	 * @var bool
	 */
	protected $bFilterWpCoreFiles;

	public function accept() {
		/** @var \SplFileInfo $oCurr */
		$oCurr = $this->current();

		$bRecurse = true; // I.e. consume the file.

		// i.e. exclude core files, hidden system dirs, and files that don't have extensions we're looking for
		if ( in_array( $oCurr->getFilename(), array( '.', '..' ) )
			 || ( $this->isFilterOutCoreFiles() && $this->isWpCoreFile() )
			 || ( $this->hasFileExts() && !in_array( $oCurr->getExtension(), $this->getFileExts() ) )
		) {
			$bRecurse = false;
		}

		return $bRecurse;
	}

	/**
	 * @return string[]
	 */
	private function getFileExts() {
		return is_array( $this->aFileExts ) ? $this->aFileExts : array();
	}

	/**
	 * @return bool
	 */
	private function hasFileExts() {
		return ( count( $this->getFileExts() ) > 0 );
	}

	/**
	 * @return bool
	 */
	protected function isFilterOutCoreFiles() {
		return isset( $this->bFilterWpCoreFiles ) ? (bool)$this->bFilterWpCoreFiles : false;
	}

	/**
	 * @param array $aTypes
	 * @return $this
	 */
	public function setFileExts( $aTypes ) {
		$this->aFileExts = is_array( $aTypes ) ? $aTypes : [];
		return $this;
	}

	/**
	 * @param bool $bFilter
	 * @return $this
	 */
	public function setIsFilterOutWpCoreFiles( $bFilter ) {
		$this->bFilterWpCoreFiles = $bFilter;
		return $this;
	}

	/**
	 * @return bool
	 */
	private function isWpCoreFile() {
		/** @var \SplFileInfo $oCurrent */
		$oCurrent = $this->current();
		return Services::CoreFileHashes()->isCoreFile( $oCurrent->getPathname() );
	}
}