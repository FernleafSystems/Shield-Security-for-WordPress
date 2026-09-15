<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\ImportExportSites;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\ImportExportSites\Ops\{
	Handler as SitesDB,
	Record
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Sites\{
	ExportWaitState,
	InvitationMetadata
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
	 *   details_html:string
	 * }
	 */
	public function build( Record $record ) :array {
		$state = $this->stateForRecord( $record );
		$details = $this->buildDetailsHtml( $record, $state );

		return [
			'state_key'    => $state,
			'label'        => $this->stateLabel( $state ),
			'badge_tone'   => $this->stateBadgeTone( $state ),
			'summary_html' => $this->buildSummaryHtml( $record, $state, $details ),
			'details_html' => $details,
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
			 || ( $record->queue_status === SitesDB::QUEUE_QUEUED && !$this->hasQueuedOrIdleProblem( $record ) ) ) {
			return self::STATE_PENDING;
		}

		if ( \in_array( $record->queue_status, [ SitesDB::QUEUE_QUEUED, SitesDB::QUEUE_IDLE ], true )
			 && $this->hasQueuedOrIdleProblem( $record ) ) {
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
				   SitesDB::STATUS_ACTIVE  => $this->text( 'Active' ),
				   SitesDB::STATUS_DELETED => $this->text( 'Inactive' ),
			   ][ $status ] ?? $this->formatKey( $status );
	}

	public function queueLabel( string $queueStatus ) :string {
		return [
				   SitesDB::QUEUE_IDLE           => $this->text( 'Idle' ),
				   SitesDB::QUEUE_QUEUED         => $this->text( 'Queued' ),
				   SitesDB::QUEUE_PROCESSING     => $this->text( 'Processing' ),
				   SitesDB::QUEUE_WAITING_EXPORT => $this->text( 'Waiting for export' ),
				   SitesDB::QUEUE_PENDING_INVITE => $this->text( 'Pending invite' ),
				   SitesDB::QUEUE_PENDING_CONNECTION => $this->text( 'Pending connection' ),
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
				   self::STATE_INACTIVE     => $this->text( 'Inactive' ),
				   self::STATE_PROBLEM      => $this->text( 'Problem' ),
				   self::STATE_PENDING      => $this->text( 'Pending' ),
				   self::STATE_WORKING      => $this->text( 'Working' ),
				   self::STATE_NEVER_SYNCED => $this->text( 'Never Synced' ),
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
				$this->text( 'Last export request' ),
				$lastRequest,
				$this->text( 'Last export' ),
				$lastExport
			) )
		);
	}

	private function buildDetailsHtml( Record $record, string $state ) :string {
		$rows = [
			$this->detailRow( $this->text( 'Current state' ), $this->stateLabel( $state ) ),
			$this->detailRow( $this->text( 'Import ID' ), $this->importIDPresenter()->displayValue( $record->import_id ) ),
			$this->detailRow( $this->text( 'Last ping attempt' ), $this->formatTimestamp( $record->last_ping_attempt_at ) ),
			$this->detailRow( $this->text( 'Last ping success' ), $this->formatTimestamp( $record->last_ping_success_at ) ),
			$this->detailRow( $this->text( 'Last ping failure' ), $this->formatTimestamp( $record->last_ping_failure_at ) ),
			$this->detailRow( $this->text( 'Ping HTTP result' ), $record->last_ping_http_code > 0 ? (string)$record->last_ping_http_code : $this->text( 'None recorded' ) ),
			$this->detailRow( $this->text( 'Ping details' ), $this->displayError( $record->last_ping_error ) ),
			$this->detailRow( $this->text( 'Last export request' ), $this->formatTimestamp( $record->last_export_request_at ) ),
			$this->detailRow( $this->text( 'Last export success' ), $this->formatTimestamp( $record->last_export_success_at ) ),
			$this->detailRow( $this->text( 'Last export failure' ), $this->formatTimestamp( $record->last_export_failure_at ) ),
			$this->detailRow( $this->text( 'Export result' ), $this->exportResultLabel( $record->last_export_result_code, $record->last_export_error ) ),
			$this->detailRow( $this->text( 'Export details' ), $this->displayExportError( $record ) ),
			$this->detailRow( $this->text( 'Expected export by' ), $this->formatTimestamp( $record->expected_export_by ) ),
			$this->detailRow( $this->text( 'Next ping due' ), $this->formatTimestamp( $record->next_ping_at ) ),
		];
		$rows = \array_merge( $rows, $this->buildInvitationDetailsRows( $record ) );

		return \sprintf( '<div class="import-export-sync-details"><dl class="mb-0">%s</dl></div>', \implode( '', $rows ) );
	}

	private function summaryReason( Record $record, string $state ) :string {
		switch ( $state ) {
			case self::STATE_INACTIVE:
				return $this->text( 'This site is no longer active for sync.' );
			case self::STATE_PROBLEM:
				if ( $this->isExpiredWaitingExportProblem( $record ) ) {
					return $this->text( 'Export request timed out before this site sent its export.' );
				}
				return $this->shortText( $this->currentFailureReason( $record ) );
			case self::STATE_PENDING:
				return $this->pendingReason( $record );
			case self::STATE_WORKING:
				return $this->text( 'The latest export completed successfully.' );
			case self::STATE_NEVER_SYNCED:
			default:
				return $this->text( 'No export has been received yet.' );
		}
	}

	private function pendingReason( Record $record ) :string {
		switch ( $record->queue_status ) {
			case SitesDB::QUEUE_PROCESSING:
				return $this->text( 'A sync ping is currently being processed.' );
			case SitesDB::QUEUE_WAITING_EXPORT:
				return $this->text( 'Update notification sent; waiting for this site to send its export.' );
			case SitesDB::QUEUE_PENDING_INVITE:
			case SitesDB::QUEUE_PENDING_CONNECTION:
				return $this->invitationReason( $record );
			case SitesDB::QUEUE_QUEUED:
				return $this->text( 'This site is queued for its next sync ping.' );
			default:
				return $this->text( 'Sync work is pending.' );
		}
	}

	private function invitationReason( Record $record ) :string {
		$invitation = ( new InvitationMetadata() )->normalize( $record->meta );
		$attempts = $invitation[ 'attempts_started' ];
		switch ( $this->invitationStateForRecord( $record ) ) {
			case self::INVITATION_STATE_QUEUED:
				return $this->text( 'The invitation is queued to be sent.' );
			case self::INVITATION_STATE_STARTED_RETRYABLE:
				return \sprintf(
					$this->text( 'Invitation attempt %1$s started, but its outcome is not recorded. The next attempt is eligible at %2$s.' ),
					$attempts,
					$this->formatTimestamp( $record->next_ping_at )
				);
			case self::INVITATION_STATE_RETRY_SCHEDULED:
				return \sprintf(
					$this->text( 'Invitation attempt %1$s failed. The next attempt is eligible at %2$s.' ),
					$attempts,
					$this->formatTimestamp( $record->next_ping_at )
				);
			case self::INVITATION_STATE_FINAL_SETTLEMENT:
				return $this->text( 'The final invitation attempt started with no recorded outcome. No further invitation will be sent; pending work will close the cycle.' );
			case self::INVITATION_STATE_RESPONSE_UNCONFIRMED:
				return $this->text( 'The invitation received a response, but the connection is unconfirmed. Check the client for the invitation or an existing master connection. If it is already linked to this master, use Sync settings now on the client.' );
			case self::INVITATION_STATE_EXHAUSTED_FAILURE:
				return $this->text( 'Automatic invitation attempts are exhausted after a failure. Inspect and correct the failure before using Retry invitation to start a new cycle.' );
			case self::INVITATION_STATE_EXHAUSTED_UNKNOWN:
				return $this->text( 'The final invitation outcome is unknown and no further invitation will be sent. Check the client for an invitation or existing master connection before using Retry invitation. If it is already linked to this master, use Sync settings now on the client.' );
			case self::INVITATION_STATE_PASSIVE:
			default:
				return $this->text( 'Waiting for this client to connect. No invitation result is recorded; use Retry invitation or connect from the client. If it is already linked to this master, use Sync settings now on the client.' );
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
			$this->detailRow( $this->text( 'Invitation state' ), $this->formatKey( $this->invitationStateForRecord( $record ) ) ),
			$this->detailRow( $this->text( 'Invitation attempts' ), \sprintf( '%d / %d', $invitation[ 'attempts_started' ], InvitationMetadata::MAX_ATTEMPTS ) ),
			$this->detailRow( $this->text( 'Last invitation attempt' ), $this->formatTimestamp( $invitation[ 'last_attempt_started_at' ] ?? 0 ) ),
			$this->detailRow( $this->text( 'Invitation result' ), $invitation[ 'last_result' ] === null ? $this->text( 'None recorded' ) : $this->formatKey( $invitation[ 'last_result' ] ) ),
			$this->detailRow( $this->text( 'Invitation HTTP result' ), $invitation[ 'last_http_status' ] === null ? $this->text( 'None recorded' ) : (string)$invitation[ 'last_http_status' ] ),
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
		return $this->text( 'The site has recorded sync failures and is waiting to retry.' );
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
			: $this->text( 'Never' );
	}

	private function lastExportSummary( Record $record ) :string {
		if ( $record->last_export_success_at > 0 && $record->last_export_success_at >= $record->last_export_failure_at ) {
			return \sprintf( '%s (%s)', $this->text( 'success' ), $this->formatTimestamp( $record->last_export_success_at ) );
		}
		if ( $record->last_export_failure_at > 0 ) {
			return \sprintf( '%s (%s)', $this->text( 'failed' ), $this->formatTimestamp( $record->last_export_failure_at ) );
		}
		return $this->text( 'Never' );
	}

	private function detailsButtonHtml( string $detailsHtml ) :string {
		$label = $this->text( 'Details' );
		$title = $this->text( 'Sync Details' );
		return \sprintf(
			'<button type="button" class="btn btn-sm btn-link p-0 import-export-sync-details__trigger" aria-label="%s" data-bs-toggle="popover" data-shield-sync-details-trigger="1" data-shield-sync-details-title="%s" data-shield-sync-details="%s">%s</button>',
			$this->escAttr( $title ),
			$this->escAttr( $title ),
			$this->escAttr( $detailsHtml ),
			$this->escHtml( $label )
		);
	}

	private function detailRow( string $label, string $value ) :string {
		return \sprintf(
			'<div class="d-flex gap-2 align-items-start"><dt class="text-muted flex-shrink-0">%s</dt><dd class="mb-1">%s</dd></div>',
			$this->escHtml( $label ),
			$this->escHtml( $value )
		);
	}

	private function displayError( string $error ) :string {
		$error = \trim( $error );
		return empty( $error ) ? $this->text( 'None recorded' ) : $error;
	}

	private function displayExportError( Record $record ) :string {
		if ( $record->last_export_result_code === SitesDB::EXPORT_RESULT_TIMEOUT
			 || $record->last_export_error === 'export_not_requested_before_grace_window' ) {
			return $this->text( 'Export request timed out before this site sent its export.' );
		}
		return $this->displayError( $record->last_export_error );
	}

	private function exportResultLabel( string $resultCode, string $error ) :string {
		if ( $resultCode === SitesDB::EXPORT_RESULT_TIMEOUT
			 || $error === 'export_not_requested_before_grace_window' ) {
			return $this->text( 'Export timeout' );
		}

		return empty( $resultCode ) ? $this->text( 'None recorded' ) : $this->formatKey( $resultCode );
	}

	private function isExpiredWaitingExportProblem( Record $record ) :bool {
		return ExportWaitState::isExpired( $record, $this->now );
	}

	private function hasQueuedOrIdleProblem( Record $record ) :bool {
		return \in_array( $record->queue_status, [ SitesDB::QUEUE_QUEUED, SitesDB::QUEUE_IDLE ], true )
			   && ( $record->consecutive_failures > 0 || $this->hasFailureNewerThanExportSuccess( $record ) );
	}

	private function hasFailureNewerThanExportSuccess( Record $record ) :bool {
		return \max( $record->last_ping_failure_at, $record->last_export_failure_at ) > $record->last_export_success_at;
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
					$this->sqlQueuedOrIdleProblem()
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
					$this->sqlQueuedOrIdleProblem()
				);
			case self::STATE_WORKING:
				return \sprintf( '(%s AND `queue_status`=%s AND `last_export_success_at`>0 AND NOT (%s))',
					$this->sqlActive(),
					$this->sqlValue( SitesDB::QUEUE_IDLE ),
					$this->sqlQueuedOrIdleProblem()
				);
			case self::STATE_NEVER_SYNCED:
				return \sprintf( '(%s AND `queue_status`=%s AND `last_export_success_at`<=0 AND NOT (%s))',
					$this->sqlActive(),
					$this->sqlValue( SitesDB::QUEUE_IDLE ),
					$this->sqlQueuedOrIdleProblem()
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

	private function sqlQueuedOrIdleProblem() :string {
		return \sprintf(
			'(`queue_status` IN (%s,%s) AND (`consecutive_failures`>0 OR `last_ping_failure_at`>`last_export_success_at` OR `last_export_failure_at`>`last_export_success_at`))',
			$this->sqlValue( SitesDB::QUEUE_QUEUED ),
			$this->sqlValue( SitesDB::QUEUE_IDLE )
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
			return $this->text( 'Never' );
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

	private function text( string $text ) :string {
		return __( $text, 'wp-simple-firewall' );
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
