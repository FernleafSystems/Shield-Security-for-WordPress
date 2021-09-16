<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Firewall\Lib\Scan\Handlers;

class WpTerms extends BaseRequestParams {

	const SLUG = 'wpterms';

	protected function getScanName() :string {
		return __( 'WP Terms', 'wp-simple-firewall' );
	}
}