<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\License;

use FernleafSystems\Wordpress\Services\Services;

class Options extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Options {

	public function getMasterSiteLicenseURL() :string {
		return apply_filters( 'shield/master_site_license_url', Services::WpGeneral()->getHomeUrl( '', true ) );
	}
}