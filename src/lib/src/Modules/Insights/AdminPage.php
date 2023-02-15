<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;

/**
 * @deprecated 17.0
 */
class AdminPage extends ExecOnceModConsumer {

	protected $screenID;

	protected function canRun() :bool {
		return false;
	}

	protected function run() {
	}
}