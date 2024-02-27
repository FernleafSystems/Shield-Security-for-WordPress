<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Lockdown;

class ModCon extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\ModCon {

	public const SLUG = 'lockdown';

	/**
	 * @param string $namespace
	 * @deprecated 19.1
	 */
	public function isPermittedAnonRestApiNamespace( $namespace ) :bool {
		return false;
	}
}