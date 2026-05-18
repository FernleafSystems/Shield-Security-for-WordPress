<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\Testing\DockerComposeExecutor;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\RecordingProcessRunner;
use PHPUnit\Framework\TestCase;

class DockerComposeExecutorTest extends TestCase {

	private string $projectRoot;

	protected function setUp() :void {
		parent::setUp();
		$this->projectRoot = \dirname( \dirname( __DIR__ ) );
	}

	public function testRunBuildsExpectedComposeCommand() :void {
		$processRunner = new RecordingProcessRunner( [ 0 ] );
		$executor = new DockerComposeExecutor( $processRunner );
		$envOverrides = [
			'COMPOSE_PROJECT_NAME' => 'shield-tests',
		];

		$exitCode = $executor->run(
			$this->projectRoot,
			[ 'tests/docker/docker-compose.yml', 'tests/docker/docker-compose.package.yml' ],
			[ 'up', '-d', 'mysql-latest' ],
			$envOverrides
		);

		$this->assertSame( 0, $exitCode );
		$this->assertCount( 1, $processRunner->calls );
		$this->assertSame(
			[
				'docker',
				'compose',
				'-f',
				'tests/docker/docker-compose.yml',
				'-f',
				'tests/docker/docker-compose.package.yml',
				'up',
				'-d',
				'mysql-latest',
			],
			$processRunner->calls[ 0 ][ 'command' ]
		);
		$this->assertSame( $envOverrides, $processRunner->calls[ 0 ][ 'env_overrides' ] );
	}

	public function testRunReturnsUnderlyingExitCode() :void {
		$processRunner = new RecordingProcessRunner( [ 9 ] );
		$executor = new DockerComposeExecutor( $processRunner );

		$exitCode = $executor->run(
			$this->projectRoot,
			[ 'tests/docker/docker-compose.yml' ],
			[ 'build', 'test-runner-latest' ]
		);

		$this->assertSame( 9, $exitCode );
	}

	public function testRunForwardsOptionalOutputCallback() :void {
		$processRunner = new RecordingProcessRunner( [ 0 ] );
		$executor = new DockerComposeExecutor( $processRunner );

		$exitCode = $executor->run(
			$this->projectRoot,
			[ 'tests/docker/docker-compose.yml' ],
			[ 'build', 'test-runner-latest' ],
			null,
			static function () :void {
			}
		);

		$this->assertSame( 0, $exitCode );
		$this->assertCount( 1, $processRunner->calls );
		$this->assertTrue( $processRunner->calls[ 0 ][ 'has_output_callback' ] );
	}

	public function testPackageComposeForwardsStraussEnvironmentToBothRunners() :void {
		$composePath = $this->projectRoot.'/tests/docker/docker-compose.package.yml';
		$content = \file_get_contents( $composePath );
		$this->assertNotFalse( $content );

		foreach ( [ 'test-runner-latest', 'test-runner-previous' ] as $service ) {
			$serviceBlock = $this->composeServiceBlock( (string)$content, $service );
			foreach ( [
				'SHIELD_STRAUSS_VERSION: ${SHIELD_STRAUSS_VERSION:-}',
				'SHIELD_STRAUSS_FORK_REPO: ${SHIELD_STRAUSS_FORK_REPO:-}',
				'SHIELD_STRAUSS_FORK_BRANCH: ${SHIELD_STRAUSS_FORK_BRANCH:-}',
			] as $expectedEnvLine ) {
				$this->assertStringContainsString( $expectedEnvLine, $serviceBlock );
			}
		}
	}

	/**
	 * @param string[] $subCommand
	 * @param string[] $expectedCommand
	 * @dataProvider providerSubCommandsWithSuppressedOutput
	 */
	public function testRunIgnoringFailureWithSuppressedOutputInjectsExpectedFlags(
		array $subCommand,
		array $expectedCommand
	) :void {
		$processRunner = new RecordingProcessRunner( [ 5 ] );
		$executor = new DockerComposeExecutor( $processRunner );

		$executor->runIgnoringFailure(
			$this->projectRoot,
			[ 'tests/docker/docker-compose.yml' ],
			$subCommand,
			null,
			false
		);

		$this->assertCount( 1, $processRunner->calls );
		$this->assertSame(
			$expectedCommand,
			$processRunner->calls[ 0 ][ 'command' ]
		);
	}

	/**
	 * @param string[] $subCommand
	 * @param string[] $expectedCommand
	 * @dataProvider providerSubCommandsWithSuppressedOutput
	 */
	public function testRunWithSuppressedOutputInjectsExpectedFlags(
		array $subCommand,
		array $expectedCommand
	) :void {
		$processRunner = new RecordingProcessRunner( [ 0 ] );
		$executor = new DockerComposeExecutor( $processRunner );

		$exitCode = $executor->run(
			$this->projectRoot,
			[ 'tests/docker/docker-compose.yml' ],
			$subCommand,
			null,
			null,
			false
		);

		$this->assertSame( 0, $exitCode );
		$this->assertCount( 1, $processRunner->calls );
		$this->assertSame(
			$expectedCommand,
			$processRunner->calls[ 0 ][ 'command' ]
		);
	}

	/**
	 * @return array<string,array{0:string[],1:string[]}>
	 */
	public function providerSubCommandsWithSuppressedOutput() :array {
		return [
			'up-noise' => [
				[ 'up', '-d', 'mysql' ],
				[ 'docker', 'compose', '-f', 'tests/docker/docker-compose.yml', 'up', '--quiet-pull', '-d', 'mysql' ],
			],
			'build-noise' => [
				[ 'build', 'test-runner-latest' ],
				[ 'docker', 'compose', '-f', 'tests/docker/docker-compose.yml', 'build', '--quiet', 'test-runner-latest' ],
			],
			'run-noise' => [
				[ 'run', '--rm', 'test-runner-latest' ],
				[ 'docker', 'compose', '-f', 'tests/docker/docker-compose.yml', 'run', '--quiet-pull', '--rm', 'test-runner-latest' ],
			],
		];
	}

	public function testRunIgnoringFailureDoesNotThrowOnNonZeroExitCode() :void {
		$processRunner = new RecordingProcessRunner( [ 5 ] );
		$executor = new DockerComposeExecutor( $processRunner );

		$executor->runIgnoringFailure(
			$this->projectRoot,
			[ 'tests/docker/docker-compose.yml' ],
			[ 'down', '-v', '--remove-orphans' ]
		);

		$this->assertCount( 1, $processRunner->calls );
		$this->assertTrue( $processRunner->calls[ 0 ][ 'has_output_callback' ] );
	}

	public function testRunIgnoringFailureSuppressesOutputWhenRequested() :void {
		$this->expectOutputString( '' );

		$processRunner = new RecordingProcessRunner( [
			[
				'exit_code' => 5,
				'stdout'    => 'compose noise',
			],
		] );
		$executor = new DockerComposeExecutor( $processRunner );

		$executor->runIgnoringFailure(
			$this->projectRoot,
			[ 'tests/docker/docker-compose.yml' ],
			[ 'down', '-v', '--remove-orphans' ],
			null,
			false
		);

		$this->assertCount( 1, $processRunner->calls );
		$this->assertTrue( $processRunner->calls[ 0 ][ 'has_output_callback' ] );
	}

	private function composeServiceBlock( string $content, string $service ) :string {
		$pattern = \sprintf(
			'/^  %s:\R(?<block>(?:    .*(?:\R|$))*)/m',
			\preg_quote( $service, '/' )
		);
		$this->assertSame( 1, \preg_match( $pattern, $content, $matches ) );
		return (string)( $matches[ 'block' ] ?? '' );
	}
}
