<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @property string $type
 * @property string $dir
 * @property string $file
 * @property int    $max_levels
 * @property int    $max_paths
 */
class File extends DynPropertiesClass {

	public function __construct( string $fileType, string $filename, $dir = ABSPATH ) {
		$this->type = $fileType;
		$this->file = $filename;
		$this->dir = wp_normalize_path( $dir );
	}

	public function __get( string $key ) {
		$value = parent::__get( $key );
		switch ( $key ) {
			case 'max_paths':
			case 'max_level':
				$value = (int)\max( 1, $value );
				break;
		}
		return $value;
	}

	/**
	 * @return string[]
	 */
	public function getExistingPossiblePaths() :array {
		return \array_filter(
			$this->getPossiblePaths(),
			function ( $path ) {
				return !empty( $path ) && Services::WpFs()->isAccessibleFile( $path );
			}
		);
	}

	/**
	 * @return string[]
	 */
	public function getPossiblePaths() :array {
		$paths = [];
		$dirCount = 0;
		$workingDir = \realpath( $this->dir );
		do {
			if ( empty( $workingDir ) ) {
				break;
			}
			$paths[] = path_join( $workingDir, $this->file );

			$workingDir = \dirname( $workingDir );
			$dirCount++;
		} while (
			$dirCount < $this->max_levels
			&& ( empty( $this->max_paths ) || \count( $paths ) <= $this->max_paths )
		);

		return $paths;
	}
}