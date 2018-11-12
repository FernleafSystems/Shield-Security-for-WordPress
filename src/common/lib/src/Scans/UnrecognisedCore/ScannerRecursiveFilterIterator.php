<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\UnrecognisedCore;

use FernleafSystems\Wordpress\Services\Services;

/**
 * Class ScannerRecursiveFilterIterator
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\UnrecognisedCore
 */
class ScannerRecursiveFilterIterator extends \RecursiveFilterIterator {

	public function accept() {
		/** @var \SplFileInfo $oCurrent */
		$oCurrent = $this->current();

		$bRecurse = true; // I.e. consume the file.

		if ( in_array( $oCurrent->getFilename(), array( '.', '..' ) ) || $this->isWpCoreFile() ) {
			$bRecurse = false;
		}

		return $bRecurse;
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