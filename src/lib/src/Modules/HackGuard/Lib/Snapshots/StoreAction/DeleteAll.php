<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\StoreAction;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModCon;
use FernleafSystems\Wordpress\Services\Services;

class DeleteAll extends BaseBulk {

	public function run() {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		Services::WpFs()->deleteDir( $mod->getPtgSnapsBaseDir() );
	}
}