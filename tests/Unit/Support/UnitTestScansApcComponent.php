<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support;

class UnitTestScansApcComponent {

	public function __construct( private bool $enabled = false ) {
	}

	public function isEnabled() :bool {
		return $this->enabled;
	}
}
