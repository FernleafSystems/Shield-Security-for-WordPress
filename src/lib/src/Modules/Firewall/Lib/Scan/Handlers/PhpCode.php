<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Firewall\Lib\Scan\Handlers;

class PhpCode extends BaseRequestParams {

	const SLUG = 'phpcode';

	protected function getScanName() :string {
		return __( 'PHP Code', 'wp-simple-firewall' );
	}
}