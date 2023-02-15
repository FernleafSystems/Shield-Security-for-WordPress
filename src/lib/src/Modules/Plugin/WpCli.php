<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin;

class WpCli extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield\WpCli {

	protected function enumCmdHandlers() :array {
		return [
			WpCli\ForceOff::class,
			WpCli\Reset::class,
			WpCli\Export::class,
			WpCli\Import::class,
		];
	}
}