<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Helpers;

class StandardDirectoryIterator {

	/**
	 * @param array $fileExts
	 * @param bool  $isExcludeCoreFiles
	 * @throws \Exception
	 */
	public static function create( string $dir, int $maxDepth = 0, $fileExts = [], $isExcludeCoreFiles = false ) :\RecursiveIteratorIterator {

		$dirIterator = new \RecursiveDirectoryIterator( $dir );
		$dirIterator->setFlags( \FilesystemIterator::SKIP_DOTS );

		$filter = new ScannerRecursiveFilterIterator( $dirIterator );
		$filter->setFileExts( \is_array( $fileExts ) ? $fileExts : [] )
			   ->setIsFilterOutWpCoreFiles( (bool)$isExcludeCoreFiles );
		$recursiveIterator = new \RecursiveIteratorIterator( $filter );
		$recursiveIterator->setMaxDepth( $maxDepth - 1 ); //since they start at zero.

		return $recursiveIterator;
	}
}