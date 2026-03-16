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
				'href'    => '/admin/zone/'.$this->slug,
				'active'  => false,
				'classes' => [],
			],
		];
	}
}
