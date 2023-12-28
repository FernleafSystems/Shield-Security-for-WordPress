<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumLogic;

class IsIpBlockedByShield extends Base {

	use Traits\RequestIP;
	use Traits\TypeShield;

	public const SLUG = 'is_ip_blocked_by_shield';

	public function getDescription() :string {
		return __( "Is the request IP on any of Shield's block lists.", 'wp-simple-firewall' );
	}

	protected function execConditionCheck() :bool {
		/** We start with `true` here since we'd only be here if all other conditions have been met. */
		return apply_filters( 'shield/is_request_blocked', true );
	}

	protected function postExecConditionCheck( bool $result ) :void {
		self::con()->this_req->is_ip_blocked_shield = $result;
	}

	protected function getSubConditions() :array {
		return [
			'logic'      => EnumLogic::LOGIC_AND,
			'conditions' => [
				[
					'conditions' => RequestBypassesAllRestrictions::class,
					'logic'      => EnumLogic::LOGIC_INVERT
				],
				[
					'logic'      => EnumLogic::LOGIC_OR,
					'conditions' => [
						[
							'conditions' => IsIpBlockedManual::class,
						],
						[
							'logic'      => EnumLogic::LOGIC_AND,
							'conditions' => [
								[
									'conditions' => IsIpBlockedAuto::class,
								],
								[
									'conditions' => IsIpHighReputation::class,
									'logic'      => EnumLogic::LOGIC_INVERT
								],
							]
						]
					]
				],
				[
					'conditions' => $this->getDefaultConditionCheckCallable(),
				],
			]
		];
	}
}