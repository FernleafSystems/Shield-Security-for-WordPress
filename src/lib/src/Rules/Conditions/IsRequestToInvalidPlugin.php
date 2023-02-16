<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions\Traits\RequestPath;

class IsRequestToInvalidPlugin extends Base {

	use RequestPath;

	public const SLUG = 'is_request_to_invalid_plugin';

	protected function execConditionCheck() :bool {
		$asset = new IsRequestToPluginAsset();
		$asset->request_path = $this->getRequestPath();
		$validAsset = new IsRequestToValidPluginAsset();
		$validAsset->request_path = $this->getRequestPath();
		return $asset->run() && !$validAsset->run();
	}

	public static function RequiredConditions() :array {
		return [
			IsRequestToPluginAsset::class,
			IsRequestToValidPluginAsset::class
		];
	}
}