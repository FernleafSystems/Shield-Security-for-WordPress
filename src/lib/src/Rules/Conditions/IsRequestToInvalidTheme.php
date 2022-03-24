<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions\Traits\RequestPath;

class IsRequestToInvalidTheme extends Base {

	use RequestPath;

	const SLUG = 'is_request_to_invalid_theme';

	protected function execConditionCheck() :bool {
		$asset = ( new IsRequestToThemeAsset() )->setCon( $this->getCon() );
		$asset->request_path = $this->getRequestPath();
		$validAsset = ( new IsRequestToValidThemeAsset() )->setCon( $this->getCon() );
		$validAsset->request_path = $this->getRequestPath();
		return $asset->run() && !$validAsset->run();
	}

	public static function RequiredConditions() :array {
		return [
			IsRequestToThemeAsset::SLUG,
			IsRequestToValidThemeAsset::SLUG
		];
	}
}