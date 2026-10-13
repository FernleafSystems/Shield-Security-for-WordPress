<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Scans;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Exceptions;
use FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\EarlyLoading\StubBuilder;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Must come after the WP Core file scan.
 */
class WpRootUnidentified extends BaseScan {

	/**
	 * @throws Exceptions\WpRootFileUnidentifiedException
	 */
	protected function runScan() :bool {
		if ( $this->inRootDir() && !$this->isEarlyLoaderStub() ) {
			throw new Exceptions\WpRootFileUnidentifiedException( $this->pathFull );
		}
		return false;
	}

	/**
	 * Shield's own early loader stub, only while its content is exactly what Shield generates.
	 */
	private function isEarlyLoaderStub() :bool {
		return \basename( $this->pathFull ) === StubBuilder::FILE_NAME
			   && self::con()->comps->early_loading->isGeneratedStub( $this->pathFull );
	}

	/**
	 * Uses the path relative to ABSPATH rather than rebuilding the absolute path, as a root ABSPATH ("//" on
	 * chrooted hosts) yields "//file.php" from the directory iterator but "/file.php" from path_join(). Paths
	 * outside ABSPATH stay absolute, so they always contain a slash.
	 */
	private function inRootDir() :bool {
		return Services::WpFs()->isAccessibleFile( $this->pathFull ) && !\str_contains( $this->pathFragment, '/' );
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