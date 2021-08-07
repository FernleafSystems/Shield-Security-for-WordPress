<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

/**
 * Detects any duplicate event keys
 * Class VerifyUniqueEvents
 * @package FernleafSystems\Wordpress\Plugin\Shield\Tests
 */
class VerifyUniqueEvents {

	use PluginControllerConsumer;

	public function run() {
		$con = $this->getCon();

		$all = [];
		foreach ( $con->modules as $mod ) {
			$keys = array_map(
				function ( $aEvt ) {
					return $aEvt[ 'key' ];
				},
				array_values( $mod->getOptions()->getDef( 'events' ) )
			);
			$all = array_merge( $all, $keys );
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