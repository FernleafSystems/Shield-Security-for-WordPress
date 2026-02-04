<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumLogic;

class IsRequestToInvalidPlugin extends Base {

	use Traits\TypeWordpress;

	public const SLUG = 'is_request_to_invalid_plugin';

	public function getDescription() :string {
		return __( 'Is the request to a path within a potentially installed WordPress plugin but is invalid.', 'wp-simple-firewall' );
	}

	protected function getSubConditions() :array {
		return [
			'logic'      => EnumLogic::LOGIC_AND,
			'conditions' => [
				[
					'conditions' => IsRequestToPluginAsset::class,
				],
				[
					'conditions' => IsRequestToValidPluginAsset::class,
					'logic'      => EnumLogic::LOGIC_INVERT
				],
			]
		];
	}
}