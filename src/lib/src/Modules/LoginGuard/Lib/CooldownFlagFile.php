<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class CooldownFlagFile
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib
 */
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
		return path_join( $this->getCon()->getPluginCachePath(), 'mode.login_throttled' );
	}

	/**
	 * @return int
	 */
	public function getSecondsSinceLastLogin() {
		$oFS = Services::WpFs();
		$sFile = $this->getFlagFilePath();
		$nLastLogin = $oFS->exists( $sFile ) ? $oFS->getModifiedTime( $sFile ) : 0;
		return ( Services::Request()->ts() - $nLastLogin );
	}

	/**
	 * @return $this
	 */
	public function updateCooldownFlag() {
		$oFS = Services::WpFs();
		$sFile = $this->getFlagFilePath();
		$oFS->deleteFile( $sFile );
		$oFS->touch( $sFile, Services::Request()->ts() );
		return $this;
	}
}
