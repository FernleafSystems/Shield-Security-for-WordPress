<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Build\BuildRuleCoreShieldBase,
	Conditions
};

class RequestStatusIsAjax extends BuildRuleCoreShieldBase {

	const SLUG = 'shield/request_status_is_ajax';

	protected function getName() :string {
		return 'Is AJAX';
	}

	protected function getDescription() :string {
		return 'Request Status - Is AJAX.';
	}

	protected function getConditions() :array {
		return [
			'logic' => static::LOGIC_OR,
			'group' => [
				[
					'condition' => Conditions\WpIsAjax::SLUG,
				],
			]
		];
	}
}