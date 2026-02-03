<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots;

use FernleafSystems\Wordpress\Services\Services;

/**
 * All standard service providers are trusted.  Some are verified, some aren't.
 */
class TrustedServices {

	public function enum() :array {
		return \array_keys( apply_filters( 'shield/trusted_services',
			Services::ServiceProviders()->getProviders_Flat() ) );
	}
}