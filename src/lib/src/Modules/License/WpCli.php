<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\License;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\License;

class WpCli extends Base\WpCli {

	/**
	 * @inheritDoc
	 */
	protected function getCmdHandlers() :array {
		return [
			new License\WpCli\License()
		];
	}
}