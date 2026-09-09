<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ShieldNetApi\FileLocker;

use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\FileLocker\GetPublicKey;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class GetPublicKeyTest extends BaseUnitTest {

	/**
	 * @dataProvider responseProvider
	 */
	public function test_response_contract( ?array $response, ?array $expected ) :void {
		$this->assertSame( $expected, ( new GetPublicKeyResponseStub( $response ) )->retrieve() );
	}

	public static function responseProvider() :array {
		return [
			'null' => [ null, null ],
			'empty' => [ [], null ],
			'missing key' => [ [ 'pub_key' => 'public' ], null ],
			'missing public key' => [ [ 'key_id' => 1 ], null ],
			'integer key' => [ [ 'key_id' => 1, 'pub_key' => 'public' ], [ 1 => 'public' ] ],
			'digit string key' => [ [ 'key_id' => '42', 'pub_key' => 'public' ], [ 42 => 'public' ] ],
			'leading zeroes' => [ [ 'key_id' => '0042', 'pub_key' => 'public' ], [ 42 => 'public' ] ],
			'max key' => [ [ 'key_id' => (string)\PHP_INT_MAX, 'pub_key' => 'public' ], [ \PHP_INT_MAX => 'public' ] ],
			'extra response fields ignored' => [ [ 'key_id' => 7, 'pub_key' => 'public', 'extra' => [ 'data' ] ], [ 7 => 'public' ] ],
			'public key whitespace preserved' => [ [ 'key_id' => 8, 'pub_key' => " public\n" ], [ 8 => " public\n" ] ],
			'zero int' => [ [ 'key_id' => 0, 'pub_key' => 'public' ], null ],
			'zero string' => [ [ 'key_id' => '0', 'pub_key' => 'public' ], null ],
			'negative' => [ [ 'key_id' => -1, 'pub_key' => 'public' ], null ],
			'negative string' => [ [ 'key_id' => '-1', 'pub_key' => 'public' ], null ],
			'plus-prefixed string' => [ [ 'key_id' => '+1', 'pub_key' => 'public' ], null ],
			'whitespace-prefixed string' => [ [ 'key_id' => ' 1', 'pub_key' => 'public' ], null ],
			'whitespace-suffixed string' => [ [ 'key_id' => '1 ', 'pub_key' => 'public' ], null ],
			'decimal string' => [ [ 'key_id' => '1.0', 'pub_key' => 'public' ], null ],
			'exponent string' => [ [ 'key_id' => '1e0', 'pub_key' => 'public' ], null ],
			'newline string' => [ [ 'key_id' => "1\n", 'pub_key' => 'public' ], null ],
			'nul string' => [ [ 'key_id' => "1\0", 'pub_key' => 'public' ], null ],
			'unicode digits' => [ [ 'key_id' => '١', 'pub_key' => 'public' ], null ],
			'empty key string' => [ [ 'key_id' => '', 'pub_key' => 'public' ], null ],
			'float' => [ [ 'key_id' => 1.5, 'pub_key' => 'public' ], null ],
			'boolean' => [ [ 'key_id' => true, 'pub_key' => 'public' ], null ],
			'null key' => [ [ 'key_id' => null, 'pub_key' => 'public' ], null ],
			'nondigits' => [ [ 'key_id' => '1x', 'pub_key' => 'public' ], null ],
			'overflow' => [ [ 'key_id' => (string)\PHP_INT_MAX.'0', 'pub_key' => 'public' ], null ],
			'array key' => [ [ 'key_id' => [ 1 ], 'pub_key' => 'public' ], null ],
			'object key' => [ [ 'key_id' => new \stdClass(), 'pub_key' => 'public' ], null ],
			'stringable key' => [ [ 'key_id' => new GetPublicKeyStringable(), 'pub_key' => 'public' ], null ],
			'empty public key' => [ [ 'key_id' => 1, 'pub_key' => '' ], null ],
			'blank public key' => [ [ 'key_id' => 1, 'pub_key' => " \t\n" ], null ],
			'nonstring public key' => [ [ 'key_id' => 1, 'pub_key' => 123 ], null ],
			'float public key' => [ [ 'key_id' => 1, 'pub_key' => 1.5 ], null ],
			'boolean public key' => [ [ 'key_id' => 1, 'pub_key' => true ], null ],
			'null public key' => [ [ 'key_id' => 1, 'pub_key' => null ], null ],
			'array public key' => [ [ 'key_id' => 1, 'pub_key' => [ 'public' ] ], null ],
			'object public key' => [ [ 'key_id' => 1, 'pub_key' => new \stdClass() ], null ],
			'stringable public key' => [ [ 'key_id' => 1, 'pub_key' => new GetPublicKeyStringable() ], null ],
		];
	}

	public function test_resource_fields_are_rejected() :void {
		$resource = \fopen( 'php://memory', 'r' );
		try {
			$this->assertNull( ( new GetPublicKeyResponseStub( [
				'key_id' => $resource,
				'pub_key' => 'public',
			] ) )->retrieve() );
			$this->assertNull( ( new GetPublicKeyResponseStub( [
				'key_id' => 1,
				'pub_key' => $resource,
			] ) )->retrieve() );
		}
		finally {
			\fclose( $resource );
		}
	}
}

class GetPublicKeyStringable {
	public function __toString() :string {
		return '1';
	}
}

class GetPublicKeyResponseStub extends GetPublicKey {

	private ?array $response;

	public function __construct( ?array $response ) {
		$this->response = $response;
	}

	protected function sendReq() :?array {
		return $this->response;
	}
}
