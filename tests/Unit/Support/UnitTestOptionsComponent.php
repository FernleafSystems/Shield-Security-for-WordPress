<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support;

class UnitTestOptionsComponent {

	public function __construct( private array $values = [] ) {
	}

	public function optGet( string $key ) :array {
		return $this->values[ $key ] ?? [];
	}
}
