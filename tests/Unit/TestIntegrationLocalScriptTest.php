<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;

class TestIntegrationLocalScriptTest extends BaseUnitTest {

	use PluginPathsTrait;

	public function testComposerIntegrationLocalScriptIsWiredToShieldCli() :void {
		if ( $this->isTestingPackage() ) {
			$this->markTestSkipped( 'composer.json is excluded from packages (development-only)' );
		}

		$commands = $this->getComposerScriptCommands( 'test:integration:local' );
		$this->assertContains( 'Composer\\Config::disableProcessTimeout', $commands );
		$this->assertContains( '@php bin/shield test:integration-local', $commands );
	}
}
