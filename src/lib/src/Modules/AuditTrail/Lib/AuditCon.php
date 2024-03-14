<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Crons\PluginCronsConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\Snapshots\Ops\Record;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Auditors;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\LogHandlers\Utility\LogFileDirCreate;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Snapshots\DiffVO;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Snapshots\Ops;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Snapshots\SnapshotVO;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class AuditCon {

	use ExecOnce;
	use ModConsumer;
	use PluginCronsConsumer;

	/**
	 * @var Auditors\Base[]
	 */
	private $auditors = null;

	/**
	 * @var Snapshots\SnapshotVO[]
	 */
	private $latestSnapshots;

	/**
	 * @var Snapshots\Queues\SnapshotDiscovery
	 */
	private $snapshotDiscoveryQueue;

	protected function canRun() :bool {
		return self::con()->opts->optIs( 'enable_audit_trail', 'Y' )
			   && self::con()->db_con->dbhActivityLogs()->isReady();
	}

	protected function run() {
		if ( Services::WpGeneral()->isCron() ) {
			$this->setupCronHooks();
		}

		( new AuditLogger() )->setIfCommit( true );

		\array_map( function ( $auditor ) {
			$auditor->execute();
		}, $this->getAuditors() );

		// Realtime Snapshotting
		if ( self::con()->db_con->dbhSnapshots()->isReady() ) {
			add_action( 'wp_loaded', function () {
				\array_map(
					function ( $auditor ) {
						if ( $auditor->canSnapRealtime() ) {
							$this->runSnapshotDiscovery( $auditor );
						}
					},
					$this->getAuditors()
				);

				$this->primeSnapshots();
			} );

			$this->getSnapshotDiscoveryQueue();
		}
	}

	public function getAutoCleanDays() :int {
		$con = self::con();
		$days = (int)\min( $con->opts->optGet( 'audit_trail_auto_clean' ), $con->caps->getMaxLogRetentionDays() );
		$con->opts->optSet( 'audit_trail_auto_clean', $days );
		return $days;
	}

	public function getLogFilePath() :string {
		try {
			$dir = ( new LogFileDirCreate() )->run();
		}
		catch ( \Exception $e ) {
			$dir = '';
		}

		$path = empty( $dir ) ? '' : path_join( $dir, 'shield.log' );
		return apply_filters( 'shield/audit_trail_log_file_path', $path );
	}

	public function getLogFileRotationLimit() :int {
		return (int)apply_filters( 'shield/audit_trail_log_file_rotation_limit', 5 );
	}

	public function getLogLevelsDB() :array {
		$optsCon = self::con()->opts;
		$levels = $optsCon->optGet( 'log_level_db' );
		if ( empty( $levels ) || !\is_array( $levels ) ) {
			$optsCon->optReset( 'log_level_db' );
		}
		elseif ( \count( $levels ) > 1 && \in_array( 'disabled', $levels ) ) {
			$optsCon->optSet( 'log_level_db', [ 'disabled' ] );
		}
		return $optsCon->optGet( 'log_level_db' );
	}

	public function isLogToDB() :bool {
		return self::con()->opts->optIs( 'enable_audit_trail', 'Y' )
			   && !\in_array( 'disabled', $this->getLogLevelsDB() );
	}

	private function primeSnapshots() {
		$primerHook = self::con()->prefix( 'auditcon_prime_snapshots' );

		if ( !wp_next_scheduled( $primerHook ) ) {
			$countAllSnappers = \count( \array_filter( \array_map(
				function ( $auditor ) {
					try {
						$snapper = $auditor->getSnapper();
					}
					catch ( \Exception $e ) {
						$snapper = null;
					}
					return $snapper;
				},
				$this->getAuditors()
			) ) );
			if ( ( new Ops\Retrieve() )->count() !== $countAllSnappers ) {
				wp_schedule_single_event( Services::Request()->ts() + 60, $primerHook );
			}
		}

		add_action( $primerHook, function () {
			$this->runAsyncSnapshotDiscovery( true );
		} );
	}

	/**
	 * @return Auditors\Base[]
	 */
	public function getAuditors() :array {
		if ( empty( $this->auditors ) ) {
			$this->auditors = [];
			$auditClasses = \array_merge( Constants::AUDITORS,
				self::con()->caps->canThirdPartyActivityLog() ? Constants::THIRDPARTY_AUDITORS : [] );
			foreach ( $auditClasses as $auditorClass ) {
				/** @var Auditors\Base $auditor */
				$this->auditors[ $auditorClass::Slug() ] = new $auditorClass();
			}
		}
		return $this->auditors;
	}

	public function runSnapshotDiscovery( Auditors\Base $auditor ) :void {
		$auditor->setIsRunningSnapshotDiscovery( true );
		try {
			$diff = $this->getCurrentDiff( $auditor );
			if ( $diff->has_diffs ) {
				\array_map(
					function ( $method ) use ( $auditor, $diff ) {
						$auditor->{$method->name}( $diff );
					},
					\array_filter(
						( new \ReflectionClass( $auditor ) )->getMethods(),
						function ( $method ) {
							return \strpos(
								(string)$method->getDocComment(),
								Services::WpGeneral()->isCron() ? '* @snapshotDiffCron' : '* @snapshotDiff'
							);
						}
					)
				);
			}
		}
		catch ( \Exception $e ) {
		}
		finally {
			$auditor->setIsRunningSnapshotDiscovery( false );
		}
	}

	/**
	 * @throws \Exception
	 */
	private function getCurrentDiff( Auditors\Base $auditor ) :DiffVO {
		$diff = new DiffVO();
		$diff->slug = $auditor::Slug();

		$current = ( new Ops\Build() )->run( $diff->slug );

		try {
			$latest = $this->getSnapshot( $diff->slug );
			$diff = ( new Ops\Diff( Ops\Convert::RecordToSnap( $latest ), $current ) )->run();
			$store = $diff->has_diffs;
		}
		catch ( \Exception $e ) {
			$store = true;
		}

		if ( $store ) {
			$this->updateStoredSnapshot( $auditor, $current );
		}

		return $diff;
	}

	/**
	 * @throws \Exception
	 */
	public function getSnapshot( string $slug ) :Record {
		if ( empty( $this->getSnapshots()[ $slug ] ) ) {
			$this->latestSnapshots[ $slug ] = ( new Ops\Retrieve() )->latest( $slug );
		}
		if ( empty( $this->latestSnapshots[ $slug ] )
			 || !\is_a( $this->latestSnapshots[ $slug ], '\FernleafSystems\Wordpress\Plugin\Shield\DBs\Snapshots\Ops\Record' ) ) {
			throw new \Exception( 'Snapshot could not be loaded for '.$slug );
		}
		return $this->latestSnapshots[ $slug ];
	}

	public function getSnapshots() :array {
		return $this->latestSnapshots ?? $this->latestSnapshots = ( new Ops\Retrieve() )->all();
	}

	/**
	 * @throws \Exception
	 */
	public function updateStoredSnapshot( Auditors\Base $auditor, ?SnapshotVO $current = null ) {
		$con = self::con();

		if ( !$con->plugin_deleting && $con->db_con->dbhSnapshots()->isReady() ) {
			$slug = $auditor::Slug();
			if ( empty( $current ) ) {
				$current = ( new Ops\Build() )->run( $slug );
			}

			if ( !empty( $current ) ) {
				unset( $this->latestSnapshots[ $slug ] );
				( new Ops\Delete() )->delete( $slug );
				( new Ops\Store() )->store( $current );
			}
		}
	}

	/**
	 * @param Auditors\Base|mixed $auditor
	 * @param mixed               $item - type depends on the zone, e.g. \WP_User, \WP_Comment
	 */
	public function updateItemOnSnapshot( Auditors\Base $auditor, $item ) :void {
		try {
			$latest = $this->getSnapshot( $auditor::Slug() );
			$latest->data = $auditor->getSnapper()->updateItemOnSnapshot( $latest->data, $item );
			$this->updateStoredSnapshot( $auditor, Ops\Convert::RecordToSnap( $latest ) );
		}
		catch ( \Exception $e ) {
//			error_log( __METHOD__.' '.$e->getMessage() );
		}
	}

	/**
	 * @param Auditors\Base|mixed $auditor
	 * @param mixed               $item - type depends on the zone, e.g. \WP_User, \WP_Comment
	 */
	public function removeItemFromSnapshot( Auditors\Base $auditor, $item ) :void {
		try {
			$latest = $this->getSnapshot( $auditor::Slug() );
			$latest->data = $auditor->getSnapper()->deleteItemOnSnapshot( $latest->data, $item );
			$this->updateStoredSnapshot( $auditor, Ops\Convert::RecordToSnap( $latest ) );
		}
		catch ( \Exception $e ) {
//			error_log( __METHOD__.' '.$e->getMessage() );
		}
	}

	public function runDailyCron() {
		$this->runAsyncSnapshotDiscovery( true );
	}

	private function runAsyncSnapshotDiscovery( bool $isDataPrime = false ) {
		$q = $this->getSnapshotDiscoveryQueue();
		foreach ( $this->getAuditors() as $auditor ) {
			try {
				$addToQ = !$isDataPrime || empty( $this->getSnapshot( $auditor::Slug() ) );
			}
			catch ( \Exception $e ) {
				$addToQ = true;
			}
			if ( $addToQ ) {
				$q->push_to_queue( $auditor::Slug() );
			}
		}
		$q->save()->dispatch();
	}

	public function flags() :AuditFlags {
		return ( new AuditFlags() )->applyFromArray(
			apply_filters( 'shield/auditing_flags', [
				'users_audit_snapshot_admins_only' => Services::WpUsers()->count() > 10000,
			] )
		);
	}

	private function getSnapshotDiscoveryQueue() :Snapshots\Queues\SnapshotDiscovery {
		return $this->snapshotDiscoveryQueue ?? $this->snapshotDiscoveryQueue = new Snapshots\Queues\SnapshotDiscovery(
			'snapshot_discovery', self::con()->prefix() );
	}
}