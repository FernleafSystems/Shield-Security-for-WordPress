<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\ImportExportSites;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\ImportExportSites\Ops\{
	Handler as SitesDB,
	Record
};
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\ImportExport\Sites\{
	ExportWaitState,
	InvitationMetadata,
	QueuedSyncState,
	SiteRepository
};
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\ImportExport\Diagnostics\{
	ObservationPresenter,
	SyncObservation
};
use FernleafSystems\Wordpress\Services\Services;

class SiteSyncStatusBuilder {

	public const STATE_INACTIVE = 'inactive';
	public const STATE_PROBLEM = 'problem';
	public const STATE_PENDING = 'pending';
	public const STATE_WORKING = 'working';
	public const STATE_NEVER_SYNCED = 'never_synced';
	public const INVITATION_STATE_QUEUED = 'queued';
	public const INVITATION_STATE_STARTED_RETRYABLE = 'started_retryable';
	public const INVITATION_STATE_RETRY_SCHEDULED = 'retry_scheduled';
	public const INVITATION_STATE_FINAL_SETTLEMENT = 'final_settlement';
	public const INVITATION_STATE_RESPONSE_UNCONFIRMED = 'response_unconfirmed';
	public const INVITATION_STATE_EXHAUSTED_FAILURE = 'exhausted_failure';
	public const INVITATION_STATE_EXHAUSTED_UNKNOWN = 'exhausted_unknown';
	public const INVITATION_STATE_PASSIVE = 'passive';

	private const STATES = [
		self::STATE_PROBLEM,
		self::STATE_PENDING,
		self::STATE_WORKING,
		self::STATE_NEVER_SYNCED,
		self::STATE_INACTIVE,
	];

	private const REGISTRATION_STATUSES = [
		SitesDB::STATUS_ACTIVE,
		SitesDB::STATUS_DELETED,
	];

	private const QUEUE_STATUSES = [
		SitesDB::QUEUE_IDLE,
		SitesDB::QUEUE_QUEUED,
		SitesDB::QUEUE_PROCESSING,
		SitesDB::QUEUE_WAITING_EXPORT,
		SitesDB::QUEUE_PENDING_INVITE,
		SitesDB::QUEUE_PENDING_CONNECTION,
	];

	private int $now;
	private ?ImportIDPresenter $importIDPresenter = null;

	public function __construct( ?int $now = null ) {
		$this->now = $now ?? $this->currentTimestamp();
	}

	/**
	 * @return array{
	 *   state_key:string,
	 *   label:string,
	 *   badge_tone:string,
	 *   summary_html:string,
	 *   details_html:string,
	 *   diagnostics:array<string,array>
	 * }
	 */
	public function build( Record $record ) :array {
		$state = $this->stateForRecord( $record );
		$diagnostics = $this->diagnosticsForRecord( $record );
		$details = $this->buildDetailsHtml( $record, $state, $diagnostics );

		return [
			'state_key'    => $state,
			'label'        => $this->stateLabel( $state ),
			'badge_tone'   => $this->stateBadgeTone( $state ),
			'summary_html' => $this->buildSummaryHtml( $record, $state, $details ),
			'details_html' => $details,
			'diagnostics'  => $diagnostics,
		];
	}

	public function stateForRecord( Record $record ) :string {
		if ( $record->status !== SitesDB::STATUS_ACTIVE ) {
			return self::STATE_INACTIVE;
		}

		if ( $this->isExpiredWaitingExportProblem( $record ) ) {
			return self::STATE_PROBLEM;
		}

		if ( \in_array( $record->queue_status, [
			SitesDB::QUEUE_PENDING_INVITE,
			SitesDB::QUEUE_PENDING_CONNECTION,
		], true ) ) {
			return self::STATE_PENDING;
		}

		if ( $record->queue_status === SitesDB::QUEUE_PROCESSING
			 || $record->queue_status === SitesDB::QUEUE_WAITING_EXPORT
			 || ( $record->queue_status === SitesDB::QUEUE_QUEUED && !QueuedSyncState::hasProblem( $record ) ) ) {
			return self::STATE_PENDING;
		}

		if ( \in_array( $record->queue_status, [ SitesDB::QUEUE_QUEUED, SitesDB::QUEUE_IDLE ], true )
			 && QueuedSyncState::hasProblem( $record ) ) {
			return self::STATE_PROBLEM;
		}

		if ( $record->queue_status === SitesDB::QUEUE_IDLE && $record->last_export_success_at > 0 ) {
			return self::STATE_WORKING;
		}

		return self::STATE_NEVER_SYNCED;
	}

	public function invitationStateForRecord( Record $record ) :string {
		$invitation = ( new InvitationMetadata() )->normalize( $record->meta );
		$attempts = $invitation[ 'attempts_started' ];
		$result = $invitation[ 'last_result' ];

		if ( $record->queue_status === SitesDB::QUEUE_PENDING_INVITE ) {
			if ( $result === InvitationMetadata::RESULT_STARTED ) {
				return $attempts >= InvitationMetadata::MAX_ATTEMPTS
					? self::INVITATION_STATE_FINAL_SETTLEMENT
					: self::INVITATION_STATE_STARTED_RETRYABLE;
			}
			if ( $result !== null && InvitationMetadata::isFailure( $result ) ) {
				return self::INVITATION_STATE_RETRY_SCHEDULED;
			}
			return self::INVITATION_STATE_QUEUED;
		}

		if ( $record->queue_status === SitesDB::QUEUE_PENDING_CONNECTION ) {
			if ( $result === InvitationMetadata::RESULT_HTTP_RESPONSE ) {
				return self::INVITATION_STATE_RESPONSE_UNCONFIRMED;
			}
			if ( $attempts >= InvitationMetadata::MAX_ATTEMPTS ) {
				return $result !== null && InvitationMetadata::isFailure( $result )
					? self::INVITATION_STATE_EXHAUSTED_FAILURE
					: self::INVITATION_STATE_EXHAUSTED_UNKNOWN;
			}
		}

		return self::INVITATION_STATE_PASSIVE;
	}

	/**
	 * @return list<array{label:string,value:string}>
	 */
	public function stateSearchPaneOptions() :array {
		return \array_map(
			fn( string $state ) :array => [
				'label' => $this->stateLabel( $state ),
				'value' => $state,
			],
			self::STATES
		);
	}

	/**
	 * @return list<array{label:string,value:string}>
	 */
	public function registrationSearchPaneOptions() :array {
		return \array_map(
			fn( string $status ) :array => [
				'label' => $this->registrationLabel( $status ),
				'value' => $status,
			],
			self::REGISTRATION_STATUSES
		);
	}

	/**
	 * @return list<array{label:string,value:string}>
	 */
	public function queueSearchPaneOptions() :array {
		return \array_map(
			fn( string $queueStatus ) :array => [
				'label' => $this->queueLabel( $queueStatus ),
				'value' => $queueStatus,
			],
			self::QUEUE_STATUSES
		);
	}

	/**
	 * @return list<string>
	 */
	public function validStateKeys( array $values ) :array {
		return $this->filterAllowedValues( $values, self::STATES );
	}

	/**
	 * @return list<string>
	 */
	public function validRegistrationStatuses( array $values ) :array {
		return $this->filterAllowedValues( $values, self::REGISTRATION_STATUSES );
	}

	/**
	 * @return list<string>
	 */
	public function validQueueStatuses( array $values ) :array {
		return $this->filterAllowedValues( $values, self::QUEUE_STATUSES );
	}

	public function sqlWhereForStates( array $states ) :string {
		$states = $this->validStateKeys( $states );
		if ( empty( $states ) ) {
			return '';
		}

		$predicates = \array_filter( \array_map(
			fn( string $state ) :string => $this->sqlStatePredicate( $state ),
			$states
		) );

		return $this->combineSqlOr( $predicates );
	}

	public function sqlWhereForRegistrationStatuses( array $statuses ) :string {
		return $this->sqlWhereIn( 'status', $this->validRegistrationStatuses( $statuses ) );
	}

	public function sqlWhereForQueueStatuses( array $queueStatuses ) :string {
		return $this->sqlWhereIn( 'queue_status', $this->validQueueStatuses( $queueStatuses ) );
	}

	public function registrationLabel( string $status ) :string {
		return [
				   SitesDB::STATUS_ACTIVE  => __( 'Active', 'wp-simple-firewall' ),
				   SitesDB::STATUS_DELETED => __( 'Inactive', 'wp-simple-firewall' ),
			   ][ $status ] ?? $this->formatKey( $status );
	}

	public function queueLabel( string $queueStatus ) :string {
		return [
				   SitesDB::QUEUE_IDLE           => __( 'Idle', 'wp-simple-firewall' ),
				   SitesDB::QUEUE_QUEUED         => __( 'Queued', 'wp-simple-firewall' ),
				   SitesDB::QUEUE_PROCESSING     => __( 'Processing', 'wp-simple-firewall' ),
				   SitesDB::QUEUE_WAITING_EXPORT => __( 'Waiting for export', 'wp-simple-firewall' ),
				   SitesDB::QUEUE_PENDING_INVITE => __( 'Pending invite', 'wp-simple-firewall' ),
				   SitesDB::QUEUE_PENDING_CONNECTION => __( 'Pending connection', 'wp-simple-firewall' ),
			   ][ $queueStatus ] ?? $this->formatKey( $queueStatus );
	}

	public function registrationHtml( string $status ) :string {
		$tone = $status === SitesDB::STATUS_ACTIVE ? 'success' : 'secondary';
		return $this->badgeHtml( $this->registrationLabel( $status ), $tone );
	}

	public function queueHtml( string $queueStatus ) :string {
		$tone = [
					SitesDB::QUEUE_IDLE           => 'secondary',
					SitesDB::QUEUE_QUEUED         => 'warning',
					SitesDB::QUEUE_PROCESSING     => 'primary',
					SitesDB::QUEUE_WAITING_EXPORT => 'info',
					SitesDB::QUEUE_PENDING_INVITE => 'warning',
					SitesDB::QUEUE_PENDING_CONNECTION => 'warning',
				][ $queueStatus ] ?? 'secondary';
		return $this->badgeHtml( $this->queueLabel( $queueStatus ), $tone );
	}

	public function stateLabel( string $state ) :string {
		return [
				   self::STATE_INACTIVE     => __( 'Inactive', 'wp-simple-firewall' ),
				   self::STATE_PROBLEM      => __( 'Problem', 'wp-simple-firewall' ),
				   self::STATE_PENDING      => __( 'Pending', 'wp-simple-firewall' ),
				   self::STATE_WORKING      => __( 'Export served', 'wp-simple-firewall' ),
				   self::STATE_NEVER_SYNCED => __( 'Never Synced', 'wp-simple-firewall' ),
			   ][ $state ] ?? $this->formatKey( $state );
	}

	private function buildSummaryHtml( Record $record, string $state, string $detailsHtml ) :string {
		$reason = $this->summaryReason( $record, $state );
		$lastRequest = $this->lastExportRequestSummary( $record );
		$lastExport = $this->lastExportSummary( $record );

		return \sprintf(
			'<div class="import-export-sync-status" data-shield-sync-state="%s"><div class="d-flex align-items-center gap-2">%s%s</div><div class="small mt-1">%s</div><div class="small text-muted">%s</div></div>',
			$this->escAttr( $state ),
			$this->badgeHtml( $this->stateLabel( $state ), $this->stateBadgeTone( $state ) ),
			$this->detailsButtonHtml( $detailsHtml ),
			$this->escHtml( $reason ),
			$this->escHtml( \sprintf( '%s: %s | %s: %s',
				__( 'Last export request', 'wp-simple-firewall' ),
				$lastRequest,
				__( 'Last export', 'wp-simple-firewall' ),
				$lastExport
			) )
		);
	}

	private function buildDetailsHtml( Record $record, string $state, array $diagnostics ) :string {
		$rows = [
			$this->detailRow( __( 'Current state', 'wp-simple-firewall' ), $this->stateLabel( $state ) ),
			$this->detailRow( __( 'Import ID', 'wp-simple-firewall' ), $this->importIDPresenter()->displayValue( $record->import_id ) ),
			$this->detailRow( __( 'Last ping attempt', 'wp-simple-firewall' ), $this->formatTimestamp( $record->last_ping_attempt_at ) ),
			$this->detailRow( __( 'Last notification dispatch attempt', 'wp-simple-firewall' ), $this->formatTimestamp( $record->last_ping_success_at ) ),
			$this->detailRow( __( 'Last ping failure', 'wp-simple-firewall' ), $this->formatTimestamp( $record->last_ping_failure_at ) ),
			$this->detailRow( __( 'Ping HTTP result', 'wp-simple-firewall' ), $record->last_ping_http_code > 0 ? (string)$record->last_ping_http_code : __( 'None recorded', 'wp-simple-firewall' ) ),
			$this->detailRow( __( 'Ping details', 'wp-simple-firewall' ), $this->displayError( $record->last_ping_error ) ),
			$this->detailRow( __( 'Last export request', 'wp-simple-firewall' ), $this->formatTimestamp( $record->last_export_request_at ) ),
			$this->detailRow( __( 'Last export served', 'wp-simple-firewall' ), $this->formatTimestamp( $record->last_export_success_at ) ),
			$this->detailRow( __( 'Last export failure', 'wp-simple-firewall' ), $this->formatTimestamp( $record->last_export_failure_at ) ),
			$this->detailRow( __( 'Last export result', 'wp-simple-firewall' ), $this->exportResultLabel( $record->last_export_result_code, $record->last_export_error ) ),
			$this->detailRow( __( 'Last export details', 'wp-simple-firewall' ), $this->displayExportError( $record ) ),
			$this->detailRow( __( 'Expected export by', 'wp-simple-firewall' ), $this->formatTimestamp( $record->expected_export_by ) ),
			$this->detailRow( __( 'Next ping due', 'wp-simple-firewall' ), $this->formatTimestamp( $record->next_ping_at ) ),
		];
		$rows = \array_merge( $rows, $this->buildInvitationDetailsRows( $record ) );
		foreach ( [
			'notification' => __( 'Latest notification', 'wp-simple-firewall' ),
			'verification' => __( 'Latest verification observation', 'wp-simple-firewall' ),
			'export'       => __( 'Latest export outcome', 'wp-simple-firewall' ),
		] as $slot => $label ) {
			$rows[] = $this->diagnosticDetailRow( $label, $diagnostics[ $slot ] );
		}

		return \sprintf( '<div class="import-export-sync-details"><dl class="mb-0">%s</dl></div>', \implode( '', $rows ) );
	}

	private function summaryReason( Record $record, string $state ) :string {
		switch ( $state ) {
			case self::STATE_INACTIVE:
				return __( 'This site is no longer active for sync.', 'wp-simple-firewall' );
			case self::STATE_PROBLEM:
				if ( $this->isExpiredWaitingExportProblem( $record ) ) {
					return __( 'No qualifying successful export was recorded within the existing grace period.', 'wp-simple-firewall' );
				}
				return $this->shortText( $this->currentFailureReason( $record ) );
			case self::STATE_PENDING:
				return $this->pendingReason( $record );
			case self::STATE_WORKING:
				return __( 'The master served a settings export. Client application is unconfirmed.', 'wp-simple-firewall' );
			case self::STATE_NEVER_SYNCED:
			default:
				return __( 'No settings export has been served yet.', 'wp-simple-firewall' );
		}
	}

	private function pendingReason( Record $record ) :string {
		switch ( $record->queue_status ) {
			case SitesDB::QUEUE_PROCESSING:
				return __( 'A sync ping is currently being processed.', 'wp-simple-firewall' );
			case SitesDB::QUEUE_WAITING_EXPORT:
				return __( 'Update notification sent; waiting for the client to request and download its settings.', 'wp-simple-firewall' );
			case SitesDB::QUEUE_PENDING_INVITE:
			case SitesDB::QUEUE_PENDING_CONNECTION:
				return $this->invitationReason( $record );
			case SitesDB::QUEUE_QUEUED:
				return __( 'This site is queued for its next sync ping.', 'wp-simple-firewall' );
			default:
				return __( 'Sync work is pending.', 'wp-simple-firewall' );
		}
	}

	private function invitationReason( Record $record ) :string {
		$invitation = ( new InvitationMetadata() )->normalize( $record->meta );
		$attempts = $invitation[ 'attempts_started' ];
		switch ( $this->invitationStateForRecord( $record ) ) {
			case self::INVITATION_STATE_QUEUED:
				return __( 'The invitation is queued to be sent.', 'wp-simple-firewall' );
			case self::INVITATION_STATE_STARTED_RETRYABLE:
				return \sprintf(
					/* translators: %1$s: invitation attempt number, %2$s: date and time the next attempt is eligible */
					__( 'Invitation attempt %1$s started, but its outcome is not recorded. The next attempt is eligible at %2$s.', 'wp-simple-firewall' ),
					$attempts,
					$this->formatTimestamp( $record->next_ping_at )
				);
			case self::INVITATION_STATE_RETRY_SCHEDULED:
				return \sprintf(
					/* translators: %1$s: invitation attempt number, %2$s: date and time the next attempt is eligible */
					__( 'Invitation attempt %1$s failed. The next attempt is eligible at %2$s.', 'wp-simple-firewall' ),
					$attempts,
					$this->formatTimestamp( $record->next_ping_at )
				);
			case self::INVITATION_STATE_FINAL_SETTLEMENT:
				return __( 'The final invitation attempt started with no recorded outcome. No further invitation will be sent; pending work will close the cycle.', 'wp-simple-firewall' );
			case self::INVITATION_STATE_RESPONSE_UNCONFIRMED:
				return __( 'The invitation received a response, but the connection is unconfirmed. Check the client for the invitation or an existing master connection. If it is already linked to this master, use Sync settings now on the client.', 'wp-simple-firewall' );
			case self::INVITATION_STATE_EXHAUSTED_FAILURE:
				return __( 'Automatic invitation attempts are exhausted after a failure. Inspect and correct the failure before using Retry invitation to start a new cycle.', 'wp-simple-firewall' );
			case self::INVITATION_STATE_EXHAUSTED_UNKNOWN:
				return __( 'The final invitation outcome is unknown and no further invitation will be sent. Check the client for an invitation or existing master connection before using Retry invitation. If it is already linked to this master, use Sync settings now on the client.', 'wp-simple-firewall' );
			case self::INVITATION_STATE_PASSIVE:
			default:
				return __( 'Waiting for this client to connect. No invitation result is recorded; use Retry invitation or connect from the client. If it is already linked to this master, use Sync settings now on the client.', 'wp-simple-firewall' );
		}
	}

	private function buildInvitationDetailsRows( Record $record ) :array {
		if ( !\in_array( $record->queue_status, [
			SitesDB::QUEUE_PENDING_INVITE,
			SitesDB::QUEUE_PENDING_CONNECTION,
		], true ) ) {
			return [];
		}

		$invitation = ( new InvitationMetadata() )->normalize( $record->meta );
		return [
			$this->detailRow( __( 'Invitation state', 'wp-simple-firewall' ), $this->formatKey( $this->invitationStateForRecord( $record ) ) ),
			$this->detailRow( __( 'Invitation attempts', 'wp-simple-firewall' ), \sprintf( '%d / %d', $invitation[ 'attempts_started' ], InvitationMetadata::MAX_ATTEMPTS ) ),
			$this->detailRow( __( 'Last invitation attempt', 'wp-simple-firewall' ), $this->formatTimestamp( $invitation[ 'last_attempt_started_at' ] ?? 0 ) ),
			$this->detailRow( __( 'Invitation result', 'wp-simple-firewall' ), $invitation[ 'last_result' ] === null ? __( 'None recorded', 'wp-simple-firewall' ) : $this->formatKey( $invitation[ 'last_result' ] ) ),
			$this->detailRow( __( 'Invitation HTTP result', 'wp-simple-firewall' ), $invitation[ 'last_http_status' ] === null ? __( 'None recorded', 'wp-simple-firewall' ) : (string)$invitation[ 'last_http_status' ] ),
		];
	}

	private function currentFailureReason( Record $record ) :string {
		$latestFailure = $this->latestFailureType( $record );
		if ( $latestFailure === 'export' ) {
			return $this->displayExportError( $record );
		}
		if ( $latestFailure === 'ping' ) {
			return $this->displayError( $record->last_ping_error );
		}
		return __( 'The site has recorded sync failures and is waiting to retry.', 'wp-simple-firewall' );
	}

	private function latestFailureType( Record $record ) :string {
		if ( $record->last_export_failure_at > $record->last_ping_failure_at ) {
			return 'export';
		}
		if ( $record->last_ping_failure_at > $record->last_export_failure_at ) {
			return 'ping';
		}
		if ( !empty( $record->last_export_error ) ) {
			return 'export';
		}
		if ( !empty( $record->last_ping_error ) ) {
			return 'ping';
		}
		return '';
	}

	private function lastExportRequestSummary( Record $record ) :string {
		return $record->last_export_request_at > 0
			? $this->formatTimestamp( $record->last_export_request_at )
			: __( 'Never', 'wp-simple-firewall' );
	}

	private function lastExportSummary( Record $record ) :string {
		if ( $record->last_export_success_at > 0 && $record->last_export_success_at >= $record->last_export_failure_at ) {
			return \sprintf( '%s (%s)', __( 'served', 'wp-simple-firewall' ), $this->formatTimestamp( $record->last_export_success_at ) );
		}
		if ( $record->last_export_failure_at > 0 ) {
			return \sprintf( '%s (%s)', __( 'failed', 'wp-simple-firewall' ), $this->formatTimestamp( $record->last_export_failure_at ) );
		}
		return __( 'Never', 'wp-simple-firewall' );
	}

	private function detailsButtonHtml( string $detailsHtml ) :string {
		$label = __( 'Details', 'wp-simple-firewall' );
		$title = __( 'Sync Details', 'wp-simple-firewall' );
		return \sprintf(
			'<button type="button" class="btn btn-sm btn-link p-0 import-export-sync-details__trigger" aria-label="%s" data-bs-toggle="popover" data-shield-sync-details-trigger="1" data-shield-sync-details-title="%s" data-shield-sync-details="%s">%s</button>',
			$this->escAttr( $title ),
			$this->htmlForDataAttribute( $this->escHtml( $title ) ),
			$this->htmlForDataAttribute( $detailsHtml ),
			$this->escHtml( $label )
		);
	}

	/**
	 * The popover reads these attributes and inserts their values as HTML. The browser decodes an attribute once,
	 * so the HTML is encoded again here, entities and all: esc_attr() leaves existing entities as they are, which
	 * would let the decoding undo the escaping inside the HTML.
	 */
	private function htmlForDataAttribute( string $html ) :string {
		return \htmlspecialchars( $html, \ENT_QUOTES, 'UTF-8', true );
	}

	private function detailRow( string $label, string $value ) :string {
		return \sprintf(
			'<div class="d-flex gap-2 align-items-start"><dt class="text-muted flex-shrink-0">%s</dt><dd class="mb-1">%s</dd></div>',
			$this->escHtml( $label ),
			$this->escHtml( $value )
		);
	}

	private function diagnosticDetailRow( string $label, array $diagnostic ) :string {
		if ( !$diagnostic[ 'has_result' ] ) {
			$value = (string)$diagnostic[ 'label' ];
		}
		else {
			$parts = [
				(string)$diagnostic[ 'label' ],
				(string)$diagnostic[ 'observed_at_display' ],
			];
			if ( $diagnostic[ 'http_status' ] !== null ) {
				$parts[] = \sprintf( '%s: %d', __( 'HTTP status', 'wp-simple-firewall' ), $diagnostic[ 'http_status' ] );
			}
			if ( $diagnostic[ 'eligible_at_display' ] !== null ) {
				$parts[] = \sprintf( '%s: %s', __( 'Eligible at', 'wp-simple-firewall' ), $diagnostic[ 'eligible_at_display' ] );
			}
			if ( !empty( $diagnostic[ 'qualification' ] ) ) {
				$parts[] = (string)$diagnostic[ 'qualification' ];
			}
			$parts[] = (string)$diagnostic[ 'explanation' ];
			$parts[] = \sprintf( '%s: %s', __( 'Next check', 'wp-simple-firewall' ), $diagnostic[ 'next_check' ] );
			$value = \implode( ' ', $parts );
		}

		return $this->detailRow( $label, $value );
	}

	private function diagnosticsForRecord( Record $record ) :array {
		$presenter = new ObservationPresenter();
		$repo = new SiteRepository();

		return [
			'notification' => $presenter->present( $repo->readObservation( $record, SyncObservation::PHASE_NOTIFICATION ) ),
			'verification' => $presenter->present( $repo->readObservation( $record, SyncObservation::PHASE_VERIFICATION ) ),
			'export'       => $presenter->present( $repo->readObservation( $record, SyncObservation::PHASE_EXPORT ) ),
		];
	}

	private function displayError( string $error ) :string {
		$error = \trim( $error );
		return empty( $error ) ? __( 'None recorded', 'wp-simple-firewall' ) : $error;
	}

	private function displayExportError( Record $record ) :string {
		if ( $record->last_export_result_code === SitesDB::EXPORT_RESULT_TIMEOUT
			 || $record->last_export_error === 'export_not_requested_before_grace_window' ) {
			return __( 'No qualifying successful export was recorded within the existing grace period.', 'wp-simple-firewall' );
		}
		return $this->displayError( $record->last_export_error );
	}

	private function exportResultLabel( string $resultCode, string $error ) :string {
		if ( $resultCode === SitesDB::EXPORT_RESULT_TIMEOUT
			 || $error === 'export_not_requested_before_grace_window' ) {
			return __( 'Export timeout', 'wp-simple-firewall' );
		}

		return empty( $resultCode ) ? __( 'None recorded', 'wp-simple-firewall' ) : $this->formatKey( $resultCode );
	}

	private function isExpiredWaitingExportProblem( Record $record ) :bool {
		return ExportWaitState::isExpired( $record, $this->now );
	}

	private function stateBadgeTone( string $state ) :string {
		return [
				   self::STATE_PROBLEM      => 'danger',
				   self::STATE_PENDING      => 'warning',
				   self::STATE_WORKING      => 'success',
				   self::STATE_NEVER_SYNCED => 'secondary',
				   self::STATE_INACTIVE     => 'secondary',
			   ][ $state ] ?? 'secondary';
	}

	private function badgeHtml( string $label, string $tone ) :string {
		return \sprintf(
			'<span class="badge text-bg-%s">%s</span>',
			$this->escAttr( $tone ),
			$this->escHtml( $label )
		);
	}

	private function sqlStatePredicate( string $state ) :string {
		switch ( $state ) {
			case self::STATE_INACTIVE:
				return \sprintf( '`status`<>%s', $this->sqlValue( SitesDB::STATUS_ACTIVE ) );
			case self::STATE_PROBLEM:
				return \sprintf( '(%s AND (%s OR %s))',
					$this->sqlActive(),
					$this->sqlExpiredWaitingExportProblem(),
					QueuedSyncState::sqlHasProblem()
				);
			case self::STATE_PENDING:
				return \sprintf( '(%s AND (`queue_status` IN (%s,%s) OR `queue_status`=%s OR (`queue_status`=%s AND NOT (%s)) OR (`queue_status`=%s AND NOT (%s))))',
					$this->sqlActive(),
					$this->sqlValue( SitesDB::QUEUE_PENDING_INVITE ),
					$this->sqlValue( SitesDB::QUEUE_PENDING_CONNECTION ),
					$this->sqlValue( SitesDB::QUEUE_PROCESSING ),
					$this->sqlValue( SitesDB::QUEUE_WAITING_EXPORT ),
					$this->sqlExpiredWaitingExportProblem(),
					$this->sqlValue( SitesDB::QUEUE_QUEUED ),
					QueuedSyncState::sqlHasProblem()
				);
			case self::STATE_WORKING:
				return \sprintf( '(%s AND `queue_status`=%s AND `last_export_success_at`>0 AND NOT (%s))',
					$this->sqlActive(),
					$this->sqlValue( SitesDB::QUEUE_IDLE ),
					QueuedSyncState::sqlHasProblem()
				);
			case self::STATE_NEVER_SYNCED:
				return \sprintf( '(%s AND `queue_status`=%s AND `last_export_success_at`<=0 AND NOT (%s))',
					$this->sqlActive(),
					$this->sqlValue( SitesDB::QUEUE_IDLE ),
					QueuedSyncState::sqlHasProblem()
				);
			default:
				return '';
		}
	}

	private function sqlExpiredWaitingExportProblem() :string {
		return \sprintf(
			'(`queue_status`=%s AND `expected_export_by`>0 AND `expected_export_by`<=%d AND %s)',
			$this->sqlValue( SitesDB::QUEUE_WAITING_EXPORT ),
			$this->now,
			ExportWaitState::sqlUnsatisfiedSuccess()
		);
	}

	private function sqlActive() :string {
		return \sprintf( '`status`=%s', $this->sqlValue( SitesDB::STATUS_ACTIVE ) );
	}

	private function sqlWhereIn( string $column, array $values ) :string {
		if ( empty( $values ) ) {
			return '';
		}

		$column = $this->sqlColumn( $column );
		if ( \count( $values ) === 1 ) {
			return \sprintf( '`%s`=%s', $column, $this->sqlValue( \array_pop( $values ) ) );
		}

		return \sprintf( '`%s` IN (%s)', $column, \implode( ',', \array_map( [ $this, 'sqlValue' ], $values ) ) );
	}

	private function combineSqlOr( array $predicates ) :string {
		if ( empty( $predicates ) ) {
			return '';
		}

		return \count( $predicates ) === 1
			? \array_pop( $predicates )
			: \sprintf( '(%s)', \implode( ' OR ', $predicates ) );
	}

	/**
	 * @return list<string>
	 */
	private function filterAllowedValues( array $values, array $allowed ) :array {
		$allowed = \array_flip( $allowed );
		return \array_values( \array_unique( \array_filter(
			\array_map(
				static fn( $value ) :string => (string)$value,
				\array_filter( $values, static fn( $value ) :bool => \is_scalar( $value ) )
			),
			static fn( string $value ) :bool => isset( $allowed[ $value ] )
		) ) );
	}

	private function sqlColumn( string $column ) :string {
		if ( !\in_array( $column, [ 'status', 'queue_status' ], true ) ) {
			throw new \InvalidArgumentException( 'Invalid import/export sites filter column.' );
		}
		return $column;
	}

	private function sqlValue( string $value ) :string {
		return "'".\str_replace( "'", "''", $value )."'";
	}

	private function formatTimestamp( int $ts ) :string {
		if ( $ts <= 0 ) {
			return __( 'Never', 'wp-simple-firewall' );
		}

		return \wp_date( 'Y-m-d H:i:s T', $ts );
	}

	private function formatKey( string $key ) :string {
		return \ucwords( \str_replace( '_', ' ', $key ) );
	}

	private function shortText( string $text ) :string {
		$text = \trim( $text );
		return \strlen( $text ) > 160 ? \substr( $text, 0, 157 ).'...' : $text;
	}

	private function escHtml( string $value ) :string {
		return esc_html( $value );
	}

	private function escAttr( string $value ) :string {
		return esc_attr( $value );
	}

	private function importIDPresenter() :ImportIDPresenter {
		return $this->importIDPresenter ??= new ImportIDPresenter();
	}

	private function currentTimestamp() :int {
		return Services::Request()->ts();
	}
}
