<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumLogic;

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
		return $this->req->request_subject_to_shield_restrictions;
	}

	protected function postExecConditionCheck( bool $result ) :void {
		$this->req->request_subject_to_shield_restrictions = $result;
	}

	protected function getSubConditions() :array {
		return [
			'logic'      => EnumLogic::LOGIC_AND,
			'conditions' => [
				[
					'conditions' => ShieldIsForceOff::class,
					'logic'      => EnumLogic::LOGIC_INVERT,
				],
				[
					'conditions' => RequestIsPublicWebOrigin::class,
				],
				[
					'conditions' => ShieldConfigPluginGlobalDisabled::class,
					'logic'      => EnumLogic::LOGIC_INVERT,
				],
			]
		];
	}
}