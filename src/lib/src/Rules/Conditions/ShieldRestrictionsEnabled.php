<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Constants;

/**
 * The lowest level test of any Shield Restrictions.
 * i.e. Shield is set to run, and it's a public web request.
 */
class ShieldRestrictionsEnabled extends Base {

	use Traits\TypeShield;

	public function getDescription() :string {
		return __( "Are Shield Security's Restrictions Enabled?", 'wp-simple-firewall' );
	}

	protected function getPreviousResult() :?bool {
		return self::con()->this_req->request_subject_to_shield_restrictions;
	}

	protected function postExecConditionCheck( bool $result ) :void {
		self::con()->this_req->request_subject_to_shield_restrictions = $result;
	}

	protected function getSubConditions() :array {
		return [
			'logic'      => Constants::LOGIC_AND,
			'conditions' => [
				[
					'conditions' => RequestIsPublicWebOrigin::class,
				],
				[
					'conditions' => IsForceOff::class,
					'logic'      => Constants::LOGIC_INVERT,
				],
				[
					'conditions' => IsShieldPluginDisabled::class,
					'logic'      => Constants::LOGIC_INVERT,
				],
			]
		];
	}
}