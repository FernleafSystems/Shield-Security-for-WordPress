<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\HackGuard\Lib\Snapshots;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\Store;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	CacheStore\CacheStoreTestFs,
	ServicesState,
	UnitTestRequest
};
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\WpPluginVo;

class SnapshotStoreParserTest extends BaseUnitTest {

	use TempDirLifecycleTrait;

	private const MD5 = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

	private array $servicesSnapshot = [];
	private CacheStoreTestFs $fs;

	protected function setUp() :void {
		parent::setUp();
		$this->servicesSnapshot = ServicesState::snapshot();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( 'path_join' )->alias( static fn( string $a, string $b ) :string => \rtrim( $a, '/\\' ).'/'.\ltrim( $b, '/\\' ) );
		$this->fs = new CacheStoreTestFs();
		ServicesState::installItems( [
			'service_request' => new UnitTestRequest( [], '127.0.0.1', \time() ),
			'service_wpfs'    => $this->fs,
		] );
	}

	protected function tearDown() :void {
		ServicesState::restore( $this->servicesSnapshot );
		$this->cleanupTrackedTempDirs();
		parent::tearDown();
	}

	public function test_parser_skips_malformed_lines_preserves_valid_siblings_and_uses_last_duplicate() :void {
		$store = $this->newStore();
		$content = \implode( "\n", [
			'valid.php'.Store::SEPARATOR.'first',
			'missing-separator',
			Store::SEPARATOR.'missing-file',
			'missing-hash'.Store::SEPARATOR,
			'duplicate.php'.Store::SEPARATOR.'old',
			'',
			'duplicate.php'.Store::SEPARATOR.'new',
			'extra.php'.Store::SEPARATOR.'hash'.Store::SEPARATOR.'extra',
		] );
		\mkdir( \dirname( $store->getSnapStorePath() ), 0777, true );
		\file_put_contents( $store->getSnapStorePath(), \gzdeflate( $content ) );

		$this->assertSame( [
			'valid.php'     => 'first',
			'duplicate.php' => 'new',
			'extra.php'     => 'hash'.Store::SEPARATOR.'extra',
		], $store->getSnapData() );
	}

	public function test_usable_store_requires_exact_metadata_and_a_nonempty_canonical_payload() :void {
		$store = $this->newStore();
		$this->writeRawStore(
			$store,
			"src/File.php".Store::SEPARATOR.self::MD5,
			[
				'unique_id' => 'parser/plugin.php',
				'version'   => '1.0.0',
			]
		);

		$this->assertTrue( $store->isUsable() );
	}

	/**
	 * @dataProvider provideMismatchedMetadata
	 */
	public function test_usable_store_rejects_mismatched_asset_metadata( array $meta ) :void {
		$store = $this->newStore();
		$this->writeRawStore( $store, 'file.php'.Store::SEPARATOR.self::MD5, $meta );
		$this->fs->compressedReadCounts = [];

		$this->assertFalse( $store->isUsable() );
		$this->assertSame( 1, $this->compressedReads( $store->getSnapStoreMetaPath() ) );
		$this->assertSame( 0, $this->compressedReads( $store->getSnapStorePath() ) );
	}

	public function provideMismatchedMetadata() :array {
		return [
			'wrong identity' => [ [
				'unique_id' => 'other/plugin.php',
				'version'   => '1.0.0',
			] ],
			'wrong version' => [ [
				'unique_id' => 'parser/plugin.php',
				'version'   => '2.0.0',
			] ],
			'missing identity' => [ [
				'version' => '1.0.0',
			] ],
			'missing version' => [ [
				'unique_id' => 'parser/plugin.php',
			] ],
		];
	}

	/**
	 * @dataProvider provideInvalidStrictPayloads
	 */
	public function test_usable_store_rejects_invalid_persisted_payloads( string $payload ) :void {
		$store = $this->newStore();
		$this->writeRawStore(
			$store,
			$payload,
			[
				'unique_id' => 'parser/plugin.php',
				'version'   => '1.0.0',
			]
		);

		$this->assertFalse( $store->isUsable() );
	}

	public function provideInvalidStrictPayloads() :array {
		return [
			'empty' => [ '' ],
			'blank only' => [ " \n\t" ],
			'malformed sibling' => [ \implode( "\n", [
				'valid.php'.Store::SEPARATOR.self::MD5,
				'missing-separator',
			] ) ],
			'empty path' => [ Store::SEPARATOR.self::MD5 ],
			'empty hash' => [ 'file.php'.Store::SEPARATOR ],
			'unsupported hash' => [ 'file.php'.Store::SEPARATOR.'unsupported-hash' ],
			'duplicate raw path' => [ \implode( "\n", [
				'file.php'.Store::SEPARATOR.self::MD5,
				'file.php'.Store::SEPARATOR.\str_repeat( 'b', 32 ),
			] ) ],
			'normalised path collision' => [ \implode( "\n", [
				'src\\File.php'.Store::SEPARATOR.self::MD5,
				'src/File.php'.Store::SEPARATOR.\str_repeat( 'b', 32 ),
			] ) ],
			'noncanonical path' => [ 'src\\File.php'.Store::SEPARATOR.self::MD5 ],
			'extra separator' => [ 'file.php'.Store::SEPARATOR.self::MD5.Store::SEPARATOR.'extra' ],
		];
	}

	public function test_usable_store_reads_payload_directly_instead_of_using_tolerant_cached_data() :void {
		$store = $this->newStore();
		$meta = [
			'unique_id' => 'parser/plugin.php',
			'version'   => '1.0.0',
		];
		$this->writeRawStore( $store, 'file.php'.Store::SEPARATOR.self::MD5, $meta );
		$this->assertSame( [ 'file.php' => self::MD5 ], $store->getSnapData() );

		$this->writeRawStore( $store, 'malformed-record', $meta );

		$this->assertSame( [ 'file.php' => self::MD5 ], $store->getSnapData() );
		$this->assertFalse( $store->isUsable() );
	}

	public function test_usable_store_returns_false_when_payload_cannot_be_read() :void {
		$store = $this->newStore();
		$this->writeRawStore(
			$store,
			'file.php'.Store::SEPARATOR.self::MD5,
			[
				'unique_id' => 'parser/plugin.php',
				'version'   => '1.0.0',
			]
		);
		$this->fs->failFileRead( $store->getSnapStorePath() );
		$this->fs->compressedReadCounts = [];

		$this->assertFalse( $store->isUsable() );
		$this->assertSame( 1, $this->compressedReads( $store->getSnapStoreMetaPath() ) );
		$this->assertSame( 1, $this->compressedReads( $store->getSnapStorePath() ) );
	}

	private function newStore() :Store {
		return ( new Store( new SnapshotStoreParserPluginVo(), true ) )
			->setWorkingDir( $this->createTrackedTempDir( 'shield-snapshot-parser-' ) );
	}

	private function writeRawStore( Store $store, string $payload, array $meta ) :void {
		$dir = \dirname( $store->getSnapStorePath() );
		if ( !\is_dir( $dir ) ) {
			\mkdir( $dir, 0777, true );
		}
		\file_put_contents( $store->getSnapStorePath(), \gzdeflate( $payload ) );
		\file_put_contents( $store->getSnapStoreMetaPath(), \gzdeflate( \json_encode( $meta ) ) );
	}

	private function compressedReads( string $path ) :int {
		return $this->fs->compressedReadCounts[ $this->fs->normalise( $path ) ] ?? 0;
	}
}

class SnapshotStoreParserPluginVo extends WpPluginVo {

	public string $file = 'parser/plugin.php';
	public string $Version = '1.0.0';

	public function __construct() {
	}

	public function __get( string $key ) {
		return $key === 'asset_type' ? 'plugin' : parent::__get( $key );
	}
}
