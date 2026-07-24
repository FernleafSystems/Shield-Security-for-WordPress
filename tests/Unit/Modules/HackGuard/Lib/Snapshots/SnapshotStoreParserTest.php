<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\HackGuard\Lib\Snapshots;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\Store;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	AssetSnapshots\SnapshotFs,
	ServicesState,
	UnitTestRequest
};
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\WpPluginVo;

class SnapshotStoreParserTest extends BaseUnitTest {

	use TempDirLifecycleTrait;

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		$this->servicesSnapshot = ServicesState::snapshot();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( 'path_join' )->alias( static fn( string $a, string $b ) :string => \rtrim( $a, '/\\' ).'/'.\ltrim( $b, '/\\' ) );
		ServicesState::installItems( [
			'service_request' => new UnitTestRequest( [], '127.0.0.1', \time() ),
			'service_wpfs'    => new SnapshotFs(),
		] );
	}

	protected function tearDown() :void {
		ServicesState::restore( $this->servicesSnapshot );
		$this->cleanupTrackedTempDirs();
		parent::tearDown();
	}

	public function test_parser_skips_malformed_lines_preserves_valid_siblings_and_uses_last_duplicate() :void {
		$store = ( new Store( new SnapshotStoreParserPluginVo(), true ) )
			->setWorkingDir( $this->createTrackedTempDir( 'shield-snapshot-parser-' ) );
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
