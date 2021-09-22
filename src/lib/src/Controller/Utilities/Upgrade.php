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
		return $con->cfg->previous_version !== $con->getVersion()
			   && !$this->isAlreadyUpgrading();
	}

	protected function isAlreadyUpgrading() :bool {
		$FS = Services::WpFs();
		$upgradeFlag = $this->getCon()->getPluginCachePath( 'upgrading.flag' );
		return !empty( $upgradeFlag )
			   && $FS->isFile( $upgradeFlag )
			   && ( Services::Request()->ts() - 600 ) < $FS->getModifiedTime( $upgradeFlag );
	}

	protected function run() {
		$con = $this->getCon();

		Services::WpFs()->touch( $con->getPluginCachePath( 'upgrading.flag' ), Services::Request()->ts() );

		$this->upgradeModules();
		do_action( $con->prefix( 'plugin_shutdown' ), function () {
			$this->deleteOldModConfigs();
		} );

		$con->cfg->previous_version = $con->getVersion();

		add_action( $con->prefix( 'plugin_shutdown' ), function () {
			Services::WpFs()->deleteFile( $this->getCon()->getPluginCachePath( 'upgrading.flag' ) );
		} );
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