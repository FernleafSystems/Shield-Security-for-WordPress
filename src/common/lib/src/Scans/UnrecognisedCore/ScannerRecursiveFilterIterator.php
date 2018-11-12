<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\UnrecognisedCore;

use FernleafSystems\Wordpress\Services\Services;

/**
 * Class ScannerRecursiveFilterIterator
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\UnrecognisedCore
 */
class ScannerRecursiveFilterIterator extends \RecursiveFilterIterator {

	/**
	 * @var string[]
	 */
	protected $aFileTypes;

	public function accept() {
		/** @var \SplFileInfo $oCurr */
		$oCurr = $this->current();

		$bRecurse = true; // I.e. consume the file.

		// i.e. exclude core files, hidden system dirs, and files that don't have extensions we're looking for
		if ( in_array( $oCurr->getFilename(), array( '.', '..' ) ) || $this->isWpCoreFile()
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
		return is_array( $this->aFileTypes ) ? $this->aFileTypes : array();
	}

	/**
	 * @return bool
	 */
	private function hasFileExts() {
		return ( count( $this->getFileExts() ) > 0 );
	}

	/**
	 * @param array $aTypes
	 * @return $this
	 */
	public function setFileExts( $aTypes ) {
		$this->aFileTypes = $aTypes;
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