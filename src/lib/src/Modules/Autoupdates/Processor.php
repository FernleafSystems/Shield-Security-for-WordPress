<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Autoupdates;

/**
 * @deprecated 19.1
 */
class Processor extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Processor {

	/**
	 * @deprecated 19.1
	 */
	private function disableAllAutoUpdates() {
	}

	/**
	 * @deprecated 19.1
	 */
	protected function getTrackedAssetsVersions() :array {
		return [];
	}

	/**
	 * @deprecated 19.1
	 */
	public function trackUpdateTimesCore( $updates ) {
	}

	/**
	 * @deprecated 19.1
	 */
	public function trackUpdateTimesPlugins( $updates ) {
	}

	/**
	 * @deprecated 19.1
	 */
	public function trackUpdateTimesThemes( $updates ) {
	}

	/**
	 * @deprecated 19.1
	 */
	protected function trackUpdateTimeCommon( $updates, $context ) {
	}

	/**
	 * @deprecated 19.1
	 */
	public function autoupdate_core_major( $toUpdate ) {
		return $toUpdate;
	}

	/**
	 * @deprecated 19.1
	 */
	public function autoupdate_core_minor( $toUpdate ) {
		return $toUpdate;
	}

	/**
	 * @param bool      $isDoUpdate
	 * @param \stdClass $coreUpgrade
	 * @return bool
	 */
	public function autoupdate_core( $isDoUpdate, $coreUpgrade ) {
		return $isDoUpdate;
	}

	/**
	 * @deprecated 19.1
	 */
	public function autoupdate_plugins( $doUpdate, $mItem ) {
		return $doUpdate;
	}

	/**
	 * @deprecated 19.1
	 */
	public function autoupdate_themes( $doAutoUpdate, $mItem ) {
		return $doAutoUpdate;
	}

	/**
	 * @param string|\stdClass $slug
	 * @param string           $context
	 */
	private function isDelayed( $slug, $context = 'plugins' ) :bool {
		return false;
	}

	/**
	 * @deprecated 19.1
	 */
	public function autoupdate_send_email( $sendEmail ) {
		return $sendEmail;
	}

	/**
	 * @deprecated 19.1
	 */
	public function autoupdate_email_override( $emailParams ) {
		return $emailParams;
	}

	/**
	 * @deprecated 19.1
	 */
	public function sendNotificationEmail( $updateResults ) {
	}

	/**
	 * @deprecated 19.1
	 */
	private function getHookPriority() :int {
		return (int)$this->opts()->getDef( 'action_hook_priority' );
	}
}