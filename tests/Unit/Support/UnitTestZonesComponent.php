<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support;

class UnitTestZonesComponent {

	public function __construct(
		private array $zones = [
			'secadmin' => true,
			'firewall' => true,
		],
		private array $zoneComponents = [
			'secadmin' => true,
		],
	) {
	}

	public function enumZones() :array {
		return $this->zones;
	}

	public function enumZoneComponents() :array {
		return $this->zoneComponents;
	}

	public function getZoneComponent( string $slug ) :object {
		return new UnitTestZoneActionComponent( $slug );
	}
}
