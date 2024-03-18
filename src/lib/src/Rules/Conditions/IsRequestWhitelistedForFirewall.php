<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum;

class IsRequestWhitelistedForFirewall extends Base {

	use Traits\TypeShield;

	protected function getSubConditions() :array {
		return [
			'logic'      => Enum\EnumLogic::LOGIC_OR,
			'conditions' => \array_map(
				function ( string $path ) {
					return [
						'conditions' => MatchRequestPath::class,
						'params'     => [
							'match_type' => Enum\EnumMatchTypes::MATCH_TYPE_CONTAINS_I,
							'match_path' => $path,
						],
					];
				},
				self::con()->cfg->configuration->def( 'whitelisted_paths' )
			),
		];
	}
}