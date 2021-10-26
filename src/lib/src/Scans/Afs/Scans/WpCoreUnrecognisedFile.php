<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Scans;

use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Exceptions;

class WpCoreUnrecognisedFile extends BaseScan {

	/**
	 * @throws Exceptions\WpCoreFileUnrecognisedException
	 */
	public function scan() :bool {
		$valid = false;
		// TODO WP Content?
		if ( strpos( $this->pathFull, path_join( ABSPATH, WPINC ) ) === 0
			 || strpos( $this->pathFull, path_join( ABSPATH, 'wp-admin' ) ) === 0 ) {
			if ( !Services::CoreFileHashes()->isCoreFile( $this->pathFull ) && !$this->isExcluded( $this->pathFull ) ) {
				throw new Exceptions\WpCoreFileUnrecognisedException( $this->pathFull );
			}
			$valid = true;
		}
		return $valid;
	}

	private function isExcluded( string $fullPath ) :bool {
		$path = wp_normalize_path( $fullPath );
		$filename = basename( $path );

		$excluded = false;
		foreach ( $this->getExcludedFiles() as $exclusion ) {
			$exclusion = wp_normalize_path( $exclusion );
			if ( strpos( $exclusion, '/' ) === false ) { // filename only
				$excluded = $filename === $exclusion;
			}
			else {
				$excluded = strpos( $path, $exclusion ) !== false;
			}

			if ( $excluded ) {
				break;
			}
		}
		return $excluded;
	}

	private function getExcludedFiles() :array {
		return [
			'error_log',
			'php_error_log',
			'.htaccess',
			'.htpasswd',
			'.user.ini',
			'php.ini',
			'web.config',
			'php_mail.log',
			'mail.log',
			'wp-content/uploads/bb-plugin/cache/',
			'wp-content/uploads/cache/wpml/twig/',
		];
	}
}