<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs;

class NormalizeFileExtensions {

	/**
	 * @param array<array-key,mixed> $extensions
	 * @return list<string>
	 */
	public function run( array $extensions ) :array {
		$normalised = [];
		foreach ( $extensions as $extension ) {
			if ( !\is_string( $extension ) ) {
				continue;
			}
			$extension = \strtolower( \trim( $extension ) );
			if ( $extension !== '' && !\in_array( $extension, $normalised, true ) ) {
				$normalised[] = $extension;
			}
		}
		return $normalised;
	}
}
