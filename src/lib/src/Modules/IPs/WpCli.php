<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;

class WpCli extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\WpCli {

	protected function enumCmdHandlers() :array {
		return [
			WpCli\Add::class,
			WpCli\Remove::class,
			WpCli\Enumerate::class,
		];
	}
}