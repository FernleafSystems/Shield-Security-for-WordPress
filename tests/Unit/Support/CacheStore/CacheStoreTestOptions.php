<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\CacheStore;

class CacheStoreTestOptions {

	public array $values = [];

	public function __construct( array $values = [] ) {
		$this->values = $values;
	}

	public function optGet( string $key ) {
		return $this->values[ $key ] ?? '';
	}

	public function optSet( string $key, $value ) :self {
		$this->values[ $key ] = $value;
		return $this;
	}

	public function optChanged( string $key ) :bool {
		unset( $key );
		return false;
	}

	public function hasChanges() :bool {
		return true;
	}

	public function store() :bool {
		return true;
	}
}
