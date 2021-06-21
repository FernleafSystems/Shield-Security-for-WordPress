<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;

use FernleafSystems\Wordpress\Plugin\Shield\Modules;

class Debug extends Modules\Base\Debug {

	public function run() {
		die( 'finish' );
	}

	private function filelocker() {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$mod->getFileLocker()->processFileLocks();
	}
}