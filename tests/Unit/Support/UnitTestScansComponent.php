<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support;

class UnitTestScansComponent {

	public function __construct(
		private ?UnitTestScansAfsComponent $afs = null,
		private ?UnitTestScansWpvComponent $wpv = null,
		private ?UnitTestScansApcComponent $apc = null,
	) {
	}

	public function AFS() :UnitTestScansAfsComponent {
		return $this->afs ??= new UnitTestScansAfsComponent();
	}

	public function WPV() :UnitTestScansWpvComponent {
		return $this->wpv ??= new UnitTestScansWpvComponent();
	}

	public function APC() :UnitTestScansApcComponent {
		return $this->apc ??= new UnitTestScansApcComponent();
	}
}
