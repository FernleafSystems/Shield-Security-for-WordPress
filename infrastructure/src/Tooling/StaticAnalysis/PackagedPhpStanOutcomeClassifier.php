<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\StaticAnalysis;

use FernleafSystems\ShieldPlatform\Tooling\Output\LineEndingNormalizer;

class PackagedPhpStanOutcomeClassifier {

	public const OUTCOME_CLEAN_SUCCESS = 'clean_success';
	public const OUTCOME_FINDINGS_SUCCESS = 'findings_success';
	public const OUTCOME_NON_REPORTABLE_FAILURE = 'non_reportable_failure';
	public const OUTCOME_PARSE_FAILURE = 'parse_failure';

	private LineEndingNormalizer $lineEndingNormalizer;

	public function __construct( ?LineEndingNormalizer $lineEndingNormalizer = null ) {
		$this->lineEndingNormalizer = $lineEndingNormalizer ?? new LineEndingNormalizer();
	}

	public function classify( int $phpstanExitCode, string $rawOutput ) :string {
		if ( $phpstanExitCode === 0 ) {
			return self::OUTCOME_CLEAN_SUCCESS;
		}

		$jsonEnvelope = $this->extractJsonEnvelope( $rawOutput );
		if ( $jsonEnvelope === null ) {
			return self::OUTCOME_PARSE_FAILURE;
		}

		$decoded = \json_decode( $jsonEnvelope, true );
		if ( !\is_array( $decoded ) || !isset( $decoded[ 'totals' ] ) || !\is_array( $decoded[ 'totals' ] ) ) {
			return self::OUTCOME_PARSE_FAILURE;
		}

		$fileErrors = (int)( $decoded[ 'totals' ][ 'file_errors' ] ?? 0 );
		$errors = (int)( $decoded[ 'totals' ][ 'errors' ] ?? 0 );

		if ( $errors > 0 ) {
			return self::OUTCOME_NON_REPORTABLE_FAILURE;
		}

		if ( $fileErrors > 0 ) {
			return self::OUTCOME_FINDINGS_SUCCESS;
		}

		return self::OUTCOME_NON_REPORTABLE_FAILURE;
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

