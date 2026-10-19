<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Options\Transient;

class BackupsCon {

	use PluginControllerConsumer;

	public const BREACH_STATUS_URL = 'https://status.orlesecurity.com/shield.json';
	private const BREACH_STATUS_CACHE_KEY = 'apto-shield-backups-breach-status';
	private const BREACH_STATUS_CACHE_TTL = 600;

	private array $status;

	public function __construct() {
		$this->status = \array_merge( [
			'last_archive_aborted'    => false,
			'last_archive_begin_at'   => 0,
			'last_archive_end_at'     => 0,
			'last_archive_fail_at'    => 0,
			'last_archive_success_at' => 0,
			'last_archive_duration'   => 0,
		], self::con()->opts->optGet( 'shieldbackups_status' ) );
	}

	public function processSignal( string $context, array $data ) :void {
		if ( $context === 'backup' ) {
			if ( $data[ 'state' ] === 'begin' ) {
				$this->beginBackup();
			}
			elseif ( $data[ 'state' ] === 'end' && \is_bool( $data[ 'success' ] ?? null ) ) {
				$this->endBackup( $data[ 'success' ] );
			}
		}
	}

	/**
	 * The single gate for the local and remote switches. Defaults leave every request permitted.
	 */
	public function isRequestPermitted( string $route ) :bool {
		$refusal = '';
		if ( !$this->isEnabled() ) {
			$refusal = 'backup_refused_disabled';
		}
		elseif ( $this->isPausedRemotely() ) {
			$refusal = 'backup_refused_paused';
		}

		if ( !empty( $refusal ) ) {
			$this->recordRefusedRequest( $refusal, $route );
		}
		return empty( $refusal );
	}

	public function isEnabled() :bool {
		return !self::con()->opts->optIs( 'shieldbackups_enabled', 'N' );
	}

	public function setEnabled( bool $enabled ) :void {
		self::con()->opts->optSet( 'shieldbackups_enabled', $enabled ? 'Y' : 'N' )->store();
	}

	/**
	 * Fails open: anything other than an explicit {"backups": {"paused": true}} leaves backups running.
	 */
	public function isPausedRemotely() :bool {
		$cached = Transient::Get( self::BREACH_STATUS_CACHE_KEY );
		if ( !\is_array( $cached ) ) {
			try {
				$decoded = \json_decode( Services::HttpRequest()->getContent( self::BREACH_STATUS_URL, [ 'timeout' => 3 ] ), true );
			}
			catch ( \Throwable $e ) {
				$decoded = null;
			}
			$cached = [
				'paused' => ( $decoded[ 'backups' ][ 'paused' ] ?? false ) === true,
			];
			Transient::Set( self::BREACH_STATUS_CACHE_KEY, $cached, self::BREACH_STATUS_CACHE_TTL );
		}
		return !empty( $cached[ 'paused' ] );
	}

	public function recordRefusedRequest( string $event, string $route ) :void {
		$this->fireEvent( $event, [
			'ip'    => (string)self::con()->this_req->ip,
			'route' => $route,
		] );
	}

	/**
	 * @return array{started_at:int,finished_at:int,result:string,duration:int}|null - null when no backup has run.
	 */
	public function lastBackup() :?array {
		if ( empty( $this->status[ 'last_archive_begin_at' ] ) ) {
			return null;
		}
		$running = $this->isBackupRunning();
		return [
			'started_at'  => (int)$this->status[ 'last_archive_begin_at' ],
			'finished_at' => $running ? 0 : (int)$this->status[ 'last_archive_end_at' ],
			'result'      => $running ? 'running' : ( $this->status[ 'last_archive_aborted' ] ? 'failed' : 'success' ),
			'duration'    => $running ? 0 : (int)$this->status[ 'last_archive_duration' ],
		];
	}

	private function beginBackup() :void {
		$this->status[ 'last_archive_begin_at' ] = Services::Request()->ts();
		$this->store();
		$this->fireEvent( 'backup_started' );
	}

	private function endBackup( bool $success ) :void {
		if ( $this->hasUnfinishedBackup() ) {
			$this->status[ 'last_archive_end_at' ]
				= $this->status[ $success ? 'last_archive_success_at' : 'last_archive_fail_at' ]
				= Services::Request()->ts();
			$this->status[ 'last_archive_duration' ] =
				Services::Request()->ts() - $this->status[ 'last_archive_begin_at' ];
			$this->status[ 'last_archive_aborted' ] = !$success;
			$this->fireEvent( 'backup_finished', [
				'result'   => $success ? 'success' : 'failed',
				'duration' => $this->status[ 'last_archive_duration' ],
			] );
		}
		$this->store();
	}

	public function isBackupRunning() :bool {
		$this->resolveStatus();
		return $this->hasUnfinishedBackup();
	}

	private function resolveStatus() :void {
		if ( $this->hasUnfinishedBackup()
			 && ( Services::Request()->ts() - $this->status[ 'last_archive_begin_at' ] > \HOUR_IN_SECONDS*8 ) ) {
			$this->endBackup( false );
		}
	}

	private function hasUnfinishedBackup() :bool {
		return $this->status[ 'last_archive_begin_at' ] > $this->status[ 'last_archive_end_at' ];
	}

	/**
	 * Logging must never interfere with a backup request.
	 */
	private function fireEvent( string $event, array $auditParams = [] ) :void {
		try {
			self::con()->comps->events->fireEvent( $event, empty( $auditParams ) ? [] : [ 'audit_params' => $auditParams ] );
		}
		catch ( \Throwable $e ) {
		}
	}

	private function store() :void {
		self::con()->opts->optSet( 'shieldbackups_status', $this->status );
	}
}
