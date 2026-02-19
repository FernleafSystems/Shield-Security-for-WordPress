<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;

class RunDockerTestsScriptTest extends BaseUnitTest {

	use PluginPathsTrait;

	public function testPackagedPhpStanUsesDedicatedClassifierScript() :void {
		if ( $this->isTestingPackage() ) {
			$this->markTestSkipped( 'bin/ directory is excluded from packages (development-only)' );
		}

		$content = $this->getPluginFileContents( 'bin/run-docker-tests.sh', 'docker tests runner script' );
		$this->assertStringContainsString( 'bin/classify-packaged-phpstan.php', $content );
	}

	public function testInlinePhpParserIsRemovedFromPackagedPhpStanPath() :void {
		if ( $this->isTestingPackage() ) {
			$this->markTestSkipped( 'bin/ directory is excluded from packages (development-only)' );
		}

		$content = $this->getPluginFileContents( 'bin/run-docker-tests.sh', 'docker tests runner script' );
		$this->assertStringNotContainsString( "php -r '", $content );
	}
}

