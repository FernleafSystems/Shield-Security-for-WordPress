<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Scans;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Exceptions;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Must come after the WP Core file scan.
 */
class WpRootUnidentified extends BaseScan {

	/**
	 * @throws Exceptions\WpRootFileUnidentifiedException
	 */
	protected function runScan() :bool {
		if ( $this->inRootDir() ) {
			throw new Exceptions\WpRootFileUnidentifiedException( $this->pathFull );
		}
		return false;
	}

	private function inRootDir() :bool {
		return Services::WpFs()->isAccessibleFile( $this->pathFull )
			   && $this->pathFull === wp_normalize_path( path_join( ABSPATH, \basename( $this->pathFull ) ) );
	}

	// TODO: empty file extension support
	protected function getSupportedFileExtensions() :array {
		return [
			'ico',
			'js',
			'mo',
			'php',
			'php5',
			'php7',
			'phtm',
		];
	}

	protected function getPathExcludes() :array {
		return [
			'wp-config.php',
			'cloner.php',
			'#widget\-.*\.php#i',
		];
	}
}