<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\License;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\License;

class WpCli extends BaseShield\WpCli {

	protected function enumCmdHandlers() :array {
		return [
			License\WpCli\License::class
		];
	}
}