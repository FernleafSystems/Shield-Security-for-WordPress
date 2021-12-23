<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin;

class WpCli extends BaseShield\WpCli {

	protected function enumCmdHandlers() :array {
		return [
			SecurityAdmin\WpCli\Pin::class,
			SecurityAdmin\WpCli\AdminAdd::class,
			SecurityAdmin\WpCli\AdminRemove::class
		];
	}
}