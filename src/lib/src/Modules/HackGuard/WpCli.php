<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;

class WpCli extends BaseShield\WpCli {

	protected function enumCmdHandlers() :array {
		return [
			WpCli\ScanRun::class
		];
	}
}