<?php

class ICWP_WPSF_FeatureHandler_Autoupdates extends ICWP_WPSF_FeatureHandler_BaseWpsf {

	/**
	 */
	protected function setupCustomHooks() {
		// Force run automatic updates
		if ( $this->loadRequest()->query( 'force_run_auto_updates' ) == 'now' ) {
			add_filter( $this->prefix( 'force_autoupdate' ), '__return_true' );
		}
	}

	/**
	 * @return string[]
	 */
	public function getAutoupdatePlugins() {
		$aSelected = array();
		if ( $this->isAutoupdateIndividualPlugins() ) {
			$aSelected = $this->getOpt( 'selected_plugins', array() );
			if ( !is_array( $aSelected ) ) {
				$aSelected = array();
			}
		}
		return $aSelected;
	}

	/**
	 * @return array
	 */
	public function getDelayTracking() {
		$aTracking = $this->getOpt( 'delay_tracking', array() );
		if ( !is_array( $aTracking ) ) {
			$aTracking = array();
		}
		$aTracking = $this->loadDP()->mergeArraysRecursive(
			array(
				'core'    => array(),
				'plugins' => array(),
				'themes'  => array(),
			),
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
			switch ( $this->loadRequest()->request( 'exec' ) ) {

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
		$sMessage = _wpsf__( 'You do not have permissions to perform this action.' );

		if ( $this->isAutoupdateIndividualPlugins() && $this->getCon()->isPluginAdmin() ) {
			$oWpPlugins = $this->loadWpPlugins();
			$sFile = $this->loadRequest()->post( 'pluginfile' );
			if ( $oWpPlugins->isInstalled( $sFile ) ) {
				$this->setPluginToAutoUpdate( $sFile );

				$aPlugin = $oWpPlugins->getPlugin( $sFile );
				$sMessage = sprintf( _wpsf__( 'Plugin "%s" will %s.' ),
					$aPlugin[ 'Name' ],
					$this->loadWp()
						 ->isPluginAutomaticallyUpdated( $sFile ) ? _wpsf__( 'update automatically' ) : _wpsf__( 'not update automatically' )
				);
				$bSuccess = true;
			}
			else {
				$sMessage = _wpsf__( 'Failed to change the update status of the plugin.' );
			}
		}

		return array(
			'success' => $bSuccess,
			'message' => $sMessage,
		);
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
		$aNotices = array(
			'title'    => _wpsf__( 'Automatic Updates' ),
			'messages' => array()
		);
		{ //really disabled?
			$oWp = $this->loadWp();
			if ( $this->isModOptEnabled() ) {
				if ( $this->isDisableAllAutoUpdates() && !$oWp->getWpAutomaticUpdater()->is_disabled() ) {
					$aNotices[ 'messages' ][ 'disabled_auto' ] = array(
						'title'   => 'Auto Updates Not Really Disabled',
						'message' => _wpsf__( 'Automatic Updates Are Not Disabled As Expected.' ),
						'href'    => $this->getUrl_DirectLinkToOption( 'enable_autoupdate_disable_all' ),
						'action'  => sprintf( 'Go To %s', _wpsf__( 'Options' ) ),
						'rec'     => sprintf( _wpsf__( 'A plugin/theme other than %s is affecting your automatic update settings.' ), $this->getCon()
																																		   ->getHumanName() )
					);
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
		$aThis = array(
			'strings'      => array(
				'title' => _wpsf__( 'Automatic Updates' ),
				'sub'   => _wpsf__( 'Control WordPress Automatic Updates' ),
			),
			'key_opts'     => array(),
			'href_options' => $this->getUrl_AdminPage()
		);

		if ( !$this->isModOptEnabled() ) {
			$aThis[ 'key_opts' ][ 'mod' ] = $this->getModDisabledInsight();
		}
		else {

			$bAllDisabled = $this->isDisableAllAutoUpdates();
			if ( $bAllDisabled ) {
				$aThis[ 'key_opts' ][ 'disabled' ] = array(
					'name'    => _wpsf__( 'Disabled All' ),
					'enabled' => !$bAllDisabled,
					'summary' => $bAllDisabled ?
						_wpsf__( 'All automatic updates on this site are disabled' )
						: _wpsf__( 'The automatic updates system is enabled' ),
					'weight'  => 2,
					'href'    => $this->getUrl_DirectLinkToOption( 'enable_autoupdate_disable_all' ),
				);
			}
			else {
				$oWp = $this->loadWp();
				$bCanCore = $oWp->canCoreUpdateAutomatically();
				$aThis[ 'key_opts' ][ 'core_minor' ] = array(
					'name'    => _wpsf__( 'Core Updates' ),
					'enabled' => $bCanCore,
					'summary' => $bCanCore ?
						_wpsf__( 'Minor WP Core updates will be installed automatically' )
						: _wpsf__( 'Minor WP Core updates will not be installed automatically' ),
					'weight'  => 2,
					'href'    => $this->getUrl_DirectLinkToOption( 'autoupdate_core' ),
				);

				$bHasDelay = $this->isModOptEnabled() && $this->getDelayUpdatesPeriod();
				$aThis[ 'key_opts' ][ 'delay' ] = array(
					'name'    => _wpsf__( 'Update Delay' ),
					'enabled' => $bHasDelay,
					'summary' => $bHasDelay ?
						_wpsf__( 'Automatic updates are applied after a short delay' )
						: _wpsf__( 'Automatic updates are applied immediately' ),
					'weight'  => 1,
					'href'    => $this->getUrl_DirectLinkToOption( 'update_delay' ),
				);

				$sName = $this->getCon()->getHumanName();
				$bSelfAuto = $this->isModOptEnabled()
							 && in_array( $this->getSelfAutoUpdateOpt(), [ 'auto', 'immediate' ] );
				$aThis[ 'key_opts' ][ 'self' ] = array(
					'name'    => _wpsf__( 'Self Auto-Update' ),
					'enabled' => $bSelfAuto,
					'summary' => $bSelfAuto ?
						sprintf( _wpsf__( '%s is automatically updated' ), $sName )
						: sprintf( _wpsf__( "%s isn't automatically updated" ), $sName ),
					'weight'  => 1,
					'href'    => $this->getUrl_DirectLinkToOption( 'autoupdate_plugin_self' ),
				);
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
				$sTitle = sprintf( _wpsf__( 'Enable Module: %s' ), $this->getMainFeatureName() );
				$aSummary = array(
					sprintf( '%s - %s', _wpsf__( 'Purpose' ), _wpsf__( 'Automatic Updates lets you manage the WordPress automatic updates engine so you choose what exactly gets updated automatically.' ) ),
					sprintf( '%s - %s', _wpsf__( 'Recommendation' ), sprintf( _wpsf__( 'Keep the %s feature turned on.' ), _wpsf__( 'Automatic Updates' ) ) )
				);
				$sTitleShort = sprintf( _wpsf__( '%s/%s Module' ), _wpsf__( 'Enable' ), _wpsf__( 'Disable' ) );
				break;

			case 'section_disable_all_wordpress_automatic_updates' :
				$sTitle = _wpsf__( 'Disable ALL WordPress Automatic Updates' );
				$aSummary = array(
					sprintf( '%s - %s', _wpsf__( 'Purpose' ), _wpsf__( 'If you never want WordPress to automatically update anything on your site, turn on this option.' ) ),
					sprintf( '%s - %s', _wpsf__( 'Recommendation' ), _wpsf__( 'Do not turn on this option unless you really need to block updates.' ) )
				);
				$sTitleShort = _wpsf__( 'Turn Off' );
				break;

			case 'section_automatic_plugin_self_update' :
				$sTitle = _wpsf__( 'Automatic Plugin Self-Update' );
				$aSummary = array(
					sprintf( '%s - %s',
						_wpsf__( 'Purpose' ),
						sprintf( _wpsf__( 'Allows the %s plugin to automatically update itself when an update is available.' ), $sPlugName )
					),
					sprintf( '%s - %s', _wpsf__( 'Recommendation' ), _wpsf__( 'Keep this option turned on.' ) )
				);
				$sTitleShort = _wpsf__( 'Self-Update' );
				break;

			case 'section_automatic_updates_for_wordpress_components' :
				$sTitle = _wpsf__( 'Automatic Updates For WordPress Components' );
				$aSummary = array(
					sprintf( '%s - %s', _wpsf__( 'Purpose' ), _wpsf__( 'Control how automatic updates for each WordPress component is handled.' ) ),
					sprintf( '%s - %s', _wpsf__( 'Recommendation' ), _wpsf__( 'You should at least allow minor updates for the WordPress core.' ) )
				);
				$sTitleShort = _wpsf__( 'WordPress Components' );
				break;

			case 'section_options' :
				$sTitle = _wpsf__( 'Auto-Update Options' );
				$sTitleShort = _wpsf__( 'Auto-Update Options' );
				$aSummary = array(
					sprintf( '%s - %s', _wpsf__( 'Purpose' ), _wpsf__( 'Make adjustments to how automatic updates are handled on your site.' ) ),
				);
				break;

			default:
				throw new \Exception( sprintf( 'A section slug was defined but with no associated strings. Slug: "%s".', $sSectionSlug ) );
		}
		$aOptionsParams[ 'title' ] = $sTitle;
		$aOptionsParams[ 'summary' ] = ( isset( $aSummary ) && is_array( $aSummary ) ) ? $aSummary : array();
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
				$sName = sprintf( _wpsf__( 'Enable %s Module' ), $this->getMainFeatureName() );
				$sSummary = sprintf( _wpsf__( 'Enable (or Disable) The %s Module' ), $this->getMainFeatureName() );
				$sDescription = sprintf( _wpsf__( 'Un-Checking this option will completely disable the %s module.' ), $this->getMainFeatureName() );
				break;

			case 'enable_autoupdate_disable_all' :
				$sName = _wpsf__( 'Disable All' );
				$sSummary = _wpsf__( 'Completely Disable WordPress Automatic Updates' );
				$sDescription = _wpsf__( 'When selected, regardless of any other settings, all WordPress automatic updates on this site will be completely disabled!' );
				break;

			case 'autoupdate_plugin_self' :
				$sName = _wpsf__( 'Auto Update Plugin' );
				$sSummary = _wpsf__( 'Always Automatically Update This Plugin' );
				$sDescription = sprintf(
					_wpsf__( 'Regardless of any other settings, automatically update the "%s" plugin.' ),
					$sPlugName
				);
				break;

			case 'autoupdate_core' :
				$sName = _wpsf__( 'WordPress Core Updates' );
				$sSummary = _wpsf__( 'Decide how the WordPress Core will automatically update, if at all' );
				$sDescription = _wpsf__( 'At least automatically upgrading minor versions is recommended (and is the WordPress default).' );
				break;

			case 'enable_autoupdate_translations' :
				$sName = _wpsf__( 'Translations' );
				$sSummary = _wpsf__( 'Automatically Update Translations' );
				$sDescription = _wpsf__( 'Note: Automatic updates for translations are enabled on WordPress by default.' );
				break;

			case 'enable_autoupdate_plugins' :
				$sName = _wpsf__( 'Plugins' );
				$sSummary = _wpsf__( 'Automatically Update All Plugins' );
				$sDescription = _wpsf__( 'Note: Automatic updates for plugins are disabled on WordPress by default.' );
				break;

			case 'enable_individual_autoupdate_plugins' :
				$sName = _wpsf__( 'Individually Select Plugins' );
				$sSummary = _wpsf__( 'Select Individual Plugins To Automatically Update' );
				$sDescription = _wpsf__( 'Turning this on will provide an option on the plugins page to select whether a plugin is automatically updated.' );
				break;

			case 'enable_autoupdate_themes' :
				$sName = _wpsf__( 'Themes' );
				$sSummary = _wpsf__( 'Automatically Update Themes' );
				$sDescription = _wpsf__( 'Note: Automatic updates for themes are disabled on WordPress by default.' );
				break;

			case 'enable_autoupdate_ignore_vcs' :
				$sName = _wpsf__( 'Ignore Version Control' );
				$sSummary = _wpsf__( 'Ignore Version Control Systems Such As GIT and SVN' );
				$sDescription = _wpsf__( 'If you use SVN or GIT and WordPress detects it, automatic updates are disabled by default. Check this box to ignore version control systems and allow automatic updates.' );
				break;

			case 'enable_upgrade_notification_email' :
				$sName = _wpsf__( 'Send Report Email' );
				$sSummary = _wpsf__( 'Send email notices after automatic updates' );
				$sDescription = _wpsf__( 'You can turn on/off email notices from automatic updates by un/checking this box.' );
				break;

			case 'override_email_address' :
				$sName = _wpsf__( 'Report Email Address' );
				$sSummary = _wpsf__( 'Where to send upgrade notification reports' );
				$sDescription = _wpsf__( 'If this is empty, it will default to the Site Admin email address' );
				break;

			case 'update_delay' :
				$sName = _wpsf__( 'Update Delay' );
				$sSummary = _wpsf__( 'Delay Automatic Updates For Period Of Stability' );
				$sDescription = sprintf( _wpsf__( '%s will delay upgrades until the new update has been available for the set number of days.' ), $sPlugName )
								.'<br />'._wpsf__( "This helps ensure updates are more stable before they're automatically applied to your site." );
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