<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\HackGuard\Lib\Hashes;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Hashes\CompareFileHash;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\ServicesState;
use FernleafSystems\Wordpress\Services\Core\Fs;

class CompareFileHashTest extends BaseUnitTest {

	use TempDirLifecycleTrait;

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		$this->servicesSnapshot = ServicesState::snapshot();
		ServicesState::installItems( [ 'service_wpfs' => new CompareFileHashFs() ] );
	}

	protected function tearDown() :void {
		ServicesState::restore( $this->servicesSnapshot );
		$this->cleanupTrackedTempDirs();
		parent::tearDown();
	}

	#[\PHPUnit\Framework\Attributes\DataProvider( 'provideAlgorithms' )]
	public function test_supported_hash_algorithms_verify( string $algorithm ) :void {
		$content = "one\ntwo\n";
		$path = $this->writeFile( $content );

		$this->assertTrue( ( new CompareFileHash() )->isEqual( $path, \hash( $algorithm, $content ) ) );
	}

	public static function provideAlgorithms() :array {
		return [
			'md5'    => [ 'md5' ],
			'sha1'   => [ 'sha1' ],
			'sha256' => [ 'sha256' ],
		];
	}

	#[\PHPUnit\Framework\Attributes\DataProvider( 'provideAlgorithms' )]
	public function test_line_ending_variants_remain_compatible( string $algorithm ) :void {
		$dosPath = $this->writeFile( "one\r\ntwo\r\n" );
		$linuxPath = $this->writeFile( "one\ntwo\n" );
		$macPath = $this->writeFile( "one\rtwo\r" );
		$mixedPath = $this->writeFile( "one\r\ntwo\rthree\n" );
		$compare = new CompareFileHash();

		$this->assertTrue( $compare->isEqual( $dosPath, \hash( $algorithm, "one\ntwo\n" ) ) );
		$this->assertTrue( $compare->isEqual( $linuxPath, \hash( $algorithm, "one\r\ntwo\r\n" ) ) );
		$this->assertTrue( $compare->isEqual( $macPath, \hash( $algorithm, "one\ntwo\n" ) ) );
		$this->assertTrue( $compare->isEqual( $mixedPath, \hash( $algorithm, "one\ntwo\nthree\n" ) ) );
		$this->assertFalse( $compare->isEqual( $mixedPath, \hash( $algorithm, "one\ntwo\nchanged\n" ) ) );
	}

	#[\PHPUnit\Framework\Attributes\DataProvider( 'provideAlgorithms' )]
	public function test_binary_raw_equality_and_changed_content( string $algorithm ) :void {
		$content = "\x00\xff\x80one\r\ntwo\r\x00";
		$path = $this->writeFile( $content );
		$compare = new CompareFileHash();

		$this->assertTrue( $compare->isEqual( $path, \hash( $algorithm, $content ) ) );
		$this->assertFalse( $compare->isEqual( $path, \hash( $algorithm, $content."\x01" ) ) );
	}

	public function test_hash_file_false_is_not_reported_as_verified() :void {
		$path = $this->writeFile( 'content' );

		$this->assertFalse( ( new CompareFileHashFalseHash() )->isEqual( $path, \md5( 'content' ) ) );
	}

	public function test_content_read_failure_after_raw_mismatch_is_not_reported_as_verified() :void {
		$path = $this->writeFile( 'content' );

		$this->assertFalse( ( new CompareFileHashFalseRead() )->isEqual( $path, \md5( 'different' ) ) );
	}

	public function test_missing_file_preserves_domain_exception() :void {
		$this->expectException( \InvalidArgumentException::class );

		( new CompareFileHash() )->isEqual( $this->createTrackedTempPath( 'missing-hash-file-' ), \md5( 'content' ) );
	}

	#[\PHPUnit\Framework\Attributes\DataProvider( 'provideAlgorithms' )]
	public function test_multi_hash_comparison_preserves_raw_and_line_ending_matches( string $algorithm ) :void {
		$compare = new CompareFileHash();
		foreach ( [ "one\r\ntwo\r\n", "one\ntwo\n", "one\rtwo\r", "one\r\ntwo\rthree\n", "\x00\xff\x80one\r\ntwo\r\x00" ] as $content ) {
			$path = $this->writeFile( $content );
			$this->assertTrue( $compare->isAnyEqual( $path, [ \hash( $algorithm, 'different' ), \hash( $algorithm, $content ) ] ) );
			$normalised = \str_replace( [ "\r\n", "\r" ], "\n", $content );
			$this->assertTrue( $compare->isAnyEqual( $path, [ \hash( $algorithm, 'different' ), \hash( $algorithm, $normalised ) ] ) );
			$this->assertTrue( $compare->isAnyEqual( $path, [ \hash( $algorithm, \str_replace( "\n", "\r\n", $normalised ) ) ] ) );
			$this->assertFalse( $compare->isAnyEqual( $path, [ \hash( $algorithm, $content.'changed' ) ] ) );
		}
	}

	public function test_multi_hash_comparison_hashes_once_per_algorithm_and_reads_content_once() :void {
		$path = $this->writeFile( "one\r\ntwo\rthree\n" );
		$compare = new class extends CompareFileHash {
			public array $hashCalls = [];
			public int $reads = 0;
			protected function hashFile( string $algorithm, string $path ) {
				$this->hashCalls[ $algorithm ] = ( $this->hashCalls[ $algorithm ] ?? 0 ) + 1;
				return parent::hashFile( $algorithm, $path );
			}
			protected function readFile( string $path ) {
				$this->reads++;
				return parent::readFile( $path );
			}
		};
		$references = [];
		foreach ( [ 'md5', 'sha1', 'sha256' ] as $algorithm ) {
			foreach ( \range( 1, 10 ) as $index ) {
				$references[] = \hash( $algorithm, 'different-'.$index );
			}
		}
		$this->assertFalse( $compare->isAnyEqual( $path, $references ) );
		$this->assertSame( [ 'md5' => 1, 'sha1' => 1, 'sha256' => 1 ], $compare->hashCalls );
		$this->assertSame( 1, $compare->reads );
	}

	public function test_multi_hash_comparison_preserves_hash_and_content_read_failures() :void {
		$path = $this->writeFile( 'content' );
		$this->assertFalse( ( new CompareFileHashFalseHash() )->isAnyEqual( $path, [ \md5( 'content' ) ] ) );
		$this->assertFalse( ( new CompareFileHashFalseRead() )->isAnyEqual( $path, [ \md5( 'different' ) ] ) );
	}

	public function test_multi_hash_comparison_preserves_missing_file_exception_and_empty_reference_miss() :void {
		$path = $this->createTrackedTempPath( 'missing-multi-hash-file-' );
		$this->assertFalse( ( new CompareFileHash() )->isAnyEqual( $path, [] ) );
		$this->expectException( \InvalidArgumentException::class );
		( new CompareFileHash() )->isAnyEqual( $path, [ \md5( 'content' ) ] );
	}

	#[\PHPUnit\Framework\Attributes\DataProvider( 'provideAlgorithms' )]
	public function test_asset_file_rule_verifies_only_its_path_with_every_other_byte_intact( string $algorithm ) :void {
		// The WP Hashes API publishes vendor/build.json with EDD's per-download manifestChecksum emptied.
		$published = \hash( $algorithm, $this->eddBuild( '' ) );
		$path = $this->writeFile( $this->eddBuild( '1900a92e-567d-40O0TYMey\/eHs=' ) );
		$compare = new CompareFileHash();

		$this->assertTrue( $compare->isEqual( $path, $published, 'vendor/build.json' ) );
		$this->assertTrue( $compare->isAnyEqual( $path, [ \hash( $algorithm, 'different' ), $published ], 'vendor/build.json' ) );
		$this->assertFalse( $compare->isEqual( $path, $published ) );
		$this->assertFalse( $compare->isAnyEqual( $path, [ $published ], 'vendor/other.json' ) );

		$edited = $this->writeFile( \str_replace( '"fileCount":5623', '"fileCount":1', $this->eddBuild( '1900a92e-567d-40O0TYMey\/eHs=' ) ) );
		$this->assertFalse( $compare->isEqual( $edited, $published, 'vendor/build.json' ) );
		$this->assertFalse( $compare->isAnyEqual( $edited, [ $published ], 'vendor/build.json' ) );

		$windows = $this->writeFile( $this->eddBuild( '1900a92e-567d-40O0TYMey\/eHs=' )."\r\n" );
		$this->assertTrue( $compare->isEqual( $windows, \hash( $algorithm, $this->eddBuild( '' )."\n" ), 'vendor/build.json' ) );
	}

	private function eddBuild( string $manifestChecksum ) :string {
		return '{"buildDate":1792016945,"buildMeta":{"buildAuthor":"@cklosowski","fileCount":5623,"fileChecksum":"22e7a10707420c21b3802eb4b8a2ecee","manifestChecksum":"'.$manifestChecksum.'"}}';
	}

	private function writeFile( string $content ) :string {
		$path = $this->createTrackedTempPath( 'shield-compare-file-hash-' );
		\file_put_contents( $path, $content );
		return $path;
	}
}

class CompareFileHashFs extends Fs {

	public function isFile( $path ) :bool {
		return \is_file( $path );
	}

	public function getFileContent( $path, $uncompress = false ) {
		unset( $uncompress );
		return \file_get_contents( $path );
	}
}

class CompareFileHashFalseHash extends CompareFileHash {

	protected function hashFile( string $algorithm, string $path ) {
		unset( $algorithm, $path );
		return false;
	}
}

class CompareFileHashFalseRead extends CompareFileHash {

	protected function readFile( string $path ) {
		unset( $path );
		return false;
	}
}
