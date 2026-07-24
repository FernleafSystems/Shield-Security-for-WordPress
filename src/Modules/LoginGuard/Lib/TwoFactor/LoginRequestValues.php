<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor;

/**
 * @phpstan-type LoginIntentRenderInput array{
 *     user_id:int,
 *     include_body:bool,
 *     plain_login_nonce:string,
 *     interim_login?:mixed,
 *     redirect_to?:mixed,
 *     rememberme?:mixed,
 *     cancel_href?:mixed,
 *     msg_error?:string,
 *     interim_message?:string
 * }
 * @phpstan-type LoginIntentRenderData array{
 *     user_id:int,
 *     include_body:bool,
 *     plain_login_nonce:string,
 *     interim_login:''|'1',
 *     redirect_to:string,
 *     rememberme:''|'forever',
 *     cancel_href:string,
 *     msg_error:string,
 *     interim_message:string
 * }
 */
class LoginRequestValues {

	/**
	 * @param LoginIntentRenderInput $input
	 * @return LoginIntentRenderData
	 */
	public static function buildLoginIntentRenderData( array $input, string $redirectFallback ) :array {
		return [
			'user_id'           => $input[ 'user_id' ],
			'include_body'      => $input[ 'include_body' ],
			'plain_login_nonce' => $input[ 'plain_login_nonce' ],
			'interim_login'     => self::tokenValue( $input[ 'interim_login' ] ?? '', '1' ),
			'redirect_to'       => self::safeRedirect( $input[ 'redirect_to' ] ?? '', $redirectFallback ),
			'rememberme'        => self::tokenValue( $input[ 'rememberme' ] ?? '', 'forever' ),
			'cancel_href'       => self::safeRedirect( $input[ 'cancel_href' ] ?? '', '' ),
			'msg_error'         => $input[ 'msg_error' ] ?? '',
			'interim_message'   => $input[ 'interim_message' ] ?? '',
		];
	}

	public static function positiveUserId( $value ) :?int {
		if ( \is_int( $value ) ) {
			return $value > 0 ? $value : null;
		}
		if ( !\is_string( $value ) || \preg_match( '#^\d+$#D', $value ) !== 1 ) {
			return null;
		}

		$digits = \ltrim( $value, '0' );
		if ( $digits === '' ) {
			return null;
		}
		$max = (string)\PHP_INT_MAX;
		if ( \strlen( $digits ) > \strlen( $max )
			 || ( \strlen( $digits ) === \strlen( $max ) && \strcmp( $digits, $max ) > 0 ) ) {
			return null;
		}
		return (int)$digits;
	}

	public static function nonEmptyString( $value ) :?string {
		return \is_string( $value ) && $value !== '' ? $value : null;
	}

	public static function isToken( $value, string $expected ) :bool {
		return \is_string( $value ) && $value === $expected;
	}

	public static function tokenValue( $value, string $expected ) :string {
		return self::isToken( $value, $expected ) ? $expected : '';
	}

	public static function safeRedirect( $value, string $fallback ) :string {
		return \is_string( $value ) && $value !== '' ? \wp_validate_redirect( $value, $fallback ) : $fallback;
	}

	public static function loginMessage( $value ) :string {
		if ( \is_string( $value ) ) {
			return $value;
		}
		if ( \is_scalar( $value ) ) {
			return (string)$value;
		}
		if ( \is_object( $value ) && \method_exists( $value, '__toString' ) ) {
			try {
				return (string)$value;
			}
			catch ( \Throwable $e ) {
			}
		}
		return '';
	}
}
