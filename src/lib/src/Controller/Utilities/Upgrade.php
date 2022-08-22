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
		return ( $con->cfg->previous_version !== $con->getVersion() ) && !$this->isAlreadyUpgrading();
	}

	protected function isAlreadyUpgrading() :bool {
		$FS = Services::WpFs();
		$upgradeFlag = $this->getCon()->cache_dir_handler->cacheItemPath( 'upgrading.flag' );
		return !empty( $upgradeFlag )
			   && $FS->isFile( $upgradeFlag )
			   && ( Services::Request()->ts() - 600 ) < $FS->getModifiedTime( $upgradeFlag );
	}

	protected function run() {
		$con = $this->getCon();
		$FS = Services::WpFs();

		$filePath = $con->cache_dir_handler->cacheItemPath( 'upgrading.flag' );

		$FS->touch( $filePath, Services::Request()->ts() );

		$this->upgradeModules();

		$con->cfg->previous_version = $con->getVersion();

		if ( $FS->isFile( $filePath ) ) {
			add_action( $con->prefix( 'plugin_shutdown' ), function () {
				$con = $this->getCon();
				Services::WpFs()->deleteFile( $con->cache_dir_handler->cacheItemPath( 'upgrading.flag' ) );
			} );
		}
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