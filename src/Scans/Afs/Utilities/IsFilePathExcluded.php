<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Utilities;

class IsFilePathExcluded {

	public function check( string $path, array $exclusions = [] ) :bool {
		$excluded = false;
		if ( !empty( $exclusions ) ) {
			$path = wp_normalize_path( $path );
			$filename = \basename( $path );

			foreach ( $exclusions as $exclusion ) {

				if ( \strpos( $exclusion, '#' ) === 0 ) {
					$excluded = \preg_match( $exclusion, $path ) > 0;
				}
				else {
					$exclusion = wp_normalize_path( $exclusion );
					if ( \strpos( $exclusion, '/' ) === false ) { // filename only
						$excluded = $filename === $exclusion;
					}
					else {
						$excluded = \strpos( $path, $exclusion ) !== false;
					}
				}

				if ( $excluded ) {
					break;
				}
			}
		}
		return $excluded;
	}
}