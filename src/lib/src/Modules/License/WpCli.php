<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\License;

class WpCli extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield\WpCli {

	protected function enumCmdHandlers() :array {
		return [
			WpCli\License::class
		];
	}
}