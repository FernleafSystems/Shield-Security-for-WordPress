<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\StoreAction;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\HashesStorageDir;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModConsumer;

class BaseExec {

	use ExecOnce;
	use ModConsumer;

	protected function canRun() :bool {
		return !empty( ( new HashesStorageDir() )->getTempDir() );
	}
}