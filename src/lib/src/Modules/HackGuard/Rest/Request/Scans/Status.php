<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Rest\Request\Scans;

class Status extends Base {

	protected function process() :array {
		return $this->getScansStatus();
	}
}