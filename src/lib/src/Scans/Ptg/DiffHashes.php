<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Ptg;

/**
 * Class DiffHashes
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Ptg
 */
class DiffHashes {

	/**
	 * @param string[] $aExistingHashes
	 * @param string[] $aNewHashes
	 * @return ResultsSet
	 */
	public function diff( $aExistingHashes, $aNewHashes ) {
		$oRes = new ResultsSet();

		// find new hashes (keys only in aNew)
		foreach ( array_diff_key( $aNewHashes, $aExistingHashes ) as $sFile => $sHash ) {
			$oItem = $this->getNewItem( $sFile );
			$oItem->is_unrecognised = true;
			$oRes->addItem( $oItem );
		}

		// find missing hashes (keys only in aExisting)
		foreach ( array_diff_key( $aExistingHashes, $aNewHashes ) as $sFile => $sHash ) {
			$oItem = $this->getNewItem( $sFile );
			$oItem->is_missing = true;
			$oRes->addItem( $oItem );
		}

		// find common files where hashes are different.
		foreach ( array_intersect_key( $aExistingHashes, $aNewHashes ) as $sFile => $sHash ) {
			if ( $sHash != $aNewHashes[ $sFile ] ) {
				$oItem = $this->getNewItem( $sFile );
				$oItem->is_different = true;
				$oRes->addItem( $oItem );
			}
		}

		return $oRes;
	}

	/**
	 * @param string $sFile
	 * @return ResultItem
	 */
	private function getNewItem( $sFile ) {
		$oItem = new ResultItem();
		// Add back the ABSPATH that was stripped previously when the file was originally hashed.
		$oItem->path_full = wp_normalize_path( path_join( ABSPATH, $sFile ) );
		$oItem->path_fragment = wp_normalize_path( $sFile ); // will eventually be overwritten
		$oItem->is_unrecognised = false;
		$oItem->is_different = false;
		$oItem->is_missing = false;
		return $oItem;
	}
}