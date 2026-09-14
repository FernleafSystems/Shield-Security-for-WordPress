<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\Testing\SourceRuntimeLogSink;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class SourceRuntimeLogSinkTest extends TestCase {

	use TempDirLifecycleTrait;

	protected function tearDown() :void {
		$this->cleanupTrackedTempDirs();
		parent::tearDown();
	}

	public function testZeroOutputPhaseStillCreatesArtifactAndSummaryEntry() :void {
		$logDir = $this->createTrackedTempDir( 'shield-source-log-' );
		$summaryPath = $this->createTrackedTempFile( 'shield-source-summary-', '.md' );
		$sink = new SourceRuntimeLogSink( $logDir, $summaryPath );

		$sink->callbackForPhase( 'mysql-up', 'Start MySQL services' );
		$sink->finishPhase( 'mysql-up', 0 );
		$sink->writeStepSummary( 0 );

		$this->assertFileExists( $logDir.'/mysql-up.log' );
		$this->assertSame( '', (string)\file_get_contents( $logDir.'/mysql-up.log' ) );
		$summary = (string)\file_get_contents( $summaryPath );
		$this->assertStringContainsString( 'Overall Result: PASS', $summary );
		$this->assertStringContainsString( '`mysql-up.log`', $summary );
	}

	public function testBufferedFailureWritesNormalizedArtifactAndFailureSummary() :void {
		$logDir = $this->createTrackedTempDir( 'shield-source-log-' );
		$summaryPath = $this->createTrackedTempFile( 'shield-source-summary-', '.md' );
		$sink = new SourceRuntimeLogSink( $logDir, $summaryPath );
		$callback = $sink->callbackForPhase( 'runtime-latest', 'Run latest WordPress runtime checks' );

		\ob_start();
		try {
			$callback( Process::OUT, "warning: first\r\nDeprec" );
			$callback( Process::OUT, "ated API\r\nFatal error: boom" );
			$sink->finishPhase( 'runtime-latest', 1 );
		}
		finally {
			$output = (string)\ob_get_clean();
		}
		$sink->writeStepSummary( 1 );

		$this->assertStringContainsString( 'Fatal error: boom', $output );
		$this->assertSame(
			"warning: first\nDeprecated API\nFatal error: boom",
			(string)\file_get_contents( $logDir.'/runtime-latest.log' )
		);
		$summary = (string)\file_get_contents( $summaryPath );
		$this->assertStringContainsString( 'Overall Result: FAIL', $summary );
		$this->assertStringContainsString( '| FAIL | 1 | 1 | 1 | `runtime-latest.log` |', $summary );
	}
}
