<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\StoreAction;

use FernleafSystems\Wordpress\Services\Services;

class DeleteAll extends Base {

	public function run() {
		if ( $this->isTempDirAvailable() ) {
			Services::WpFs()->deleteDir( $this->getTempDir() );
		}
	}
}