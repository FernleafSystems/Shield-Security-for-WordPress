<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Testing;

use FernleafSystems\ShieldPlatform\Tooling\Output\LineEndingNormalizer;
use Symfony\Component\Process\Process;

class SourceRuntimeLogSink {

	private const ERROR_PATTERN = '/\b(error|fatal|fail(?:ed|ure|ures)?|exception|uncaught|assertionfailed|sqlstate|segmentation fault)\b/i';
	private const DEPRECATION_PATTERN = '/\bdeprecat(?:ed|ion)\b/i';
	private const WARNING_PATTERN = '/\b(warn(?:ing)?|notice)\b/i';
	private const SUMMARY_PATTERN = '/^(OK \(|FAILURES!|Tests: .*Assertions:)/i';

	private string $logDir;

	private ?string $stepSummaryPath;

	private LineEndingNormalizer $lineEndingNormalizer;

	/** @var string[] */
	private array $phaseOrder = [];

	/** @var array<string,array{label:string,log_file:string,status:string,warnings:int,deprecations:int,errors:int}> */
	private array $phaseState = [];

	/** @var array<string,string> */
	private array $partialLineBuffers = [];

	public function __construct(
		string $logDir,
		?string $stepSummaryPath = null,
		?LineEndingNormalizer $lineEndingNormalizer = null
	) {
		$this->logDir = $logDir;
		$this->stepSummaryPath = $stepSummaryPath;
		$this->lineEndingNormalizer = $lineEndingNormalizer ?? new LineEndingNormalizer();

		if ( !\is_dir( $this->logDir ) ) {
			\mkdir( $this->logDir, 0777, true );
		}
	}

	public static function createFromEnvironment() :?self {
		$logDir = \trim( (string)( \getenv( 'SHIELD_SOURCE_RUNTIME_LOG_DIR' ) ?: '' ) );
		if ( $logDir === '' ) {
			return null;
		}

		$stepSummaryPath = \trim( (string)( \getenv( 'GITHUB_STEP_SUMMARY' ) ?: '' ) );

		return new self(
			$logDir,
			$stepSummaryPath !== '' ? $stepSummaryPath : null
		);
	}

	public function callbackForPhase( string $phaseKey, string $label ) :callable {
		$this->ensurePhaseState( $phaseKey, $label );

		return function ( string $type, string $buffer ) use ( $phaseKey ) :void {
			$this->appendRawBuffer( $phaseKey, $buffer );
			$this->streamMatchingLines( $phaseKey, $type, $buffer );
		};
	}

	public function finishPhase( string $phaseKey, int $exitCode ) :void {
		if ( !isset( $this->phaseState[ $phaseKey ] ) ) {
			return;
		}

		$this->flushPartialLine( $phaseKey );
		$this->phaseState[ $phaseKey ][ 'status' ] = $exitCode === 0 ? 'pass' : 'fail';

		if ( $exitCode !== 0 ) {
			$this->emitFailureTail( $phaseKey );
		}
	}

	public function writeStepSummary( int $overallExitCode ) :void {
		if ( $this->stepSummaryPath === null || $this->phaseOrder === [] ) {
			return;
		}

		$totalWarnings = 0;
		$totalDeprecations = 0;
		$totalErrors = 0;

		$lines = [
			'## Source Docker Runtime Summary',
			'',
			'Overall Result: '.( $overallExitCode === 0 ? 'PASS' : 'FAIL' ),
			'',
			'| Phase | Status | Warnings | Deprecations | Errors | Raw Log |',
			'|---|---|---:|---:|---:|---|',
		];

		foreach ( $this->phaseOrder as $phaseKey ) {
			$state = $this->phaseState[ $phaseKey ];
			$totalWarnings += $state[ 'warnings' ];
			$totalDeprecations += $state[ 'deprecations' ];
			$totalErrors += $state[ 'errors' ];

			$lines[] = \sprintf(
				'| %s | %s | %d | %d | %d | `%s` |',
				$state[ 'label' ],
				\strtoupper( $state[ 'status' ] ),
				$state[ 'warnings' ],
				$state[ 'deprecations' ],
				$state[ 'errors' ],
				\basename( $state[ 'log_file' ] )
			);
		}

		$lines[] = '';
		$lines[] = \sprintf(
			'Totals: %d warning(s), %d deprecation(s), %d error signal(s)',
			$totalWarnings,
			$totalDeprecations,
			$totalErrors
		);

		\file_put_contents(
			$this->stepSummaryPath,
			\implode( \PHP_EOL, $lines ).\PHP_EOL,
			\FILE_APPEND
		);
	}

	private function ensurePhaseState( string $phaseKey, string $label ) :void {
		if ( isset( $this->phaseState[ $phaseKey ] ) ) {
			return;
		}

		$logFile = $this->buildLogFilePath( $phaseKey );
		if ( \is_file( $logFile ) ) {
			\unlink( $logFile );
		}

		$this->phaseOrder[] = $phaseKey;
		$this->phaseState[ $phaseKey ] = [
			'label' => $label,
			'log_file' => $logFile,
			'status' => 'pending',
			'warnings' => 0,
			'deprecations' => 0,
			'errors' => 0,
		];
		$this->partialLineBuffers[ $phaseKey ] = '';
	}

	private function appendRawBuffer( string $phaseKey, string $buffer ) :void {
		\file_put_contents(
			$this->phaseState[ $phaseKey ][ 'log_file' ],
			$this->lineEndingNormalizer->toLf( $buffer ),
			\FILE_APPEND
		);
	}

	private function streamMatchingLines( string $phaseKey, string $type, string $buffer ) :void {
		$normalized = $this->lineEndingNormalizer->toLf( $buffer );
		$chunks = \explode( "\n", $this->partialLineBuffers[ $phaseKey ].$normalized );
		$this->partialLineBuffers[ $phaseKey ] = (string)\array_pop( $chunks );

		foreach ( $chunks as $line ) {
			$this->emitLiveLine( $phaseKey, $type, $line );
		}
	}

	private function flushPartialLine( string $phaseKey ) :void {
		$partial = $this->partialLineBuffers[ $phaseKey ] ?? '';
		if ( $partial === '' ) {
			return;
		}

		$this->emitLiveLine( $phaseKey, Process::OUT, $partial );
		$this->partialLineBuffers[ $phaseKey ] = '';
	}

	private function emitLiveLine( string $phaseKey, string $type, string $line ) :void {
		$trimmed = \trim( $line );
		if ( $trimmed === '' ) {
			return;
		}

		if ( \preg_match( self::ERROR_PATTERN, $trimmed ) === 1 ) {
			$this->phaseState[ $phaseKey ][ 'errors' ]++;
			$this->writeLiveLine( $type, $trimmed );
			return;
		}

		if ( \preg_match( self::DEPRECATION_PATTERN, $trimmed ) === 1 ) {
			$this->phaseState[ $phaseKey ][ 'deprecations' ]++;
			$this->writeLiveLine( $type, $trimmed );
			return;
		}

		if ( \preg_match( self::WARNING_PATTERN, $trimmed ) === 1 ) {
			$this->phaseState[ $phaseKey ][ 'warnings' ]++;
			$this->writeLiveLine( $type, $trimmed );
			return;
		}

		if ( \preg_match( self::SUMMARY_PATTERN, $trimmed ) === 1 ) {
			$this->writeLiveLine( $type, $trimmed );
		}
	}

	private function writeLiveLine( string $type, string $line ) :void {
		$rendered = $line.\PHP_EOL;
		if ( $type === Process::ERR ) {
			\fwrite( \STDERR, $rendered );
		}
		else {
			echo $rendered;
		}
	}

	private function emitFailureTail( string $phaseKey ) :void {
		$state = $this->phaseState[ $phaseKey ];
		$contents = (string)\file_get_contents( $state[ 'log_file' ] );
		$tail = $this->tailOutput( $contents, 40 );

		if ( $tail === '' ) {
			return;
		}

		echo 'Failure tail for '.$state[ 'label' ].' (last 40 lines):'.\PHP_EOL;
		echo $tail.\PHP_EOL;
	}

	private function buildLogFilePath( string $phaseKey ) :string {
		return $this->logDir.\DIRECTORY_SEPARATOR.$phaseKey.'.log';
	}

	private function tailOutput( string $output, int $lines = 30 ) :string {
		$normalized = $this->lineEndingNormalizer->toLf( $output );
		$parts = \preg_split( '/\n/', $normalized ) ?: [];
		$parts = \array_values( \array_filter(
			$parts,
			static function ( string $line ) :bool {
				return $line !== '';
			}
		) );

		return \implode( \PHP_EOL, \array_slice( $parts, -$lines ) );
	}
}
