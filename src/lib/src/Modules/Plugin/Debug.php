<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin;

use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Services\Utilities\Net\IpIdentify;

class Debug extends Modules\Base\Debug {

	public function run() {
		$this->ipID();
		die();
	}

	private function ipID() {
		$id = ( new IpIdentify( '198.61.176.9' ) )
			->run();
		var_dump( $id );
	}
}