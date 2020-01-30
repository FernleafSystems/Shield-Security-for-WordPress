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
		$oCon = $this->getCon();

		$aAllKeys = [];
		foreach ( $oCon->getModules() as $oMod ) {
			$aKeys = array_map(
				function ( $aEvt ) {
					return $aEvt[ 'key' ];
				},
				array_values( $oMod->getOptions()->getDef( 'events' ) )
			);
			$aAllKeys = array_merge( $aAllKeys, $aKeys );
		}
		if ( count( $aAllKeys ) != count( array_unique( $aAllKeys ) ) ) {
			echo "duplicates!\n";
			var_dump( array_diff( $aAllKeys, array_unique( $aAllKeys ) ) );
		}
		else {
			echo 'NO duplicates!';
		}
	}
}