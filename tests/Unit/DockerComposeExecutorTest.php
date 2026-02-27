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
}
