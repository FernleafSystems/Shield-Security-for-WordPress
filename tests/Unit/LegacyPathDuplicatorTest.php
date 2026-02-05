<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\PluginPackager\LegacyPathDuplicator;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
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

	private function getConstant( string $name ) {
		$reflection = new ReflectionClass( LegacyPathDuplicator::class );
		return $reflection->getConstant( $name );
	}

	private function setupMinimalPackageStructure() :void {
		// Source directories to mirror — create dir + dummy file so mirror has content
		foreach ( $this->getConstant( 'SRC_DIRECTORIES_TO_MIRROR' ) as $pathParts ) {
			$dirPath = $this->tempDir.'/src/'.\implode( '/', $pathParts );
			$this->fs->mkdir( $dirPath );
			$this->fs->dumpFile( $dirPath.'/'.\end( $pathParts ).'Test.php', '<?php' );
		}

		// Individual source files to copy
		foreach ( $this->getConstant( 'SRC_FILES_TO_COPY' ) as $pathParts ) {
			$filePath = $this->tempDir.'/src/'.\implode( '/', $pathParts );
			$this->fs->mkdir( \dirname( $filePath ) );
			$this->fs->dumpFile( $filePath, '<?php' );
		}

		// Vendor prefixed directories to mirror — create dir + dummy file
		foreach ( $this->getConstant( 'VENDOR_PREFIXED_DIRECTORIES_TO_MIRROR' ) as $pathParts ) {
			$dirPath = $this->tempDir.'/vendor_prefixed/'.\implode( '/', $pathParts );
			$this->fs->mkdir( $dirPath );
			$this->fs->dumpFile( $dirPath.'/'.\ucfirst( \end( $pathParts ) ).'Dummy.php', '<?php' );
		}

		// Vendor prefixed files to copy
		foreach ( $this->getConstant( 'VENDOR_PREFIXED_FILES_TO_COPY' ) as $file ) {
			$this->fs->dumpFile( $this->tempDir.'/vendor_prefixed/'.$file, '<?php' );
		}

		// Standard vendor directories to mirror — create dir + dummy file
		foreach ( $this->getConstant( 'STD_VENDOR_DIRECTORIES_TO_MIRROR' ) as $pathParts ) {
			$dirPath = $this->tempDir.'/vendor/'.\implode( '/', $pathParts );
			$this->fs->mkdir( $dirPath );
			$this->fs->dumpFile( $dirPath.'/'.\ucfirst( \end( $pathParts ) ).'Dummy.php', '<?php' );
		}

		// Standard vendor files to copy
		foreach ( $this->getConstant( 'STD_VENDOR_FILES_TO_COPY' ) as $file ) {
			$this->fs->dumpFile( $this->tempDir.'/vendor/'.$file, '<?php' );
		}
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

		foreach ( $this->getConstant( 'SRC_DIRECTORIES_TO_MIRROR' ) as $pathParts ) {
			$legacyPath = $this->tempDir.'/src/lib/src/'.\implode( '/', $pathParts );
			$this->assertDirectoryExists( $legacyPath );
			$this->assertFileExists( $legacyPath.'/'.\end( $pathParts ).'Test.php' );
		}
	}

	public function testCreateDuplicatesCopiesIndividualSourceFiles() :void {
		$this->setupMinimalPackageStructure();

		$duplicator = $this->createDuplicator();
		$duplicator->createDuplicates( $this->tempDir );

		foreach ( $this->getConstant( 'SRC_FILES_TO_COPY' ) as $pathParts ) {
			$this->assertFileExists(
				$this->tempDir.'/src/lib/src/'.\implode( '/', $pathParts )
			);
		}
	}

	public function testCreateDuplicatesMirrorsVendorPrefixedDirectories() :void {
		$this->setupMinimalPackageStructure();

		$duplicator = $this->createDuplicator();
		$duplicator->createDuplicates( $this->tempDir );

		foreach ( $this->getConstant( 'VENDOR_PREFIXED_DIRECTORIES_TO_MIRROR' ) as $pathParts ) {
			$legacyPath = $this->tempDir.'/src/lib/vendor_prefixed/'.\implode( '/', $pathParts );
			$this->assertDirectoryExists( $legacyPath );
			$this->assertFileExists( $legacyPath.'/'.\ucfirst( \end( $pathParts ) ).'Dummy.php' );
		}
	}

	public function testCreateDuplicatesCopiesVendorPrefixedFiles() :void {
		$this->setupMinimalPackageStructure();

		$duplicator = $this->createDuplicator();
		$duplicator->createDuplicates( $this->tempDir );

		foreach ( $this->getConstant( 'VENDOR_PREFIXED_FILES_TO_COPY' ) as $file ) {
			$this->assertFileExists( $this->tempDir.'/src/lib/vendor_prefixed/'.$file );
		}
	}

	public function testCreateDuplicatesMirrorsStandardVendorDirectories() :void {
		$this->setupMinimalPackageStructure();

		$duplicator = $this->createDuplicator();
		$duplicator->createDuplicates( $this->tempDir );

		foreach ( $this->getConstant( 'STD_VENDOR_DIRECTORIES_TO_MIRROR' ) as $pathParts ) {
			$legacyPath = $this->tempDir.'/src/lib/vendor/'.\implode( '/', $pathParts );
			$this->assertDirectoryExists( $legacyPath );
			$this->assertFileExists( $legacyPath.'/'.\ucfirst( \end( $pathParts ) ).'Dummy.php' );
		}
	}

	public function testCreateDuplicatesCopiesStandardVendorFiles() :void {
		$this->setupMinimalPackageStructure();

		$duplicator = $this->createDuplicator();
		$duplicator->createDuplicates( $this->tempDir );

		foreach ( $this->getConstant( 'STD_VENDOR_FILES_TO_COPY' ) as $file ) {
			$this->assertFileExists( $this->tempDir.'/src/lib/vendor/'.$file );
		}
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
