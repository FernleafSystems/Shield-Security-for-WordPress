<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Services\Services;

class CooldownFlagFile {

	use Modules\ModConsumer;

	public function isWithinCooldownPeriod() :bool {
		return $this->getCooldownRemaining() > 0;
	}

	/**
	 * @return int
	 */
	public function getCooldownRemaining() {
		/** @var Modules\LoginGuard\Options $opts */
		$opts = $this->getOptions();
		return max( 0, $opts->getCooldownInterval() - $this->getSecondsSinceLastLogin() );
	}

	public function getFlagFilePath() :string {
		return $this->getCon()->getPluginCachePath( 'mode.login_throttled' );
	}

	/**
	 * @return int
	 */
	public function getSecondsSinceLastLogin() {
		$FS = Services::WpFs();
		$file = $this->getFlagFilePath();
		$lastLogin = $FS->exists( $file ) ? $FS->getModifiedTime( $file ) : 0;
		return Services::Request()->ts() - $lastLogin;
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
