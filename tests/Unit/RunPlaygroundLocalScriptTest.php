<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\ScriptCommandTestTrait;

/**
 * Safety checks for local Playground helper script and isolated npm tooling.
 */
class RunPlaygroundLocalScriptTest extends BaseUnitTest {

	use PluginPathsTrait;
	use ScriptCommandTestTrait;

	public function testRunPlaygroundScriptHasValidSyntax() :void {
		$this->skipIfPackageScriptUnavailable();
		$this->assertPhpScriptSyntaxValid( 'bin/run-playground-local.php' );
	}

	public function testRunPlaygroundScriptHelpShowsExpectedCliSurface() :void {
		$this->skipIfPackageScriptUnavailable();
		$process = $this->runPhpScript( 'bin/run-playground-local.php', [ '--help' ] );

		$this->assertSame( 0, $process->getExitCode() ?? 1 );
		$output = $process->getOutput().$process->getErrorOutput();
		$this->assertStringContainsString( '--run-blueprint', $output );
		$this->assertStringContainsString( '--clean', $output );
		$this->assertStringContainsString( '--retention-days', $output );
		$this->assertStringContainsString( '--max-runs', $output );
		$this->assertStringContainsString( '--runtime-root', $output );
		$this->assertStringContainsString( '--plugin-root', $output );
		$this->assertStringContainsString( '--strict', $output );
		$this->assertStringContainsString( 'npm ci --prefix tools/playground --no-audit --no-fund', $output );
		$this->assertStringNotContainsString( 'npm install --prefix tools/playground', $output );
	}

	public function testRunPlaygroundInstallGuidanceUsesLockedCiCommand() :void {
		$this->skipIfPackageScriptUnavailable();
		$script = $this->getPluginFileContents( 'bin/run-playground-local.php', 'Playground helper script' );

		$this->assertStringContainsString(
			'return \'npm ci --prefix tools/playground --no-audit --no-fund\';',
			$script
		);
		$this->assertStringContainsString(
			'fwrite( STDERR, "Install it with: ".getPlaygroundInstallCommand()."\n" );',
			$script
		);
		$this->assertStringContainsString(
			'$summary[\'errors\'][] = \'Install it with: \'.getPlaygroundInstallCommand();',
			$script
		);
		$this->assertStringNotContainsString( 'npm install --prefix tools/playground', $script );
	}

	public function testPlaygroundPackageLockDoesNotLinkRootPackage() :void {
		$this->skipIfPackageScriptUnavailable();
		$package = $this->decodePluginJsonFile( 'tools/playground/package.json', 'Playground package.json' );
		$lock = $this->decodePluginJsonFile( 'tools/playground/package-lock.json', 'Playground package-lock.json' );
		$lockContent = $this->getPluginFileContents( 'tools/playground/package-lock.json', 'Playground package-lock.json' );

		$this->assertArrayHasKey( 'devDependencies', $package );
		$this->assertArrayHasKey( '@wp-playground/cli', $package[ 'devDependencies' ] );
		$this->assertArrayNotHasKey( 'shieldsecurity', $package[ 'dependencies' ] ?? [] );

		$lockPackages = $lock[ 'packages' ] ?? [];
		$this->assertIsArray( $lockPackages );
		$this->assertArrayHasKey( '', $lockPackages );
		$lockRoot = $lockPackages[ '' ];
		$this->assertIsArray( $lockRoot );

		$this->assertSame(
			$this->normaliseDependencyMap( $package[ 'dependencies' ] ?? [] ),
			$this->normaliseDependencyMap( $lockRoot[ 'dependencies' ] ?? [] ),
			'Playground package-lock root dependencies must match tools/playground/package.json.'
		);
		$this->assertSame(
			$this->normaliseDependencyMap( $package[ 'devDependencies' ] ?? [] ),
			$this->normaliseDependencyMap( $lockRoot[ 'devDependencies' ] ?? [] ),
			'Playground package-lock root devDependencies must match tools/playground/package.json.'
		);

		$this->assertArrayHasKey( 'node_modules/@wp-playground/cli', $lockPackages );
		$this->assertArrayNotHasKey( '../..', $lockPackages );
		$this->assertArrayNotHasKey( 'node_modules/shieldsecurity', $lockPackages );

		foreach ( $lockPackages as $packagePath => $packageMeta ) {
			$this->assertIsArray( $packageMeta, sprintf( 'Lockfile package entry should be an array: %s', $packagePath ) );
			$this->assertNotSame( 'shieldsecurity', $packageMeta[ 'name' ] ?? null, sprintf( 'Unexpected root package snapshot at %s', $packagePath ) );
		}
		$this->assertStringNotContainsString( 'shieldsecurity', $lockContent );
		$this->assertStringNotContainsString( '../..', $lockContent );
	}

	public function testRunPlaygroundCheckFailsFastForMissingPluginRoot() :void {
		$this->skipIfPackageScriptUnavailable();
		$missingPluginRoot = sys_get_temp_dir().DIRECTORY_SEPARATOR.'shield-playground-missing-'.bin2hex( random_bytes( 4 ) );
		$this->assertDirectoryDoesNotExist( $missingPluginRoot );

		$output = [];
		$returnCode = 0;
		\exec(
			'php '.\escapeshellarg( $this->getPluginFilePath( 'bin/run-playground-local.php' ) )
			.' --run-blueprint --plugin-root='.escapeshellarg( $missingPluginRoot ).' 2>&1',
			$output,
			$returnCode
		);

		$this->assertSame( 2, $returnCode, 'Missing plugin root should fail with environment exit code (2).' );
		$this->assertStringContainsString( 'Plugin root directory not found:', \implode( "\n", $output ) );
	}

	/**
	 * @param array<string,string> $dependencies
	 * @return array<string,string>
	 */
	private function normaliseDependencyMap( array $dependencies ) :array {
		\ksort( $dependencies );
		return $dependencies;
	}
}
