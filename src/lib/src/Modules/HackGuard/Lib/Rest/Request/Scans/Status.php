<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Rest\Request\Scans;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Init\ScansStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Strings;

class Status extends Base {

	protected function process() :array {
		return $this->getScansStatus();
	}
}