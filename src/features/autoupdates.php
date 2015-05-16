<?php

if ( !class_exists('ICWP_WPSF_FeatureHandler_Autoupdates_V3') ):

	require_once( dirname(__FILE__).ICWP_DS.'base.php' );

	class ICWP_WPSF_FeatureHandler_Autoupdates_V3 extends ICWP_WPSF_FeatureHandler_Base {

		/**
		 * this feature doesn't need to consider IP whitelists - it has no security implications.
		 */
		protected function doExecuteProcessor() {
			parent::doExecuteProcessor();
		}

		/**
		 * @return bool|void
		 */
		protected function doExtraSubmitProcessing() {
			// Force run automatic updates
			$oDp = $this->loadDataProcessor();
			if ( $oDp->FetchGet( 'force_run_auto_updates' ) == 'now' ) {
				/** @var ICWP_WPSF_Processor_Autoupdates $oProc */
				$oProc = $this->getProcessor();
				$oProc->setForceRunAutoupdates( true );
				return;
			}
		}

		/**
		 * @param array $aOptionsParams
		 * @return array
		 * @throws Exception
		 */
		protected function loadStrings_SectionTitles( $aOptionsParams ) {

			$sSectionSlug = $aOptionsParams['section_slug'];
			switch( $sSectionSlug ) {

				case 'section_enable_plugin_feature_automatic_updates_control' :
					$sTitle = sprintf( _wpsf__( 'Enable Plugin Feature: %s' ), $this->getMainFeatureName() );
					$sTitleShort = sprintf( '%s / %s', _wpsf__( 'Enable' ), _wpsf__( 'Disable' ) );
					break;

				case 'section_disable_all_wordpress_automatic_updates' :
					$sTitle = _wpsf__( 'Disable ALL WordPress Automatic Updates' );
					$sTitleShort = _wpsf__( 'Turn Off' );
					break;

				case 'section_automatic_plugin_self_update' :
					$sTitle = _wpsf__( 'Automatic Plugin Self-Update' );
					$sTitleShort = _wpsf__( 'Self-Update' );
					break;

				case 'section_automatic_updates_for_wordpress_components' :
					$sTitle = _wpsf__( 'Automatic Updates For WordPress Components' );
					$sTitleShort = _wpsf__( 'WordPress Components' );
					break;

				case 'section_automatic_update_email_notifications' :
					$sTitle = _wpsf__( 'Automatic Update Email Notifications' );
					$sTitleShort = _wpsf__( 'Notifications' );
					break;

				default:
					throw new Exception( sprintf( 'A section slug was defined but with no associated strings. Slug: "%s".', $sSectionSlug ) );
			}
			$aOptionsParams['section_title'] = $sTitle;
			$aOptionsParams['section_title_short'] = $sTitleShort;
			return $aOptionsParams;
		}

		/**
		 * @param array $aOptionsParams
		 * @return array
		 * @throws Exception
		 */
		protected function loadStrings_Options( $aOptionsParams ) {

			$sKey = $aOptionsParams['key'];
			switch( $sKey ) {

				case 'enable_autoupdates' :
					$sName = sprintf( _wpsf__( 'Enable %s' ), $this->getMainFeatureName() );
					$sSummary = sprintf( _wpsf__( 'Enable (or Disable) The %s Feature' ), $this->getMainFeatureName() );
					$sDescription = sprintf( _wpsf__( 'Checking/Un-Checking this option will completely turn on/off the whole %s feature.' ), $this->getMainFeatureName() );
					break;

				case 'enable_autoupdate_disable_all' :
					$sName = _wpsf__( 'Disable All' );
					$sSummary = _wpsf__( 'Completely Disable WordPress Automatic Updates' );
					$sDescription = _wpsf__( 'When selected, regardless of any other settings, all WordPress automatic updates on this site will be completely disabled!' );
					break;

				case 'autoupdate_plugin_self' :
					$sName = _wpsf__( 'Auto Update Plugin' );
					$sSummary = _wpsf__( 'Always Automatically Update This Plugin' );
					$sDescription = sprintf( _wpsf__( 'Regardless of any component settings below, automatically update the "%s" plugin.' ), $this->getController()->getHumanName() );
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
					$sSummary = _wpsf__( 'Automatically Update Plugins' );
					$sDescription = _wpsf__( 'Note: Automatic updates for plugins are disabled on WordPress by default.' );
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

				default:
					throw new Exception( sprintf( 'An option has been defined but without strings assigned to it. Option key: "%s".', $sKey ) );
			}

			$aOptionsParams['name'] = $sName;
			$aOptionsParams['summary'] = $sSummary;
			$aOptionsParams['description'] = $sDescription;
			return $aOptionsParams;
		}
	}

endif;

class ICWP_WPSF_FeatureHandler_Autoupdates extends ICWP_WPSF_FeatureHandler_Autoupdates_V3 { }