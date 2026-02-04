<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\PluginPackager\LegacyPathDuplicator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Unit tests for LegacyPathDuplicator.
 * Tests directory mirroring and file copying for upgrade compatibility.
 */
class LegacyPathDuplicatorTest extends TestCase {

	private string $tempDir;

	private Filesystem $fs;

	protected function setUp() :void {
		parent::setUp();
		$this->fs = new Filesystem();
		$this->tempDir = sys_get_temp_dir().'/shield-test-'.uniqid();
		$this->fs->mkdir( $this->tempDir );
	}

	protected function tearDown() :void {
		if ( is_dir( $this->tempDir ) ) {
			$this->fs->remove( $this->tempDir );
		}
		parent::tearDown();
	}

	private function createDuplicator( ?callable $logger = null ) :LegacyPathDuplicator {
		return new LegacyPathDuplicator( $logger ?? function () {} );
	}

	private function setupMinimalPackageStructure() :void {
		// Create minimal directory structure that the duplicator expects
		// Source directories
		$this->fs->mkdir( $this->tempDir.'/src/Controller/Config' );
		$this->fs->mkdir( $this->tempDir.'/src/DBs' );
		$this->fs->mkdir( $this->tempDir.'/src/Logging' );
		$this->fs->mkdir( $this->tempDir.'/src/Modules/IPs/Lib/IpRules' );

		// Source files directories
		$this->fs->mkdir( $this->tempDir.'/src/Controller/Dependencies' );
		$this->fs->mkdir( $this->tempDir.'/src/Modules/AuditTrail/Lib/LogHandlers' );
		$this->fs->mkdir( $this->tempDir.'/src/Modules/HackGuard/Lib/Snapshots/StoreAction' );
		$this->fs->mkdir( $this->tempDir.'/src/Modules/IPs/Components' );
		$this->fs->mkdir( $this->tempDir.'/src/Modules/IPs/Lib/Bots' );
		$this->fs->mkdir( $this->tempDir.'/src/Modules/Traffic/Lib/LogHandlers' );

		// Vendor prefixed
		$this->fs->mkdir( $this->tempDir.'/vendor_prefixed/monolog' );
		$this->fs->mkdir( $this->tempDir.'/vendor_prefixed/composer' );

		// Standard vendor
		$this->fs->mkdir( $this->tempDir.'/vendor/mlocati/ip-lib' );
		$this->fs->mkdir( $this->tempDir.'/vendor/composer' );

		// Create sample files
		$this->fs->dumpFile( $this->tempDir.'/src/Controller/Config/ConfigTest.php', '<?php' );
		$this->fs->dumpFile( $this->tempDir.'/src/DBs/DbTest.php', '<?php' );
		$this->fs->dumpFile( $this->tempDir.'/src/Logging/LogTest.php', '<?php' );
		$this->fs->dumpFile( $this->tempDir.'/src/Modules/IPs/Lib/IpRules/IpRuleTest.php', '<?php' );

		// Individual source files
		$this->fs->dumpFile( $this->tempDir.'/src/Controller/Dependencies/Monolog.php', '<?php' );
		$this->fs->dumpFile( $this->tempDir.'/src/Modules/AuditTrail/Lib/ActivityLogMessageBuilder.php', '<?php' );
		$this->fs->dumpFile( $this->tempDir.'/src/Modules/AuditTrail/Lib/LogHandlers/LocalDbWriter.php', '<?php' );
		$this->fs->dumpFile( $this->tempDir.'/src/Modules/HackGuard/Lib/Snapshots/FindAssetsToSnap.php', '<?php' );
		$this->fs->dumpFile( $this->tempDir.'/src/Modules/HackGuard/Lib/Snapshots/StoreAction/Load.php', '<?php' );
		$this->fs->dumpFile( $this->tempDir.'/src/Modules/IPs/Components/ProcessOffense.php', '<?php' );
		$this->fs->dumpFile( $this->tempDir.'/src/Modules/IPs/Lib/Bots/BotSignalsRecord.php', '<?php' );
		$this->fs->dumpFile( $this->tempDir.'/src/Modules/Traffic/Lib/LogHandlers/LocalDbWriter.php', '<?php' );

		// Vendor prefixed files
		$this->fs->dumpFile( $this->tempDir.'/vendor_prefixed/autoload.php', '<?php' );
		$this->fs->dumpFile( $this->tempDir.'/vendor_prefixed/autoload-classmap.php', '<?php' );
		$this->fs->dumpFile( $this->tempDir.'/vendor_prefixed/monolog/Logger.php', '<?php' );
		$this->fs->dumpFile( $this->tempDir.'/vendor_prefixed/composer/autoload_classmap.php', '<?php' );

		// Standard vendor files
		$this->fs->dumpFile( $this->tempDir.'/vendor/autoload.php', '<?php' );
		$this->fs->dumpFile( $this->tempDir.'/vendor/mlocati/ip-lib/IpLib.php', '<?php' );
		$this->fs->dumpFile( $this->tempDir.'/vendor/composer/autoload_real.php', '<?php' );
	}

	// =========================================================================
	// createDuplicates() tests
	// =========================================================================

	public function testCreateDuplicatesCreatesLegacyDirectoryStructure() :void {
		$this->setupMinimalPackageStructure();

		$duplicator = $this->createDuplicator();
		$duplicator->createDuplicates( $this->tempDir );

		// Check legacy directories exist
		$this->assertDirectoryExists( $this->tempDir.'/src/lib/src' );
		$this->assertDirectoryExists( $this->tempDir.'/src/lib/vendor_prefixed' );
		$this->assertDirectoryExists( $this->tempDir.'/src/lib/vendor' );
	}

	public function testCreateDuplicatesMirrorsSourceDirectories() :void {
		$this->setupMinimalPackageStructure();

		$duplicator = $this->createDuplicator();
		$duplicator->createDuplicates( $this->tempDir );

		// Check mirrored directories exist
		$this->assertDirectoryExists( $this->tempDir.'/src/lib/src/Controller/Config' );
		$this->assertDirectoryExists( $this->tempDir.'/src/lib/src/DBs' );
		$this->assertDirectoryExists( $this->tempDir.'/src/lib/src/Logging' );
		$this->assertDirectoryExists( $this->tempDir.'/src/lib/src/Modules/IPs/Lib/IpRules' );

		// Check files were copied
		$this->assertFileExists( $this->tempDir.'/src/lib/src/Controller/Config/ConfigTest.php' );
		$this->assertFileExists( $this->tempDir.'/src/lib/src/DBs/DbTest.php' );
	}

	public function testCreateDuplicatesCopiesIndividualSourceFiles() :void {
		$this->setupMinimalPackageStructure();

		$duplicator = $this->createDuplicator();
		$duplicator->createDuplicates( $this->tempDir );

		// Check individual files were copied
		$this->assertFileExists( $this->tempDir.'/src/lib/src/Controller/Dependencies/Monolog.php' );
		$this->assertFileExists( $this->tempDir.'/src/lib/src/Modules/AuditTrail/Lib/ActivityLogMessageBuilder.php' );
		$this->assertFileExists( $this->tempDir.'/src/lib/src/Modules/HackGuard/Lib/Snapshots/FindAssetsToSnap.php' );
		$this->assertFileExists( $this->tempDir.'/src/lib/src/Modules/IPs/Components/ProcessOffense.php' );
	}

	public function testCreateDuplicatesMirrorsVendorPrefixedDirectories() :void {
		$this->setupMinimalPackageStructure();

		$duplicator = $this->createDuplicator();
		$duplicator->createDuplicates( $this->tempDir );

		// Check vendor_prefixed directories were mirrored
		$this->assertDirectoryExists( $this->tempDir.'/src/lib/vendor_prefixed/monolog' );
		$this->assertDirectoryExists( $this->tempDir.'/src/lib/vendor_prefixed/composer' );
		$this->assertFileExists( $this->tempDir.'/src/lib/vendor_prefixed/monolog/Logger.php' );
	}

	public function testCreateDuplicatesCopiesVendorPrefixedFiles() :void {
		$this->setupMinimalPackageStructure();

		$duplicator = $this->createDuplicator();
		$duplicator->createDuplicates( $this->tempDir );

		// Check vendor_prefixed files were copied
		$this->assertFileExists( $this->tempDir.'/src/lib/vendor_prefixed/autoload.php' );
		$this->assertFileExists( $this->tempDir.'/src/lib/vendor_prefixed/autoload-classmap.php' );
	}

	public function testCreateDuplicatesMirrorsStandardVendorDirectories() :void {
		$this->setupMinimalPackageStructure();

		$duplicator = $this->createDuplicator();
		$duplicator->createDuplicates( $this->tempDir );

		// Check standard vendor directories were mirrored
		$this->assertDirectoryExists( $this->tempDir.'/src/lib/vendor/mlocati/ip-lib' );
		$this->assertDirectoryExists( $this->tempDir.'/src/lib/vendor/composer' );
		$this->assertFileExists( $this->tempDir.'/src/lib/vendor/mlocati/ip-lib/IpLib.php' );
	}

	public function testCreateDuplicatesCopiesStandardVendorFiles() :void {
		$this->setupMinimalPackageStructure();

		$duplicator = $this->createDuplicator();
		$duplicator->createDuplicates( $this->tempDir );

		// Check standard vendor autoload was copied
		$this->assertFileExists( $this->tempDir.'/src/lib/vendor/autoload.php' );
	}

	public function testCreateDuplicatesLogsProgress() :void {
		$this->setupMinimalPackageStructure();

		$messages = [];
		$duplicator = $this->createDuplicator( function ( string $msg ) use ( &$messages ) {
			$messages[] = $msg;
		} );
		$duplicator->createDuplicates( $this->tempDir );

		$this->assertTrue( \count( $messages ) > 0 );
		$hasSuccessMessage = \count( \array_filter(
			$messages,
			fn( $m ) => \strpos( $m, 'legacy path duplicates' ) !== false
		) ) > 0;
		$this->assertTrue( $hasSuccessMessage );
	}
}
