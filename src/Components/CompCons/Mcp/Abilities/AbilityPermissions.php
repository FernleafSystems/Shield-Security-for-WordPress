<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\Mcp\Abilities;

use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\Mcp\Support\QuerySurfaceAccessPolicy;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class AbilityPermissions {

	use PluginControllerConsumer;

	/**
	 * @param mixed $input
	 * @return true|\WP_Error
	 */
	public function canExecute( $input = null ) {
		unset( $input );
		return $this->getAccessPolicy()->verifyCurrentRequest();
	}

	protected function getAccessPolicy() :QuerySurfaceAccessPolicy {
		return new QuerySurfaceAccessPolicy();
	}
}
