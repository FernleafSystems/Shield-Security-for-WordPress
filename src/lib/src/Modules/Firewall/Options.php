<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Firewall;

/**
 * @deprecated 19.1
 */
class Options extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Options {

	/**
	 * @deprecated 19.1
	 */
	public function getCustomWhitelist() :array {
		$w = $this->getOpt( 'page_params_whitelist', [] );
		return \is_array( $w ) ? $w : [];
	}
}