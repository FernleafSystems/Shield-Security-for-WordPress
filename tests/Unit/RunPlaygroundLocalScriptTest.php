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
		$this->assertStringContainsString( "'strict'", $content );
		$this->assertStringNotContainsString( "'keep-success-artifacts'", $content );
		$this->assertStringNotContainsString( '@wp-playground/cli@latest', $content );
		$this->assertStringContainsString( 'Local @wp-playground/cli binary not found.', $content );
		$this->assertStringContainsString( 'Version Verification:', $content );
		$this->assertStringContainsString( 'runtime_php_version_match', $content );
		$this->assertStringContainsString( "=== Shield Playground Local Check ===", $content );
		$this->assertStringContainsString( "Result: ", $content );
	}

	public function testComposerDeclaresPlaygroundCleanScript() :void {
		$composerJson = $this->getPluginFileContents( 'composer.json', 'composer manifest' );
		$decoded = \json_decode( $composerJson, true );

		$this->assertIsArray( $decoded );
		$this->assertArrayHasKey( 'scripts', $decoded );
		$this->assertIsArray( $decoded['scripts'] );
		$this->assertArrayHasKey( 'playground:local:clean', $decoded['scripts'] );
		$this->assertSame( '@php bin/run-playground-local.php --clean', $decoded['scripts']['playground:local:clean'] );
	}
}
