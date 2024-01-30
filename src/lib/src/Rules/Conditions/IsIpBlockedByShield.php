<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumLogic;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\WPHooksOrder;

class IsIpBlockedByShield extends Base {

	use Traits\TypeShield;

	public const SLUG = 'is_ip_blocked_by_shield';

	public static function MinimumHook() :int {
		return WPHooksOrder::INIT;
	}

	protected function execConditionCheck() :bool {
		/** We start with `true` here since we'd only be here if all other sub-conditions had been met. */
		return apply_filters( 'shield/is_request_blocked', true );
	}

	public function getDescription() :string {
		return __( 'Is the request IP auto or manually blocked by Shield.', 'wp-simple-firewall' );
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
							'conditions' => IsIpBlockedAuto::class,
						],
					]
				],
				[
					'conditions' => $this->getDefaultConditionCheckCallable(),
				],
			]
		];
	}

	protected function postExecConditionCheck( bool $result ) :void {
		$this->req->is_ip_blocked_shield = $result;
	}
}