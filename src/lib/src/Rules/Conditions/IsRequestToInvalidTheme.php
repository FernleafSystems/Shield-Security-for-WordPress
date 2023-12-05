<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Constants;

class IsRequestToInvalidTheme extends Base {

	use Traits\RequestPath;

	public const SLUG = 'is_request_to_invalid_theme';

	protected function getSubConditions() :array {
		return [
			'logic'      => Constants::LOGIC_AND,
			'conditions' => [
				[
					'conditions' => IsRequestToThemeAsset::class,
				],
				[
					'conditions' => IsRequestToValidThemeAsset::class,
					'logic'      => Constants::LOGIC_INVERT
				],
			]
		];
	}
}