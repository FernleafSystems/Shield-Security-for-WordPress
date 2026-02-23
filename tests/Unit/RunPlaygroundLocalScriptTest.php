<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;

/**
 * Safety checks for local Playground helper script and composer wiring.
 */
class RunPlaygroundLocalScriptTest extends BaseUnitTest {

	use PluginPathsTrait;

	public function testRunPlaygroundScriptHasValidSyntax() :void {
		if ( $this->isTestingPackage() ) {
			$this->markTestSkipped( 'bin/ directory is excluded from packages (development-only)' );
		}

		$scriptPath = $this->getPluginFilePath( 'bin/run-playground-local.php' );
		$output = [];
		$returnCode = 0;
		\exec( 'php -l '.\escapeshellarg( $scriptPath ).' 2>&1', $output, $returnCode );

		$this->assertSame(
			0,
			$returnCode,
			'bin/run-playground-local.php should have valid PHP syntax: '.\implode( "\n", $output )
		);
	}

	public function testRunPlaygroundScriptDeclaresExpectedOptionsAndOutputSections() :void {
		if ( $this->isTestingPackage() ) {
			$this->markTestSkipped( 'bin/ directory is excluded from packages (development-only)' );
		}

		$content = $this->getPluginFileContents( 'bin/run-playground-local.php', 'playground local runner script' );

		$this->assertStringContainsString( "'run-blueprint'", $content );
		$this->assertStringContainsString( "'clean'", $content );
		$this->assertStringContainsString( "'retention-days::'", $content );
		$this->assertStringContainsString( "'max-runs::'", $content );
		$this->assertStringContainsString( "'runtime-root::'", $content );
		$this->assertStringContainsString( "'plugin-root::'", $content );
		$this->assertStringContainsString( "'strict'", $content );
		$this->assertStringNotContainsString( "'keep-success-artifacts'", $content );
		$this->assertStringNotContainsString( '@wp-playground/cli@latest', $content );
		$this->assertStringContainsString( 'Local @wp-playground/cli binary not found.', $content );
		$this->assertStringContainsString( 'node_modules/.bin/wp-playground-cli', $content );
		$this->assertStringContainsString( 'Version Verification:', $content );
		$this->assertStringContainsString( 'runtime_php_version_match', $content );
		$this->assertStringContainsString( "'preferredVersions' => buildPreferredVersions( \$phpVersion, \$wpVersion )", $content );
		$this->assertStringContainsString( 'function buildPreferredVersions( string $phpVersion, string $wpVersion ) :array {', $content );
		$this->assertStringContainsString( "=== Shield Playground Local Check ===", $content );
		$this->assertStringContainsString( "Result: ", $content );
	}

	public function testComposerDeclaresPlaygroundCleanScript() :void {
		if ( $this->isTestingPackage() ) {
			$this->markTestSkipped( 'composer.json is excluded from packaged artifacts (source-only assertion)' );
		}

		$cleanCommands = $this->getComposerScriptCommands( 'playground:local:clean' );
		$this->assertContains( '@php bin/run-playground-local.php --clean', $cleanCommands );

		$packageCheckCommands = $this->getComposerScriptCommands( 'playground:package:check' );
		$this->assertContains(
			'@php bin/run-playground-local.php --run-blueprint --plugin-root=./shield-package',
			$packageCheckCommands
		);
	}

	public function testRunPlaygroundCheckFailsFastForMissingPluginRoot() :void {
		if ( $this->isTestingPackage() ) {
			$this->markTestSkipped( 'bin/ directory is excluded from packages (development-only)' );
		}

		$scriptPath = $this->getPluginFilePath( 'bin/run-playground-local.php' );
		$missingPluginRoot = sys_get_temp_dir().DIRECTORY_SEPARATOR.'shield-playground-missing-'.bin2hex( random_bytes( 4 ) );
		$this->assertDirectoryDoesNotExist( $missingPluginRoot );

		$output = [];
		$returnCode = 0;
		\exec(
			'php '.\escapeshellarg( $scriptPath ).' --run-blueprint --plugin-root='.escapeshellarg( $missingPluginRoot ).' 2>&1',
			$output,
			$returnCode
		);

		$this->assertSame( 2, $returnCode, 'Missing plugin root should fail with environment exit code (2).' );
		$this->assertStringContainsString( 'Plugin root directory not found:', \implode( "\n", $output ) );
	}
}
