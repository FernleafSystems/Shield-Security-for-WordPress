<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumLogic;

class IsRequestWhitelisted extends Base {

	use Traits\TypeShield;

	public const SLUG = 'is_request_whitelisted';

	public function getDescription() :string {
		return __( "Is the request whitelisted?", 'wp-simple-firewall' );
	}

	protected function getSubConditions() :array {
		return [
			'logic'      => EnumLogic::LOGIC_OR,
			'conditions' => [
				[
					'conditions' => ShieldRestrictionsEnabled::class,
					'logic'      => EnumLogic::LOGIC_INVERT,
				],
				[
					'conditions' => IsTrustedBot::class,
				],
				[
					'conditions' => RequestIsPathWhitelisted::class,
				],
				[
					'conditions' => IsIpWhitelisted::class,
				],
			]
		];
	}
}