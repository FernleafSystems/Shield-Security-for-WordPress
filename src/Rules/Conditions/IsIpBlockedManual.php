<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

class IsIpBlockedManual extends Base {

	use Traits\TypeShield;

	public const SLUG = 'is_ip_blocked_manual';

	public function getDescription() :string {
		return sprintf( __( "Is the request IP on %s's Manual block list.", 'wp-simple-firewall' ), self::con()->labels->Name );
	}

	protected function execConditionCheck() :bool {
		return $this->req->is_ip_blocked_shield_manual;
	}
}