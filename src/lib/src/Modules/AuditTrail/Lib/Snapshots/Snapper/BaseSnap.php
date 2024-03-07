<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Snapshots\Snapper;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

abstract class BaseSnap {

	use PluginControllerConsumer;

	abstract public function snap() :array;

	public function updateItemOnSnapshot( array $snapshotData, $item ) :array {
		return $snapshotData;
	}

	public function deleteItemOnSnapshot( array $snapshotData, $item ) :array {
		return $snapshotData;
	}
}