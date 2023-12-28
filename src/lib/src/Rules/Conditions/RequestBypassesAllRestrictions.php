<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumLogic;

class RequestBypassesAllRestrictions extends Base {

	use Traits\TypeShield;

	public const SLUG = 'request_bypasses_all_restrictions';

	public function getDescription() :string {
		return __( "Does the request bypass any and all Shield restrictions.", 'wp-simple-firewall' );
	}

	protected function getPreviousResult() :?bool {
		return $this->req->request_bypasses_all_restrictions;
	}

	protected function postExecConditionCheck( bool $result ) :void {
		$this->req->request_bypasses_all_restrictions = $result;
	}

	protected function getSubConditions() :array {
		return [
			'logic'      => EnumLogic::LOGIC_AND,
			'conditions' => [
				[
					'conditions' => RequestIsSiteBlockdownBlocked::class,
					'logic'      => EnumLogic::LOGIC_INVERT,
				],
				[
					'conditions' => IsRequestWhitelisted::class,
				],
			]
		];
	}
}