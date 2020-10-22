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
		return array_filter(
			$this->getPossiblePaths(),
			function ( $sPath ) {
				return Services::WpFs()->isFile( $sPath );
			}
		);
	}

	/**
	 * @return string[]
	 */
	public function getPossiblePaths() {
		$paths = [];
		$dirCount = 0;
		$workingDir = realpath( $this->dir );
		do {
			if ( empty( $workingDir ) ) {
				break;
			}
			$paths[] = path_join( $workingDir, $this->file );

			$workingDir = dirname( $workingDir );
			$dirCount++;
		} while (
			$dirCount < $this->getMaxDirLevels()
			&& ( empty( $this->max_paths ) || count( $paths ) < $this->max_paths )
		);

		return $paths;
	}

	/**
	 * @return int
	 */
	protected function getMaxDirLevels() {
		return (int)max( 1, (int)$this->max_levels );
	}
}