<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\ScriptCommandTestTrait;

class ShieldCliCommandTest extends BaseUnitTest {

	use PluginPathsTrait;
	use ScriptCommandTestTrait;

	public function testShieldCliScriptHasValidSyntax() :void {
		$this->skipIfPackageScriptUnavailable();
		$this->assertPhpScriptSyntaxValid( 'bin/shield' );
	}

	public function testHelpListsAllCanonicalCommands() :void {
		$this->skipIfPackageScriptUnavailable();

		$process = $this->runPhpScript( 'bin/shield', [ '--help' ] );
		$this->assertSame( 0, $process->getExitCode() ?? 1 );

		$output = $this->processOutput( $process );
		foreach (
			[
				'test:source',
				'test:package-targeted',
				'test:package-full',
				'analyze:source',
				'analyze:package',
			] as $commandName
		) {
			$this->assertStringContainsString( $commandName, $output );
		}
	}

	/**
	 * @dataProvider providerCommandNames
	 */
	public function testEachCommandProvidesHelp( string $commandName ) :void {
		$this->skipIfPackageScriptUnavailable();

		$process = $this->runPhpScript( 'bin/shield', [ $commandName, '--help' ] );
		$this->assertSame( 0, $process->getExitCode() ?? 1, $this->processOutput( $process ) );
		$this->assertStringContainsString( $commandName, $this->processOutput( $process ) );
	}

	public function testPackageTargetedHelpIncludesStrictSkipOptions() :void {
		$this->skipIfPackageScriptUnavailable();

		$process = $this->runPhpScript( 'bin/shield', [ 'test:package-targeted', '--help' ] );
		$this->assertSame( 0, $process->getExitCode() ?? 1, $this->processOutput( $process ) );

		$output = $this->processOutput( $process );
		$this->assertStringContainsString( '--fail-on-skipped', $output );
		$this->assertStringContainsString( '--no-fail-on-skipped', $output );
	}

	/**
	 * @return array<string,array{string}>
	 */
	public function providerCommandNames() :array {
		return [
			'test-source' => [ 'test:source' ],
			'test-package-targeted' => [ 'test:package-targeted' ],
			'test-package-full' => [ 'test:package-full' ],
			'analyze-source' => [ 'analyze:source' ],
			'analyze-package' => [ 'analyze:package' ],
		];
	}
}
