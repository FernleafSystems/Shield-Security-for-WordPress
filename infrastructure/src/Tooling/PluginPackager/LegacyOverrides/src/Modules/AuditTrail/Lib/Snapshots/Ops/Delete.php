<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Snapshots\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class Delete {

	use PluginControllerConsumer;

	public function delete( string $slug ) :bool {
		return false;
	}
}
