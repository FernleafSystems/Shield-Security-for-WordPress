<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\PluginPackager\LegacyPathDuplicator;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

class LegacyUpgradeSimulationTest extends TestCase {

	private string $projectRoot;

	private string $tempDir;

	private Filesystem $fs;

	protected function setUp() :void {
		parent::setUp();
		$this->projectRoot = \dirname( \dirname( __DIR__ ) );
		$this->fs = new Filesystem();
		$this->tempDir = \sys_get_temp_dir().'/shield-upgrade-sim-'.\uniqid();
		$this->fs->mkdir( $this->tempDir );
	}

	protected function tearDown() :void {
		if ( \is_dir( $this->tempDir ) ) {
			$this->fs->remove( $this->tempDir );
		}
		parent::tearDown();
	}

	public function testLegacyAutoloaderCannotResolveShutdownClassesBeforeDuplication() :void {
		$this->setupMinimalSourceAndVendorStructure();

		$result = $this->runLegacyProbe( $this->tempDir, 'precheck' );
		$this->assertTrue( $result[ 'ok' ] ?? false, \json_encode( $result ) );

		$classes = [
			'FernleafSystems\\Wordpress\\Plugin\\Shield\\Controller\\Dependencies\\Monolog',
			'FernleafSystems\\Wordpress\\Plugin\\Shield\\Modules\\HackGuard\\Lib\\Snapshots\\FindAssetsToSnap',
			'FernleafSystems\\Wordpress\\Plugin\\Shield\\Modules\\IPs\\Components\\ProcessOffense',
			'FernleafSystems\\Wordpress\\Plugin\\Shield\\Modules\\IPs\\Lib\\Bots\\BotSignalsRecord',
			'FernleafSystems\\Wordpress\\Plugin\\Shield\\DBs\\Event\\Ops\\Handler',
			'FernleafSystems\\Wordpress\\Plugin\\Shield\\DBs\\CrowdSecSignals\\Ops\\Handler',
			'FernleafSystems\\Wordpress\\Plugin\\Shield\\Modules\\AuditTrail\\Lib\\Snapshots\\Ops\\Delete',
			'FernleafSystems\\Wordpress\\Plugin\\Shield\\Modules\\AuditTrail\\Lib\\Snapshots\\Ops\\Store',
		];

		foreach ( $classes as $className ) {
			$this->assertTrue( $result[ 'checks' ][ $className ][ 'ok' ] ?? false, \json_encode( $result[ 'checks' ][ $className ] ?? [] ) );
			$this->assertFalse( $result[ 'checks' ][ $className ][ 'found' ] ?? true );
		}
	}

	public function testLegacyAutoloaderResolvesGuardedShutdownClassesAfterDuplication() :void {
		$this->setupMinimalSourceAndVendorStructure();

		( new LegacyPathDuplicator( static function () {} ) )->createDuplicates( $this->tempDir );
		$result = $this->runLegacyProbe( $this->tempDir, 'guards' );

		$this->assertTrue( $result[ 'ok' ] ?? false, \json_encode( $result ) );
		$this->assertTrue( $result[ 'checks' ][ 'monolog_guard' ][ 'ok' ] ?? false );
		$this->assertTrue( $result[ 'checks' ][ 'find_assets_guard' ][ 'ok' ] ?? false );
		$this->assertTrue( $result[ 'checks' ][ 'process_offense_guard' ][ 'ok' ] ?? false );
		$this->assertTrue( $result[ 'checks' ][ 'bot_signals_guard' ][ 'ok' ] ?? false );
		$this->assertTrue( $result[ 'checks' ][ 'event_db_handler_guard' ][ 'ok' ] ?? false );
		$this->assertTrue( $result[ 'checks' ][ 'crowdsec_signals_db_handler_guard' ][ 'ok' ] ?? false );
		$this->assertTrue( $result[ 'checks' ][ 'snapshot_delete_guard' ][ 'ok' ] ?? false );
		$this->assertTrue( $result[ 'checks' ][ 'snapshot_store_guard' ][ 'ok' ] ?? false );

		$this->assertSame(
			'Legacy shutdown guard: monolog disabled.',
			$result[ 'checks' ][ 'monolog_guard' ][ 'details' ][ 'message' ] ?? ''
		);
		$this->assertSame(
			0,
			$result[ 'checks' ][ 'bot_signals_guard' ][ 'details' ][ 'notbot_at' ] ?? -1
		);
		$this->assertFalse(
			(bool)( $result[ 'checks' ][ 'event_db_handler_guard' ][ 'details' ][ 'isReady' ] ?? true )
		);
		$this->assertTrue(
			(bool)( $result[ 'checks' ][ 'crowdsec_signals_db_handler_guard' ][ 'details' ][ 'inserted' ] ?? false )
		);
		$this->assertSame(
			0,
			$result[ 'checks' ][ 'crowdsec_signals_db_handler_guard' ][ 'details' ][ 'count' ] ?? -1
		);
		$this->assertFalse(
			(bool)( $result[ 'checks' ][ 'snapshot_delete_guard' ][ 'details' ][ 'deleted' ] ?? true )
		);
		$this->assertFalse(
			(bool)( $result[ 'checks' ][ 'snapshot_store_guard' ][ 'details' ][ 'stored' ] ?? true )
		);

		$this->assertStringContainsString( '/src/lib/src/', $this->normalisePath( (string)( $result[ 'checks' ][ 'monolog_guard' ][ 'details' ][ 'file' ] ?? '' ) ) );
		$this->assertStringContainsString( '/src/lib/src/', $this->normalisePath( (string)( $result[ 'checks' ][ 'find_assets_guard' ][ 'details' ][ 'file' ] ?? '' ) ) );
		$this->assertStringContainsString( '/src/lib/src/', $this->normalisePath( (string)( $result[ 'checks' ][ 'process_offense_guard' ][ 'details' ][ 'file' ] ?? '' ) ) );
		$this->assertStringContainsString( '/src/lib/src/', $this->normalisePath( (string)( $result[ 'checks' ][ 'bot_signals_guard' ][ 'details' ][ 'file' ] ?? '' ) ) );
		$this->assertStringContainsString( '/src/lib/src/', $this->normalisePath( (string)( $result[ 'checks' ][ 'bot_signals_guard' ][ 'details' ][ 'recordFile' ] ?? '' ) ) );
		$this->assertStringContainsString( '/src/lib/src/', $this->normalisePath( (string)( $result[ 'checks' ][ 'event_db_handler_guard' ][ 'details' ][ 'file' ] ?? '' ) ) );
		$this->assertStringContainsString( '/src/lib/src/', $this->normalisePath( (string)( $result[ 'checks' ][ 'crowdsec_signals_db_handler_guard' ][ 'details' ][ 'file' ] ?? '' ) ) );
		$this->assertStringContainsString( '/src/lib/src/', $this->normalisePath( (string)( $result[ 'checks' ][ 'snapshot_delete_guard' ][ 'details' ][ 'file' ] ?? '' ) ) );
		$this->assertStringContainsString( '/src/lib/src/', $this->normalisePath( (string)( $result[ 'checks' ][ 'snapshot_store_guard' ][ 'details' ][ 'file' ] ?? '' ) ) );

		$this->assertFileExists( $this->tempDir.'/src/lib/src/DBs/BotSignal/BotSignalRecord.php' );
		$this->assertFileDoesNotExist( $this->tempDir.'/src/lib/src/DBs/BotSignal/LoadBotSignalRecords.php' );
		$this->assertFileDoesNotExist( $this->tempDir.'/src/lib/src/DBs/BotSignal/Ops/Handler.php' );
		$this->assertFileDoesNotExist( $this->tempDir.'/src/lib/src/DBs/BotSignal/Ops/Record.php' );
		$this->assertFileDoesNotExist( $this->tempDir.'/src/lib/src/DBs/BotSignal/Ops/Insert.php' );
		$this->assertFileDoesNotExist( $this->tempDir.'/src/lib/src/DBs/BotSignal/Ops/Delete.php' );
		$this->assertFileDoesNotExist( $this->tempDir.'/src/lib/src/DBs/BotSignal/Ops/Select.php' );
	}

	public function testBuiltPackageCanPassLegacyProbeWhenPackagePathProvided() :void {
		$packagePath = \getenv( 'SHIELD_PACKAGE_PATH' );
		if ( !\is_string( $packagePath ) || $packagePath === '' ) {
			$this->markTestSkipped( 'Legacy package probe runs only when SHIELD_PACKAGE_PATH is set.' );
		}

		$result = $this->runLegacyProbe( $packagePath, 'guards' );
		$this->assertTrue( $result[ 'ok' ] ?? false, \json_encode( $result ) );
	}

	private function setupMinimalSourceAndVendorStructure() :void {
		$srcRoot = $this->tempDir.'/src';
		$sourceSrcRoot = $this->projectRoot.'/src';

		foreach ( $this->getConstant( 'SRC_DIRECTORIES_TO_MIRROR' ) as $pathParts ) {
			$relativePath = \implode( '/', $pathParts );
			$sourcePath = $sourceSrcRoot.'/'.$relativePath;
			$targetPath = $srcRoot.'/'.$relativePath;

			if ( \is_dir( $sourcePath ) ) {
				$this->fs->mirror( $sourcePath, $targetPath );
			}
			else {
				$this->fs->mkdir( $targetPath );
				$this->fs->dumpFile( $targetPath.'/placeholder.php', '<?php' );
			}
		}

		foreach ( $this->getConstant( 'SRC_FILES_TO_COPY' ) as $pathParts ) {
			$relativePath = \implode( '/', $pathParts );
			$sourcePath = $sourceSrcRoot.'/'.$relativePath;
			$targetPath = $srcRoot.'/'.$relativePath;
			$this->fs->mkdir( \dirname( $targetPath ) );

			if ( \file_exists( $sourcePath ) ) {
				$this->fs->copy( $sourcePath, $targetPath );
			}
			else {
				$this->fs->dumpFile( $targetPath, '<?php' );
			}
		}

		foreach ( [
			'Modules/PluginControllerConsumer.php',
			'Modules/AuditTrail/Lib/Snapshots/SnapshotVO.php',
		] as $relativePath ) {
			$sourcePath = $sourceSrcRoot.'/'.$relativePath;
			$targetPath = $srcRoot.'/'.$relativePath;
			$this->fs->mkdir( \dirname( $targetPath ) );
			if ( \file_exists( $sourcePath ) ) {
				$this->fs->copy( $sourcePath, $targetPath );
			}
			else {
				$this->fs->dumpFile( $targetPath, '<?php' );
			}
		}

		$vendorPrefixedRoot = $this->tempDir.'/vendor_prefixed';
		foreach ( $this->getConstant( 'VENDOR_PREFIXED_DIRECTORIES_TO_MIRROR' ) as $pathParts ) {
			$dirPath = $vendorPrefixedRoot.'/'.\implode( '/', $pathParts );
			$this->fs->mkdir( $dirPath );
			$this->fs->dumpFile( $dirPath.'/placeholder.php', '<?php' );
		}
		foreach ( $this->getConstant( 'VENDOR_PREFIXED_FILES_TO_COPY' ) as $file ) {
			$this->fs->mkdir( \dirname( $vendorPrefixedRoot.'/'.$file ) );
			$this->fs->dumpFile( $vendorPrefixedRoot.'/'.$file, '<?php' );
		}

		$vendorRoot = $this->tempDir.'/vendor';
		foreach ( $this->getConstant( 'STD_VENDOR_DIRECTORIES_TO_MIRROR' ) as $pathParts ) {
			$dirPath = $vendorRoot.'/'.\implode( '/', $pathParts );
			$this->fs->mkdir( $dirPath );
			$this->fs->dumpFile( $dirPath.'/placeholder.php', '<?php' );
		}
		foreach ( $this->getConstant( 'STD_VENDOR_FILES_TO_COPY' ) as $file ) {
			$this->fs->mkdir( \dirname( $vendorRoot.'/'.$file ) );
			$this->fs->dumpFile( $vendorRoot.'/'.$file, '<?php' );
		}
	}

	private function runLegacyProbe( string $pluginRoot, string $scenario ) :array {
		$probePath = $this->projectRoot.'/tests/fixtures/legacy-upgrade/legacy_probe.php';
		$process = new Process(
			[
				\PHP_BINARY,
				$probePath,
				'--plugin-root='.$this->normalisePath( $pluginRoot ),
				'--scenario='.$scenario,
			],
			$this->projectRoot
		);
		$process->run();

		$this->assertSame(
			0,
			$process->getExitCode(),
			"Legacy probe exited unexpectedly.\nSTDOUT:\n".$process->getOutput()."\nSTDERR:\n".$process->getErrorOutput()
		);

		$decoded = \json_decode( \trim( $process->getOutput() ), true );
		$this->assertIsArray(
			$decoded,
			"Legacy probe did not emit JSON.\nSTDOUT:\n".$process->getOutput()."\nSTDERR:\n".$process->getErrorOutput()
		);

		return $decoded;
	}

	private function getConstant( string $name ) {
		$reflection = new ReflectionClass( LegacyPathDuplicator::class );
		return $reflection->getConstant( $name );
	}

	private function normalisePath( string $path ) :string {
		return \str_replace( '\\', '/', $path );
	}
}
