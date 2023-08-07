<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Snapshots\Snapper;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\ModConsumer;

abstract class BaseSnap {

	use ModConsumer;

	abstract public function snap() :array;

	public function updateItemOnSnapshot( array $snapshotData, $item ) :array {
		return $snapshotData;
	}

	public function deleteItemOnSnapshot( array $snapshotData, $item ) :array {
		return $snapshotData;
	}
}