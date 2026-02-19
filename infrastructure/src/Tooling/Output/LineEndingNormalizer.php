<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Output;

class LineEndingNormalizer {

	/**
	 * Tooling-scoped line-ending normalizer.
	 * Runtime/plugin code intentionally uses wordpress-services ConvertLineEndings.
	 */
	public function toLf( string $text ) :string {
		return \str_replace( [ "\r\n", "\r" ], "\n", $text );
	}

	public function toHostEol( string $text ) :string {
		$normalized = $this->toLf( $text );
		if ( \PHP_EOL !== "\n" ) {
			$normalized = \str_replace( "\n", \PHP_EOL, $normalized );
		}
		return $normalized;
	}
}
