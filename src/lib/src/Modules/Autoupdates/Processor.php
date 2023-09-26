<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Autoupdates;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Services\Services;

class Processor extends BaseShield\Processor {

	/**
	 * @var array
	 */
	private $assetsVersions = [];

	/**
	 * The allow_* core filters are run first in a "should_update" query. Then comes the "auto_update_core"
	 * filter. What this filter decides will ultimately determine the fate of any core upgrade.
	 */
	protected function run() {
		/** @var Options $opts */
		$opts = $this->opts();

		$priority = $this->getHookPriority();
		if ( Services::WpGeneral()->isClassicPress() ) {
			add_filter( 'allow_patch_auto_core_updates', [ $this, 'autoupdate_core_minor' ], $priority );
			add_filter( 'allow_minor_auto_core_updates', [ $this, 'autoupdate_core_major' ], $priority );
		}
		else {
			add_filter( 'allow_minor_auto_core_updates', [ $this, 'autoupdate_core_minor' ], $priority );
			add_filter( 'allow_major_auto_core_updates', [ $this, 'autoupdate_core_major' ], $priority );
		}

		add_filter( 'auto_update_plugin', [ $this, 'autoupdate_plugins' ], $priority, 2 );
		add_filter( 'auto_update_theme', [ $this, 'autoupdate_themes' ], $priority, 2 );
		add_filter( 'auto_update_core', [ $this, 'autoupdate_core' ], $priority, 2 );

		if ( !$opts->isDisableAllAutoUpdates() ) {
			//more parameter options here for later
			add_filter( 'auto_core_update_send_email', [ $this, 'autoupdate_send_email' ], $priority );
			add_filter( 'auto_core_update_email', [ $this, 'autoupdate_email_override' ], $priority );
			add_filter( 'auto_plugin_theme_update_email', [ $this, 'autoupdate_email_override' ], $priority );

			add_action( 'set_site_transient_update_core', [ $this, 'trackUpdateTimesCore' ] );
			add_action( 'set_site_transient_update_plugins', [ $this, 'trackUpdateTimesPlugins' ] );
			add_action( 'set_site_transient_update_themes', [ $this, 'trackUpdateTimesThemes' ] );

			if ( $opts->isSendAutoupdatesNotificationEmail()
				 && !Services::WpGeneral()->getWordpressIsAtLeastVersion( '5.5' ) ) {
				$this->trackAssetsVersions();
				add_action( 'automatic_updates_complete', [ $this, 'sendNotificationEmail' ] );
			}
		}
	}

	public function onWpLoaded() {
		parent::onWpLoaded();
		/** @var Options $opts */
		$opts = $this->opts();
		if ( $opts->isDisableAllAutoUpdates() ) {
			$this->disableAllAutoUpdates();
		}
	}

	private function disableAllAutoUpdates() {
		remove_all_filters( 'automatic_updater_disabled' );
		add_filter( 'automatic_updater_disabled', '__return_true', \PHP_INT_MAX );
		if ( !\defined( 'WP_AUTO_UPDATE_CORE' ) ) {
			\define( 'WP_AUTO_UPDATE_CORE', false );
		}
	}

	private function trackAssetsVersions() {
		$assetVers = $this->getTrackedAssetsVersions();

		$WPP = Services::WpPlugins();
		foreach ( \array_keys( $WPP->getUpdates() ) as $file ) {
			$assetVers[ 'plugins' ][ $file ] = $WPP->getPluginAsVo( $file )->Version;
		}
		$WPT = Services::WpThemes();
		foreach ( \array_keys( $WPT->getUpdates() ) as $file ) {
			$assetVers[ 'themes' ][ $file ] = $WPT->getTheme( $file )->get( 'Version' );
		}
		$this->assetsVersions = $assetVers;
	}

	protected function getTrackedAssetsVersions() :array {
		if ( empty( $this->assetsVersions ) || !\is_array( $this->assetsVersions ) ) {
			$this->assetsVersions = [
				'plugins' => [],
				'themes'  => [],
			];
		}
		return $this->assetsVersions;
	}

	/**
	 * @param \stdClass $updates
	 */
	public function trackUpdateTimesCore( $updates ) {

		if ( !empty( $updates ) && isset( $updates->updates ) && \is_array( $updates->updates ) ) {
			/** @var Options $opts */
			$opts = $this->opts();

			$delayTracking = $opts->getDelayTracking();
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
			$opts->setDelayTracking( $delayTracking );
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
		/** @var Options $opts */
		$opts = $this->opts();

		if ( !empty( $updates ) && isset( $updates->response ) && \is_array( $updates->response ) ) {

			$delayTracking = $opts->getDelayTracking();
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
			$opts->setDelayTracking( $delayTracking );
		}
	}

	/**
	 * This is a filter method designed to say whether a major core WordPress upgrade should be permitted,
	 * based on the plugin settings.
	 * @param bool $toUpdate
	 * @return bool
	 */
	public function autoupdate_core_major( $toUpdate ) {
		/** @var Options $opts */
		$opts = $this->opts();

		if ( $opts->isDisableAllAutoUpdates() || $opts->isAutoUpdateCoreNever() ) {
			$toUpdate = false;
		}
		elseif ( !$opts->isDelayUpdates() ) { // delay handled elsewhere
			$toUpdate = $opts->isAutoUpdateCoreMajor();
		}

		return $toUpdate;
	}

	/**
	 * This is a filter method designed to say whether a minor core WordPress upgrade should be permitted,
	 * based on the plugin settings.
	 * @param bool $toUpdate
	 * @return bool
	 */
	public function autoupdate_core_minor( $toUpdate ) {
		/** @var Options $opts */
		$opts = $this->opts();

		if ( $opts->isDisableAllAutoUpdates() || $opts->isAutoUpdateCoreNever() ) {
			$toUpdate = false;
		}
		elseif ( !$opts->isDelayUpdates() ) {
			$toUpdate = !$opts->isAutoUpdateCoreNever();
		}
		return $toUpdate;
	}

	/**
	 * @param bool      $isDoUpdate
	 * @param \stdClass $coreUpgrade
	 * @return bool
	 */
	public function autoupdate_core( $isDoUpdate, $coreUpgrade ) {
		/** @var Options $opts */
		$opts = $this->opts();

		if ( $opts->isDisableAllAutoUpdates() ) {
			$isDoUpdate = false;
		}
		elseif ( $this->isDelayed( $coreUpgrade, 'core' ) ) {
			$isDoUpdate = false;
		}

		return $isDoUpdate;
	}

	/**
	 * @param bool             $doUpdate
	 * @param \stdClass|string $mItem
	 * @return bool
	 */
	public function autoupdate_plugins( $doUpdate, $mItem ) {
		/** @var Options $opts */
		$opts = $this->opts();

		if ( $opts->isDisableAllAutoUpdates() ) {
			$doUpdate = false;
		}
		else {
			$file = Services::WpGeneral()->getFileFromAutomaticUpdateItem( $mItem );

			if ( $this->isDelayed( $file, 'plugins' ) ) {
				$doUpdate = false;
			}
			elseif ( $opts->isAutoupdateAllPlugins() ) {
				$doUpdate = true;
			}
			elseif ( $file === self::con()->base_file ) {
				$auto = $opts->getSelfAutoUpdateOpt();
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
		/** @var Options $opts */
		$opts = $this->opts();

		if ( $opts->isDisableAllAutoUpdates() ) {
			$doAutoUpdate = false;
		}
		else {
			$file = Services::WpGeneral()->getFileFromAutomaticUpdateItem( $mItem, 'theme' );

			if ( $this->isDelayed( $file, 'themes' ) ) {
				$doAutoUpdate = false;
			}
			elseif ( $opts->isOpt( 'enable_autoupdate_themes', 'Y' ) ) {
				$doAutoUpdate = true;
			}
		}

		return $doAutoUpdate;
	}

	/**
	 * @param string|\stdClass $slug
	 * @param string           $context
	 */
	private function isDelayed( $slug, $context = 'plugins' ) :bool {
		/** @var Options $opts */
		$opts = $this->opts();

		$delayed = false;

		if ( $opts->isDelayUpdates() ) {

			$delayTrack = $opts->getDelayTracking();

			$version = '';
			if ( $context == 'core' ) {
				$version = $slug->current; // \stdClass from transient update_core
				$slug = 'wp';
			}

			$itemTrack = $delayTrack[ $context ][ $slug ] ?? [];

			if ( $context == 'plugins' ) {
				$pluginInfo = Services::WpPlugins()->getUpdateInfo( $slug );
				$version = $pluginInfo->new_version ?? '';
			}
			elseif ( $context == 'themes' ) {
				$themeInfo = Services::WpThemes()->getUpdateInfo( $slug );
				$version = $themeInfo[ 'new_version' ] ?? '';
			}

			if ( !empty( $version ) && isset( $itemTrack[ $version ] ) ) {
				$delayed = ( Services::Request()->ts() - $itemTrack[ $version ] ) < $opts->getDelayUpdatesPeriod();
			}
		}

		return $delayed;
	}

	/**
	 * A filter on whether a notification email is sent after core upgrades are attempted.
	 * @param bool $sendEmail
	 * @return bool
	 */
	public function autoupdate_send_email( $sendEmail ) {
		/** @var Options $opts */
		$opts = $this->opts();
		return $opts->isSendAutoupdatesNotificationEmail();
	}

	/**
	 * A filter on the target email address to which to send upgrade notification emails.
	 * @param array $emailParams
	 * @return array
	 */
	public function autoupdate_email_override( $emailParams ) {
		$override = $this->opts()->getOpt( 'override_email_address', '' );
		if ( Services::Data()->validEmail( $override ) ) {
			$emailParams[ 'to' ] = $override;
		}
		return $emailParams;
	}

	/**
	 * @param array $updateResults
	 */
	public function sendNotificationEmail( $updateResults ) {
		if ( empty( $updateResults ) || !\is_array( $updateResults ) ) {
			return;
		}

		// Are there really updates?
		$reallyUpdates = false;

		$body = [
			sprintf(
				__( 'This is a quick notification from the %s that WordPress Automatic Updates just completed on your site with the following results.', 'wp-simple-firewall' ),
				self::con()->getHumanName()
			),
			''
		];

		$assetVersionTrack = $this->getTrackedAssetsVersions();

		$WPP = Services::WpPlugins();
		if ( !empty( $updateResults[ 'plugin' ] ) && \is_array( $updateResults[ 'plugin' ] ) ) {
			$hasPluginUpdates = false;
			$trackedPlugins = $assetVersionTrack[ 'plugins' ];

			$tempContent[] = __( 'Plugins Updated:', 'wp-simple-firewall' );
			foreach ( $updateResults[ 'plugin' ] as $update ) {
				$p = $WPP->getPluginAsVo( $update->item->plugin, true );
				$validUpdate = !empty( $update->result ) && !empty( $update->name )
							   && isset( $trackedPlugins[ $p->file ] )
							   && \version_compare( $trackedPlugins[ $p->file ], $p->Version, '<' );
				if ( $validUpdate ) {
					$tempContent[] = ' - '.sprintf(
							__( 'Plugin "%s" auto-updated from "%s" to version "%s"', 'wp-simple-firewall' ),
							$update->name, $trackedPlugins[ $p->file ], $p->Version );
					$hasPluginUpdates = true;
				}
			}
			$tempContent[] = '';

			if ( $hasPluginUpdates ) {
				$reallyUpdates = true;
				$body = \array_merge( $body, $tempContent );
			}
		}

		if ( !empty( $updateResults[ 'theme' ] ) && \is_array( $updateResults[ 'theme' ] ) ) {
			$hasThemesUpdates = false;
			$trackedThemes = $assetVersionTrack[ 'themes' ];

			$tempContent = [ __( 'Themes Updated:', 'wp-simple-firewall' ) ];
			foreach ( $updateResults[ 'theme' ] as $update ) {
				$oItem = $update->item;
				$validUpdate = isset( $update->result ) && $update->result && !empty( $update->name )
							   && isset( $trackedThemes[ $oItem->theme ] )
							   && \version_compare( $trackedThemes[ $oItem->theme ], $oItem->new_version, '<' );
				if ( $validUpdate ) {
					$tempContent[] = ' - '.sprintf(
							__( 'Theme "%s" auto-updated from "%s" to version "%s"', 'wp-simple-firewall' ),
							$update->name, $trackedThemes[ $oItem->theme ], $oItem->new_version );
					$hasThemesUpdates = true;
				}
			}
			$tempContent[] = '';

			if ( $hasThemesUpdates ) {
				$reallyUpdates = true;
				$body = \array_merge( $body, $tempContent );
			}
		}

		if ( !empty( $updateResults[ 'core' ] ) && \is_array( $updateResults[ 'core' ] ) ) {
			$hasCoreUpdates = false;
			$tempContent = [ __( 'WordPress Core Updated:', 'wp-simple-firewall' ) ];
			foreach ( $updateResults[ 'core' ] as $update ) {
				if ( isset( $update->result ) && !is_wp_error( $update->result ) ) {
					$tempContent[] = ' - '.sprintf( 'WordPress was automatically updated to "%s"', $update->name );
					$hasCoreUpdates = true;
				}
			}
			$tempContent[] = '';

			if ( $hasCoreUpdates ) {
				$reallyUpdates = true;
				$body = \array_merge( $body, $tempContent );
			}
		}

		if ( !$reallyUpdates ) {
			return;
		}

		$body[] = __( 'Thank you.', 'wp-simple-firewall' );

		( self::con()->email_con ? self::con()->email_con : $this->mod()->getEmailProcessor() )->sendEmailWithWrap(
			$this->opts()->getOpt( 'override_email_address' ),
			sprintf( __( "Notice: %s", 'wp-simple-firewall' ), __( "Automatic Updates Completed", 'wp-simple-firewall' ) ),
			$body
		);
		die();
	}

	private function getHookPriority() :int {
		return (int)$this->opts()->getDef( 'action_hook_priority' );
	}
}