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

	/**
	 * @return bool
	 */
	public function isAutoUpdateCoreNever() {
		return $this->isOpt( 'autoupdate_core', 'core_never' );
	}

	/**
	 * @return bool
	 */
	public function isAutoUpdateCoreMajor() {
		return $this->isOpt( 'autoupdate_core', 'core_major' );
	}

	/**
	 * @return bool
	 */
	public function isAutoUpdateCoreMinor() {
		return !$this->isAutoUpdateCoreNever();
	}

	/**
	 * @return bool
	 */
	public function isAutoupdateAllPlugins() {
		return $this->isOpt( 'enable_autoupdate_plugins', 'Y' );
	}

	/**
	 * @return bool
	 */
	public function isDisableAllAutoUpdates() {
		return $this->isOpt( 'enable_autoupdate_disable_all', 'Y' );
	}

	/**
	 * @return bool
	 */
	public function isDelayUpdates() {
		return $this->getDelayUpdatesPeriod() > 0;
	}

	/**
	 * @return bool
	 */
	public function isSendAutoupdatesNotificationEmail() {
		return $this->isOpt( 'enable_upgrade_notification_email', 'Y' );
	}

	/**
	 * @param array $aTrackingInfo
	 * @return $this
	 */
	public function setDelayTracking( $aTrackingInfo ) {
		return $this->setOpt( 'delay_tracking', $aTrackingInfo );
	}
}