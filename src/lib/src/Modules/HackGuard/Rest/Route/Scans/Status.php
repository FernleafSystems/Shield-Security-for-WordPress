<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Rest\Route\Scans;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Rest\Request\Scans;

class Status extends ScansBase {

	protected function getRequestProcessorClass() :string {
		return Scans\Status::class;
	}
}