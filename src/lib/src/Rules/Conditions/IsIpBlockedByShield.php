<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Constants;

class IsIpBlockedByShield extends Base {

	use Traits\RequestIP;

	public const SLUG = 'is_ip_blocked_by_shield';

	public function getName() :string {
		return __( "Is the request IP on any of Shield's block lists.", 'wp-simple-firewall' );
	}

	protected function postExecConditionCheck( bool $result ) :void {
		self::con()->this_req->is_ip_blocked_shield = $result;
	}

	protected function getSubConditions() :array {
		return [
			'logic' => Constants::LOGIC_AND,
			'conditions' => [
				[
					'conditions' => RequestBypassesAllRestrictions::class,
					'logic'      => Constants::LOGIC_INVERT
				],
				[
					'logic' => Constants::LOGIC_OR,
					'conditions' => [
						[
							'conditions' => IsIpBlockedManual::class,
						],
						[
							'logic' => Constants::LOGIC_AND,
							'conditions' => [
								[
									'conditions' => IsIpBlockedAuto::class,
								],
								[
									'conditions' => IsIpHighReputation::class,
									'logic'      => Constants::LOGIC_INVERT
								],
							]
						]
					]
				]
			]
		];
	}
}