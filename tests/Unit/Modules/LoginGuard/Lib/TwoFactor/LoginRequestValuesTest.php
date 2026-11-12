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

	#[\PHPUnit\Framework\Attributes\DataProvider( 'loginIntentRenderDataProvider' )]
	public function test_build_login_intent_render_data_contract(
		array $input,
		string $redirectFallback,
		array $expected
	) :void {
		$actual = LoginRequestValues::buildLoginIntentRenderData( $input, $redirectFallback );

		$this->assertSame( $expected, $actual );
		$this->assertSame(
			[
				'user_id',
				'include_body',
				'plain_login_nonce',
				'interim_login',
				'redirect_to',
				'rememberme',
				'cancel_href',
				'msg_error',
				'interim_message',
			],
			\array_keys( $actual )
		);
		$this->assertIsInt( $actual[ 'user_id' ] );
		$this->assertIsBool( $actual[ 'include_body' ] );
		foreach ( \array_slice( $actual, 2 ) as $value ) {
			$this->assertIsString( $value );
		}
	}

	public static function loginIntentRenderDataProvider() :array {
		return [
			'exact values' => [
				[
					'user_id'           => 42,
					'include_body'      => true,
					'plain_login_nonce' => 'nonce',
					'interim_login'     => '1',
					'redirect_to'       => '/target',
					'rememberme'        => 'forever',
					'cancel_href'       => '/cancel',
					'msg_error'         => 'error',
					'interim_message'   => 'interim',
				],
				'/fallback',
				[
					'user_id'           => 42,
					'include_body'      => true,
					'plain_login_nonce' => 'nonce',
					'interim_login'     => '1',
					'redirect_to'       => '/target',
					'rememberme'        => 'forever',
					'cancel_href'       => '/cancel',
					'msg_error'         => 'error',
					'interim_message'   => 'interim',
				],
			],
			'malformed optional values' => [
				[
					'user_id'           => 7,
					'include_body'      => false,
					'plain_login_nonce' => 'other-nonce',
					'interim_login'     => [ '1' ],
					'redirect_to'       => [ '/target' ],
					'rememberme'        => new \stdClass(),
					'cancel_href'       => true,
				],
				'/request-path',
				[
					'user_id'           => 7,
					'include_body'      => false,
					'plain_login_nonce' => 'other-nonce',
					'interim_login'     => '',
					'redirect_to'       => '/request-path',
					'rememberme'        => '',
					'cancel_href'       => '',
					'msg_error'         => '',
					'interim_message'   => '',
				],
			],
			'unsafe redirects and omitted optionals' => [
				[
					'user_id'           => 1,
					'include_body'      => true,
					'plain_login_nonce' => 'nonce',
					'redirect_to'       => 'https://evil.example/target',
					'cancel_href'       => 'https://evil.example/cancel',
				],
				'/safe-fallback',
				[
					'user_id'           => 1,
					'include_body'      => true,
					'plain_login_nonce' => 'nonce',
					'interim_login'     => '',
					'redirect_to'       => '/safe-fallback',
					'rememberme'        => '',
					'cancel_href'       => '',
					'msg_error'         => '',
					'interim_message'   => '',
				],
			],
		];
	}

	#[\PHPUnit\Framework\Attributes\DataProvider( 'nonceProvider' )]
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

	#[\PHPUnit\Framework\Attributes\DataProvider( 'exactTokenProvider' )]
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

	#[\PHPUnit\Framework\Attributes\DataProvider( 'invalidTokenProvider' )]
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

	#[\PHPUnit\Framework\Attributes\DataProvider( 'invalidRedirectProvider' )]
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

	#[\PHPUnit\Framework\Attributes\DataProvider( 'loginMessageProvider' )]
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
