<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller\Wcf;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\ResultsRetrieve;

class Debug extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Debug {

	public function run() {
		$this->testscans();
		die( 'finish' );
	}

	private function testscans() {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$res = ( new ResultsRetrieve() )
			->setScanController( $mod->getScanCon( Wcf::SCAN_SLUG ) )
			->setMod( $this->getMod() )
			->retrieveLatest();
		var_dump( $res );
	}
}