<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive\Database\Operators;

class SqlDumpBitValueFormatter {

	public function format( $value, string $type ) :string {
		if ( \is_int( $value ) || ( \is_string( $value ) && \ctype_digit( $value ) ) ) {
			$bytes = \preg_match( '#^bit\((\d+)\)#i', $type, $matches ) ? (int)\ceil( (int)$matches[ 1 ]/8 ) : 1;
			return '0x'.\str_pad( \dechex( (int)$value ), \max( 2, $bytes*2 ), '0', STR_PAD_LEFT );
		}
		return '0x'.\bin2hex( (string)$value );
	}
}
