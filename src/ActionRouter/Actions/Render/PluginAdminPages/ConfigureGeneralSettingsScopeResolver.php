<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\GetOptionsForZoneComponents;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Zone;

class ConfigureGeneralSettingsScopeResolver {

	/**
	 * @param ?Zone\Base $zone
	 * @param list<string> $coveredOptionKeys
	 * @return array{}|array{zone_component_slugs:list<string>,option_keys:list<string>}
	 */
	public function resolve( ?Zone\Base $zone, array $coveredOptionKeys ) :array {
		if ( $zone === null ) {
			return [];
		}

		$moduleSlugs = \array_values( \array_filter( \array_map(
			static fn( string $slug ) :string => \trim( $slug ),
			$zone->getConfigZoneComponentSlugs()
		) ) );
		if ( empty( $moduleSlugs ) ) {
			return [];
		}

		$coveredOptionKeys = \array_values( \array_unique( \array_filter( \array_map(
			static fn( $optionKey ) :string => \trim( (string)$optionKey ),
			$coveredOptionKeys
		) ) ) );

		$moduleOptionKeys = ( new GetOptionsForZoneComponents() )->run( $moduleSlugs );
		$leftoverOptionKeys = \array_values( \array_unique( \array_filter(
			$moduleOptionKeys,
			static fn( string $optionKey ) :bool => !\in_array( $optionKey, $coveredOptionKeys, true )
		) ) );
		if ( empty( $leftoverOptionKeys ) ) {
			return [];
		}

		return [
			'zone_component_slugs' => $moduleSlugs,
			'option_keys'          => $leftoverOptionKeys,
		];
	}
}
