<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin;

use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\RunTests;

class Debug extends Modules\Base\Debug {

	public function run() {
		( new RunTests() )
			->setCon( $this->getCon() )
			->run();
		die( 'finish' );
	}
}