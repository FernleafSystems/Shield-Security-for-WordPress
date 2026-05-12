<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support;

class UnitTestScansWpvComponent {

	private bool $enabled;

	private bool $restricted;

	public function __construct( bool $enabled = false, bool $restricted = false ) {
		$this->enabled = $enabled;
		$this->restricted = $restricted;
	}

	public function isEnabled() :bool {
		return $this->enabled;
	}

	public function isRestricted() :bool {
		return $this->restricted;
	}
}
