<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Constants;

class RequestIsSiteBlockdownBlocked extends Base {

	public const SLUG = 'request_is_site_blockdown_blocked';

	public function getDescription() :string {
		return __( "Is the request blocked by Shield's Site Lockdown feature.", 'wp-simple-firewall' );
	}

	protected function getSubConditions() :array {
		return [
			'logic' => Constants::LOGIC_AND,
			'conditions' => [
				[
					'conditions' => IsSiteLockdownActive::class,
				],
				[
					'conditions' => IsForceOff::class,
					'logic'      => Constants::LOGIC_INVERT,
				],
				[
					'conditions' => RequestIsPublicWebOrigin::class,
				],
				[
					'conditions' => IsIpWhitelisted::class,
					'logic'      => Constants::LOGIC_INVERT,
				],
			]
		];
	}
}