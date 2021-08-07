<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin;

use FernleafSystems\Wordpress\Plugin\Shield\Modules;

class Debug extends Modules\Base\Debug {

	public function run() {
		die( 'finish' );
	}
}