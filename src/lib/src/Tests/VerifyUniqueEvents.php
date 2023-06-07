<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

/**
 * Detects any duplicate event keys
 */
class VerifyUniqueEvents {

	use PluginControllerConsumer;

	public function run() {
		$con = $this->con();

		$all = [];
		foreach ( $con->modules as $mod ) {
			$all = array_merge( $all, array_keys( $mod->getOptions()->getEvents() ) );
		}
		if ( count( $all ) != count( array_unique( $all ) ) ) {
			echo "duplicates!\n";
			var_dump( array_diff( $all, array_unique( $all ) ) );
		}
		else {
			echo 'NO duplicates!';
		}
	}
}