<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Helpers;

class StandardDirectoryIterator {

	/**
	 * @throws \Exception
	 */
	public static function create( string $dir, int $maxDepth = 0, array $fileExts = [], bool $isExcludeCoreFiles = false ) :\RecursiveIteratorIterator {

		$dirIterator = new \RecursiveDirectoryIterator( $dir );
		$dirIterator->setFlags( \FilesystemIterator::SKIP_DOTS );

		$filter = new ScannerRecursiveFilterIterator( $dirIterator );
		$filter->setFileExts( $fileExts )
			   ->setIsFilterOutWpCoreFiles( $isExcludeCoreFiles );
		$recursiveIterator = new \RecursiveIteratorIterator( $filter );
		$recursiveIterator->setMaxDepth( $maxDepth - 1 ); //since they start at zero.

		return $recursiveIterator;
	}
}