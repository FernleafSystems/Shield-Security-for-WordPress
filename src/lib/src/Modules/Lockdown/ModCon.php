<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Lockdown;

class ModCon extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield\ModCon {

	public const SLUG = 'lockdown';

	/**
	 * @param string $namespace
	 */
	public function isPermittedAnonRestApiNamespace( $namespace ) :bool {
		/** @var Options $opts */
		$opts = $this->opts();
		return \in_array( $namespace, $opts->getRestApiAnonymousExclusions() );
	}
}