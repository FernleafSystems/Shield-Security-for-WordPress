<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\HackGuard\Lib\Snapshots\StoreAction;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\{
	HashesStorageDir,
	Store
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\StoreAction\ScheduleBuildAll;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	ServicesState,
	UnitTestRequest
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\AssetSnapshots\{
	SnapshotFs,
	SnapshotPlugins,
	SnapshotPluginVo,
	SnapshotThemes,
	SnapshotThemeVo
};

class ScheduleBuildAllTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	private array $tempDirs = [];

	protected function setUp() :void {
		parent::setUp();
		$this->servicesSnapshot = ServicesState::snapshot();
		$this->resetHashesStorageDir();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( 'path_join' )->alias( fn( string $a, string $b ) :string => $this->normalizePath( \rtrim( $a, '/\\' ).'/'.\ltrim( $b, '/\\' ) ) );
		Functions\when( 'wp_json_encode' )->alias( static fn( $data ) :string => \json_encode( $data ) );
	}

	protected function tearDown() :void {
		$this->resetHashesStorageDir();
		ServicesState::restore( $this->servicesSnapshot );
		foreach ( \array_reverse( $this->tempDirs ) as $dir ) {
			$this->removeDir( $dir );
		}
		parent::tearDown();
	}

	public function test_verified_current_snapshot_excludes_asset_from_build_list() :void {
		$asset = new SnapshotPluginVo( 'snapshot-current/plugin.php', '1.0.0' );
		$this->installEnvironment( [ $asset ] );
		$this->writeStore( $asset, [
			'plugin.php' => 'current-hash',
		], [
			'version'   => '1.0.0',
			'unique_id' => 'snapshot-current/plugin.php',
		] );

		$this->assertSame( [], $this->assetKeysThatNeedBuilt() );
	}

	public function test_missing_snapshot_includes_asset_in_build_list() :void {
		$asset = new SnapshotPluginVo( 'snapshot-missing/plugin.php', '1.0.0' );
		$this->installEnvironment( [ $asset ] );

		$this->assertSame( [ 'snapshot-missing/plugin.php' ], $this->assetKeysThatNeedBuilt() );
	}

	public function test_loadable_snapshot_with_mismatched_version_meta_includes_asset_in_build_list() :void {
		$asset = new SnapshotPluginVo( 'snapshot-stale/plugin.php', '2.0.0' );
		$this->installEnvironment( [ $asset ] );
		$this->writeStore( $asset, [
			'plugin.php' => 'old-hash',
		], [
			'version'   => '1.0.0',
			'unique_id' => 'snapshot-stale/plugin.php',
		] );

		$this->assertSame( [ 'snapshot-stale/plugin.php' ], $this->assetKeysThatNeedBuilt() );
	}

	public function test_verified_current_theme_snapshot_excludes_asset_from_build_list() :void {
		$asset = new SnapshotThemeVo( 'snapshot-current-theme', '1.0.0' );
		$this->installEnvironment( [], [ $asset ] );
		$this->writeStore( $asset, [
			'style.css' => 'current-hash',
		], [
			'version'   => '1.0.0',
			'unique_id' => 'snapshot-current-theme',
		] );

		$this->assertSame( [], $this->assetKeysThatNeedBuilt() );
	}

	public function test_missing_current_theme_snapshot_includes_asset_in_build_list() :void {
		$asset = new SnapshotThemeVo( 'snapshot-missing-theme', '1.0.0' );
		$this->installEnvironment( [], [ $asset ] );

		$this->assertSame( [ 'snapshot-missing-theme' ], $this->assetKeysThatNeedBuilt() );
	}

	public function test_loadable_current_theme_snapshot_with_mismatched_version_meta_includes_asset_in_build_list() :void {
		$asset = new SnapshotThemeVo( 'snapshot-stale-theme', '2.0.0' );
		$this->installEnvironment( [], [ $asset ] );
		$this->writeStore( $asset, [
			'style.css' => 'old-hash',
		], [
			'version'   => '1.0.0',
			'unique_id' => 'snapshot-stale-theme',
		] );

		$this->assertSame( [ 'snapshot-stale-theme' ], $this->assetKeysThatNeedBuilt() );
	}

	/**
	 * @param SnapshotPluginVo[] $plugins
	 * @param SnapshotThemeVo[]  $themes
	 */
	private function installEnvironment( array $plugins, array $themes = [] ) :void {
		$this->setHashesStorageDir( $this->makeTempDir( 'hashes' ) );
		ServicesState::installItems( [
			'service_request'   => new UnitTestRequest( [], '127.0.0.1', 1700000500 ),
			'service_wpfs'      => new SnapshotFs(),
			'service_wpplugins' => new SnapshotPlugins( $plugins ),
			'service_wpthemes'  => new SnapshotThemes( $themes ),
		] );
	}

	/**
	 * @param SnapshotPluginVo|SnapshotThemeVo $asset
	 */
	private function writeStore( $asset, array $hashes, array $meta ) :void {
		( new Store( $asset, true ) )
			->setWorkingDir( ( new HashesStorageDir() )->getTempDir() )
			->setSnapData( $hashes )
			->setSnapMeta( $meta )
			->save();
	}

	/**
	 * @return string[]
	 */
	private function assetKeysThatNeedBuilt() :array {
		$method = new \ReflectionMethod( ScheduleBuildAll::class, 'getAssetsThatNeedBuilt' );
		$method->setAccessible( true );

		return \array_values( \array_map(
			static fn( $asset ) :string => $asset->asset_type === 'plugin' ? $asset->file : $asset->stylesheet,
			$method->invoke( new ScheduleBuildAll() )
		) );
	}

	private function setHashesStorageDir( string $dir ) :void {
		$property = ( new \ReflectionClass( HashesStorageDir::class ) )->getProperty( 'dir' );
		$property->setAccessible( true );
		$property->setValue( null, $dir );
	}

	private function resetHashesStorageDir() :void {
		$property = ( new \ReflectionClass( HashesStorageDir::class ) )->getProperty( 'dir' );
		$property->setAccessible( true );
		$property->setValue( null, null );
	}

	private function makeTempDir( string $suffix ) :string {
		$dir = $this->normalizePath( \sys_get_temp_dir().'/shield-schedule-build-'.$suffix.'-'.\uniqid() );
		@mkdir( $dir, 0777, true );
		$this->tempDirs[] = $dir;
		return $dir;
	}

	private function normalizePath( string $path ) :string {
		return \str_replace( '\\', '/', $path );
	}

	private function removeDir( string $dir ) :void {
		if ( !\is_dir( $dir ) ) {
			return;
		}
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $iterator as $item ) {
			$item->isDir() ? @rmdir( $item->getPathname() ) : @unlink( $item->getPathname() );
		}
		@rmdir( $dir );
	}
}
