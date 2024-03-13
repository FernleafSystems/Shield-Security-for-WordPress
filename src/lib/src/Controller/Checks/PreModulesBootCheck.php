<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Checks;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

/**
 * All Plugin Modules have been created at this stage.
 * We now run some pre-checks to ensure we're ok to do full modules boot.
 */
class PreModulesBootCheck {

	use PluginControllerConsumer;

	public function run() :array {
		$con = self::con();

		$checks = [
			'dbs' => [
			]
		];

		foreach ( $con->db_con->getHandlers() as $dbKey => $handlerDef ) {
			try {
				if ( $handlerDef[ 'def' ][ 'is_core' ] ) {
					$dbh = $con->db_con->loadDbH( $dbKey );
					$checks[ 'dbs' ][ $dbh->getTableSchema()->slug ] = $dbh->isReady();
				}
			}
			catch ( \Exception $e ) {
			}
		}

		return $checks;
	}
}
