<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan;

/**
 * @phpstan-type StartScanFailure array{scan:string,reason:string,message:string}
 */
class StartScansResult {

	public const REASON_UNKNOWN_SCAN = 'unknown_scan';
	public const REASON_SCAN_UNAVAILABLE = 'scan_unavailable';
	public const REASON_ALREADY_EXISTS = 'already_exists';
	public const REASON_CREATE_FAILED = 'create_failed';

	public const CODE_NO_SCANS_SELECTED = 'no_scans_selected';
	public const CODE_START_BLOCKED = 'start_blocked';
	public const CODE_START_FAILED = 'start_failed';
	public const CODE_PARTIAL_START = 'partial_start';

	/**
	 * @var list<string>
	 */
	private array $requestedSlugs = [];

	/**
	 * @var list<int>
	 */
	private array $startedScanIDs = [];

	/**
	 * @var list<string>
	 */
	private array $startedSlugs = [];

	/**
	 * @var array<string,StartScanFailure>
	 */
	private array $failures = [];

	public function __construct( array $requestedSlugs = [] ) {
		$this->requestedSlugs = self::normalizeSlugs( $requestedSlugs );
	}

	public static function fromRequested( array $requestedSlugs ) :self {
		return new self( $requestedSlugs );
	}

	public function addStarted( string $slug, int $scanID ) :self {
		$slug = trim( $slug );
		if ( $slug !== '' ) {
			$this->startedSlugs[] = $slug;
			$this->startedSlugs = self::normalizeSlugs( $this->startedSlugs );
			$this->startedScanIDs[] = $scanID;
		}
		return $this;
	}

	public function addFailure( string $slug, string $reason, string $message = '' ) :self {
		$slug = trim( $slug );
		if ( $slug !== '' ) {
			$this->failures[ $slug ] = [
				'scan'    => $slug,
				'reason'  => $reason,
				'message' => $message,
			];
		}
		return $this;
	}

	public function addFailures( array $slugs, string $reason, string $message = '' ) :self {
		foreach ( self::normalizeSlugs( $slugs ) as $slug ) {
			$this->addFailure( $slug, $reason, $message );
		}
		return $this;
	}

	/**
	 * @return list<string>
	 */
	public function getRequestedSlugs() :array {
		return $this->requestedSlugs;
	}

	/**
	 * @return list<int>
	 */
	public function getStartedScanIDs() :array {
		return $this->startedScanIDs;
	}

	/**
	 * @return list<string>
	 */
	public function getStartedSlugs() :array {
		return $this->startedSlugs;
	}

	/**
	 * @return list<StartScanFailure>
	 */
	public function getFailures() :array {
		return \array_values( $this->failures );
	}

	public function hasRequestedScans() :bool {
		return !empty( $this->requestedSlugs );
	}

	public function hasStarted() :bool {
		return !empty( $this->startedScanIDs );
	}

	public function hasFailures() :bool {
		return !empty( $this->failures );
	}

	public function isPartialSuccess() :bool {
		return $this->hasStarted() && $this->hasFailures();
	}

	public function getErrorCode() :string {
		if ( !$this->hasRequestedScans() ) {
			return self::CODE_NO_SCANS_SELECTED;
		}
		return $this->isPartialSuccess() ? self::CODE_PARTIAL_START : ( $this->hasFailures() ? self::CODE_START_FAILED : '' );
	}

	public function getMessage() :string {
		if ( !$this->hasRequestedScans() ) {
			return __( 'No scans were selected', 'wp-simple-firewall' );
		}
		if ( $this->isPartialSuccess() ) {
			return __( 'Some scans started, but others could not be started.', 'wp-simple-firewall' );
		}
		if ( $this->hasFailures() ) {
			return __( 'Scans could not be started.', 'wp-simple-firewall' );
		}
		if ( $this->hasStarted() ) {
			return __( 'Scans started.', 'wp-simple-firewall' ).' '.__( 'Please wait, as this will take a few moments.', 'wp-simple-firewall' );
		}
		return __( 'No scans were selected', 'wp-simple-firewall' );
	}

	public function getFailureLogMessage() :string {
		if ( !$this->hasFailures() ) {
			return '';
		}

		$failures = \array_map(
			static fn( array $failure ) :string => sprintf(
				'%s:%s',
				(string)$failure[ 'scan' ],
				(string)$failure[ 'reason' ]
			),
			$this->getFailures()
		);

		return sprintf( 'Shield scan start failures: %s', \implode( ', ', $failures ) );
	}

	private static function normalizeSlugs( array $slugs ) :array {
		$normalized = [];
		foreach ( $slugs as $slug ) {
			if ( \is_string( $slug ) ) {
				$slug = trim( $slug );
				if ( $slug !== '' && !\in_array( $slug, $normalized, true ) ) {
					$normalized[] = $slug;
				}
			}
		}
		return $normalized;
	}
}
