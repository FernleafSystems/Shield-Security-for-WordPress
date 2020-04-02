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
 * @property int    $max_paths
 */
class File {

	use StdClassAdapter;

	public function __construct( $sFilename, $sDir = ABSPATH ) {
		$this->file = $sFilename;
		$this->dir = wp_normalize_path( $sDir );
	}

	/**
	 * @return string[]
	 */
	public function getExistingPossiblePaths() {
		$aPaths = array_filter(
			$this->getPossiblePaths(),
			function ( $sPath ) {
				return Services::WpFs()->isFile( $sPath );
			}
		);

		if ( (int)$this->max_paths > 0 ) {
			$aPaths = array_slice( $aPaths, 0, $this->max_paths );
		}
		return $aPaths;
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
			$sDir = realpath( dirname( $sDir ) );
			$nLimiter++;
		} while ( $nLimiter <= $this->getMaxDirLevels() );

		return $aPossible;
	}

	/**
	 * @return int
	 */
	protected function getMaxDirLevels() {
		return (int)max( 1, (int)$this->max_levels );
	}
}