<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Common;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class GetOptionsForZoneComponents {

	use PluginControllerConsumer;

	public function run( array $zoneComponentSlugs, array $optionKeys = [] ) :array {
		$options = [];
		foreach ( $zoneComponentSlugs as $zoneComponentSlug ) {
			$options = \array_merge(
				$options,
				self::con()->comps->zones->getZoneComponent( $zoneComponentSlug )->getOptions()
			);
		}

		$options = \array_values( \array_unique( $options ) );
		if ( !empty( $optionKeys ) ) {
			$options = \array_values( \array_filter(
				$options,
				static fn( string $optionKey ) :bool => \in_array( $optionKey, $optionKeys, true )
			) );
		}

		return $options;
	}
}
