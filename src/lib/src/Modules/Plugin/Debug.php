<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin;

use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\ShieldNET\BuildData;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\RunTests;

class Debug extends Modules\Base\Debug {

	public function run() {
		$this->tests();
		die( 'finish' );
	}

	private function testbotsignals() {
		$r = ( new BuildData() )
			->setMod( $this->getCon()->getModule_IPs() )
			->build( true );
		var_dump( $r );
	}

	private function getIpRefs() {
		$ipRefs = $this->getCon()
					   ->getModule_Data()
					   ->getDbH_ReqLogs()
					   ->getQuerySelector()
					   ->getDistinctForColumn( 'ip_ref' );
		var_dump( $ipRefs );
	}

	private function tests() {
		( new RunTests() )
			->setCon( $this->getCon() )
			->run();
	}
}