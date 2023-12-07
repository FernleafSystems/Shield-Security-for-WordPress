<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\CustomBuilder;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Utility\RulesEnum;

class GetAvailable {

	public static function Conditions() :array {
		return \array_filter(
			\array_map( function ( string $class ) {
				$condition = new $class();
				return [
					'name'       => $condition->getName(),
					'slug'       => $condition->getSlug(),
					'type'       => $condition->getType(),
					'params_def' => $condition->getParamsDef(),
				];
			}, RulesEnum::Conditions() ),
			function ( array $condition ) {
				$available = true;
				// we don't (yet) allow for condition with array parameters
				foreach ( $condition[ 'params_def' ] as $paramDef ) {
					if ( $paramDef[ 'type' ] === 'array' ) {
						$available = false;
						break;
					}
				}
				return $available;
			}
		);
	}

	public static function Responses() :array {
		return \array_filter(
			\array_map( function ( string $class ) {
				$response = new $class();
				return [
					'name'       => $response->getName(),
					'slug'       => $response->getSlug(),
					'params_def' => $response->getParamsDef(),
				];
			}, RulesEnum::Responses() ),
			function ( array $response ) {
				return true;
			}
		);
	}
}