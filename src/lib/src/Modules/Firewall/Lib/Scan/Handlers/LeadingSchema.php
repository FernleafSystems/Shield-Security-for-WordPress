<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Firewall\Lib\Scan\Handlers;

class LeadingSchema extends BaseRequestParams {

	const SLUG = 'schema';

	protected function getScanName() :string {
		return __( 'Leading Schema', 'wp-simple-firewall' );
	}
}