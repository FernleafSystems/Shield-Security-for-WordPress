<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

class IsIpBlacklisted extends Base {

	use Traits\TypeShield;

	public const SLUG = 'is_ip_blacklisted';

	public function getDescription() :string {
		return __( 'Is the request IP blacklisted by Shield.', 'wp-simple-firewall' );
	}

	protected function execConditionCheck() :bool {
		return $this->req->is_ip_blacklisted;
	}
}