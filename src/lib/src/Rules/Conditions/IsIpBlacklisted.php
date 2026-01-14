<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

class IsIpBlacklisted extends Base {

	use Traits\TypeShield;

	public const SLUG = 'is_ip_blacklisted';

	public function getDescription() :string {
		return sprintf( __( 'Is the request IP blacklisted by %s.', 'wp-simple-firewall' ), self::con()->labels->Name );
	}

	protected function execConditionCheck() :bool {
		return $this->req->is_ip_blacklisted;
	}
}