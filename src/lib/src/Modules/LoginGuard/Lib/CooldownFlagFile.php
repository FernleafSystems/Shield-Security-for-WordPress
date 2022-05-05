<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Services\Services;

class CooldownFlagFile {

	use Modules\ModConsumer;

	public function isWithinCooldownPeriod() :bool {
		return $this->getCooldownRemaining() > 0;
	}

	public function getCooldownRemaining() :int {
		/** @var Modules\LoginGuard\Options $opts */
		$opts = $this->getOptions();
		return (int)max( 0, $opts->getCooldownInterval() - $this->getSecondsSinceLastLogin() );
	}

	public function getFlagFilePath() :string {
		return $this->getCon()->paths->forCacheItem( 'mode.login_throttled' );
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
