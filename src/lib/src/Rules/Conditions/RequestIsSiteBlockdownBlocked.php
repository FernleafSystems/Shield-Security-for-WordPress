<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumLogic;

class RequestIsSiteBlockdownBlocked extends Base {

	use Traits\TypeShield;

	public const SLUG = 'request_is_site_blockdown_blocked';

	public function getDescription() :string {
		return __( "Is the request blocked by Shield's Site Lockdown feature.", 'wp-simple-firewall' );
	}

	protected function getSubConditions() :array {
		return [
			'logic'      => EnumLogic::LOGIC_AND,
			'conditions' => [
				[
					'conditions' => ShieldRestrictionsEnabled::class,
				],
				[
					'conditions' => IsSiteLockdownActive::class,
				],
				[
					'conditions' => IsIpWhitelisted::class,
					'logic'      => EnumLogic::LOGIC_INVERT,
				],
			]
		];
	}
}