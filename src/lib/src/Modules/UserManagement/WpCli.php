<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement;

class WpCli extends BaseShield\WpCli {

	protected function enumCmdHandlers() :array {
		return [];
	}
}