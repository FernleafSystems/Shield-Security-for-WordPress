<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support;

class UnitTestOptionsComponent {

	private array $values;

	public function __construct( array $values = [] ) {
		$this->values = $values;
	}

	public function optGet( string $key ) :array {
		return $this->values[ $key ] ?? [];
	}
}
