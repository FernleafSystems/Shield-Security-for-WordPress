<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support;

use FernleafSystems\Wordpress\Services\Core\Fs;

class MaintenanceFsService extends Fs {

	public function isAccessibleFile( string $path ) :bool {
		return false;
	}
}
