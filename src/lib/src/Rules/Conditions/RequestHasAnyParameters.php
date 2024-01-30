<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumLogic;

class RequestHasAnyParameters extends Base {

	use Traits\TypeRequest;

	public const SLUG = 'request_has_any_parameters';

	public function getDescription() :string {
		return __( "Does the request have any POST or QUERY parameters.", 'wp-simple-firewall' );
	}

	protected function getSubConditions() :array {
		return [
			'logic'      => EnumLogic::LOGIC_OR,
			'conditions' => [
				[
					'conditions' => RequestHasQueryParameters::class,
				],
				[
					'conditions' => RequestHasPostParameters::class,
				],
			]
		];
	}
}