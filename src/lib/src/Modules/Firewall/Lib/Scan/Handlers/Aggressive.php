<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Firewall\Lib\Scan\Handlers;

class Aggressive extends BaseRequestParams {

	const SLUG = 'aggressive';

	protected function getScanName() :string {
		return __( 'Aggressive', 'wp-simple-firewall' );
	}
}