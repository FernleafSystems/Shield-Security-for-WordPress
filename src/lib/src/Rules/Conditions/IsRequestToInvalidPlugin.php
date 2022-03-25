<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions\Traits\RequestPath;

class IsRequestToInvalidPlugin extends Base {

	use RequestPath;

	const SLUG = 'is_request_to_invalid_plugin';

	protected function execConditionCheck() :bool {
		$asset = ( new IsRequestToPluginAsset() )->setCon( $this->getCon() );
		$asset->request_path = $this->getRequestPath();
		$validAsset = ( new IsRequestToValidPluginAsset() )->setCon( $this->getCon() );
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