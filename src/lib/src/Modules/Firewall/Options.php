<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Firewall;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;

class Options extends BaseShield\Options {

	public function getCustomWhitelist() :array {
		$w = $this->getOpt( 'page_params_whitelist', [] );
		return is_array( $w ) ? $w : [];
	}
}