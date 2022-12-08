<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Conditions
};

class RequestStatusIsAjax extends RequestStatusBase {

	public const SLUG = 'shield/request_status_is_ajax';

	protected function getName() :string {
		return 'Is AJAX?';
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