<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;

class WpCli extends BaseShield\WpCli {

	protected function enumCmdHandlers() :array {
		return [
			AuditTrail\WpCli\Display::class
		];
	}
}