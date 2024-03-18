<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class AutoUpdatesCon {

	use ExecOnce;
	use PluginControllerConsumer;

	protected function canRun() :bool {
		return self::con()->opts->optIs( 'enable_autoupdates', 'Y' );
	}

	/**
	 * The allow_* core filters are run first in a "should_update" query. Then comes the "auto_update_core"
	 * filter. What this filter decides will ultimately determine the fate of any core upgrade.
	 */
	protected function run() {
		$priority = (int)self::con()->cfg->configuration->def( 'action_hook_priority' );

		add_action( 'wp_loaded', [ $this, 'onWpLoaded' ], $priority );

		add_filter( 'allow_minor_auto_core_updates', [ $this, 'autoupdate_core_minor' ], $priority );
		add_filter( 'allow_major_auto_core_updates', [ $this, 'autoupdate_core_major' ], $priority );

		add_filter( 'auto_update_plugin', [ $this, 'autoupdate_plugins' ], $priority, 2 );
		add_filter( 'auto_update_theme', [ $this, 'autoupdate_themes' ], $priority, 2 );
		add_filter( 'auto_update_core', [ $this, 'autoupdate_core' ], $priority, 2 );

		if ( !$this->disableAll() ) {
			add_filter( 'auto_core_update_send_email', [ $this, 'autoupdate_send_email' ], $priority, 0 );
			add_filter( 'auto_core_update_email', [ $this, 'autoupdate_email_override' ], $priority );
			add_filter( 'auto_plugin_theme_update_email', [ $this, 'autoupdate_email_override' ], $priority );
			add_action( 'set_site_transient_update_core', [ $this, 'trackUpdateTimesCore' ] );
			add_action( 'set_site_transient_update_plugins', [ $this, 'trackUpdateTimesPlugins' ] );
			add_action( 'set_site_transient_update_themes', [ $this, 'trackUpdateTimesThemes' ] );
		}
	}

	public function onWpLoaded() {
		if ( $this->disableAll() ) {
			remove_all_filters( 'automatic_updater_disabled' );
			add_filter( 'automatic_updater_disabled', '__return_true', \PHP_INT_MAX );
			if ( !\defined( 'WP_AUTO_UPDATE_CORE' ) ) {
				\define( 'WP_AUTO_UPDATE_CORE', false );
			}
		}
	}

	/**
	 * @param \stdClass $updates
	 */
	public function trackUpdateTimesCore( $updates ) {

		if ( !empty( $updates ) && isset( $updates->updates ) && \is_array( $updates->updates ) ) {

			$delayTracking = $this->getDelayTracking();

			$item = $delayTracking[ 'core' ][ 'wp' ] ?? [];
			foreach ( $updates->updates as $upd ) {
				if ( 'autoupdate' == $upd->response ) {
					$version = $upd->current;
					if ( !isset( $item[ $version ] ) ) {
						$item[ $version ] = Services::Request()->ts();
					}
				}
			}
			$delayTracking[ 'core' ][ 'wp' ] = \array_slice( $item, -5 );

			self::con()->opts->optSet( 'delay_tracking', $delayTracking );
		}
	}

	/**
	 * @param \stdClass $updates
	 */
	public function trackUpdateTimesPlugins( $updates ) {
		$this->trackUpdateTimeCommon( $updates, 'plugins' );
	}

	/**
	 * @param \stdClass $updates
	 */
	public function trackUpdateTimesThemes( $updates ) {
		$this->trackUpdateTimeCommon( $updates, 'themes' );
	}

	/**
	 * @param \stdClass $updates
	 * @param string    $context - plugins/themes
	 */
	protected function trackUpdateTimeCommon( $updates, $context ) {
		if ( !empty( $updates ) && isset( $updates->response ) && \is_array( $updates->response ) ) {
			$delayTracking = $this->getDelayTracking();

			foreach ( $updates->response as $slug => $theUpdate ) {
				$itemTrack = $delayTracking[ $context ][ $slug ] ?? [];
				if ( \is_array( $theUpdate ) ) {
					$theUpdate = (object)$theUpdate;
				}

				$newVersion = $theUpdate->new_version ?? '';
				if ( !empty( $newVersion ) ) {
					if ( !isset( $itemTrack[ $newVersion ] ) ) {
						$itemTrack[ $newVersion ] = Services::Request()->ts();
					}
					$delayTracking[ $context ][ $slug ] = \array_slice( $itemTrack, -3 );
				}
			}

			self::con()->opts->optSet( 'delay_tracking', $delayTracking );
		}
	}

	/**
	 * This is a filter method designed to say whether a major core WordPress upgrade should be permitted,
	 * based on the plugin settings.
	 * @param bool $toUpdate
	 * @return bool
	 */
	public function autoupdate_core_major( $toUpdate ) {
		if ( $this->isCoreAutoUpgradesDisabled() ) {
			$toUpdate = false;
		}
		elseif ( !$this->isDelayUpdates() ) {
			$toUpdate = self::con()->opts->optIs( 'autoupdate_core', 'core_major' );
		}
		return $toUpdate;
	}

	/**
	 * This is a filter method designed to say whether a minor core WordPress upgrade should be permitted,
	 * based on the plugin settings.
	 * @param bool $doUpdate
	 * @return bool
	 */
	public function autoupdate_core_minor( $doUpdate ) {
		if ( $this->isCoreAutoUpgradesDisabled() ) {
			$doUpdate = false;
		}
		elseif ( !$this->isDelayUpdates() ) {
			$doUpdate = !self::con()->opts->optIs( 'autoupdate_core', 'core_never' );
		}
		return $doUpdate;
	}

	/**
	 * @param bool      $doUpdate
	 * @param \stdClass $coreUpgrade
	 * @return bool
	 */
	public function autoupdate_core( $doUpdate, $coreUpgrade ) {
		if ( ( $this->isCoreAutoUpgradesDisabled() ) || $this->isDelayed( $coreUpgrade, 'core' ) ) {
			$doUpdate = false;
		}
		return $doUpdate;
	}

	/**
	 * @param bool             $doUpdate
	 * @param \stdClass|string $mItem
	 * @return bool
	 */
	public function autoupdate_plugins( $doUpdate, $mItem ) {
		if ( $this->disableAll() ) {
			$doUpdate = false;
		}
		else {
			$file = Services::WpGeneral()->getFileFromAutomaticUpdateItem( $mItem );

			if ( $this->isDelayed( $file, 'plugins' ) ) {
				$doUpdate = false;
			}
			elseif ( self::con()->opts->optIs( 'enable_autoupdate_plugins', 'Y' ) ) {
				$doUpdate = true;
			}
			elseif ( $file === self::con()->base_file ) {
				$auto = self::con()->opts->optGet( 'autoupdate_plugin_self' );
				if ( $auto === 'immediate' ) {
					$doUpdate = true;
				}
				elseif ( $auto === 'disabled' ) {
					$doUpdate = false;
				}
			}
		}
		return $doUpdate;
	}

	/**
	 * @param bool             $doAutoUpdate
	 * @param \stdClass|string $mItem
	 * @return bool
	 */
	public function autoupdate_themes( $doAutoUpdate, $mItem ) {
		if ( $this->disableAll() ) {
			$doAutoUpdate = false;
		}
		else {
			$file = Services::WpGeneral()->getFileFromAutomaticUpdateItem( $mItem, 'theme' );

			if ( $this->isDelayed( $file, 'themes' ) ) {
				$doAutoUpdate = false;
			}
			elseif ( self::con()->opts->optIs( 'enable_autoupdate_themes', 'Y' ) ) {
				$doAutoUpdate = true;
			}
		}
		return $doAutoUpdate;
	}

	/**
	 * @param string|\stdClass $slug
	 */
	private function isDelayed( $slug, string $context ) :bool {
		$delayed = false;

		if ( $this->isDelayUpdates() ) {

			$delayTracking = $this->getDelayTracking();

			$version = '';
			if ( $context == 'core' ) {
				$version = $slug->current; // \stdClass from transient update_core
				$slug = 'wp';
			}

			$itemTrack = $delayTracking[ $context ][ $slug ] ?? [];

			if ( $context == 'plugins' ) {
				$pluginInfo = Services::WpPlugins()->getUpdateInfo( $slug );
				$version = $pluginInfo->new_version ?? '';
			}
			elseif ( $context == 'themes' ) {
				$themeInfo = Services::WpThemes()->getUpdateInfo( $slug );
				$version = $themeInfo[ 'new_version' ] ?? '';
			}

			if ( !empty( $version ) && isset( $itemTrack[ $version ] ) ) {
				$delayed = ( Services::Request()->ts() - $itemTrack[ $version ] )
						   < self::con()->opts->optGet( 'update_delay' )*\DAY_IN_SECONDS;
			}
		}

		return $delayed;
	}

	/**
	 * A filter on whether a notification email is sent after core upgrades are attempted.
	 */
	public function autoupdate_send_email() :bool {
		return self::con()->opts->optIs( 'enable_upgrade_notification_email', 'Y' );
	}

	/**
	 * A filter on the target email address to which to send upgrade notification emails.
	 * @param array $emailParams
	 * @return array
	 */
	public function autoupdate_email_override( $emailParams ) {
		$override = self::con()->opts->optGet( 'override_email_address' );
		if ( Services::Data()->validEmail( $override ) ) {
			$emailParams[ 'to' ] = $override;
		}
		return $emailParams;
	}

	public function getDelayTracking() :array {
		$opts = self::con()->opts;

		$opts->optSet( 'delay_tracking',
			Services::DataManipulation()->mergeArraysRecursive( [
				'core'    => [],
				'plugins' => [],
				'themes'  => [],
			], $opts->optGet( 'delay_tracking' ) )
		);

		return $opts->optGet( 'delay_tracking' );
	}

	public function isCoreAutoUpgradesDisabled() :bool {
		return $this->disableAll() || self::con()->opts->optIs( 'autoupdate_core', 'core_never' );
	}

	public function isDelayUpdates() :bool {
		return self::con()->opts->optGet( 'update_delay' ) > 0;
	}

	public function disableAll() :bool {
		return self::con()->opts->optIs( 'enable_autoupdate_disable_all', 'Y' );
	}
}