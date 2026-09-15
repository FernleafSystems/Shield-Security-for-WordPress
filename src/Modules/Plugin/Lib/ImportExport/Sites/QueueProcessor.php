<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Sites;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\ImportExportSites\Ops\{
	Handler as SitesDB,
	Record
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\BackgroundProcessing\BackgroundProcess;

class QueueProcessor extends BackgroundProcess {

	use PluginControllerConsumer;

	public const START_CUTOFF = 10.0;
	public const INVITE_TIMEOUT = 2;
	public const NOTIFY_TIMEOUT = 5;
	public const EXPORT_GRACE = 600;
	public const INVITE_PROCESSING_DEADLINE = 600;
	private const EXPORT_MAINTENANCE_LIMIT = 5;

	private \Closure $canRun;
	private float $processingStartedAt = 0.0;

	public function __construct( ?callable $canRun = null ) {
		$this->canRun = \Closure::fromCallable( $canRun ?? static fn() :bool => false );
		parent::__construct(
			'importexport_sites_queue_'.\get_current_blog_id(),
			self::con()->getPluginPrefix( '_' )
		);
	}

	public function maybe_handle() {
		\session_write_close();
		\check_ajax_referer( $this->identifier, 'nonce' );
		if ( $this->canRun() && !$this->is_processing() ) {
			$this->handle();
		}
		return $this->maybe_wp_die();
	}

	public function runFromCron() :void {
		if ( $this->canRun() && !$this->is_processing() ) {
			$this->handle();
		}
	}

	protected function handle() {
		$this->processingStartedAt = $this->now();
		$this->lock_process();
		$workHandled = false;
		$halt = false;

		try {
			$repo = $this->repository();
			if ( !$repo->ensureLegacyImported() ) {
				$halt = true;
			}

			if ( !$halt ) {
				foreach ( $repo->selectExportMaintenanceRows( self::EXPORT_MAINTENANCE_LIMIT ) as $row ) {
					$result = ExportWaitState::isReconcilable( $row )
						? $repo->recordExportReconciliation( $row )
						: $repo->recordExportTimeout( $row );
					if ( $result === false ) {
						$halt = true;
						break;
					}
					$workHandled = true;
				}
			}

			while ( !$halt && !$this->memory_exceeded() && !$this->cutoffReached() ) {
				$interrupted = $repo->selectNextInterruptedNotification();
				if ( $interrupted instanceof Record ) {
					$interrupted = $repo->refreshInterruptedNotification( $interrupted );
					if ( !$interrupted instanceof Record ) {
						continue;
					}
				}
				$row = $interrupted ?? $repo->selectNextDueWork();
				if ( !$row instanceof Record ) {
					break;
				}
				if ( !$this->canRun() || $this->cutoffReached() ) {
					break;
				}

				if ( $row->queue_status === SitesDB::QUEUE_PENDING_INVITE ) {
					$result = $this->processInvitation( $repo, $row );
					if ( $result === false ) {
						$halt = true;
					}
					elseif ( $result === true ) {
						$workHandled = true;
					}
					else {
						$workHandled = true;
						break;
					}
				}
				else {
					$result = $this->processNotification( $repo, $row, $interrupted instanceof Record );
					if ( $result === false ) {
						$halt = true;
					}
					else {
						$workHandled = true;
					}
				}
			}
		}
		finally {
			$this->unlock_process();
		}

		if ( !$halt && $workHandled && $this->canRun() && $this->repository()->hasActionableWork() ) {
			$this->dispatch();
		}
		return null;
	}

	private function processInvitation( SiteRepository $repo, Record $row ) :?bool {
		$invitation = ( new InvitationMetadata() )->normalize( $row->meta );
		if ( $invitation[ 'attempts_started' ] >= InvitationMetadata::MAX_ATTEMPTS ) {
			$result = $repo->settleInterruptedFinalInviteAttempt( $row );
			return $result === false ? false : ( $result === 1 ? true : null );
		}

		$result = $repo->startInviteAttempt( $row, Services::Request()->ts() );
		if ( $result !== 1 ) {
			return $result === false ? false : null;
		}

		try {
			$outcome = $this->inviteSender()->send( $row->url, self::INVITE_TIMEOUT );
		}
		catch ( \Throwable $e ) {
			$outcome = [
				'result'      => InvitationMetadata::RESULT_SENDER_FAILURE,
				'http_status' => 0,
			];
		}
		$result = $repo->recordInviteResult( $row, (string)$outcome[ 'result' ], (int)$outcome[ 'http_status' ] );
		return $result === false ? false : ( $result === 1 ? true : null );
	}

	/**
	 * @return false|int
	 */
	private function processNotification( SiteRepository $repo, Record $row, bool $recovery ) {
		$metadata = new NotificationMetadata();
		if ( $recovery && $metadata->attemptsStarted( $row->meta ) >= NotificationMetadata::MAX_ATTEMPTS ) {
			return $repo->recordInterruptedNotificationExhaustion( $row );
		}

		if ( !$repo->startNotificationAttempt( $row, Services::Request()->ts(), $recovery ) ) {
			return false;
		}

		try {
			$result = $this->pingSender()->send( $row->url, self::NOTIFY_TIMEOUT, (string)$row->import_id );
		}
		catch ( \Throwable $e ) {
			return $repo->recordPingFailure( $row, 0, 'Notification sender failed.' );
		}

		return $result[ 'success' ]
			? $repo->recordNotifyDispatched( $row, (int)$result[ 'http_code' ], Services::Request()->ts() + self::EXPORT_GRACE )
			: $repo->recordPingFailure( $row, (int)$result[ 'http_code' ], (string)$result[ 'error' ] );
	}

	/** @phpstan-impure */
	protected function cutoffReached() :bool {
		return $this->now() - $this->processingStartedAt >= self::START_CUTOFF;
	}

	protected function now() :float {
		return \microtime( true );
	}

	protected function repository() :SiteRepository {
		return new SiteRepository();
	}

	protected function pingSender() :PingSender {
		return new PingSender();
	}

	protected function inviteSender() :SyncSiteInviteSender {
		return new SyncSiteInviteSender();
	}

	protected function is_queue_empty() {
		return !$this->repository()->hasActionableWork();
	}

	protected function schedule_event() {
	}

	protected function clear_scheduled_event() {
	}

	public function schedule_cron_healthcheck( $schedules ) {
		return $schedules;
	}

	public function handle_cron_healthcheck() {
	}

	protected function task( $item ) {
		return false;
	}

	private function canRun() :bool {
		return ( $this->canRun )();
	}
}
