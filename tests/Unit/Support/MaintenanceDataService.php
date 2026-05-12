<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support;

use FernleafSystems\Wordpress\Services\Utilities\Data;

class MaintenanceDataService extends Data {

	public function getPhpVersionIsAtLeast( string $minimumVersion ) :bool {
		return true;
	}

	public function getPhpVersionCleaned( bool $excludeMinor = false ) :string {
		return '8.2';
	}

	public function isWindows() :bool {
		return false;
	}
}
