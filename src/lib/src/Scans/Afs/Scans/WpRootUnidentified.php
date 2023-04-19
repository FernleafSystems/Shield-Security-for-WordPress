<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Scans;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Exceptions;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\File\Paths;

/**
 * Must come after the WP Core file scan.
 */
class WpRootUnidentified extends BaseScan {

	/**
	 * @throws Exceptions\WpRootFileUnidentifiedException
	 */
	public function scan() :bool {
		// Is it in the WP root dir?
		if ( $this->inRootDir() && $this->isExtensionIncluded() && !$this->isExcluded() ) {
			throw new Exceptions\WpRootFileUnidentifiedException( $this->pathFull );
		}
		return false;
	}

	private function inRootDir() :bool {
		return Services::WpFs()->isAccessibleFile( $this->pathFull )
			   && $this->pathFull === wp_normalize_path( path_join( ABSPATH, basename( $this->pathFull ) ) );
	}

	private function isExtensionIncluded() :bool {
		$ext = Paths::Ext( $this->pathFull );
		return empty( $ext ) ||
			   preg_match( sprintf( '#^(%s)$#i', implode( '|', [
				   'ico',
				   'php',
				   'phtm',
				   'js',
			   ] ) ), $ext );
	}

	protected function getExcludes() :array {
		return [
			'wp-config.php',
			'cloner.php',
			'#widget\-.*\.php#i',
		];
	}
}