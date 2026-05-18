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
				'testBuildCommandUsesParatestFunctionalWhenFilterIsPresent',
				'tests/Unit/UnitTestExecutionSelectorTest.php',
			]
		);
		$this->assertSame( 0, $process->getExitCode() ?? 1, $this->processOutput( $process ) );
	}

	public function testRunUnitTestsAutoModeEqualsFilterExecutesSuccessfully() :void {
		$this->skipIfPackageScriptUnavailable();

		$process = $this->runPhpScript(
			'bin/run-unit-tests.php',
			[
				'--runner-mode=auto',
				'--filter=testBuildCommandUsesParatestFunctionalWhenEqualsFilterIsPresent',
				'tests/Unit/UnitTestExecutionSelectorTest.php',
			]
		);
		$this->assertSame( 0, $process->getExitCode() ?? 1, $this->processOutput( $process ) );
	}

	public function testRunUnitTestsAutoModeFilterWithPathExecutesSuccessfully() :void {
		$this->skipIfPackageScriptUnavailable();

		$process = $this->runPhpScript(
			'bin/run-unit-tests.php',
			[
				'--runner-mode=auto',
				'--filter',
				'testBuildCommandUsesParatestFunctionalForMethodFilterPlusPath',
				'tests/Unit/UnitTestExecutionSelectorTest.php',
			]
		);
		$this->assertSame( 0, $process->getExitCode() ?? 1, $this->processOutput( $process ) );
	}

	public function testRunUnitTestsAutoModeDatasetShortcutPreservesPhpUnitParity() :void {
		$this->skipIfPackageScriptUnavailable();

		$junitPath = \rtrim( \str_replace( '\\', '/', \sys_get_temp_dir() ), '/' )
					 .'/shield-unit-junit-'.\bin2hex( \random_bytes( 6 ) ).'.xml';

		try {
			$process = $this->runPhpScript(
				'bin/run-unit-tests.php',
				[
					'--runner-mode=auto',
					'--filter',
					'testOutputDirectoryRequired@null',
					'--log-junit',
					$junitPath,
					'tests/Unit/PluginPackagerTest.php',
				]
			);
			$this->assertSame( 0, $process->getExitCode() ?? 1, $this->processOutput( $process ) );
			$this->assertFileExists( $junitPath );

			$xml = \simplexml_load_file( $junitPath );
			$this->assertInstanceOf( \SimpleXMLElement::class, $xml );
			$suites = $xml->xpath( '/testsuites/testsuite' );
			$this->assertIsArray( $suites );
			$this->assertNotEmpty( $suites );
			$this->assertSame( '1', (string)$suites[ 0 ][ 'tests' ] );
			$this->assertSame( '1', (string)$suites[ 0 ][ 'assertions' ] );
		}
		finally {
			if ( \is_file( $junitPath ) ) {
				\unlink( $junitPath );
			}
		}
	}

	public function testRunUnitTestsFailsOnInvalidMode() :void {
		$this->skipIfPackageScriptUnavailable();

		$process = $this->runPhpScript( 'bin/run-unit-tests.php', [ '--runner-mode=bogus' ] );
		$this->assertSame( 1, $process->getExitCode() ?? 0 );
		$this->assertStringContainsString( 'Invalid unit test runner mode', $this->processOutput( $process ) );
	}
}
