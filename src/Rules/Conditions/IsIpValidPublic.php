<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

class IsIpValidPublic extends Base {

	use Traits\TypeRequest;

	public const SLUG = 'is_ip_valid_public';

	public function getDescription() :string {
		return __( 'Does the request originate from a valid public IP address.', 'wp-simple-firewall' );
	}

	protected function execConditionCheck() :bool {
		return $this->req->ip_is_public;
	}

	public function getName() :string {
		return __( 'Is Valid Public IP Address', 'wp-simple-firewall' );
	}
}