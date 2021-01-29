<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Utilities;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class Upgrade {

	use Shield\Modules\PluginControllerConsumer;
	use ExecOnce;

	protected function canRun() :bool {
		$con = $this->getCon();
		return $con->cfg->previous_version !== $con->getVersion();
	}

	protected function run() {
		$con = $this->getCon();

		$this->upgradeModules();
		do_action( $con->prefix( 'plugin_shutdown' ), function () {
			$this->deleteOldModConfigs();
		} );

		$con->cfg->previous_version = $con->getVersion();
	}

	private function deleteOldModConfigs() {
		$DB = Services::WpDb();
		$DB->doSql(
			sprintf( 'DELETE from `%s` where `option_name` LIKE "shield_mod_config_%%"', $DB->getTable_Options() )
		);
	}

	private function upgradeModules() {
		foreach ( $this->getCon()->modules as $mod ) {
			$H = $mod->getUpgradeHandler();
			if ( $H instanceof Shield\Modules\Base\Upgrade ) {
				$H->execute();
			}
		}
	}
}