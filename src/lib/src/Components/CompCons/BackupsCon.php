<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class BackupsCon {

	use PluginControllerConsumer;

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

	private function beginBackup() :void {
		$this->status[ 'last_archive_begin_at' ] = Services::Request()->ts();
		$this->store();
	}

	private function endBackup( bool $success ) :void {
		if ( $this->status[ 'last_archive_begin_at' ] > $this->status[ 'last_archive_end_at' ] ) {
			$this->status[ 'last_archive_end_at' ]
				= $this->status[ $success ? 'last_archive_success_at' : 'last_archive_fail_at' ]
				= Services::Request()->ts();
			$this->status[ 'last_archive_duration' ] =
				Services::Request()->ts() - $this->status[ 'last_archive_begin_at' ];
			$this->status[ 'last_archive_aborted' ] = !$success;
		}
		$this->store();
	}

	public function isBackupRunning() :bool {
		$this->resolveStatus();
		return $this->status[ 'last_archive_begin_at' ] > $this->status[ 'last_archive_end_at' ];
	}

	private function resolveStatus() :void {
		if ( $this->isBackupRunning()
			 && ( Services::Request()->ts() - $this->status[ 'last_archive_begin_at' ] > \HOUR_IN_SECONDS*8 ) ) {
			$this->endBackup( false );
		}
	}

	private function store() :void {
		self::con()->opts->optSet( 'shieldbackups_status', $this->status );
	}
}