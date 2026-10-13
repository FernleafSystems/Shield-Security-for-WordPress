<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\HackGuard\Lib\Hashes;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Hashes\FileHashAlgorithm;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class FileHashAlgorithmTest extends BaseUnitTest {

	#[\PHPUnit\Framework\Attributes\DataProvider( 'supportedAlgorithms' )]
	public function test_supported_algorithm_and_digest_length( string $algorithm, int $length ) :void {
		$this->assertSame( $algorithm, FileHashAlgorithm::validate( $algorithm ) );
		$this->assertSame( $length, FileHashAlgorithm::digestLength( $algorithm ) );
	}

	public static function supportedAlgorithms() :array {
		return [ [ 'md5', 32 ], [ 'sha1', 40 ], [ 'sha256', 64 ] ];
	}

	#[\PHPUnit\Framework\Attributes\DataProvider( 'invalidAlgorithms' )]
	public function test_invalid_algorithm_is_rejected_without_coercion( $algorithm ) :void {
		$this->expectException( \InvalidArgumentException::class );
		FileHashAlgorithm::validate( $algorithm );
	}

	public static function invalidAlgorithms() :array {
		return [
			[ '' ], [ 'SHA256' ], [ ' sha256' ], [ 'sha384' ], [ 'sha512' ], [ 'crc32' ],
			[ null ], [ false ], [ 256 ], [ [] ], [ new \stdClass() ],
			[ new class {
				public function __toString() :string {
					return 'sha256';
				}
			} ],
		];
	}

	public function test_digest_length_rejects_an_unsupported_name() :void {
		$this->expectException( \InvalidArgumentException::class );
		FileHashAlgorithm::digestLength( 'sha512' );
	}
}
