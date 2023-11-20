<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool;

class ArrayOps {

	public static function CleanStrings( array $arr, string $pregReplacePattern ) :array {
		$cleaned = [];
		foreach ( $arr as $val ) {
			if ( \is_string( $val ) ) {
				$val = \preg_replace( $pregReplacePattern, '', $val );
				if ( \strlen( $val ) > 0 ) {
					$cleaned[] = $val;
				}
			}
		}
		return $cleaned;
	}
}