<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Common;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class GetOptionsForZoneComponents {

	use PluginControllerConsumer;

	public function run( array $zoneComponentSlugs ) :array {
		$options = [];
		foreach ( $zoneComponentSlugs as $zoneComponentSlug ) {
			$options = \array_merge(
				$options,
				self::con()->comps->zones->getZoneComponent( $zoneComponentSlug )->getOptions()
			);
		}
		return $options;
	}
}