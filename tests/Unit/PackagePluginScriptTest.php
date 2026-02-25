<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;

/**
 * Safety checks for package-plugin.php script wiring.
 */
class PackagePluginScriptTest extends BaseUnitTest {

	use PluginPathsTrait;

	public function testPackagePluginScriptHasValidSyntax() :void {
		if ( $this->isTestingPackage() ) {
			$this->markTestSkipped( 'bin/ directory is excluded from packages (development-only)' );
		}

		$scriptPath = $this->getPluginFilePath( 'bin/package-plugin.php' );
		$output = [];
		$returnCode = 0;
		\exec( 'php -l '.\escapeshellarg( $scriptPath ).' 2>&1', $output, $returnCode );

		$this->assertSame( 0, $returnCode, 'bin/package-plugin.php should have valid PHP syntax: '.\implode( "\n", $output ) );
	}

	public function testComposerScriptExists() :void {
		if ( $this->isTestingPackage() ) {
			$this->markTestSkipped( 'composer.json is excluded from packages (development-only)' );
		}

		$commands = $this->getComposerScriptCommands( 'package-plugin' );

		$this->assertContains( '@php bin/package-plugin.php', $commands );
	}
}
