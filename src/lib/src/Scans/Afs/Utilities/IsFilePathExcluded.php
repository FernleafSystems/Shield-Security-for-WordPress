<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Utilities;

class IsFilePathExcluded {

	public function check( string $path, ?array $exclusions = null ) :bool {
		$path = wp_normalize_path( $path );
		$filename = basename( $path );

		$excluded = false;
		foreach ( empty( $exclusions ) ? $this->excluded() : $exclusions as $exclusion ) {

			if ( strpos( $exclusion, '#' ) === 0 ) {
				$excluded = preg_match( $exclusion, $path ) > 0;
			}
			else {
				$exclusion = wp_normalize_path( $exclusion );
				if ( strpos( $exclusion, '/' ) === false ) { // filename only
					$excluded = $filename === $exclusion;
				}
				else {
					$excluded = strpos( $path, $exclusion ) !== false;
				}
			}

			if ( $excluded ) {
				break;
			}
		}
		return $excluded;
	}

	private function excluded() :array {
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