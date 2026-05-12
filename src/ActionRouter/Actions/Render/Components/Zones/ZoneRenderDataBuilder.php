<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Zones;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class ZoneRenderDataBuilder {

	use PluginControllerConsumer;

	/**
	 * @var array<string,array{
	 *   slug:string,
	 *   label:string,
	 *   icon_class:string,
	 *   href:string
	 * }>|null
	 */
	private ?array $zonesIndexed = null;

	/**
	 * @return array<string,array{
	 *   slug:string,
	 *   label:string,
	 *   icon_class:string,
	 *   href:string
	 * }>
	 */
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

	/**
	 * @return list<array{
	 *   slug:string,
	 *   label:string,
	 *   icon_class:string,
	 *   href:string
	 * }>
	 */
	public function getZoneLinks() :array {
		return \array_values( $this->getZonesIndexed() );
	}

	/**
	 * @return list<string>
	 */
	public function getZoneSlugs() :array {
		return \array_keys( $this->getZonesIndexed() );
	}
}
