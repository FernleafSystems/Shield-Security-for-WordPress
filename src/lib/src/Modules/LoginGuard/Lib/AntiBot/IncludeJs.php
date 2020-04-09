<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\AntiBot;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class IncludeJs
 * @package    FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\AntiBot
 * @deprecated 9.0
 */
class IncludeJs {

	use ModConsumer;

	private static $bAntiBotJsEnqueued = false;

	public function run() {
	}

	public function onWpEnqueueJs() {
	}
}
