<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\ScriptCommandTestTrait;

class RunUnitTestsScriptTest extends BaseUnitTest {

	use PluginPathsTrait;
	use ScriptCommandTestTrait;

	public function testRunUnitTestsScriptHasValidSyntax() :void {
		$this->skipIfPackageScriptUnavailable();
		$this->assertPhpScriptSyntaxValid( 'bin/run-unit-tests.php' );
	}

	public function testComposerUnitScriptUsesDispatcher() :void {
		if ( $this->isTestingPackage() ) {
			$this->markTestSkipped( 'composer.json is excluded from packages (development-only)' );
		}

		$commands = $this->getComposerScriptCommands( 'test:unit' );
		$this->assertContains( '@build:config', $commands );
		$this->assertContains( '@php bin/run-unit-tests.php', $commands );
	}

	public function testRunUnitTestsUsesParatestWhenNoFilterIsPassed() :void {
		$this->skipIfPackageScriptUnavailable();

		$process = $this->runPhpScript( 'bin/run-unit-tests.php', [ 'tests/Unit/UnitTestExecutionSelectorTest.php' ] );
		$this->assertSame( 0, $process->getExitCode() ?? 1, $this->processOutput( $process ) );
		$this->assertStringContainsString( 'ParaTest', $this->processOutput( $process ) );
	}

	public function testRunUnitTestsFallsBackToSerialWhenFilterIsPassed() :void {
		$this->skipIfPackageScriptUnavailable();

		$process = $this->runPhpScript(
			'bin/run-unit-tests.php',
			[ '--filter', 'testBuildCommandUsesSerialPhpUnitWhenFilterIsPresent', 'tests/Unit/UnitTestExecutionSelectorTest.php' ]
		);
		$this->assertSame( 0, $process->getExitCode() ?? 1, $this->processOutput( $process ) );
		$this->assertStringContainsString( 'PHPUnit', $this->processOutput( $process ) );
	}
}
