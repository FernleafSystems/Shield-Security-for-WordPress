<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\StaticAnalysis;

class PackagedPhpStanOutcome {

	public const STATUS_CLEAN_SUCCESS = 'clean_success';
	public const STATUS_FINDINGS_SUCCESS = 'findings_success';
	public const STATUS_NON_REPORTABLE_FAILURE = 'non_reportable_failure';
	public const STATUS_PARSE_FAILURE = 'parse_failure';

	private const ALLOWED_STATUSES = [
		self::STATUS_CLEAN_SUCCESS,
		self::STATUS_FINDINGS_SUCCESS,
		self::STATUS_NON_REPORTABLE_FAILURE,
		self::STATUS_PARSE_FAILURE,
	];

	private string $status;

	public function __construct( string $status ) {
		if ( !\in_array( $status, self::ALLOWED_STATUSES, true ) ) {
			throw new \InvalidArgumentException( 'Unsupported packaged PHPStan outcome status: '.$status );
		}
		$this->status = $status;
	}

	public static function cleanSuccess() :self {
		return new self( self::STATUS_CLEAN_SUCCESS );
	}

	public static function findingsSuccess() :self {
		return new self( self::STATUS_FINDINGS_SUCCESS );
	}

	public static function nonReportableFailure() :self {
		return new self( self::STATUS_NON_REPORTABLE_FAILURE );
	}

	public static function parseFailure() :self {
		return new self( self::STATUS_PARSE_FAILURE );
	}

	public function getStatus() :string {
		return $this->status;
	}

	public function isSuccess() :bool {
		return $this->status === self::STATUS_CLEAN_SUCCESS
			   || $this->status === self::STATUS_FINDINGS_SUCCESS;
	}

	public function toExitCode() :int {
		return $this->isSuccess() ? 0 : 1;
	}

	public function toConsoleMessage() :string {
		if ( $this->status === self::STATUS_CLEAN_SUCCESS ) {
			return '✅ Packaged PHPStan analysis completed with no findings.';
		}

		if ( $this->status === self::STATUS_FINDINGS_SUCCESS ) {
			return '⚠️  Packaged PHPStan completed with findings (informational only).';
		}

		if ( $this->status === self::STATUS_NON_REPORTABLE_FAILURE ) {
			return 'ERROR: Packaged PHPStan returned non-zero without reportable findings.';
		}

		return 'ERROR: Packaged PHPStan output could not be parsed as JSON (infrastructure/config failure).';
	}
}
