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
		/** @var Options $oOpts */
		$oOpts = $this->getOptions();

		$nPriority = $this->getHookPriority();
		if ( Services::WpGeneral()->isClassicPress() ) {
			add_filter( 'allow_patch_auto_core_updates', [ $this, 'autoupdate_core_minor' ], $nPriority );
			add_filter( 'allow_minor_auto_core_updates', [ $this, 'autoupdate_core_major' ], $nPriority );
		}
		else {
			add_filter( 'allow_minor_auto_core_updates', [ $this, 'autoupdate_core_minor' ], $nPriority );
			add_filter( 'allow_major_auto_core_updates', [ $this, 'autoupdate_core_major' ], $nPriority );
		}

		add_filter( 'auto_update_plugin', [ $this, 'autoupdate_plugins' ], $nPriority, 2 );
		add_filter( 'auto_update_theme', [ $this, 'autoupdate_themes' ], $nPriority, 2 );
		add_filter( 'auto_update_core', [ $this, 'autoupdate_core' ], $nPriority, 2 );

		if ( !$oOpts->isDisableAllAutoUpdates() ) {
			//more parameter options here for later
			add_filter( 'auto_core_update_send_email', [ $this, 'autoupdate_send_email' ], $nPriority, 1 );
			add_filter( 'auto_core_update_email', [ $this, 'autoupdate_email_override' ], $nPriority, 1 );
			add_filter( 'auto_plugin_theme_update_email', [ $this, 'autoupdate_email_override' ], $nPriority, 1 );

			add_action( 'set_site_transient_update_core', [ $this, 'trackUpdateTimesCore' ] );
			add_action( 'set_site_transient_update_plugins', [ $this, 'trackUpdateTimesPlugins' ] );
			add_action( 'set_site_transient_update_themes', [ $this, 'trackUpdateTimesThemes' ] );

			if ( $oOpts->isSendAutoupdatesNotificationEmail()
				 && !Services::WpGeneral()->getWordpressIsAtLeastVersion( '5.5' ) ) {
				$this->trackAssetsVersions();
				add_action( 'automatic_updates_complete', [ $this, 'sendNotificationEmail' ] );
			}
		}
	}

	public function onWpLoaded() {
		/** @var Options $opts */
		$opts = $this->getOptions();
		if ( $opts->isDisableAllAutoUpdates() ) {
			$this->disableAllAutoUpdates();
		}
	}

	private function disableAllAutoUpdates() {
		remove_all_filters( 'automatic_updater_disabled' );
		add_filter( 'automatic_updater_disabled', '__return_true', PHP_INT_MAX );
		if ( !defined( 'WP_AUTO_UPDATE_CORE' ) ) {
			define( 'WP_AUTO_UPDATE_CORE', false );
		}
	}

	private function trackAssetsVersions() {
		$aAssVers = $this->getTrackedAssetsVersions();

		$WPP = Services::WpPlugins();
		foreach ( array_keys( $WPP->getUpdates() ) as $file ) {
			$aAssVers[ 'plugins' ][ $file ] = $WPP->getPluginAsVo( $file )->Version;
		}
		$WPT = Services::WpThemes();
		foreach ( array_keys( $WPT->getUpdates() ) as $file ) {
			$aAssVers[ 'themes' ][ $file ] = $WPT->getTheme( $file )->get( 'Version' );
		}
		$this->assetsVersions = $aAssVers;
	}

	protected function getTrackedAssetsVersions() :array {
		if ( empty( $this->assetsVersions ) || !is_array( $this->assetsVersions ) ) {
			$this->assetsVersions = [
				'plugins' => [],
				'themes'  => [],
			];
		}
		return $this->assetsVersions;
	}

	/**
	 * @param \stdClass $oUpdates
	 */
	public function trackUpdateTimesCore( $oUpdates ) {

		if ( !empty( $oUpdates ) && isset( $oUpdates->updates ) && is_array( $oUpdates->updates ) ) {
			/** @var Options $oOpts */
			$oOpts = $this->getOptions();

			$aTk = $oOpts->getDelayTracking();
			$aItemTk = isset( $aTk[ 'core' ][ 'wp' ] ) ? $aTk[ 'core' ][ 'wp' ] : [];
			foreach ( $oUpdates->updates as $oUpdate ) {
				if ( 'autoupdate' == $oUpdate->response ) {
					$sVersion = $oUpdate->current;
					if ( !isset( $aItemTk[ $sVersion ] ) ) {
						$aItemTk[ $sVersion ] = Services::Request()->ts();
					}
				}
			}
			$aTk[ 'core' ][ 'wp' ] = array_slice( $aItemTk, -5 );
			$oOpts->setDelayTracking( $aTk );
		}
	}

	/**
	 * @param \stdClass $oUpdates
	 */
	public function trackUpdateTimesPlugins( $oUpdates ) {
		$this->trackUpdateTimeCommon( $oUpdates, 'plugins' );
	}

	/**
	 * @param \stdClass $oUpdates
	 */
	public function trackUpdateTimesThemes( $oUpdates ) {
		$this->trackUpdateTimeCommon( $oUpdates, 'themes' );
	}

	/**
	 * @param \stdClass $oUpdates
	 * @param string    $sContext - plugins/themes
	 */
	protected function trackUpdateTimeCommon( $oUpdates, $sContext ) {
		/** @var Options $oOpts */
		$oOpts = $this->getOptions();

		if ( !empty( $oUpdates ) && isset( $oUpdates->response ) && is_array( $oUpdates->response ) ) {

			$aTk = $oOpts->getDelayTracking();
			foreach ( $oUpdates->response as $sSlug => $oUpdate ) {
				$aItemTk = isset( $aTk[ $sContext ][ $sSlug ] ) ? $aTk[ $sContext ][ $sSlug ] : [];
				if ( is_array( $oUpdate ) ) {
					$oUpdate = (object)$oUpdate;
				}

				$sNewVersion = isset( $oUpdate->new_version ) ? $oUpdate->new_version : '';
				if ( !empty( $sNewVersion ) ) {
					if ( !isset( $aItemTk[ $sNewVersion ] ) ) {
						$aItemTk[ $sNewVersion ] = Services::Request()->ts();
					}
					$aTk[ $sContext ][ $sSlug ] = array_slice( $aItemTk, -3 );
				}
			}
			$oOpts->setDelayTracking( $aTk );
		}
	}

	/**
	 * This is a filter method designed to say whether a major core WordPress upgrade should be permitted,
	 * based on the plugin settings.
	 * @param bool $bUpdate
	 * @return bool
	 */
	public function autoupdate_core_major( $bUpdate ) {
		/** @var Options $oOpts */
		$oOpts = $this->getOptions();

		if ( $oOpts->isDisableAllAutoUpdates() || $oOpts->isAutoUpdateCoreNever() ) {
			$bUpdate = false;
		}
		elseif ( !$oOpts->isDelayUpdates() ) { // delay handled elsewhere
			$bUpdate = $oOpts->isAutoUpdateCoreMajor();
		}

		return $bUpdate;
	}

	/**
	 * This is a filter method designed to say whether a minor core WordPress upgrade should be permitted,
	 * based on the plugin settings.
	 * @param bool $bUpdate
	 * @return bool
	 */
	public function autoupdate_core_minor( $bUpdate ) {
		/** @var Options $oOpts */
		$oOpts = $this->getOptions();

		if ( $oOpts->isDisableAllAutoUpdates() || $oOpts->isAutoUpdateCoreNever() ) {
			$bUpdate = false;
		}
		elseif ( !$oOpts->isDelayUpdates() ) {
			$bUpdate = !$oOpts->isAutoUpdateCoreNever();
		}
		return $bUpdate;
	}

	/**
	 * @param bool      $bDoAutoUpdate
	 * @param \stdClass $oCoreUpdate
	 * @return bool
	 */
	public function autoupdate_core( $bDoAutoUpdate, $oCoreUpdate ) {
		/** @var Options $oOpts */
		$oOpts = $this->getOptions();

		if ( $oOpts->isDisableAllAutoUpdates() ) {
			$bDoAutoUpdate = false;
		}
		elseif ( $this->isDelayed( $oCoreUpdate, 'core' ) ) {
			$bDoAutoUpdate = false;
		}

		return $bDoAutoUpdate;
	}

	/**
	 * @param bool             $bDoAutoUpdate
	 * @param \stdClass|string $mItem
	 * @return bool
	 */
	public function autoupdate_plugins( $bDoAutoUpdate, $mItem ) {
		/** @var Options $oOpts */
		$oOpts = $this->getOptions();

		if ( $oOpts->isDisableAllAutoUpdates() ) {
			$bDoAutoUpdate = false;
		}
		else {
			$file = Services::WpGeneral()->getFileFromAutomaticUpdateItem( $mItem );

			if ( $this->isDelayed( $file, 'plugins' ) ) {
				$bDoAutoUpdate = false;
			}
			elseif ( $oOpts->isAutoupdateAllPlugins() ) {
				$bDoAutoUpdate = true;
			}
			elseif ( $file === $this->getCon()->base_file ) {
				$sAuto = $oOpts->getSelfAutoUpdateOpt();
				if ( $sAuto === 'immediate' ) {
					$bDoAutoUpdate = true;
				}
				elseif ( $sAuto === 'disabled' ) {
					$bDoAutoUpdate = false;
				}
			}
		}

		return $bDoAutoUpdate;
	}

	/**
	 * @param bool             $bDoAutoUpdate
	 * @param \stdClass|string $mItem
	 * @return bool
	 */
	public function autoupdate_themes( $bDoAutoUpdate, $mItem ) {
		/** @var Options $opts */
		$opts = $this->getOptions();

		if ( $opts->isDisableAllAutoUpdates() ) {
			$bDoAutoUpdate = false;
		}
		else {
			$file = Services::WpGeneral()->getFileFromAutomaticUpdateItem( $mItem, 'theme' );

			if ( $this->isDelayed( $file, 'themes' ) ) {
				$bDoAutoUpdate = false;
			}
			elseif ( $opts->isOpt( 'enable_autoupdate_themes', 'Y' ) ) {
				$bDoAutoUpdate = true;
			}
		}

		return $bDoAutoUpdate;
	}

	/**
	 * @param string|\stdClass $sSlug
	 * @param string           $sContext
	 * @return bool
	 */
	private function isDelayed( $sSlug, $sContext = 'plugins' ) {
		/** @var Options $oOpts */
		$oOpts = $this->getOptions();

		$bDelayed = false;

		if ( $oOpts->isDelayUpdates() ) {

			$aTk = $oOpts->getDelayTracking();

			$sVersion = '';
			if ( $sContext == 'core' ) {
				$sVersion = $sSlug->current; // \stdClass from transient update_core
				$sSlug = 'wp';
			}

			$aItemTk = isset( $aTk[ $sContext ][ $sSlug ] ) ? $aTk[ $sContext ][ $sSlug ] : [];

			if ( $sContext == 'plugins' ) {
				$oPlugin = Services::WpPlugins()->getUpdateInfo( $sSlug );
				$sVersion = isset( $oPlugin->new_version ) ? $oPlugin->new_version : '';
			}
			elseif ( $sContext == 'themes' ) {
				$aThemeInfo = Services::WpThemes()->getUpdateInfo( $sSlug );
				$sVersion = isset( $aThemeInfo[ 'new_version' ] ) ? $aThemeInfo[ 'new_version' ] : '';
			}

			if ( !empty( $sVersion ) && isset( $aItemTk[ $sVersion ] ) ) {
				$bDelayed = ( Services::Request()->ts() - $aItemTk[ $sVersion ] < $oOpts->getDelayUpdatesPeriod() );
			}
		}

		return $bDelayed;
	}

	/**
	 * A filter on whether or not a notification email is sent after core upgrades are attempted.
	 * @param bool $bSendEmail
	 * @return bool
	 */
	public function autoupdate_send_email( $bSendEmail ) {
		/** @var Options $opts */
		$opts = $this->getOptions();
		return $opts->isSendAutoupdatesNotificationEmail();
	}

	/**
	 * A filter on the target email address to which to send upgrade notification emails.
	 * @param array $aEmailParams
	 * @return array
	 */
	public function autoupdate_email_override( $aEmailParams ) {
		$sOverride = $this->getOptions()->getOpt( 'override_email_address', '' );
		if ( Services::Data()->validEmail( $sOverride ) ) {
			$aEmailParams[ 'to' ] = $sOverride;
		}
		return $aEmailParams;
	}

	/**
	 * @param array $aUpdateResults
	 */
	public function sendNotificationEmail( $aUpdateResults ) {
		if ( empty( $aUpdateResults ) || !is_array( $aUpdateResults ) ) {
			return;
		}

		// Are there really updates?
		$bReallyUpdates = false;

		$body = [
			sprintf(
				__( 'This is a quick notification from the %s that WordPress Automatic Updates just completed on your site with the following results.', 'wp-simple-firewall' ),
				$this->getCon()->getHumanName()
			),
			''
		];

		$aTrkd = $this->getTrackedAssetsVersions();

		$oWpPlugins = Services::WpPlugins();
		if ( !empty( $aUpdateResults[ 'plugin' ] ) && is_array( $aUpdateResults[ 'plugin' ] ) ) {
			$bHasPluginUpdates = false;
			$aTrkdPlugs = $aTrkd[ 'plugins' ];

			$aTempContent[] = __( 'Plugins Updated:', 'wp-simple-firewall' );
			foreach ( $aUpdateResults[ 'plugin' ] as $oUpdate ) {
				$oP = $oWpPlugins->getPluginAsVo( $oUpdate->item->plugin, true );
				$bValidUpdate = !empty( $oUpdate->result ) && !empty( $oUpdate->name )
								&& isset( $aTrkdPlugs[ $oP->file ] )
								&& version_compare( $aTrkdPlugs[ $oP->file ], $oP->Version, '<' );
				if ( $bValidUpdate ) {
					$aTempContent[] = ' - '.sprintf(
							__( 'Plugin "%s" auto-updated from "%s" to version "%s"', 'wp-simple-firewall' ),
							$oUpdate->name, $aTrkdPlugs[ $oP->file ], $oP->Version );
					$bHasPluginUpdates = true;
				}
			}
			$aTempContent[] = '';

			if ( $bHasPluginUpdates ) {
				$bReallyUpdates = true;
				$body = array_merge( $body, $aTempContent );
			}
		}

		if ( !empty( $aUpdateResults[ 'theme' ] ) && is_array( $aUpdateResults[ 'theme' ] ) ) {
			$bHasThemesUpdates = false;
			$aTrkdThemes = $aTrkd[ 'themes' ];

			$aTempContent = [ __( 'Themes Updated:', 'wp-simple-firewall' ) ];
			foreach ( $aUpdateResults[ 'theme' ] as $oUpdate ) {
				$oItem = $oUpdate->item;
				$bValidUpdate = isset( $oUpdate->result ) && $oUpdate->result && !empty( $oUpdate->name )
								&& isset( $aTrkdThemes[ $oItem->theme ] )
								&& version_compare( $aTrkdThemes[ $oItem->theme ], $oItem->new_version, '<' );
				if ( $bValidUpdate ) {
					$aTempContent[] = ' - '.sprintf(
							__( 'Theme "%s" auto-updated from "%s" to version "%s"', 'wp-simple-firewall' ),
							$oUpdate->name, $aTrkdThemes[ $oItem->theme ], $oItem->new_version );
					$bHasThemesUpdates = true;
				}
			}
			$aTempContent[] = '';

			if ( $bHasThemesUpdates ) {
				$bReallyUpdates = true;
				$body = array_merge( $body, $aTempContent );
			}
		}

		if ( !empty( $aUpdateResults[ 'core' ] ) && is_array( $aUpdateResults[ 'core' ] ) ) {
			$bHasCoreUpdates = false;
			$aTempContent = [ __( 'WordPress Core Updated:', 'wp-simple-firewall' ) ];
			foreach ( $aUpdateResults[ 'core' ] as $oUpdate ) {
				if ( isset( $oUpdate->result ) && !is_wp_error( $oUpdate->result ) ) {
					$aTempContent[] = ' - '.sprintf( 'WordPress was automatically updated to "%s"', $oUpdate->name );
					$bHasCoreUpdates = true;
				}
			}
			$aTempContent[] = '';

			if ( $bHasCoreUpdates ) {
				$bReallyUpdates = true;
				$body = array_merge( $body, $aTempContent );
			}
		}

		if ( !$bReallyUpdates ) {
			return;
		}

		$body[] = __( 'Thank you.', 'wp-simple-firewall' );

		$this->getMod()
			 ->getEmailProcessor()
			 ->sendEmailWithWrap(
			 	$this->getOptions()->getOpt( 'override_email_address' ),
				 sprintf( __( "Notice: %s", 'wp-simple-firewall' ), __( "Automatic Updates Completed", 'wp-simple-firewall' ) ),
				$body
			 );
		die();
	}

	private function getHookPriority() :int {
		return (int)$this->getOptions()->getDef( 'action_hook_priority' );
	}
}