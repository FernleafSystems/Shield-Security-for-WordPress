<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions\Traits\RequestPath;

class IsRequestToInvalidTheme extends Base {

	use RequestPath;

	public const SLUG = 'is_request_to_invalid_theme';

	protected function execConditionCheck() :bool {
		$asset = new IsRequestToThemeAsset();
		$asset->request_path = $this->getRequestPath();
		$validAsset = new IsRequestToValidThemeAsset();
		$validAsset->request_path = $this->getRequestPath();
		return $asset->run() && !$validAsset->run();
	}

	public static function RequiredConditions() :array {
		return [
			IsRequestToThemeAsset::class,
			IsRequestToValidThemeAsset::class
		];
	}
}