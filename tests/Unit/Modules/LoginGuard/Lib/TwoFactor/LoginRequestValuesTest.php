<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\LoginGuard\Lib\TwoFactor;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\LoginRequestValues;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class LoginRequestValuesTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( 'wp_validate_redirect' )->alias(
			static function ( string $url, string $fallback ) :string {
				return \strpos( $url, 'https://evil.example/' ) === 0 ? $fallback : $url;
			}
		);
	}

	/**
	 * @dataProvider userIdProvider
	 */
	public function test_positive_user_id_contract( $value, ?int $expected ) :void {
		$this->assertSame( $expected, LoginRequestValues::positiveUserId( $value ) );
	}

	public static function userIdProvider() :array {
		return [
			'native int'       => [ 42, 42 ],
			'native max'       => [ \PHP_INT_MAX, \PHP_INT_MAX ],
			'digit string'     => [ '42', 42 ],
			'leading zeroes'   => [ '0042', 42 ],
			'string max'       => [ (string)\PHP_INT_MAX, \PHP_INT_MAX ],
			'zero int'         => [ 0, null ],
			'zero string'      => [ '0', null ],
			'all zeroes'       => [ '000', null ],
			'negative int'     => [ -1, null ],
			'negative string'  => [ '-1', null ],
			'positive sign'    => [ '+1', null ],
			'whitespace'       => [ ' 42 ', null ],
			'decimal'          => [ '1.0', null ],
			'exponent'         => [ '1e2', null ],
			'unicode digit'    => [ "\xD9\xA1", null ],
			'newline suffix'   => [ "42\n", null ],
			'nul suffix'       => [ "42\0", null ],
			'float'            => [ 1.0, null ],
			'boolean'          => [ true, null ],
			'empty'            => [ '', null ],
			'nondigits'        => [ '1x', null ],
			'overflow'         => [ (string)\PHP_INT_MAX.'0', null ],
			'array'            => [ [ 1 ], null ],
			'nested array'     => [ [ [ 1 ] ], null ],
			'object'           => [ new \stdClass(), null ],
			'stringable'       => [ new LoginRequestNumericStringable(), null ],
			'null'             => [ null, null ],
		];
	}

	public function test_resource_is_not_a_user_id() :void {
		$resource = \fopen( 'php://memory', 'r' );
		try {
			$this->assertNull( LoginRequestValues::positiveUserId( $resource ) );
		}
		finally {
			\fclose( $resource );
		}
	}

	/**
	 * @dataProvider nonceProvider
	 */
	public function test_nonce_contract( $value, ?string $expected ) :void {
		$this->assertSame( $expected, LoginRequestValues::nonEmptyString( $value ) );
	}

	public static function nonceProvider() :array {
		return [
			'ordinary string' => [ 'nonce', 'nonce' ],
			'zero string'     => [ '0', '0' ],
			'whitespace'      => [ ' ', ' ' ],
			'empty'           => [ '', null ],
			'integer'         => [ 1, null ],
			'float'           => [ 1.5, null ],
			'boolean'         => [ true, null ],
			'array'           => [ [ 'nonce' ], null ],
			'nested array'    => [ [ [ 'nonce' ] ], null ],
			'object'          => [ new \stdClass(), null ],
			'stringable'      => [ new LoginRequestStringable(), null ],
			'null'            => [ null, null ],
		];
	}

	public function test_resource_is_not_a_nonce() :void {
		$resource = \fopen( 'php://memory', 'r' );
		try {
			$this->assertNull( LoginRequestValues::nonEmptyString( $resource ) );
		}
		finally {
			\fclose( $resource );
		}
	}

	/**
	 * @dataProvider exactTokenProvider
	 */
	public function test_exact_token_contract( string $expected ) :void {
		$this->assertTrue( LoginRequestValues::isToken( $expected, $expected ) );
		$this->assertSame( $expected, LoginRequestValues::tokenValue( $expected, $expected ) );
	}

	public static function exactTokenProvider() :array {
		return [
			'cancel'       => [ '1' ],
			'skip mfa'     => [ 'Y' ],
			'interim login' => [ '1' ],
			'remember me'  => [ 'forever' ],
		];
	}

	/**
	 * @dataProvider invalidTokenProvider
	 */
	public function test_token_rejects_nonexact_value( $value, string $expected ) :void {
		$this->assertFalse( LoginRequestValues::isToken( $value, $expected ) );
		$this->assertSame( '', LoginRequestValues::tokenValue( $value, $expected ) );
	}

	public static function invalidTokenProvider() :array {
		return [
			'cancel integer'       => [ 1, '1' ],
			'cancel whitespace'    => [ ' 1', '1' ],
			'cancel suffix'        => [ '1x', '1' ],
			'skip lower case'      => [ 'y', 'Y' ],
			'skip whitespace'      => [ ' Y ', 'Y' ],
			'remember case'        => [ 'Forever', 'forever' ],
			'remember prefix'      => [ 'xforever', 'forever' ],
			'boolean'              => [ true, 'forever' ],
			'array'                => [ [ 'forever' ], 'forever' ],
			'nested array'         => [ [ [ 'forever' ] ], 'forever' ],
			'object'               => [ new \stdClass(), 'forever' ],
			'stringable'           => [ new LoginRequestStringable(), 'forever' ],
			'null'                 => [ null, 'forever' ],
			'empty'                => [ '', 'forever' ],
		];
	}

	public function test_resource_is_not_a_token() :void {
		$resource = \fopen( 'php://memory', 'r' );
		try {
			$this->assertFalse( LoginRequestValues::isToken( $resource, 'forever' ) );
			$this->assertSame( '', LoginRequestValues::tokenValue( $resource, 'forever' ) );
		}
		finally {
			\fclose( $resource );
		}
	}

	public function test_safe_redirect_preserves_valid_strings_and_rejects_other_values() :void {
		$this->assertSame( '/relative', LoginRequestValues::safeRedirect( '/relative', '/fallback' ) );
		$this->assertSame( 'https://example.com/path', LoginRequestValues::safeRedirect( 'https://example.com/path', '/fallback' ) );
		$this->assertSame( '/fallback', LoginRequestValues::safeRedirect( 'https://evil.example/path', '/fallback' ) );
	}

	/**
	 * @dataProvider invalidRedirectProvider
	 */
	public function test_safe_redirect_rejects_invalid_value_without_wordpress_validation( $value ) :void {
		Functions\expect( 'wp_validate_redirect' )->never();
		$this->assertSame( '/fallback', LoginRequestValues::safeRedirect( $value, '/fallback' ) );
	}

	public static function invalidRedirectProvider() :array {
		return [
			'empty'        => [ '' ],
			'null'         => [ null ],
			'boolean'      => [ false ],
			'integer'      => [ 1 ],
			'float'        => [ 1.5 ],
			'array'        => [ [ '/relative' ] ],
			'nested array' => [ [ [ '/relative' ] ] ],
			'object'       => [ new \stdClass() ],
			'stringable'   => [ new LoginRequestStringable() ],
		];
	}

	public function test_resource_is_not_a_redirect() :void {
		$resource = \fopen( 'php://memory', 'r' );
		try {
			Functions\expect( 'wp_validate_redirect' )->never();
			$this->assertSame( '/fallback', LoginRequestValues::safeRedirect( $resource, '/fallback' ) );
		}
		finally {
			\fclose( $resource );
		}
	}

	/**
	 * @dataProvider loginMessageProvider
	 */
	public function test_login_message_contract( $value, string $expected ) :void {
		$this->assertSame( $expected, LoginRequestValues::loginMessage( $value ) );
	}

	public static function loginMessageProvider() :array {
		return [
			'string' => [ 'message', 'message' ],
			'int' => [ 12, '12' ],
			'float' => [ 1.5, '1.5' ],
			'true' => [ true, '1' ],
			'false' => [ false, '' ],
			'stringable' => [ new LoginRequestStringable(), 'stringable' ],
			'array' => [ [ 'message' ], '' ],
			'nested array' => [ [ [ 'message' ] ], '' ],
			'object' => [ new \stdClass(), '' ],
			'throwing stringable' => [ new LoginRequestThrowingStringable(), '' ],
			'error stringable' => [ new LoginRequestErrorStringable(), '' ],
			'null' => [ null, '' ],
		];
	}

	public function test_resource_login_message_becomes_empty_string() :void {
		$resource = \fopen( 'php://memory', 'r' );
		try {
			$this->assertSame( '', LoginRequestValues::loginMessage( $resource ) );
		}
		finally {
			\fclose( $resource );
		}
	}
}

class LoginRequestStringable {
	public function __toString() :string {
		return 'stringable';
	}
}

class LoginRequestThrowingStringable {
	public function __toString() :string {
		throw new \RuntimeException( 'conversion failed' );
	}
}

class LoginRequestErrorStringable {
	public function __toString() :string {
		throw new \Error( 'conversion failed' );
	}
}

class LoginRequestNumericStringable {
	public function __toString() :string {
		return '42';
	}
}
