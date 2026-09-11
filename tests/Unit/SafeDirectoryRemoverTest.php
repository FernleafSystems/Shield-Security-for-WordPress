<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\PluginPackager\SafeDirectoryRemover;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\Filesystem\Path;

/**
 * Unit tests for SafeDirectoryRemover.
 * Focus: Test safety validation logic that prevents accidental system damage.
 */
class SafeDirectoryRemoverTest extends TestCase {

	use TempDirLifecycleTrait;

	private string $projectRoot;

	protected function setUp() :void {
		parent::setUp();
		$this->projectRoot = dirname( dirname( __DIR__ ) );
	}

	protected function tearDown() :void {
		$this->cleanupTrackedTempDirs();
		parent::tearDown();
	}

	private function invokePrivateMethod( object $object, string $methodName, array $args = [] ) {
		$reflection = new ReflectionClass( $object );
		$method = $reflection->getMethod( $methodName );
		$method->setAccessible( true );
		return $method->invokeArgs( $object, $args );
	}

	private function createRemover() :SafeDirectoryRemover {
		return new SafeDirectoryRemover( $this->projectRoot );
	}

	// =========================================================================
	// Dangerous path blocking - Critical safety tests
	// =========================================================================

	/**
	 * @dataProvider providerDangerousPaths
	 */
	public function testBlocksDangerousPaths( string $dangerousPath ) :void {
		$remover = $this->createRemover();

		$this->expectException( \RuntimeException::class );
		$this->invokePrivateMethod( $remover, 'validateDirectoryOutsideProject', [ $dangerousPath ] );
	}

	public static function providerDangerousPaths() :array {
		return [
			'unix root'              => [ '/' ],
			'unix bin'               => [ '/bin' ],
			'unix etc'               => [ '/etc' ],
			'unix usr'               => [ '/usr' ],
			'unix var'               => [ '/var' ],
			'windows c: root'        => [ 'c:/' ],
			'windows c: lowercase'   => [ 'c:' ],
			'windows system'         => [ 'c:/windows' ],
			'windows system32'       => [ 'c:/windows/system32' ],
			'windows program files'  => [ 'c:/program files' ],
			'too short path'         => [ '/a' ],
		];
	}

	/**
	 * Test that paths inside the project root are blocked
	 */
	public function testBlocksProjectInternalPaths() :void {
		$remover = $this->createRemover();

		// Path that is within the project root
		$internalPath = Path::join( $this->projectRoot, 'tests' );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Cannot build package within project directory' );
		$this->invokePrivateMethod( $remover, 'validateDirectoryIsSafeToDelete', [ $internalPath ] );
	}

	public function testBlocksProjectChildWhenProjectRootHasDotSegmentSpelling() :void {
		$projectRoot = $this->createTrackedTempDir( 'shield-project-root-' );
		$child = Path::join( $projectRoot, 'child' );
		mkdir( $child, 0777, true );
		$remover = new SafeDirectoryRemover( Path::join( $projectRoot, '.' ) );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Cannot build package within project directory' );
		$this->invokePrivateMethod( $remover, 'validateDirectoryIsSafeToDelete', [ (string)\realpath( $child ) ] );
	}

	public function testAllowsExternalSiblingWithSamePathPrefix() :void {
		$parent = $this->createTrackedTempDir( 'shield-remover-parent-' );
		$projectRoot = Path::join( $parent, 'project' );
		$externalSibling = Path::join( $parent, 'project-output' );
		mkdir( $projectRoot, 0777, true );
		mkdir( $externalSibling, 0777, true );
		$remover = new SafeDirectoryRemover( $projectRoot );

		$this->invokePrivateMethod( $remover, 'validateDirectoryIsSafeToDelete', [ (string)\realpath( $externalSibling ) ] );
		$this->assertDirectoryExists( $externalSibling );
	}

	/**
	 * Test subdirectory validation - must be inside parent
	 */
	public function testRemoveSubdirectoryRequiresValidParent() :void {
		$remover = $this->createRemover();

		$parentDir = $this->createTrackedTempDir( 'shield-test-parent-' );
		$childDir = Path::join( $parentDir, 'child' );

		mkdir( $childDir, 0777, true );

		$remover->removeSubdirectoryOf( $childDir, $parentDir );
		$this->assertDirectoryDoesNotExist( $childDir );
	}

	/**
	 * Test that removing directory outside claimed parent throws exception
	 */
	public function testRemoveSubdirectoryBlocksOutsideParent() :void {
		$remover = $this->createRemover();

		$dir1 = $this->createTrackedTempDir( 'shield-test-dir1-' );
		$dir2 = $this->createTrackedTempDir( 'shield-test-dir2-' );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'SAFETY CHECK FAILED' );
		$remover->removeSubdirectoryOf( $dir1, $dir2 );
	}

	/**
	 * Test that removeTempDirectory works in system temp
	 */
	public function testRemoveTempDirectoryWorksInSystemTemp() :void {
		$remover = $this->createRemover();

		$testDir = $this->createTrackedTempDir( 'shield-test-temp-' );

		$remover->removeTempDirectory( $testDir );
		$this->assertDirectoryDoesNotExist( $testDir );
	}

	/**
	 * Test that removeTempDirectory blocks paths outside allowed locations
	 */
	public function testRemoveTempDirectoryBlocksArbitraryPaths() :void {
		$remover = $this->createRemover();

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Refusing to delete directory outside allowed paths' );

		// Try to remove project root (which is not in temp or allowed base)
		$remover->removeTempDirectory( $this->projectRoot );
	}

	/**
	 * Test that non-existent directory is handled gracefully
	 */
	public function testRemoveSafelyHandlesNonExistentDirectory() :void {
		$remover = $this->createRemover();
		$missingDirectory = $this->createTrackedTempPath( 'shield-missing-safe-dir-' );

		$remover->removeSafely( $missingDirectory );

		$this->assertDirectoryDoesNotExist( $missingDirectory );
	}
}
