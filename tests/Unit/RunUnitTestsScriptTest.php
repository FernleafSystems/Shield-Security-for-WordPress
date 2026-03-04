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
		$this->assertContains( '@php bin/run-unit-tests.php --runner-mode=auto', $commands );
	}

	public function testComposerUnitSerialScriptUsesDispatcher() :void {
		if ( $this->isTestingPackage() ) {
			$this->markTestSkipped( 'composer.json is excluded from packages (development-only)' );
		}

		$commands = $this->getComposerScriptCommands( 'test:unit:serial' );
		$this->assertContains( '@build:config', $commands );
		$this->assertContains( '@php bin/run-unit-tests.php --runner-mode=serial', $commands );
	}

	public function testComposerUnitParallelScriptUsesDispatcher() :void {
		if ( $this->isTestingPackage() ) {
			$this->markTestSkipped( 'composer.json is excluded from packages (development-only)' );
		}

		$commands = $this->getComposerScriptCommands( 'test:unit:parallel' );
		$this->assertContains( '@build:config', $commands );
		$this->assertContains( '@php bin/run-unit-tests.php --runner-mode=parallel', $commands );
	}

	public function testRunUnitTestsAutoModeExecutesSuccessfully() :void {
		$this->skipIfPackageScriptUnavailable();

		$process = $this->runPhpScript(
			'bin/run-unit-tests.php',
			[ '--runner-mode=auto', 'tests/Unit/UnitTestExecutionSelectorTest.php' ]
		);
		$this->assertSame( 0, $process->getExitCode() ?? 1, $this->processOutput( $process ) );
	}

	public function testRunUnitTestsAutoModeFilterExecutesSuccessfully() :void {
		$this->skipIfPackageScriptUnavailable();

		$process = $this->runPhpScript(
			'bin/run-unit-tests.php',
			[
				'--runner-mode=auto',
				'--filter',
				'testBuildCommandUsesSerialPhpUnitWhenFilterIsPresent',
				'tests/Unit/UnitTestExecutionSelectorTest.php',
			]
		);
		$this->assertSame( 0, $process->getExitCode() ?? 1, $this->processOutput( $process ) );
	}

	public function testRunUnitTestsFailsOnInvalidMode() :void {
		$this->skipIfPackageScriptUnavailable();

		$process = $this->runPhpScript( 'bin/run-unit-tests.php', [ '--runner-mode=bogus' ] );
		$this->assertSame( 1, $process->getExitCode() ?? 0 );
		$this->assertStringContainsString( 'Invalid unit test runner mode', $this->processOutput( $process ) );
	}
}
