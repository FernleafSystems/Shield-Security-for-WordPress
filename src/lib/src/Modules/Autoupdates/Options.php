<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Autoupdates;

use FernleafSystems\Wordpress\Services\Services;

/**
 * @deprecated 19.1
 */
class Options extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Options {

	/**
	 * @deprecated 19.1
	 */
	public function isAutoUpdateCoreNever() :bool {
		return $this->isOpt( 'autoupdate_core', 'core_never' );
	}

	/**
	 * @deprecated 19.1
	 */
	public function isDelayUpdates() :bool {
		return ( self::con()->isPremiumActive() ? $this->getOpt( 'update_delay', 0 ) : 0 ) > 0;
	}

	/**
	 * @deprecated 19.1
	 */
	public function getSelfAutoUpdateOpt() :string {
		return $this->getOpt( 'autoupdate_plugin_self' );
	}

	/**
	 * @deprecated 19.1
	 */
	public function isAutoUpdateCoreMajor() :bool {
		return $this->isOpt( 'autoupdate_core', 'core_major' );
	}

	/**
	 * @deprecated 19.1
	 */
	public function isAutoupdateAllPlugins() :bool {
		return $this->isOpt( 'enable_autoupdate_plugins', 'Y' );
	}

	/**
	 * @deprecated 19.1
	 */
	public function isDisableAllAutoUpdates() :bool {
		return $this->isOpt( 'enable_autoupdate_disable_all', 'Y' );
	}

	/**
	 * @deprecated 19.1
	 */
	public function isSendAutoupdatesNotificationEmail() :bool {
		return $this->isOpt( 'enable_upgrade_notification_email', 'Y' );
	}

	/**
	 * @deprecated 19.1
	 */
	public function setDelayTracking( array $trackingInfo ) {
		$this->setOpt( 'delay_tracking', $trackingInfo );
	}

	/**
	 * @deprecated 19.1
	 */
	public function getDelayTracking() :array {
		$tracking = $this->getOpt( 'delay_tracking', [] );
		if ( !\is_array( $tracking ) ) {
			$tracking = [];
		}
		$tracking = Services::DataManipulation()->mergeArraysRecursive( [
			'core'    => [],
			'plugins' => [],
			'themes'  => [],
		], $tracking );

		$this->setOpt( 'delay_tracking', $tracking );

		return $tracking;
	}

	/**
	 * @deprecated 19.1
	 */
	public function getDelayUpdatesPeriod() {
		return self::con()->isPremiumActive() ? $this->getOpt( 'update_delay', 0 )*\DAY_IN_SECONDS : 0;
	}
}