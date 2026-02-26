<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\ScriptCommandTestTrait;

/**
 * Safety checks for package-plugin.php script wiring.
 */
class PackagePluginScriptTest extends BaseUnitTest {

	use PluginPathsTrait;
	use ScriptCommandTestTrait;

	public function testPackagePluginScriptHasValidSyntax() :void {
		$this->skipIfPackageScriptUnavailable();
		$this->assertPhpScriptSyntaxValid( 'bin/package-plugin.php' );
	}

	public function testComposerScriptExists() :void {
		if ( $this->isTestingPackage() ) {
			$this->markTestSkipped( 'composer.json is excluded from packages (development-only)' );
		}

		$commands = $this->getComposerScriptCommands( 'package-plugin' );

		$this->assertContains( '@php bin/package-plugin.php', $commands );
	}
}
