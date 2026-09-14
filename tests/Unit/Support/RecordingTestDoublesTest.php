<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;

class RecordingTestDoublesTest extends TestCase {

	public function testStrictDockerComposeExecutorRejectsUnexpectedCalls() :void {
		$executor = RecordingDockerComposeExecutor::strict( [] );

		$this->expectException( \LogicException::class );
		$this->expectExceptionMessage( 'Unexpected Docker Compose call exhausted the configured exit-code queue.' );

		$executor->run( '/project-root', [ 'docker-compose.yml' ], [ 'up' ] );
	}

	public function testStrictProcessRunnerRejectsUnexpectedCalls() :void {
		$runner = RecordingProcessRunner::strict( [] );

		$this->expectException( \LogicException::class );
		$this->expectExceptionMessage( 'Unexpected process call exhausted the configured response queue.' );

		$runner->run( [ 'php', '-v' ], '/project-root' );
	}
}
