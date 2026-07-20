<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Utility;

class NormalizeAbsPath {

	public function normalize( string $path ) :string {
		return \trailingslashit( \wp_normalize_path( $path ) );
	}

	public function normalizeResolved( string $path ) :string {
		$resolved = \realpath( $path );
		return $this->normalize( \is_string( $resolved ) ? $resolved : $path );
	}

	public function areSame( string $stored, string $current ) :bool {
		return $this->normalize( $stored ) === $this->normalize( $current )
			   || $this->normalizeResolved( $stored ) === $this->normalizeResolved( $current );
	}
}
