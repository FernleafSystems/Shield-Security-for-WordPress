<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Firewall;

class Options extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield\Options {

	public function getCustomWhitelist() :array {
		$w = $this->getOpt( 'page_params_whitelist', [] );
		return \is_array( $w ) ? $w : [];
	}
}