<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\StaticAnalysis;

use FernleafSystems\ShieldPlatform\Tooling\Output\LineEndingNormalizer;

class PackagedPhpStanOutcomeClassifier {

	private LineEndingNormalizer $lineEndingNormalizer;

	public function __construct( ?LineEndingNormalizer $lineEndingNormalizer = null ) {
		$this->lineEndingNormalizer = $lineEndingNormalizer ?? new LineEndingNormalizer();
	}

	public function classify( int $phpstanExitCode, string $rawOutput ) :PackagedPhpStanOutcome {
		if ( $phpstanExitCode === 0 ) {
			return PackagedPhpStanOutcome::cleanSuccess();
		}

		$jsonEnvelope = $this->extractJsonEnvelope( $rawOutput );
		if ( $jsonEnvelope === null ) {
			return PackagedPhpStanOutcome::parseFailure();
		}

		$decoded = \json_decode( $jsonEnvelope, true );
		if ( !\is_array( $decoded ) || !isset( $decoded[ 'totals' ] ) || !\is_array( $decoded[ 'totals' ] ) ) {
			return PackagedPhpStanOutcome::parseFailure();
		}

		$fileErrors = (int)( $decoded[ 'totals' ][ 'file_errors' ] ?? 0 );
		$errors = (int)( $decoded[ 'totals' ][ 'errors' ] ?? 0 );

		if ( $errors > 0 ) {
			return PackagedPhpStanOutcome::nonReportableFailure();
		}

		if ( $fileErrors > 0 ) {
			return PackagedPhpStanOutcome::findingsSuccess();
		}

		return PackagedPhpStanOutcome::nonReportableFailure();
	}

	private function extractJsonEnvelope( string $rawOutput ) :?string {
		$normalized = $this->lineEndingNormalizer->toLf( $rawOutput );
		$start = \strpos( $normalized, '{' );
		$end = \strrpos( $normalized, '}' );

		if ( $start === false || $end === false || $end < $start ) {
			return null;
		}

		return \substr( $normalized, $start, $end - $start + 1 );
	}
}
