<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Utilties;

class OneTimePassword {

	public static function Generate( int $length = 6, string $exclude = 'iol01' ) :string {
		do {
			$otp = \substr(
				strtoupper( \preg_replace( sprintf( '#[%s]#i', $exclude ), '', wp_generate_password( 50, false ) ) ),
				0, $length
			);
		} while ( \strlen( $otp ) !== $length );
		return $otp;
	}
}