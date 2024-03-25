<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail;

class WpCli extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\WpCli {

	protected function enumCmdHandlers() :array {
		return [
			WpCli\Display::class
		];
	}
}