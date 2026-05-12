<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support;

class UnitTestScansApcComponent {

	private bool $enabled;

	public function __construct( bool $enabled = false ) {
		$this->enabled = $enabled;
	}

	public function isEnabled() :bool {
		return $this->enabled;
	}
}
