<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Rest\Worpdrive\Fixtures;

class WorpdriveTestConfig {

	private string $version;

	public function __construct( string $version ) {
		$this->version = $version;
	}

	public function version() :string {
		return $this->version;
	}
}
