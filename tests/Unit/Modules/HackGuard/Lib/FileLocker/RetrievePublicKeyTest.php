<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\HackGuard\Lib\FileLocker;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Exceptions\PublicKeyRetrievalFailure;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Utility\RetrievePublicKey;
use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\FileLocker\GetPublicKey;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class RetrievePublicKeyTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->returnArg();
	}

	public function test_valid_key_map_is_preserved() :void {
		$key = [ 42 => 'public' ];
		$this->assertSame( $key, ( new RetrievePublicKeyProbe( $key ) )->retrieve() );
	}

	public function test_max_key_and_public_key_text_are_preserved() :void {
		$key = [ \PHP_INT_MAX => " public\n" ];
		$this->assertSame( $key, ( new RetrievePublicKeyProbe( $key ) )->retrieve() );
	}

	/**
	 * @dataProvider invalidResultProvider
	 */
	public function test_invalid_getter_result_becomes_controlled_failure( ?array $key ) :void {
		$this->expectException( PublicKeyRetrievalFailure::class );
		( new RetrievePublicKeyProbe( $key ) )->retrieve();
	}

	public static function invalidResultProvider() :array {
		return [
			'null' => [ null ],
			'empty' => [ [] ],
			'multiple entries' => [ [ 1 => 'one', 2 => 'two' ] ],
			'zero key' => [ [ 0 => 'public' ] ],
			'negative key' => [ [ -1 => 'public' ] ],
			'nonnumeric string key' => [ [ 'key' => 'public' ] ],
			'empty public key' => [ [ 1 => '' ] ],
			'blank public key' => [ [ 1 => " \t\n" ] ],
			'nonstring public key' => [ [ 1 => 123 ] ],
			'array public key' => [ [ 1 => [ 'public' ] ] ],
			'object public key' => [ [ 1 => new \stdClass() ] ],
		];
	}

	public function test_resource_public_key_becomes_controlled_failure() :void {
		$resource = \fopen( 'php://memory', 'r' );
		try {
			$this->expectException( PublicKeyRetrievalFailure::class );
			( new RetrievePublicKeyProbe( [ 1 => $resource ] ) )->retrieve();
		}
		finally {
			\fclose( $resource );
		}
	}
}

class RetrievePublicKeyProbe extends RetrievePublicKey {

	private ?array $key;

	public function __construct( ?array $key ) {
		$this->key = $key;
	}

	protected function buildGetter() :GetPublicKey {
		return new class( $this->key ) extends GetPublicKey {
			private ?array $key;

			public function __construct( ?array $key ) {
				$this->key = $key;
			}

			public function retrieve() :?array {
				return $this->key;
			}
		};
	}
}
