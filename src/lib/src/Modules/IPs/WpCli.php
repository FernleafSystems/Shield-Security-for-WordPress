<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;

class WpCli extends BaseShield\WpCli {

	protected function enumCmdHandlers() :array {
		return [
			IPs\WpCli\Add::class,
			IPs\WpCli\Remove::class,
			IPs\WpCli\Enumerate::class,
		];
	}
}