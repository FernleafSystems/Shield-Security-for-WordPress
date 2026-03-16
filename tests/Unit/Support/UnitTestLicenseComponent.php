<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support;

class UnitTestLicenseComponent {

	private bool $isPremium;

	public function __construct( bool $isPremium = false ) {
		$this->isPremium = $isPremium;
	}

	public function hasValidWorkingLicense() :bool {
		return $this->isPremium;
	}
}
