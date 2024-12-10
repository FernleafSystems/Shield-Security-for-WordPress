<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @deprecated 20.1
 */
class CooldownFlagFile {

	use PluginControllerConsumer;

	public function getCooldownRemaining() :int {
		return (int)\max( 0, self::con()->opts->optGet( 'login_limit_interval' ) - $this->getSecondsSinceLastLogin() );
	}

	public function getFlagFilePath() :string {
		return self::con()->cache_dir_handler->cacheItemPath( 'mode.login_throttled' );
	}

	public function getSecondsSinceLastLogin() :int {
		$FS = Services::WpFs();
		$file = $this->getFlagFilePath();
		return Services::Request()->ts() - ( $FS->exists( $file ) ? $FS->getModifiedTime( $file ) : 0 );
	}

	public function updateCooldownFlag() :void {
		$FS = Services::WpFs();
		$file = $this->getFlagFilePath();
		$FS->deleteFile( $file );
		$FS->touch( $file, Services::Request()->ts() );
	}
}