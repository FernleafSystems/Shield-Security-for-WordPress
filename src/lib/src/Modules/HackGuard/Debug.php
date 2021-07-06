<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;

class Debug extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Debug {

	public function run() {
		die( 'finish' );
	}
}