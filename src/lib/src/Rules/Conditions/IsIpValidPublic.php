<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Services\Services;

class IsIpValidPublic extends Base {

	use Traits\RequestIP;

	public const SLUG = 'is_ip_valid_public';

	public function getName() :string {
		return __( 'Does the request originate from a valid public IP address.', 'wp-simple-firewall' );
	}

	protected function execConditionCheck() :bool {
		$ip = $this->getRequestIP();
		return !empty( $ip ) && Services::IP()->isValidIp_PublicRemote( $ip );
	}
}