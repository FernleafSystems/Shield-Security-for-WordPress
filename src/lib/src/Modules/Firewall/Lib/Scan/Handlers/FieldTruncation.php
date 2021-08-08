<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Firewall\Lib\Scan\Handlers;

class FieldTruncation extends BaseRequestParams {

	const SLUG = 'fieldtruncation';

	protected function getScanName() :string {
		return __( 'Field Truncation', 'wp-simple-firewall' );
	}
}