<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Build\BuildRuleCoreShieldBase,
	Conditions
};

class SetupRequestStatus extends BuildRuleCoreShieldBase {

	const SLUG = 'shield/setup_request_status';

	protected function getName() :string {
		return 'Setup Request Status';
	}

	protected function getDescription() :string {
		return 'Setup Request Status.';
	}

	protected function getConditions() :array {
		return [
			'logic' => static::LOGIC_OR,
			'group' => [
				[
					'rule' => Conditions\WpIsAjax::SLUG,
				],
				[
					'rule' => Conditions\WpIsAdmin::SLUG,
				],
				[
					'rule' => Conditions\WpIsWpcli::SLUG,
				],
			]
		];
	}
}