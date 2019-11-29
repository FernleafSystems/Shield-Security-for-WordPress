<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Autoupdates;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;
use FernleafSystems\Wordpress\Services\Services;

class Options extends Base\ShieldOptions {

	/**
	 * @return string[]
	 */
	public function getAutoupdatePlugins() {
		$aSelected = [];
		if ( $this->isAutoupdateIndividualPlugins() ) {
			$aSelected = $this->getOpt( 'selected_plugins', [] );
			if ( !is_array( $aSelected ) ) {
				$aSelected = [];
			}
		}
		return $aSelected;
	}

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
	public function isAutoUpdateCoreMajor() {
		return $this->isOpt( 'autoupdate_core', 'core_major' );
	}

	/**
	 * @return bool
	 */
	public function isAutoUpdateCoreMinor() {
		return !$this->isOpt( 'autoupdate_core', 'core_never' );
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
	public function isAutoupdateIndividualPlugins() {
		return $this->isPremium() && $this->isOpt( 'enable_individual_autoupdate_plugins', 'Y' );
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
	 * @param string $sPluginFile
	 * @return bool
	 */
	public function isPluginSetToAutoupdate( $sPluginFile ) {
		return in_array( $sPluginFile, $this->getAutoupdatePlugins() );
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

	/**
	 * @param string $sPluginFile
	 * @return $this
	 */
	public function setPluginToAutoUpdate( $sPluginFile ) {
		$aPlugins = $this->getAutoupdatePlugins();
		$nKey = array_search( $sPluginFile, $aPlugins );

		if ( $nKey === false ) {
			$aPlugins[] = $sPluginFile;
		}
		else {
			unset( $aPlugins[ $nKey ] );
		}

		return $this->setOpt( 'selected_plugins', $aPlugins );
	}
}