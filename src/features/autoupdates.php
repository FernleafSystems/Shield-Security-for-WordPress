<?php

use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_FeatureHandler_Autoupdates extends ICWP_WPSF_FeatureHandler_BaseWpsf {

	/**
	 */
	protected function setupCustomHooks() {
		// Force run automatic updates
		if ( Services::Request()->query( 'force_run_auto_updates' ) == 'now' ) {
			add_filter( $this->prefix( 'force_autoupdate' ), '__return_true' );
		}
	}

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
		$aTracking = $this->loadDP()->mergeArraysRecursive(
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
	 * @param array $aTrackingInfo
	 * @return $this
	 */
	public function setDelayTracking( $aTrackingInfo ) {
		return $this->setOpt( 'delay_tracking', $aTrackingInfo );
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
	public function isAutoupdateAllPlugins() {
		return $this->isOpt( 'enable_autoupdate_plugins', 'Y' );
	}

	/**
	 * @premium
	 * @return bool
	 */
	public function isAutoupdateIndividualPlugins() {
		return $this->isOpt( 'enable_individual_autoupdate_plugins', 'Y' );
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
	 * @param array $aAjaxResponse
	 * @return array
	 */
	public function handleAuthAjax( $aAjaxResponse ) {

		if ( empty( $aAjaxResponse ) ) {
			switch ( Services::Request()->request( 'exec' ) ) {

				case 'toggle_plugin_autoupdate':
					$aAjaxResponse = $this->ajaxExec_TogglePluginAutoupdate();
					break;

				default:
					break;
			}
		}
		return parent::handleAuthAjax( $aAjaxResponse );
	}

	/**
	 * @return array
	 */
	public function ajaxExec_TogglePluginAutoupdate() {
		$bSuccess = false;
		$sMessage = __( 'You do not have permissions to perform this action.', 'wp-simple-firewall' );

		if ( $this->isAutoupdateIndividualPlugins() && $this->getCon()->isPluginAdmin() ) {
			$oWpPlugins = Services::WpPlugins();
			$sFile = Services::Request()->post( 'pluginfile' );
			if ( $oWpPlugins->isInstalled( $sFile ) ) {
				$this->setPluginToAutoUpdate( $sFile );

				$sMessage = sprintf( __( 'Plugin "%s" will %s.', 'wp-simple-firewall' ),
					$oWpPlugins->getPluginAsVo( $sFile )->Name,
					Services::WpPlugins()->isPluginAutomaticallyUpdated( $sFile ) ?
						__( 'update automatically', 'wp-simple-firewall' )
						: __( 'not update automatically', 'wp-simple-firewall' )
				);
				$bSuccess = true;
			}
			else {
				$sMessage = __( 'Failed to change the update status of the plugin.', 'wp-simple-firewall' );
			}
		}

		return [
			'success' => $bSuccess,
			'message' => $sMessage,
		];
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
	public function isAutoUpdateCoreMinor() {
		return !$this->isOpt( 'autoupdate_core', 'core_never' );
	}

	/**
	 * @return bool
	 */
	public function isAutoUpdateCoreMajor() {
		return $this->isOpt( 'autoupdate_core', 'core_major' );
	}

	/**
	 * @param string $sPluginFile
	 * @return $this
	 */
	protected function setPluginToAutoUpdate( $sPluginFile ) {
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

	/**
	 * @param array $aAllNotices
	 * @return array
	 */
	public function addInsightsNoticeData( $aAllNotices ) {
		$aNotices = [
			'title'    => __( 'Automatic Updates', 'wp-simple-firewall' ),
			'messages' => []
		];
		{ //really disabled?
			$oWp = Services::WpGeneral();
			if ( $this->isModOptEnabled() ) {
				if ( $this->isDisableAllAutoUpdates() && !$oWp->getWpAutomaticUpdater()->is_disabled() ) {
					$aNotices[ 'messages' ][ 'disabled_auto' ] = [
						'title'   => 'Auto Updates Not Really Disabled',
						'message' => __( 'Automatic Updates Are Not Disabled As Expected.', 'wp-simple-firewall' ),
						'href'    => $this->getUrl_DirectLinkToOption( 'enable_autoupdate_disable_all' ),
						'action'  => sprintf( 'Go To %s', __( 'Options', 'wp-simple-firewall' ) ),
						'rec'     => sprintf( __( 'A plugin/theme other than %s is affecting your automatic update settings.', 'wp-simple-firewall' ), $this->getCon()
																																							->getHumanName() )
					];
				}
			}
		}

		$aNotices[ 'count' ] = count( $aNotices[ 'messages' ] );

		$aAllNotices[ 'autoupdates' ] = $aNotices;
		return $aAllNotices;
	}

	/**
	 * @param array $aAllData
	 * @return array
	 */
	public function addInsightsConfigData( $aAllData ) {
		$aThis = [
			'strings'      => [
				'title' => __( 'Automatic Updates', 'wp-simple-firewall' ),
				'sub'   => __( 'Control WordPress Automatic Updates', 'wp-simple-firewall' ),
			],
			'key_opts'     => [],
			'href_options' => $this->getUrl_AdminPage()
		];

		if ( !$this->isModOptEnabled() ) {
			$aThis[ 'key_opts' ][ 'mod' ] = $this->getModDisabledInsight();
		}
		else {

			$bAllDisabled = $this->isDisableAllAutoUpdates();
			if ( $bAllDisabled ) {
				$aThis[ 'key_opts' ][ 'disabled' ] = [
					'name'    => __( 'Disabled All', 'wp-simple-firewall' ),
					'enabled' => !$bAllDisabled,
					'summary' => $bAllDisabled ?
						__( 'All automatic updates on this site are disabled', 'wp-simple-firewall' )
						: __( 'The automatic updates system is enabled', 'wp-simple-firewall' ),
					'weight'  => 2,
					'href'    => $this->getUrl_DirectLinkToOption( 'enable_autoupdate_disable_all' ),
				];
			}
			else {
				$oWp = $this->loadWp();
				$bCanCore = $oWp->canCoreUpdateAutomatically();
				$aThis[ 'key_opts' ][ 'core_minor' ] = [
					'name'    => __( 'Core Updates', 'wp-simple-firewall' ),
					'enabled' => $bCanCore,
					'summary' => $bCanCore ?
						__( 'Minor WP Core updates will be installed automatically', 'wp-simple-firewall' )
						: __( 'Minor WP Core updates will not be installed automatically', 'wp-simple-firewall' ),
					'weight'  => 2,
					'href'    => $this->getUrl_DirectLinkToOption( 'autoupdate_core' ),
				];

				$bHasDelay = $this->isModOptEnabled() && $this->getDelayUpdatesPeriod();
				$aThis[ 'key_opts' ][ 'delay' ] = [
					'name'    => __( 'Update Delay', 'wp-simple-firewall' ),
					'enabled' => $bHasDelay,
					'summary' => $bHasDelay ?
						__( 'Automatic updates are applied after a short delay', 'wp-simple-firewall' )
						: __( 'Automatic updates are applied immediately', 'wp-simple-firewall' ),
					'weight'  => 1,
					'href'    => $this->getUrl_DirectLinkToOption( 'update_delay' ),
				];

				$sName = $this->getCon()->getHumanName();
				$bSelfAuto = $this->isModOptEnabled()
							 && in_array( $this->getSelfAutoUpdateOpt(), [ 'auto', 'immediate' ] );
				$aThis[ 'key_opts' ][ 'self' ] = [
					'name'    => __( 'Self Auto-Update', 'wp-simple-firewall' ),
					'enabled' => $bSelfAuto,
					'summary' => $bSelfAuto ?
						sprintf( __( '%s is automatically updated', 'wp-simple-firewall' ), $sName )
						: sprintf( __( "%s isn't automatically updated", 'wp-simple-firewall' ), $sName ),
					'weight'  => 1,
					'href'    => $this->getUrl_DirectLinkToOption( 'autoupdate_plugin_self' ),
				];
			}
		}

		$aAllData[ $this->getSlug() ] = $aThis;
		return $aAllData;
	}

	/**
	 * @param array $aOptionsParams
	 * @return array
	 * @throws \Exception
	 */
	protected function loadStrings_SectionTitles( $aOptionsParams ) {

		$sSectionSlug = $aOptionsParams[ 'slug' ];
		$sPlugName = $this->getCon()->getHumanName();
		switch ( $sSectionSlug ) {

			case 'section_enable_plugin_feature_automatic_updates_control' :
				$sTitleShort = sprintf( '%s/%s', __( 'On', 'wp-simple-firewall' ), __( 'Off', 'wp-simple-firewall' ) );
				$sTitle = sprintf( __( 'Enable Module: %s', 'wp-simple-firewall' ), $this->getMainFeatureName() );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Automatic Updates lets you manage the WordPress automatic updates engine so you choose what exactly gets updated automatically.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), sprintf( __( 'Keep the %s feature turned on.', 'wp-simple-firewall' ), __( 'Automatic Updates', 'wp-simple-firewall' ) ) )
				];
				break;

			case 'section_disable_all_wordpress_automatic_updates' :
				$sTitle = __( 'Disable ALL WordPress Automatic Updates', 'wp-simple-firewall' );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'If you never want WordPress to automatically update anything on your site, turn on this option.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Do not turn on this option unless you really need to block updates.', 'wp-simple-firewall' ) )
				];
				$sTitleShort = __( 'Turn Off', 'wp-simple-firewall' );
				break;

			case 'section_automatic_plugin_self_update' :
				$sTitle = __( 'Automatic Plugin Self-Update', 'wp-simple-firewall' );
				$aSummary = [
					sprintf( '%s - %s',
						__( 'Purpose', 'wp-simple-firewall' ),
						sprintf( __( 'Allows the %s plugin to automatically update itself when an update is available.', 'wp-simple-firewall' ), $sPlugName )
					),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Keep this option turned on.', 'wp-simple-firewall' ) )
				];
				$sTitleShort = __( 'Self-Update', 'wp-simple-firewall' );
				break;

			case 'section_automatic_updates_for_wordpress_components' :
				$sTitle = __( 'Automatic Updates For WordPress Components', 'wp-simple-firewall' );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Control how automatic updates for each WordPress component is handled.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'You should at least allow minor updates for the WordPress core.', 'wp-simple-firewall' ) )
				];
				$sTitleShort = __( 'WordPress Components', 'wp-simple-firewall' );
				break;

			case 'section_options' :
				$sTitle = __( 'Auto-Update Options', 'wp-simple-firewall' );
				$sTitleShort = __( 'Auto-Update Options', 'wp-simple-firewall' );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Make adjustments to how automatic updates are handled on your site.', 'wp-simple-firewall' ) ),
				];
				break;

			default:
				throw new \Exception( sprintf( 'A section slug was defined but with no associated strings. Slug: "%s".', $sSectionSlug ) );
		}
		$aOptionsParams[ 'title' ] = $sTitle;
		$aOptionsParams[ 'summary' ] = ( isset( $aSummary ) && is_array( $aSummary ) ) ? $aSummary : [];
		$aOptionsParams[ 'title_short' ] = $sTitleShort;
		return $aOptionsParams;
	}

	/**
	 * @param array $aOptionsParams
	 * @return array
	 * @throws \Exception
	 */
	protected function loadStrings_Options( $aOptionsParams ) {

		$sKey = $aOptionsParams[ 'key' ];
		$sPlugName = $this->getCon()->getHumanName();
		switch ( $sKey ) {

			case 'enable_autoupdates' :
				$sName = sprintf( __( 'Enable %s Module', 'wp-simple-firewall' ), $this->getMainFeatureName() );
				$sSummary = sprintf( __( 'Enable (or Disable) The %s Module', 'wp-simple-firewall' ), $this->getMainFeatureName() );
				$sDescription = sprintf( __( 'Un-Checking this option will completely disable the %s module.', 'wp-simple-firewall' ), $this->getMainFeatureName() );
				break;

			case 'enable_autoupdate_disable_all' :
				$sName = __( 'Disable All', 'wp-simple-firewall' );
				$sSummary = __( 'Completely Disable WordPress Automatic Updates', 'wp-simple-firewall' );
				$sDescription = __( 'When selected, regardless of any other settings, all WordPress automatic updates on this site will be completely disabled!', 'wp-simple-firewall' );
				break;

			case 'autoupdate_plugin_self' :
				$sName = __( 'Auto Update Plugin', 'wp-simple-firewall' );
				$sSummary = __( 'Always Automatically Update This Plugin', 'wp-simple-firewall' );
				$sDescription = sprintf(
					__( 'Regardless of any other settings, automatically update the "%s" plugin.', 'wp-simple-firewall' ),
					$sPlugName
				);
				break;

			case 'autoupdate_core' :
				$sName = __( 'WordPress Core Updates', 'wp-simple-firewall' );
				$sSummary = __( 'Decide how the WordPress Core will automatically update, if at all', 'wp-simple-firewall' );
				$sDescription = __( 'At least automatically upgrading minor versions is recommended (and is the WordPress default).', 'wp-simple-firewall' );
				break;

			case 'enable_autoupdate_translations' :
				$sName = __( 'Translations', 'wp-simple-firewall' );
				$sSummary = __( 'Automatically Update Translations', 'wp-simple-firewall' );
				$sDescription = __( 'Note: Automatic updates for translations are enabled on WordPress by default.', 'wp-simple-firewall' );
				break;

			case 'enable_autoupdate_plugins' :
				$sName = __( 'Plugins', 'wp-simple-firewall' );
				$sSummary = __( 'Automatically Update All Plugins', 'wp-simple-firewall' );
				$sDescription = __( 'Note: Automatic updates for plugins are disabled on WordPress by default.', 'wp-simple-firewall' );
				break;

			case 'enable_individual_autoupdate_plugins' :
				$sName = __( 'Individually Select Plugins', 'wp-simple-firewall' );
				$sSummary = __( 'Select Individual Plugins To Automatically Update', 'wp-simple-firewall' );
				$sDescription = __( 'Turning this on will provide an option on the plugins page to select whether a plugin is automatically updated.', 'wp-simple-firewall' );
				break;

			case 'enable_autoupdate_themes' :
				$sName = __( 'Themes', 'wp-simple-firewall' );
				$sSummary = __( 'Automatically Update Themes', 'wp-simple-firewall' );
				$sDescription = __( 'Note: Automatic updates for themes are disabled on WordPress by default.', 'wp-simple-firewall' );
				break;

			case 'enable_autoupdate_ignore_vcs' :
				$sName = __( 'Ignore Version Control', 'wp-simple-firewall' );
				$sSummary = __( 'Ignore Version Control Systems Such As GIT and SVN', 'wp-simple-firewall' );
				$sDescription = __( 'If you use SVN or GIT and WordPress detects it, automatic updates are disabled by default. Check this box to ignore version control systems and allow automatic updates.', 'wp-simple-firewall' );
				break;

			case 'enable_upgrade_notification_email' :
				$sName = __( 'Send Report Email', 'wp-simple-firewall' );
				$sSummary = __( 'Send email notices after automatic updates', 'wp-simple-firewall' );
				$sDescription = __( 'You can turn on/off email notices from automatic updates by un/checking this box.', 'wp-simple-firewall' );
				break;

			case 'override_email_address' :
				$sName = __( 'Report Email Address', 'wp-simple-firewall' );
				$sSummary = __( 'Where to send upgrade notification reports', 'wp-simple-firewall' );
				$sDescription = __( 'If this is empty, it will default to the Site Admin email address', 'wp-simple-firewall' );
				break;

			case 'update_delay' :
				$sName = __( 'Update Delay', 'wp-simple-firewall' );
				$sSummary = __( 'Delay Automatic Updates For Period Of Stability', 'wp-simple-firewall' );
				$sDescription = sprintf( __( '%s will delay upgrades until the new update has been available for the set number of days.', 'wp-simple-firewall' ), $sPlugName )
								.'<br />'.__( "This helps ensure updates are more stable before they're automatically applied to your site.", 'wp-simple-firewall' );
				break;

			default:
				throw new \Exception( sprintf( 'An option has been defined but without strings assigned to it. Option key: "%s".', $sKey ) );
		}

		$aOptionsParams[ 'name' ] = $sName;
		$aOptionsParams[ 'summary' ] = $sSummary;
		$aOptionsParams[ 'description' ] = $sDescription;
		return $aOptionsParams;
	}
}