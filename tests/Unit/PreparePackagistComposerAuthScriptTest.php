<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\ScriptCommandTestTrait;
use Symfony\Component\Process\Process;

class PreparePackagistComposerAuthScriptTest extends BaseUnitTest {

	use PluginPathsTrait;
	use ScriptCommandTestTrait;
	use TempDirLifecycleTrait;

	protected function tearDown() :void {
		$this->cleanupTrackedTempDirs();
		parent::tearDown();
	}

	public function testScriptHasValidSyntax() :void {
		$this->skipIfPackageScriptUnavailable();
		$this->assertPhpScriptSyntaxValid( '.github/scripts/prepare-packagist-composer-auth.php' );
	}

	public function testMissingTokenFailsBeforeWritingAuth() :void {
		$this->skipIfPackageScriptUnavailable();

		$envFile = $this->createEnvFile();
		$process = $this->runAuthScript(
			[
				'PACKAGIST_TOKEN' => false,
				'GITHUB_ENV'      => $envFile,
			]
		);

		$this->assertSame( 2, $process->getExitCode() ?? 1, $this->processOutput( $process ) );
		$this->assertStringContainsString( 'PACKAGIST_TOKEN', $this->processOutput( $process ) );
		$this->assertSame( '', (string)\file_get_contents( $envFile ) );
	}

	public function testTokenWritesComposerAuthWithoutPrintingSecret() :void {
		$this->skipIfPackageScriptUnavailable();

		$token = 'dummy-packagist-token-for-test';
		$envFile = $this->createEnvFile();
		$process = $this->runAuthScript(
			[
				'PACKAGIST_TOKEN' => $token,
				'GITHUB_ENV'      => $envFile,
			]
		);

		$this->assertSame( 0, $process->getExitCode() ?? 1, $this->processOutput( $process ) );
		$this->assertStringNotContainsString( $token, $this->processOutput( $process ) );

		$envLine = \trim( (string)\file_get_contents( $envFile ) );
		$this->assertStringStartsWith( 'COMPOSER_AUTH=', $envLine );

		$decoded = \json_decode( \substr( $envLine, \strlen( 'COMPOSER_AUTH=' ) ), true );
		$this->assertSame( \JSON_ERROR_NONE, \json_last_error() );
		$this->assertSame( 'token', $decoded[ 'http-basic' ][ 'repo.packagist.com' ][ 'username' ] ?? null );
		$this->assertSame( $token, $decoded[ 'http-basic' ][ 'repo.packagist.com' ][ 'password' ] ?? null );
	}

	/**
	 * @param array<string,mixed> $env
	 */
	private function runAuthScript( array $env ) :Process {
		$process = new Process(
			[ \PHP_BINARY, $this->getPluginFilePath( '.github/scripts/prepare-packagist-composer-auth.php' ) ],
			$this->getPluginRoot(),
			$env
		);
		$process->run();
		return $process;
	}

	private function createEnvFile() :string {
		return $this->createTrackedTempFile( 'shield-composer-auth-', '.env' );
	}
}
