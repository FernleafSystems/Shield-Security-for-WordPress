<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\StoreAction;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\HashesStorageDir;
use FernleafSystems\Wordpress\Services\Services;

class DeleteAll extends BaseExec {

	protected function run() {
		Services::WpFs()->deleteDir( ( new HashesStorageDir() )->getTempDir() );
	}
}