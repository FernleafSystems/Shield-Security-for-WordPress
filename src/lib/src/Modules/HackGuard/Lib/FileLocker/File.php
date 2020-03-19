<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker;

use FernleafSystems\Utilities\Data\Adapter\StdClassAdapter;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class BaseFile
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker
 * @property string $dir
 * @property string $file
 * @property int    $max_levels
 */
class File {

	use StdClassAdapter;

	public function __construct( $sFilename, $sDir = ABSPATH ) {
		$this->file = $sFilename;
		$this->dir = wp_normalize_path( $sDir );
	}

	/**
	 * @return string|null
	 */
	public function getPathname() {
		try {
			return $this->findFile();
		}
		catch ( \Exception $oE ) {
			return null;
		}
	}

	/**
	 * @return string[]
	 */
	public function getPossiblePaths() {
		$aPossible = [];
		$nLimiter = 1;
		$sDir = realpath( $this->dir );
		do {
			if ( empty( $sDir ) ) {
				break;
			}
			$aPossible[] = path_join( $sDir, $this->file );
			$sDir = realpath( dirname( $this->dir, $nLimiter ) );
			$nLimiter++;
		} while ( $nLimiter <= $this->getMaxDirLevels() );

		return $aPossible;
	}

	/**
	 * @return string
	 * @throws \Exception
	 */
	protected function findFile() {
		$oFS = Services::WpFs();

		$sDir = $this->dir;
		$sFullPath = null;
		$nLimiter = 1;
		do {
			if ( empty( $sDir ) ) {
				break;
			}
			$sMaybePath = path_join( $sDir, $this->file );
			if ( $oFS->isFile( $sMaybePath ) ) {
				$sFullPath = wp_normalize_path( $sMaybePath );
				break;
			}
			$sDir = realpath( dirname( $sDir ) );
			$nLimiter++;
		} while ( $nLimiter < $this->getMaxDirLevels() );

		if ( empty( $sFullPath ) ) {
			throw new \Exception( sprintf( 'Could not find full file from dir "%s" and file "%s".',
				$this->dir, $this->file ) );
		}
		else {
			$this->dir = $sDir;
		}
		return $sFullPath;
	}

	/**
	 * @return int
	 */
	protected function getMaxDirLevels() {
		return (int)max( 1, empty( $this->max_levels ) ? 1 : $this->max_levels );
	}
}