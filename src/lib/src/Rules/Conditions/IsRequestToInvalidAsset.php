<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

class IsRequestToInvalidAsset extends Base {

	const SLUG = 'is_request_to_invalid_asset';

	protected function execConditionCheck() :bool {
		return ( new IsRequestToInvalidPlugin() )->setCon( $this->getCon() )->run()
			   || ( new IsRequestToInvalidTheme() )->setCon( $this->getCon() )->run();
	}

	public static function RequiredConditions() :array {
		return [
			IsRequestToInvalidPlugin::SLUG,
			IsRequestToInvalidTheme::SLUG
		];
	}
}