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

	/**
	 * @dataProvider provideAlgorithms
	 */
	public function test_supported_hash_algorithms_verify( string $algorithm ) :void {
		$content = "one\ntwo\n";
		$path = $this->writeFile( $content );

		$this->assertTrue( ( new CompareFileHash() )->isEqual( $path, \hash( $algorithm, $content ) ) );
	}

	public function provideAlgorithms() :array {
		return [
			'md5'    => [ 'md5' ],
			'sha1'   => [ 'sha1' ],
			'sha256' => [ 'sha256' ],
		];
	}

	public function test_line_ending_variants_remain_compatible() :void {
		$dosPath = $this->writeFile( "one\r\ntwo\r\n" );
		$linuxPath = $this->writeFile( "one\ntwo\n" );
		$compare = new CompareFileHash();

		$this->assertTrue( $compare->isEqual( $dosPath, \sha1( "one\ntwo\n" ) ) );
		$this->assertTrue( $compare->isEqual( $linuxPath, \sha1( "one\r\ntwo\r\n" ) ) );
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
