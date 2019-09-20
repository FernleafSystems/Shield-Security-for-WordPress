<?php

use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_Processor_Autoupdates extends Modules\BaseShield\ShieldProcessor {

	/**
	 * @var bool
	 */
	protected $bDoForceRunAutoupdates = false;

	/**
	 * @var array
	 */
	private $aAssetsVersions = [];

	/**
	 * @param boolean $bDoForceRun
	 */
	public function setForceRunAutoupdates( $bDoForceRun ) {
		$this->bDoForceRunAutoupdates = $bDoForceRun;
	}

	/**
	 * @return boolean
	 */
	public function getIfForceRunAutoupdates() {
		return apply_filters( $this->getMod()->prefix( 'force_autoupdate' ), $this->bDoForceRunAutoupdates );
	}

	/**
	 * The allow_* core filters are run first in a "should_update" query. Then comes the "auto_update_core"
	 * filter. What this filter decides will ultimately determine the fate of any core upgrade.
	 */
	public function run() {
		/** @var Modules\Autoupdates\Options $oOpts */
		$oOpts = $this->getOptions();

		$nFilterPriority = $this->getHookPriority();
		add_filter( 'allow_minor_auto_core_updates', [ $this, 'autoupdate_core_minor' ], $nFilterPriority );
		add_filter( 'allow_major_auto_core_updates', [ $this, 'autoupdate_core_major' ], $nFilterPriority );

		add_filter( 'auto_update_translation', [ $this, 'autoupdate_translations' ], $nFilterPriority, 1 );
		add_filter( 'auto_update_plugin', [ $this, 'autoupdate_plugins' ], $nFilterPriority, 2 );
		add_filter( 'auto_update_theme', [ $this, 'autoupdate_themes' ], $nFilterPriority, 2 );
		add_filter( 'auto_update_core', [ $this, 'autoupdate_core' ], $nFilterPriority, 2 );

		if ( $oOpts->isOpt( 'enable_autoupdate_ignore_vcs', 'Y' ) ) {
			add_filter( 'automatic_updates_is_vcs_checkout', '__return_false', $nFilterPriority );
		}

		if ( !$oOpts->isDisableAllAutoUpdates() ) {
			//more parameter options here for later
			add_filter( 'auto_core_update_send_email', [ $this, 'autoupdate_send_email' ], $nFilterPriority, 1 );
			add_filter( 'auto_core_update_email', [ $this, 'autoupdate_email_override' ], $nFilterPriority, 1 );

			add_action( 'set_site_transient_update_core', [ $this, 'trackUpdateTimesCore' ] );
			add_action( 'set_site_transient_update_plugins', [ $this, 'trackUpdateTimesPlugins' ] );
			add_action( 'set_site_transient_update_themes', [ $this, 'trackUpdateTimesThemes' ] );

			if ( $oOpts->isSendAutoupdatesNotificationEmail() ) {
				$this->trackAssetsVersions();
				add_action( 'automatic_updates_complete', [ $this, 'sendNotificationEmail' ] );
			}

			if ( $oOpts->isAutoupdateIndividualPlugins() ) {
				// Adds automatic update indicator column to all plugins in plugin listing.
				add_filter( 'manage_plugins_columns', [ $this, 'fAddPluginsListAutoUpdateColumn' ] );
			}
		}
	}

	public function onWpLoaded() {
		/** @var Modules\Autoupdates\Options $oOpts */
		$oOpts = $this->getOptions();
		if ( $oOpts->isDisableAllAutoUpdates() ) {
			$this->disableAllAutoUpdates();
		}
		else {
			$this->forceRunAutoUpdates();
		}
	}

	private function disableAllAutoUpdates() {
		remove_all_filters( 'automatic_updater_disabled' );
		add_filter( 'automatic_updater_disabled', '__return_true', PHP_INT_MAX );
		if ( !defined( 'WP_AUTO_UPDATE_CORE' ) ) {
			define( 'WP_AUTO_UPDATE_CORE', false );
		}
	}

	/**
	 * This is hooked right after the autoupdater lock is saved.
	 */
	private function trackAssetsVersions() {
		$aAssVers = $this->getTrackedAssetsVersions();

		$oWpPlugins = Services::WpPlugins();
		foreach ( array_keys( $oWpPlugins->getUpdates() ) as $sFile ) {
			$aAssVers[ 'plugins' ][ $sFile ] = $oWpPlugins->getPluginAsVo( $sFile )->Version;
		}
		$oWpThemes = Services::WpThemes();
		foreach ( array_keys( $oWpThemes->getUpdates() ) as $sFile ) {
			$aAssVers[ 'themes' ][ $sFile ] = $oWpThemes->getTheme( $sFile )->get( 'Version' );
		}
		$this->aAssetsVersions = $aAssVers;
	}

	/**
	 * @return array
	 */
	protected function getTrackedAssetsVersions() {
		if ( empty( $this->aAssetsVersions ) || !is_array( $this->aAssetsVersions ) ) {
			$this->aAssetsVersions = [
				'plugins' => [],
				'themes'  => [],
			];
		}
		return $this->aAssetsVersions;
	}

	/**
	 * @param stdClass $oUpdates
	 */
	public function trackUpdateTimesCore( $oUpdates ) {

		if ( !empty( $oUpdates ) && isset( $oUpdates->updates ) && is_array( $oUpdates->updates ) ) {
			/** @var Modules\Autoupdates\Options $oOpts */
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
	 * @param stdClass $oUpdates
	 */
	public function trackUpdateTimesPlugins( $oUpdates ) {
		$this->trackUpdateTimeCommon( $oUpdates, 'plugins' );
	}

	/**
	 * @param stdClass $oUpdates
	 */
	public function trackUpdateTimesThemes( $oUpdates ) {
		$this->trackUpdateTimeCommon( $oUpdates, 'themes' );
	}

	/**
	 * @param stdClass $oUpdates
	 * @param string   $sContext - plugins/themes
	 */
	protected function trackUpdateTimeCommon( $oUpdates, $sContext ) {
		/** @var Modules\Autoupdates\Options $oOpts */
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
	 * Will force-run the WordPress automatic updates process and then redirect to the updates screen.
	 */
	private function forceRunAutoUpdates() {
		if ( $this->getIfForceRunAutoupdates() ) {
			Services::WpGeneral()->doForceRunAutomaticUpdates();
		}
	}

	/**
	 * This is a filter method designed to say whether a major core WordPress upgrade should be permitted,
	 * based on the plugin settings.
	 * @param boolean $bUpdate
	 * @return boolean
	 */
	public function autoupdate_core_major( $bUpdate ) {
		/** @var Modules\Autoupdates\Options $oOpts */
		$oOpts = $this->getOptions();

		if ( $oOpts->isDisableAllAutoUpdates() ) {
			$bUpdate = false;
		}
		else if ( !$oOpts->isDelayUpdates() ) { // the delay is handles elsewhere
			$bUpdate = $oOpts->isAutoUpdateCoreMajor();
		}

		return $bUpdate;
	}

	/**
	 * This is a filter method designed to say whether a minor core WordPress upgrade should be permitted,
	 * based on the plugin settings.
	 * @param boolean $bUpdate
	 * @return boolean
	 */
	public function autoupdate_core_minor( $bUpdate ) {
		/** @var Modules\Autoupdates\Options $oOpts */
		$oOpts = $this->getOptions();

		if ( $oOpts->isDisableAllAutoUpdates() ) {
			$bUpdate = false;
		}
		else if ( !$oOpts->isDelayUpdates() ) {//TODO delay
			$bUpdate = $oOpts->isAutoUpdateCoreMinor();
		}
		return $bUpdate;
	}

	/**
	 * This is a filter method designed to say whether a WordPress translations upgrades should be permitted,
	 * based on the plugin settings.
	 * @param boolean $bUpdate
	 * @return boolean
	 */
	public function autoupdate_translations( $bUpdate ) {
		return $this->getOptions()->isOpt( 'enable_autoupdate_translations', 'Y' );
	}

	/**
	 * @param bool     $bDoAutoUpdate
	 * @param stdClass $oCoreUpdate
	 * @return bool
	 */
	public function autoupdate_core( $bDoAutoUpdate, $oCoreUpdate ) {
		/** @var Modules\Autoupdates\Options $oOpts */
		$oOpts = $this->getOptions();

		if ( $oOpts->isDisableAllAutoUpdates() ) {
			$bDoAutoUpdate = false;
		}
		else if ( $this->isDelayed( $oCoreUpdate, 'core' ) ) {
			$bDoAutoUpdate = false;
		}

		return $bDoAutoUpdate;
	}

	/**
	 * @param bool            $bDoAutoUpdate
	 * @param StdClass|string $mItem
	 * @return boolean
	 */
	public function autoupdate_plugins( $bDoAutoUpdate, $mItem ) {
		/** @var Modules\Autoupdates\Options $oOpts */
		$oOpts = $this->getOptions();

		if ( $oOpts->isDisableAllAutoUpdates() ) {
			$bDoAutoUpdate = false;
		}
		else {
			$sFile = Services::WpGeneral()->getFileFromAutomaticUpdateItem( $mItem );

			if ( $this->isDelayed( $sFile, 'plugins' ) ) {
				return false;
			}

			// first, is global auto updates for plugins set
			if ( $oOpts->isAutoupdateAllPlugins() ) {
				$bDoAutoUpdate = true;
			}
			else if ( $oOpts->isPluginSetToAutoupdate( $sFile ) ) {
				$bDoAutoUpdate = true;
			}
			else if ( $sFile === $this->getCon()->getPluginBaseFile() ) {
				$sAuto = $oOpts->getSelfAutoUpdateOpt();
				if ( $sAuto === 'immediate' ) {
					$bDoAutoUpdate = true;
				}
				else if ( $sAuto === 'disabled' ) {
					$bDoAutoUpdate = false;
				}
			}
		}

		return $bDoAutoUpdate;
	}

	/**
	 * @param bool            $bDoAutoUpdate
	 * @param stdClass|string $mItem
	 * @return boolean
	 */
	public function autoupdate_themes( $bDoAutoUpdate, $mItem ) {
		/** @var Modules\Autoupdates\Options $oOpts */
		$oOpts = $this->getOptions();

		if ( $oOpts->isDisableAllAutoUpdates() ) {
			$bDoAutoUpdate = false;
		}
		else {
			$sFile = Services::WpGeneral()->getFileFromAutomaticUpdateItem( $mItem, 'theme' );

			if ( $this->isDelayed( $sFile, 'themes' ) ) {
				return false;
			}

			// first, is global auto updates for themes set
			if ( $this->getMod()->isOpt( 'enable_autoupdate_themes', 'Y' ) ) {
				return true;
			}

			$aAutoUpdates = apply_filters( 'icwp_wpsf_autoupdate_themes', [] );
			if ( !empty( $aAutoUpdates ) && is_array( $aAutoUpdates ) && in_array( $sFile, $aAutoUpdates ) ) {
				$bDoAutoUpdate = true;
			}
		}
		return $bDoAutoUpdate;
	}

	/**
	 * @param string|stdClass $sSlug
	 * @param string          $sContext
	 * @return bool
	 */
	private function isDelayed( $sSlug, $sContext = 'plugins' ) {
		/** @var Modules\Autoupdates\Options $oOpts */
		$oOpts = $this->getOptions();

		$bDelayed = false;

		/** @var \ICWP_WPSF_FeatureHandler_Autoupdates $oFO */
		$oFO = $this->getMod();
		if ( $oOpts->isDelayUpdates() ) {

			$aTk = $oOpts->getDelayTracking();

			$sVersion = '';
			if ( $sContext == 'core' ) {
				$sVersion = $sSlug->current; // stdClass from transient update_core
				$sSlug = 'wp';
			}

			$aItemTk = isset( $aTk[ $sContext ][ $sSlug ] ) ? $aTk[ $sContext ][ $sSlug ] : [];

			if ( $sContext == 'plugins' ) {
				$oPlugin = Services::WpPlugins()->getUpdateInfo( $sSlug );
				$sVersion = isset( $oPlugin->new_version ) ? $oPlugin->new_version : '';
			}
			else if ( $sContext == 'themes' ) {
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
	 * A filter on whether or not a notification email is send after core upgrades are attempted.
	 * @param boolean $bSendEmail
	 * @return boolean
	 */
	public function autoupdate_send_email( $bSendEmail ) {
		/** @var Modules\Autoupdates\Options $oOpts */
		$oOpts = $this->getOptions();
		return $oOpts->isSendAutoupdatesNotificationEmail();
	}

	/**
	 * A filter on the target email address to which to send upgrade notification emails.
	 * @param array $aEmailParams
	 * @return array
	 */
	public function autoupdate_email_override( $aEmailParams ) {
		$sOverride = $this->getOption( 'override_email_address', '' );
		if ( Services::Data()->validEmail( $sOverride ) ) {
			$aEmailParams[ 'to' ] = $sOverride;
		}
		return $aEmailParams;
	}

	/**
	 * @filter
	 * @param array  $aPluginMeta
	 * @param string $sPluginBaseFileName
	 * @return array
	 */
	public function fAddAutomaticUpdatePluginMeta( $aPluginMeta, $sPluginBaseFileName ) {

		// first we prevent collision between iControlWP <-> Simple Firewall by not duplicating icons
		foreach ( $aPluginMeta as $sMetaItem ) {
			if ( strpos( $sMetaItem, 'icwp-pluginautoupdateicon' ) !== false ) {
				return $aPluginMeta;
			}
		}
		$bUpdate = Services::WpPlugins()->isPluginAutomaticallyUpdated( $sPluginBaseFileName );
		$sHtml = $this->getPluginAutoupdateIconHtml( $bUpdate );
		array_unshift( $aPluginMeta, sprintf( '%s', $sHtml ) );
		return $aPluginMeta;
	}

	/**
	 * Adds the column to the plugins listing table to indicate whether WordPress will automatically update the plugins
	 * @param array $aColumns
	 * @return array
	 */
	public function fAddPluginsListAutoUpdateColumn( $aColumns ) {
		if ( $this->getCon()->isPluginAdmin() && !isset( $aColumns[ 'icwp_autoupdate' ] ) ) {
			$aColumns[ 'icwp_autoupdate' ] = 'Auto Update';
			add_action( 'manage_plugins_custom_column',
				[ $this, 'aPrintPluginsListAutoUpdateColumnContent' ],
				100, 2
			);
		}
		return $aColumns;
	}

	/**
	 * @param string $sColumnName
	 * @param string $sPluginBaseFileName
	 */
	public function aPrintPluginsListAutoUpdateColumnContent( $sColumnName, $sPluginBaseFileName ) {
		if ( $sColumnName != 'icwp_autoupdate' ) {
			return;
		}
		/** @var Modules\Autoupdates\Options $oOpts */
		$oOpts = $this->getOptions();

		$bUpdate = Services::WpPlugins()->isPluginAutomaticallyUpdated( $sPluginBaseFileName );
//		$bUpdate = in_array( $sPluginBaseFileName, $oOpts->getAutoupdatePlugins() );
		$bDisabled = $bUpdate && !in_array( $sPluginBaseFileName, $oOpts->getAutoupdatePlugins() );
		echo $this->getPluginAutoupdateIconHtml( $sPluginBaseFileName, $bUpdate, $bDisabled );
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

		$aEmailContent = [
			sprintf(
				__( 'This is a quick notification from the %s that WordPress Automatic Updates just completed on your site with the following results.', 'wp-simple-firewall' ),
				$this->getCon()->getHumanName()
			),
			''
		];

		$aTrkd = $this->getTrackedAssetsVersions();

		if ( !empty( $aUpdateResults[ 'plugin' ] ) && is_array( $aUpdateResults[ 'plugin' ] ) ) {
			$bHasPluginUpdates = false;
			$aTrkdPlugs = $aTrkd[ 'plugins' ];

			$aTempContent[] = __( 'Plugins Updated:', 'wp-simple-firewall' );
			foreach ( $aUpdateResults[ 'plugin' ] as $oUpdate ) {
				$oItem = $oUpdate->item;
				$bValidUpdate = isset( $oUpdate->result ) && $oUpdate->result && !empty( $oUpdate->name )
								&& isset( $aTrkdPlugs[ $oItem->plugin ] )
								&& version_compare( $aTrkdPlugs[ $oItem->plugin ], $oUpdate->item->new_version, '<' );
				if ( $bValidUpdate ) {
					$aTempContent[] = ' - '.sprintf(
							__( 'Plugin "%s" auto-updated from "%s" to version "%s"', 'wp-simple-firewall' ),
							$oUpdate->name, $aTrkdPlugs[ $oItem->plugin ], $oUpdate->item->new_version );
					$bHasPluginUpdates = true;
				}
			}
			$aTempContent[] = '';

			if ( $bHasPluginUpdates ) {
				$bReallyUpdates = true;
				$aEmailContent = array_merge( $aEmailContent, $aTempContent );
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
				$aEmailContent = array_merge( $aEmailContent, $aTempContent );
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
				$aEmailContent = array_merge( $aEmailContent, $aTempContent );
			}
		}

		if ( !$bReallyUpdates ) {
			return;
		}

		$aEmailContent[] = __( 'Thank you.', 'wp-simple-firewall' );

		$sTitle = sprintf( __( "Notice: %s", 'wp-simple-firewall' ), __( "Automatic Updates Completed", 'wp-simple-firewall' ) );
		$this->getEmailProcessor()
			 ->sendEmailWithWrap( $this->getOption( 'override_email_address' ), $sTitle, $aEmailContent );
		die();
	}

	/**
	 * @param string $sPluginBaseFileName
	 * @param bool   $bIsAutoupdate
	 * @param bool   $bDisabled
	 * @return string
	 */
	protected function getPluginAutoupdateIconHtml( $sPluginBaseFileName, $bIsAutoupdate, $bDisabled ) {
		return sprintf( '<label class="icwp-toggle-switch %s">
				<input type="checkbox"
				class="icwp-autoupdate-plugin"
				data-pluginfile="%s"
				data-disabled="%s"
				%s />
				<span class="slider"></span></label>',
			$bDisabled ? 'disabled' : '',
			$sPluginBaseFileName,
			$bDisabled ? __( 'Automatic updates for this plugin is controlled by another plugin or setting.', 'wp-simple-firewall' ) : 'no',
			$bIsAutoupdate ? 'checked="checked"' : ''
		);
	}

	/**
	 * Removes all filters that have been added from auto-update related WordPress filters
	 */
	protected function removeAllAutoupdateFilters() {
		$aFilters = [
			'allow_minor_auto_core_updates',
			'allow_major_auto_core_updates',
			'auto_update_translation',
			'auto_update_plugin',
			'auto_update_theme',
			'automatic_updates_is_vcs_checkout',
			'automatic_updater_disabled'
		];
		foreach ( $aFilters as $sFilter ) {
			remove_all_filters( $sFilter );
		}
	}

	/**
	 * @return int
	 */
	protected function getHookPriority() {
		return $this->getOptions()->getDef( 'action_hook_priority' );
	}
}