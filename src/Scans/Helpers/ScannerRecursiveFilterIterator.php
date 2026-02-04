<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Helpers;

use FernleafSystems\Wordpress\Services\Services;

class ScannerRecursiveFilterIterator extends \RecursiveFilterIterator {

	/**
	 * @var string[]
	 */
	protected static $FileExts = [];

	/**
	 * @var bool
	 */
	protected static $IsFilterWpCoreFiles = false;

	public function accept() :bool {
		/** @var \SplFileInfo $file */
		$file = $this->current();

		$recurse = true; // I.e. consume the file.
		// i.e. exclude core files, hidden system dirs, and files that don't have extensions we're looking for
		if ( \in_array( $file->getFilename(), [ '.', '..' ] )
			 || $file->isFile() && (
				( self::$IsFilterWpCoreFiles && $this->isWpCoreFile() )
				|| ( !empty( self::$FileExts ) && !\in_array( \strtolower( $file->getExtension() ), self::$FileExts ) )
			)
		) {
			$recurse = false;
		}

		return $recurse;
	}

	public function setFileExts( array $types ) :self {
		self::$FileExts = \array_map( '\strtolower', $types );
		return $this;
	}

	public function setIsFilterOutWpCoreFiles( bool $filter ) :self {
		self::$IsFilterWpCoreFiles = $filter;
		return $this;
	}

	private function isWpCoreFile() :bool {
		/** @var \SplFileInfo $current */
		$current = $this->current();
		return Services::CoreFileHashes()->isCoreFile( $current->getPathname() );
	}
}