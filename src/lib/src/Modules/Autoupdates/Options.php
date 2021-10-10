<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Autoupdates;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Services\Services;

class Options extends BaseShield\Options {

	/**
	 * @return array
	 */
	public function getDelayTracking() {
		$aTracking = $this->getOpt( 'delay_tracking', [] );
		if ( !is_array( $aTracking ) ) {
			$aTracking = [];
		}
		$aTracking = Services::DataManipulation()->mergeArraysRecursive(
			[
				'core'    => [],
				'plugins' => [],
				'themes'  => [],
			],
			$aTracking
		);
		$this->setOpt( 'delay_tracking', $aTracking );

		return $aTracking;
	}

	/**
	 * @return int
	 */
	public function getDelayUpdatesPeriod() {
		return $this->isPremium() ? $this->getOpt( 'update_delay', 0 )*DAY_IN_SECONDS : 0;
	}

	/**
	 * @return string
	 */
	public function getSelfAutoUpdateOpt() {
		return $this->getOpt( 'autoupdate_plugin_self' );
	}

	public function isAutoUpdateCoreNever() :bool {
		return $this->isOpt( 'autoupdate_core', 'core_never' );
	}

	public function isAutoUpdateCoreMajor() :bool {
		return $this->isOpt( 'autoupdate_core', 'core_major' );
	}

	public function isAutoupdateAllPlugins() :bool {
		return $this->isOpt( 'enable_autoupdate_plugins', 'Y' );
	}

	public function isDisableAllAutoUpdates() :bool {
		return $this->isOpt( 'enable_autoupdate_disable_all', 'Y' );
	}

	public function isDelayUpdates() :bool {
		return $this->getDelayUpdatesPeriod() > 0;
	}

	public function isSendAutoupdatesNotificationEmail() :bool {
		return $this->isOpt( 'enable_upgrade_notification_email', 'Y' );
	}

	/**
	 * @param array $trackingInfo
	 * @return $this
	 */
	public function setDelayTracking( $trackingInfo ) {
		return $this->setOpt( 'delay_tracking', $trackingInfo );
	}
}