<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\PluginPackager\SafeDirectoryRemover;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\Filesystem\Path;

/**
 * Unit tests for SafeDirectoryRemover.
 * Focus: Test safety validation logic that prevents accidental system damage.
 */
class SafeDirectoryRemoverTest extends TestCase {

	private string $projectRoot;

	protected function setUp() :void {
		parent::setUp();
		$this->projectRoot = dirname( dirname( __DIR__ ) );
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

	/**
	 * Test subdirectory validation - must be inside parent
	 */
	public function testRemoveSubdirectoryRequiresValidParent() :void {
		$remover = $this->createRemover();
		$tempDir = sys_get_temp_dir();

		// Create a temp test directory
		$parentDir = Path::join( $tempDir, 'shield-test-parent-'.uniqid() );
		$childDir = Path::join( $parentDir, 'child' );

		mkdir( $parentDir, 0777, true );
		mkdir( $childDir, 0777, true );

		try {
			// This should work - child is inside parent
			$remover->removeSubdirectoryOf( $childDir, $parentDir );
			$this->assertDirectoryDoesNotExist( $childDir );
		}
		finally {
			// Cleanup
			if ( is_dir( $childDir ) ) {
				rmdir( $childDir );
			}
			if ( is_dir( $parentDir ) ) {
				rmdir( $parentDir );
			}
		}
	}

	/**
	 * Test that removing directory outside claimed parent throws exception
	 */
	public function testRemoveSubdirectoryBlocksOutsideParent() :void {
		$remover = $this->createRemover();
		$tempDir = sys_get_temp_dir();

		// Create two unrelated directories
		$dir1 = Path::join( $tempDir, 'shield-test-dir1-'.uniqid() );
		$dir2 = Path::join( $tempDir, 'shield-test-dir2-'.uniqid() );

		mkdir( $dir1, 0777, true );
		mkdir( $dir2, 0777, true );

		try {
			$this->expectException( \RuntimeException::class );
			$this->expectExceptionMessage( 'SAFETY CHECK FAILED' );

			// Try to remove dir1 claiming it's a subdirectory of dir2 (it's not)
			$remover->removeSubdirectoryOf( $dir1, $dir2 );
		}
		finally {
			// Cleanup
			if ( is_dir( $dir1 ) ) {
				rmdir( $dir1 );
			}
			if ( is_dir( $dir2 ) ) {
				rmdir( $dir2 );
			}
		}
	}

	/**
	 * Test that removeTempDirectory works in system temp
	 */
	public function testRemoveTempDirectoryWorksInSystemTemp() :void {
		$remover = $this->createRemover();
		$tempDir = sys_get_temp_dir();

		// Create a temp test directory
		$testDir = Path::join( $tempDir, 'shield-test-temp-'.uniqid() );
		mkdir( $testDir, 0777, true );

		try {
			$remover->removeTempDirectory( $testDir );
			$this->assertDirectoryDoesNotExist( $testDir );
		}
		finally {
			// Cleanup if test failed
			if ( is_dir( $testDir ) ) {
				rmdir( $testDir );
			}
		}
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

		// This should not throw - just return silently
		$remover->removeSafely( Path::join( '/path/that/does/not/exist', uniqid() ) );

		$this->assertTrue( true ); // If we got here, no exception was thrown
	}
}

