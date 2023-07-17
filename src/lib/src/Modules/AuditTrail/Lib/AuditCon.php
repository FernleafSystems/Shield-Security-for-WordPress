<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Crons\PluginCronsConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Auditors;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\DB\Snapshots\Ops\Record;
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

	protected function run() {
		if ( Services::WpGeneral()->isCron() ) {
			$this->setupCronHooks();
		}

		$this->mod()->getAuditLogger()->setIfCommit( true );
		\array_map( function ( $auditor ) {
			$auditor->execute();
		}, $this->getAuditors() );

		// Realtime Snapshotting
		add_action( 'wp_loaded', function () {
			\array_map(
				function ( $auditor ) {
					if ( $auditor->canSnapRealtime() ) {
						$this->runSnapshotDiscovery( $auditor );
					}
				},
				$this->getAuditors()
			);

			$allSnappers = \array_filter( \array_map(
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
			) );

			// Typically on initial installation we want to prime all the snapshots.
			if ( \count( $this->getSnapshots() ) !== \count( $allSnappers ) ) {
				$hook = $this->con()->prefix( 'auditcon_prime_snapshots' );
				if ( !wp_next_scheduled( $hook ) ) {
					wp_schedule_single_event( Services::Request()->ts() + 60, $hook );
				}
				add_action( $hook, function () {
					$this->runAsyncSnapshotDiscovery();
				} );
			}
		} );

		$this->getSnapshotDiscoveryQueue();
	}

	/**
	 * @return Auditors\Base[]
	 */
	public function getAuditors() :array {
		if ( empty( $this->auditors ) ) {
			$this->auditors = [];
			$auditClasses = \array_merge( Constants::AUDITORS,
				$this->con()->caps->canThirdPartyActivityLog() ? Constants::THIRDPARTY_AUDITORS : [] );
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
		if ( empty( $this->latestSnapshots[ $slug ] ) ) {
			throw new \Exception( 'Snapshot could not be loaded for '.$slug );
		}
		return $this->latestSnapshots[ $slug ];
	}

	private function getSnapshots() :array {
		return $this->latestSnapshots ?? $this->latestSnapshots = ( new Ops\Retrieve() )->all();
	}

	/**
	 * @throws \Exception
	 */
	public function updateStoredSnapshot( Auditors\Base $auditor, ?SnapshotVO $current = null ) {
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
			error_log( __METHOD__.' '.$e->getMessage() );
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
			error_log( __METHOD__.' '.$e->getMessage() );
		}
	}

	public function runDailyCron() {
		$this->runAsyncSnapshotDiscovery();
	}

	private function runAsyncSnapshotDiscovery() {
		$q = $this->getSnapshotDiscoveryQueue();
		foreach ( $this->getAuditors() as $auditor ) {
			$q->push_to_queue( $auditor::Slug() );
		}
		$q->save()->dispatch();
	}

	private function getSnapshotDiscoveryQueue() :Snapshots\Queues\SnapshotDiscovery {
		return $this->snapshotDiscoveryQueue ?? $this->snapshotDiscoveryQueue = new Snapshots\Queues\SnapshotDiscovery(
			'snapshot_discovery', $this->con()->prefix() );
	}
}