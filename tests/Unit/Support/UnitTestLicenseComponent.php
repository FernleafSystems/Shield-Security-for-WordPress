<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support;

class UnitTestLicenseComponent {

	public function __construct( private bool $isPremium = false ) {
	}

	public function hasValidWorkingLicense() :bool {
		return $this->isPremium;
	}
}
