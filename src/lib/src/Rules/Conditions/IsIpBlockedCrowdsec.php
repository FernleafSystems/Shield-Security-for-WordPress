<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumLogic;

class IsIpBlockedCrowdsec extends Base {

	use Traits\TypeShield;

	public const SLUG = 'is_ip_blocked_crowdsec';

	public function getDescription() :string {
		return __( "Is the request IP on Shield's CrowdSec block list.", 'wp-simple-firewall' );
	}

	protected function execConditionCheck() :bool {
		return $this->req->is_ip_blocked_crowdsec;
	}

	protected function getSubConditions() :array {
		return [
			'logic'      => EnumLogic::LOGIC_AND,
			'conditions' => [
				[
					'conditions' => RequestBypassesAllRestrictions::class,
					'logic'      => EnumLogic::LOGIC_INVERT,
				],
				[
					'conditions' => IsIpBlockedByShield::class,
					'logic'      => EnumLogic::LOGIC_INVERT
				],
				[
					'conditions' => $this->getDefaultConditionCheckCallable(),
				],
			]
		];
	}
}