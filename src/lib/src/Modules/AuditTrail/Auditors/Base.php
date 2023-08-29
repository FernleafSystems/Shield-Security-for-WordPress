<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Auditors;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Report\Changes\BaseZoneReport;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Snapshots\Snapper\BaseSnap;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\ModConsumer;

class Base {

	use ModConsumer;
	use ExecOnce;

	protected $isRunningSnapshotDiscovery = false;

	protected function run() {
		$this->initAuditHooks();
	}

	public function canSnapRealtime() :bool {
		return false;
	}

	protected function initAuditHooks() :void {
	}

	public static function Slug() :string {
		return \strtolower( ( new \ReflectionClass( static::class ) )->getShortName() );
	}

	/**
	 * @return BaseZoneReport|mixed
	 * @throws \Exception
	 */
	public function getReporter() {
		throw new \Exception( 'No Reporter defined' );
	}

	/**
	 * @return BaseSnap|mixed
	 * @throws \Exception
	 */
	public function getSnapper() {
		throw new \Exception( 'No Snapper defined' );
	}

	protected function fireAuditEvent( string $event, array $params ) {
		if ( $this->isRunningSnapshotDiscovery ) {
			$params[ 'snapshot_discovery' ] = 1;
		}
		elseif ( $this->canSnapRealtime() ) {
			try {
				$this->mod()->getAuditCon()->updateStoredSnapshot( $this );
			}
			catch ( \Exception $e ) {
			}
		}
		self::con()->fireEvent( $event, [ 'audit_params' => $params ] );
	}

	public function setIsRunningSnapshotDiscovery( bool $isRunning ) :void {
		$this->isRunningSnapshotDiscovery = $isRunning;
	}

	protected function removeSnapshotItem( $item ) {
		if ( !$this->isRunningSnapshotDiscovery ) {
			$this->mod()->getAuditCon()->removeItemFromSnapshot( $this, $item );
		}
	}

	protected function updateSnapshotItem( $item ) {
		if ( !$this->isRunningSnapshotDiscovery ) {
			$this->mod()->getAuditCon()->updateItemOnSnapshot( $this, $item );
		}
	}
}