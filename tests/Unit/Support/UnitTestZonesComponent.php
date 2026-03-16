<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support;

class UnitTestZonesComponent {

	private array $zones;

	private array $zoneComponents;

	public function __construct(
		array $zones = [
			'secadmin' => true,
			'firewall' => true,
		],
		array $zoneComponents = [
			'secadmin' => true,
		]
	) {
		$this->zones = $zones;
		$this->zoneComponents = $zoneComponents;
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
