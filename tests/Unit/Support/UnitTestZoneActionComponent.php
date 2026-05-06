<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support;

class UnitTestZoneActionComponent {

	private string $slug;

	public function __construct( string $slug ) {
		$this->slug = $slug;
	}

	public function getActions() :array {
		return [
			'config' => [
				'href'      => '',
				'active'    => false,
				'classes'   => [ 'zone_component_action' ],
				'data'      => [
					'zone_component_action' => 'offcanvas_zone_component_config',
					'zone_component_slug'   => $this->slug,
				],
				'is_action' => true,
			],
		];
	}
}
