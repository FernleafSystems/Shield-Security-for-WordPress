<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Checks;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

/**
 * All Plugin Modules have been created at this stage.
 * We now run some pre-checks to ensure we're ok to do full modules boot.
 */
class PreModulesBootCheck {

	use PluginControllerConsumer;

	public function run( bool $ensureFreshResults = false ) :array {
		$con = $this->con();

		$checks = [
			'dbs' => [
			]
		];

		foreach ( $con->modules as $mod ) {
			try {
				foreach ( $mod->getDbHandler()->loadAllDbHandlers( $ensureFreshResults ) as $dbh ) {
					$checks[ 'dbs' ][ $dbh->getTableSchema()->slug ] = $dbh->isReady();
				}
			}
			catch ( \Exception $e ) {
			}
		}

		return $checks;
	}
}
