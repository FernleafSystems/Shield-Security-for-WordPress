<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin;

class WpCli extends BaseShield\WpCli {

	protected function enumCmdHandlers() :array {
		return [
			Plugin\WpCli\ForceOff::class,
			Plugin\WpCli\Reset::class,
			Plugin\WpCli\Export::class,
			Plugin\WpCli\Import::class,
		];
	}
}