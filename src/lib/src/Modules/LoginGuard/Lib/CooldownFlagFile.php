<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class CooldownFlagFile {

	use ModConsumer;

	public function getCooldownRemaining() :int {
		return (int)\max( 0, $this->opts()->getOpt( 'login_limit_interval' ) - $this->getSecondsSinceLastLogin() );
	}

	public function getFlagFilePath() :string {
		return self::con()->cache_dir_handler->cacheItemPath( 'mode.login_throttled' );
	}

	public function getSecondsSinceLastLogin() :int {
		$FS = Services::WpFs();
		$file = $this->getFlagFilePath();
		return Services::Request()->ts() - ( $FS->exists( $file ) ? $FS->getModifiedTime( $file ) : 0 );
	}

	/**
	 * @return $this
	 */
	public function updateCooldownFlag() {
		$FS = Services::WpFs();
		$file = $this->getFlagFilePath();
		$FS->deleteFile( $file );
		$FS->touch( $file, Services::Request()->ts() );
		return $this;
	}
}
