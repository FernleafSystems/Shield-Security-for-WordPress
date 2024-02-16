<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Autoupdates;

use FernleafSystems\Wordpress\Services\Services;

class Options extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Options {

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
	 * @return int
	 */
	public function getDelayUpdatesPeriod() {
		return self::con()->isPremiumActive() ? $this->getOpt( 'update_delay', 0 )*\DAY_IN_SECONDS : 0;
	}

	public function getSelfAutoUpdateOpt() :string {
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

	public function setDelayTracking( array $trackingInfo ) {
		$this->setOpt( 'delay_tracking', $trackingInfo );
	}
}