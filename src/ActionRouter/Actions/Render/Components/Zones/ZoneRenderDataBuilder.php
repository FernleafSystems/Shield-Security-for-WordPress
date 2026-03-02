<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Zones;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class ZoneRenderDataBuilder {

	use PluginControllerConsumer;

	private ?array $zonesIndexed = null;

	public function getZonesIndexed() :array {
		if ( $this->zonesIndexed === null ) {
			$this->zonesIndexed = [];
			$con = self::con();
			foreach ( $con->comps->zones->getZones() as $zone ) {
				$slug = $zone::Slug();
				$this->zonesIndexed[ $slug ] = [
					'slug'       => $slug,
					'label'      => $zone->title(),
					'icon_class' => $con->svgs->iconClass( $zone->icon() ),
					'href'       => $con->plugin_urls->zone( $slug ),
				];
			}
		}
		return $this->zonesIndexed;
	}

	public function getZoneLinks() :array {
		return \array_values( $this->getZonesIndexed() );
	}

	public function getZoneSlugs() :array {
		return \array_keys( $this->getZonesIndexed() );
	}
}

