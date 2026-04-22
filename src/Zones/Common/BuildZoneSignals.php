<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Common;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\SecurityZonesCon;

class BuildZoneSignals {

	/**
	 * @return list<array<string,mixed>>
	 */
	public function build() :array {
		$signals = [];
		$zonesCon = new SecurityZonesCon();
		foreach ( $zonesCon->getZones() as $zone ) {
			foreach ( $zonesCon->getComponentsForZone( $zone ) as $component ) {
				foreach ( $component->postureSignals() as $signal ) {
					$signal[ 'zone' ] = $zone::Slug();
					$signals[] = $signal;
				}
			}
		}

		return $signals;
	}
}
