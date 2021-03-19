<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Services\Services;

class CooldownFlagFile {

	use Modules\ModConsumer;

	/**
	 * @return bool
	 */
	public function isWithinCooldownPeriod() {
		return $this->getCooldownRemaining() > 0;
	}

	/**
	 * @return int
	 */
	public function getCooldownRemaining() {
		/** @var Modules\LoginGuard\Options $oOpts */
		$oOpts = $this->getOptions();
		return max( 0, $oOpts->getCooldownInterval() - $this->getSecondsSinceLastLogin() );
	}

	/**
	 * @return string
	 */
	public function getFlagFilePath() {
		return $this->getCon()->getPluginCachePath( 'mode.login_throttled' );
	}

	/**
	 * @return int
	 */
	public function getSecondsSinceLastLogin() {
		$FS = Services::WpFs();
		$file = $this->getFlagFilePath();
		$nLastLogin = $FS->exists( $file ) ? $FS->getModifiedTime( $file ) : 0;
		return ( Services::Request()->ts() - $nLastLogin );
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
