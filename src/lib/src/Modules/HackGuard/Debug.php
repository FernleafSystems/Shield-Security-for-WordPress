<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue\QueueItems;

class Debug extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Debug {

	public function run() {
		$this->testscans();
		die( 'finish' );
	}

	private function testscans() {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$res = ( new QueueItems() )
			->setMod( $mod )
			->next();
		var_dump( $res );
	}
}