<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Helpers\StandardDirectoryIterator;

/**
 * Class BuildFileMap
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal
 */
class BuildFileMap {

	/**
	 * @var string[]
	 */
	private $aWhitelistPaths;

	/**
	 * @return string[]
	 */
	public function build() {
		$aFiles = [];
		try {
			$oDirIt = StandardDirectoryIterator::create( ABSPATH, 0, [ 'php', 'php5' ], false );
			foreach ( $oDirIt as $oFsItem ) {
				$sFullPath = wp_normalize_path( $oFsItem->getPathname() );
				/** @var \SplFileInfo $oFsItem */
				if ( $this->isWhitelistedPath( $sFullPath ) || $oFsItem->getSize() == 0 ) {
					continue;
				}
				$aFiles[] = $sFullPath;
			}
		}
		catch ( \Exception $oE ) {
			error_log(
				sprintf( 'Shield file scanner attempted to read directory but there was error: "%s".', $oE->getMessage() )
			);
		}
		return $aFiles;
	}

	/**
	 * @return string[]
	 */
	public function getWhitelistedPaths() {
		return is_array( $this->aWhitelistPaths ) ? $this->aWhitelistPaths : [];
	}

	/**
	 * @param string $sThePath
	 * @return bool
	 */
	private function isWhitelistedPath( $sThePath ) {
		$bWhitelisted = false;
		foreach ( $this->getWhitelistedPaths() as $sWlPath ) {
			if ( stripos( $sThePath, $sWlPath ) === 0 ) {
				$bWhitelisted = true;
				break;
			}
		}
		return $bWhitelisted;
	}


	/**
	 * @param string[] $aSigs
	 * @return $this
	 */
	public function setWhitelistedPaths( $aSigs ) {
		$this->aWhitelistPaths = $aSigs;
		return $this;
	}
}