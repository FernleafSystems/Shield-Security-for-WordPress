<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumLogic;

class IsRequestToInvalidTheme extends Base {

	use Traits\TypeWordpress;

	public const SLUG = 'is_request_to_invalid_theme';

	public function getDescription() :string {
		return __( 'Is the request to a path within a potentially installed WordPress theme but is invalid.', 'wp-simple-firewall' );
	}

	protected function getSubConditions() :array {
		return [
			'logic'      => EnumLogic::LOGIC_AND,
			'conditions' => [
				[
					'conditions' => IsRequestToThemeAsset::class,
				],
				[
					'conditions' => IsRequestToValidThemeAsset::class,
					'logic'      => EnumLogic::LOGIC_INVERT
				],
			]
		];
	}
}