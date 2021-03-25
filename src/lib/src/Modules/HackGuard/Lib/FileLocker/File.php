<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker;

use FernleafSystems\Utilities\Data\Adapter\DynProperties;
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

	use DynProperties;

	public function __construct( string $filename, $dir = ABSPATH ) {
		$this->file = $filename;
		$this->dir = wp_normalize_path( $dir );
	}

	/**
	 * @return string[]
	 */
	public function getExistingPossiblePaths() :array {
		return array_filter(
			$this->getPossiblePaths(),
			function ( $path ) {
				return !empty( $path ) && Services::WpFs()->isFile( $path );
			}
		);
	}

	/**
	 * @return string[]
	 */
	public function getPossiblePaths() :array {
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

	protected function getMaxDirLevels() :int {
		return (int)max( 1, (int)$this->max_levels );
	}
}