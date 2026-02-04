<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

class RequestIsHttps extends Base {

	use Traits\TypeRequest;

	protected function execConditionCheck() :bool {
		return is_ssl();
	}

	public function getDescription() :string {
		return __( 'Is the request HTTPS.', 'wp-simple-firewall' );
	}
}