<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Utility;

class GenerateNameFromSlug {

	public function gen( string $slug ) :?string {
		return \preg_replace_callback(
			sprintf( '#\b(%s)\b#i', \implode( '|', $this->abbreviations() ) ),
			function ( $matches ) {
				return \strtoupper( $matches[ 0 ] );
			},
			\ucwords( \str_replace( '_', ' ', $slug ) )
		);
	}

	private function abbreviations() :array {
		return [
			'ade',
			'api',
			'cli',
			'ajax',
			'http',
			'https',
			'id',
			'ip',
			'php',
			'wp',
			'wpcli',
			'xmlrpc',
		];
	}
}