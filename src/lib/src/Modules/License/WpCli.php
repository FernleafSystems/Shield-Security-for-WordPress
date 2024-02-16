<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\License;

class WpCli extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\WpCli {

	protected function isFeatureAvailable() :bool {
		return self::con()->caps->canWpcliLevel1();
	}

	protected function enumCmdHandlers() :array {
		return [
			WpCli\License::class
		];
	}
}