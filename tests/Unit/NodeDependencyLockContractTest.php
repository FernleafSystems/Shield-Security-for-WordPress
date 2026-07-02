<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;

class NodeDependencyLockContractTest extends BaseUnitTest {

	use PluginPathsTrait;

	public function testRootBuildDependencyLockFloors() :void {
		$package = $this->decodePluginJsonFile( 'package.json', 'Root package.json' );
		$lock = $this->decodePluginJsonFile( 'package-lock.json', 'Root package-lock.json' );

		$this->assertDependencyRangeAtLeast( $package, 'devDependencies', '@babel/core', '7.29.6' );
		$this->assertPackageLockedAtLeast( $lock, 'node_modules/@babel/core', '7.29.6' );
		$this->assertPackageLockedAtLeast( $lock, 'node_modules/js-yaml', '4.3.0' );
	}

	public function testPlaygroundDependencyLockFloors() :void {
		$package = $this->decodePluginJsonFile( 'tools/playground/package.json', 'Playground package.json' );
		$lock = $this->decodePluginJsonFile( 'tools/playground/package-lock.json', 'Playground package-lock.json' );

		$this->assertDependencyRangeAtLeast( $package, 'devDependencies', '@wp-playground/cli', '3.1.41' );
		$this->assertPackageLockedAtLeast( $lock, 'node_modules/@wp-playground/cli', '3.1.41' );
		$this->assertPackageLockedAtLeast( $lock, 'node_modules/qs', '6.15.2' );
		$this->assertPackageLockedAtLeast( $lock, 'node_modules/tmp', '0.2.6' );
	}

	private function assertDependencyRangeAtLeast(
		array $package,
		string $dependencyType,
		string $packageName,
		string $minimumVersion
	) :void {
		$this->assertArrayHasKey( $dependencyType, $package );
		$dependencies = $package[ $dependencyType ];
		$this->assertIsArray( $dependencies );
		$this->assertArrayHasKey( $packageName, $dependencies );
		$this->assertIsString( $dependencies[ $packageName ] );

		$actualVersion = $this->extractDependencyVersion( $dependencies[ $packageName ] );
		$this->assertTrue(
			\version_compare( $actualVersion, $minimumVersion, '>=' ),
			sprintf(
				'%s should require >= %s in %s; found %s.',
				$packageName,
				$minimumVersion,
				$dependencyType,
				$dependencies[ $packageName ]
			)
		);
	}

	private function assertPackageLockedAtLeast( array $lock, string $packagePath, string $minimumVersion ) :void {
		$this->assertArrayHasKey( 'packages', $lock );
		$packages = $lock[ 'packages' ];
		$this->assertIsArray( $packages );
		$this->assertArrayHasKey( $packagePath, $packages );
		$this->assertIsArray( $packages[ $packagePath ] );

		$this->assertArrayHasKey( 'version', $packages[ $packagePath ] );
		$actualVersion = $packages[ $packagePath ][ 'version' ];
		$this->assertIsString( $actualVersion, sprintf( '%s should declare a locked version.', $packagePath ) );
		$this->assertTrue(
			\version_compare( $actualVersion, $minimumVersion, '>=' ),
			sprintf( '%s should be locked at >= %s; found %s.', $packagePath, $minimumVersion, $actualVersion )
		);
	}

	private function extractDependencyVersion( string $dependencyRange ) :string {
		$this->assertSame(
			1,
			\preg_match( '/\d+(?:\.\d+){0,2}/', $dependencyRange, $matches ),
			sprintf( 'Dependency range should contain a semantic version: %s', $dependencyRange )
		);
		return $matches[ 0 ];
	}
}
