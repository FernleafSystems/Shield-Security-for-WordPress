<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Checks;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

/**
 * All Plugin Modules have been created at this stage.
 * We now run some pre-checks to ensure we're ok to do full modules boot.
 */
class PreModulesBootCheck {

	use PluginControllerConsumer;

	/**
	 * @throws \Exception
	 */
	public function run() :array {
		$con = $this->getCon();

		$checks = [
			'dbs' => [
			]
		];

		foreach ( $con->modules as $mod ) {
			foreach ( $mod->getDbHandler()->loadAllDbHandlers() as $dbh ) {
				$checks[ 'dbs' ][ $dbh->getTableSchema()->slug ] = $dbh->isReady();
			}
		}

		return $checks;
	}
}
