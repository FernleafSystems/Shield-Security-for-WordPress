<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;

use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal\Signatures;
use FernleafSystems\Wordpress\Services\Utilities\Net\IpIdentify;

class Debug extends Modules\Base\Debug {

	public function run() {
		$this->dumpSigs();
		die();
	}

	private function dumpSigs() {
		var_dump(Signatures::getAll());
	}
}