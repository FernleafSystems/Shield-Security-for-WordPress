<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Helpers;

/**
 * Class Scanner
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal
 */
class Scanner {

	/**
	 * @return ResultsSet
	 */
	public function run() {

		$oResultSet = new ResultsSet();

		try {
			/**
			 * The filter handles the bulk of the file inclusions and exclusions
			 * We can set the types (extensions) of the files to include
			 * useful for the upload directory where we're only interested in JS and PHP
			 * The filter will also be responsible (in this case) for filtering out
			 * WP Core files from the collection of files to be assessed
			 */
			$oDirIt = Helpers\StandardDirectoryIterator::create( ABSPATH, 0, [], false );

			foreach ( $oDirIt as $oFsItem ) {
				/** @var \SplFileInfo $oFsItem */
				$oResultItem = new ResultItem();
				$oResultItem->path_full = wp_normalize_path( $oFsItem->getPathname() );
				$oResultItem->path_fragment = str_replace( wp_normalize_path( ABSPATH ), '', $oResultItem->path_full );
				$oResultSet->addItem( $oResultItem );
			}
		}
		catch ( \Exception $oE ) {
			error_log(
				sprintf( 'Shield file scanner attempted to read directory but there was error: "%s".', $oE->getMessage() )
			);
		}

		return $oResultSet;
	}
}