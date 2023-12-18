<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Constants;

class IsRequestWhitelisted extends Base {

	use Traits\TypeShield;

	public const SLUG = 'is_request_whitelisted';

	public function getDescription() :string {
		return __( "Is the request whitelisted?", 'wp-simple-firewall' );
	}

	protected function getSubConditions() :array {
		return [
			'logic'      => Constants::LOGIC_OR,
			'conditions' => [
				[
					'conditions' => ShieldRestrictionsEnabled::class,
					'logic'      => Constants::LOGIC_INVERT,
				],
				[
					'conditions' => RequestIsTrustedBot::class,
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