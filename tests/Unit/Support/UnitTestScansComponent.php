<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support;

class UnitTestScansComponent {

	private ?UnitTestScansAfsComponent $afs;

	private ?UnitTestScansWpvComponent $wpv;

	private ?UnitTestScansApcComponent $apc;

	public function __construct(
		?UnitTestScansAfsComponent $afs = null,
		?UnitTestScansWpvComponent $wpv = null,
		?UnitTestScansApcComponent $apc = null
	) {
		$this->afs = $afs;
		$this->wpv = $wpv;
		$this->apc = $apc;
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
