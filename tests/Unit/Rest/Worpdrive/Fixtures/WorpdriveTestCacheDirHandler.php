<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Rest\Worpdrive\Fixtures;

class WorpdriveTestCacheDirHandler {

	private string $dir;

	public function __construct( string $dir ) {
		$this->dir = $dir;
	}

	public function dir() :string {
		return $this->dir;
	}
}
