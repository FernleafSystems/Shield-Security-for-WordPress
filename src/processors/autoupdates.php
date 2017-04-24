<?php

if ( !class_exists( 'ICWP_WPSF_Processor_Autoupdates', false ) ):

	require_once( dirname(__FILE__).DIRECTORY_SEPARATOR.'base_wpsf.php' );

	class ICWP_WPSF_Processor_Autoupdates extends ICWP_WPSF_Processor_BaseWpsf {

		/**
		 * @var boolean
		 */
		protected $bDoForceRunAutoupdates = false;

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
			return apply_filters( $this->getFeature()->prefix( 'force_autoupdate' ), $this->bDoForceRunAutoupdates );
		}

		/**
		 */
		public function run() {

			$nFilterPriority = $this->getHookPriority();
			add_filter( 'allow_minor_auto_core_updates',	array( $this, 'autoupdate_core_minor' ), $nFilterPriority );
			add_filter( 'allow_major_auto_core_updates',	array( $this, 'autoupdate_core_major' ), $nFilterPriority );

			add_filter( 'auto_update_translation',	array( $this, 'autoupdate_translations' ), $nFilterPriority, 2 );
			add_filter( 'auto_update_plugin',		array( $this, 'autoupdate_plugins' ), $nFilterPriority, 2 );
			add_filter( 'auto_update_theme',		array( $this, 'autoupdate_themes' ), $nFilterPriority, 2 );

			if ( $this->getIsOption('enable_autoupdate_ignore_vcs', 'Y') ) {
				add_filter( 'automatic_updates_is_vcs_checkout', array( $this, 'disable_for_vcs' ), 10, 2 );
			}

			if ( $this->getIsOption('enable_autoupdate_disable_all', 'Y') ) {
				add_filter( 'automatic_updater_disabled', '__return_true', $nFilterPriority );
			}

			add_filter( 'auto_core_update_send_email', array( $this, 'autoupdate_send_email' ), $nFilterPriority, 1 ); //more parameter options here for later
			add_filter( 'auto_core_update_email', array( $this, 'autoupdate_email_override' ), $nFilterPriority, 1 ); //more parameter options here for later

			add_action( 'wp_loaded', array( $this, 'force_run_autoupdates' ) );

			// Adds automatic update indicator icon to all plugin meta in plugin listing.
//			add_filter( 'plugin_row_meta', array( $this, 'fAddAutomaticUpdatePluginMeta' ), $nFilterPriority, 2 );

			// Adds automatic update indicator column to all plugins in plugin listing.
			add_filter( 'manage_plugins_columns', array( $this, 'fAddPluginsListAutoUpdateColumn') );

			if ( $this->getIsOption( 'enable_upgrade_notification_email', 'Y' ) ) {
				add_action( 'automatic_updates_complete', array( $this, 'sendNotificationEmail' ) );
			}

			if ( isset( $_GET['auto'] ) ) {
				$this->loadWpFunctions()->doForceRunAutomaticUpdates();
			}
		}

		/**
		 * Will force-run the WordPress automatic updates process and then redirect to the updates screen.
		 *
		 * @return bool
		 */
		public function force_run_autoupdates() {

			if ( !$this->getIfForceRunAutoupdates() ) {
				return true;
			}
			$this->doStatIncrement( 'autoupdates.forcerun' );
			return $this->loadWpFunctions()->doForceRunAutomaticUpdates();
		}

		/**
		 * This is a filter method designed to say whether a major core WordPress upgrade should be permitted,
		 * based on the plugin settings.
		 *
		 * @param boolean $bUpdate
		 * @return boolean
		 */
		public function autoupdate_core_major( $bUpdate ) {
			if ( $this->getIsOption( 'autoupdate_core', 'core_never' ) ) {
				$this->doStatIncrement( 'autoupdates.core.major.blocked' );
				return false;
			}
			else if ( $this->getIsOption( 'autoupdate_core', 'core_major' ) ) {
				$this->doStatIncrement( 'autoupdates.core.major.allowed' );
				return true;
			}
			return $bUpdate;
		}

		/**
		 * This is a filter method designed to say whether a minor core WordPress upgrade should be permitted,
		 * based on the plugin settings.
		 *
		 * @param boolean $bUpdate
		 * @return boolean
		 */
		public function autoupdate_core_minor( $bUpdate ) {
			if ( $this->getIsOption('autoupdate_core', 'core_never') ) {
				$this->doStatIncrement( 'autoupdates.core.minor.blocked' );
				return false;
			}
			else if ( $this->getIsOption('autoupdate_core', 'core_minor') ) {
				$this->doStatIncrement( 'autoupdates.core.minor.allowed' );
				return true;
			}
			return $bUpdate;
		}

		/**
		 * This is a filter method designed to say whether a WordPress translations upgrades should be permitted,
		 * based on the plugin settings.
		 *
		 * @param boolean $bUpdate
		 * @param string $sSlug
		 * @return boolean
		 */
		public function autoupdate_translations( $bUpdate, $sSlug ) {
			if ( $this->getIsOption( 'enable_autoupdate_translations', 'Y' ) ) {
				return true;
			}
			return $bUpdate;
		}

		/**
		 * @param bool $bDoAutoUpdate
		 * @param StdClass|string $mItem
		 * @return boolean
		 */
		public function autoupdate_plugins( $bDoAutoUpdate, $mItem ) {

			// first, is global auto updates for plugins set
			if ( $this->getIsOption( 'enable_autoupdate_plugins', 'Y' ) ) {
				$this->doStatIncrement( 'autoupdates.plugins.all' );
				return true;
			}

			$sItemFile = $this->loadWpFunctions()->getFileFromAutomaticUpdateItem( $mItem );

			// If it's this plugin and autoupdate this plugin is set...
			if ( $sItemFile === $this->getFeature()->getController()->getPluginBaseFile() ) {
				$bDoAutoUpdate = true;
				if ( $this->loadWpFunctions()->getIsRunningAutomaticUpdates() ) {
					$this->doStatIncrement( 'autoupdates.plugins.self' );
				}
			}
			else {
				$aAutoUpdates = apply_filters( 'icwp_wpsf_autoupdate_plugins', array() );
				if ( !empty( $aAutoUpdates ) && is_array( $aAutoUpdates ) && in_array( $sItemFile, $aAutoUpdates ) ) {
					$bDoAutoUpdate = true;
				}
			}

			return $bDoAutoUpdate;
		}

		/**
		 * @param bool $bDoAutoUpdate
		 * @param stdClass|string $mItem
		 * @return boolean
		 */
		public function autoupdate_themes( $bDoAutoUpdate, $mItem ) {

			// first, is global auto updates for themes set
			if ( $this->getIsOption( 'enable_autoupdate_themes', 'Y' ) ) {
				$this->doStatIncrement( 'autoupdates.themes.all' );
				return true;
			}

			$sItemFile = $this->loadWpFunctions()->getFileFromAutomaticUpdateItem( $mItem, 'theme' );

			$aAutoUpdates = apply_filters( 'icwp_wpsf_autoupdate_themes', array() );
			if ( !empty( $aAutoUpdates ) && is_array( $aAutoUpdates ) && in_array( $sItemFile, $aAutoUpdates ) ) {
				$bDoAutoUpdate = true;
			}
			return $bDoAutoUpdate;
		}

		/**
		 * This is a filter method designed to say whether WordPress automatic upgrades should be permitted
		 * if a version control system is detected.
		 *
		 * @param $checkout
		 * @param $context
		 * @return boolean
		 */
		public function disable_for_vcs( $checkout, $context ) {
			return false;
		}

		/**
		 * A filter on whether or not a notification email is send after core upgrades are attempted.
		 *
		 * @param boolean $bSendEmail
		 * @return boolean
		 */
		public function autoupdate_send_email( $bSendEmail ) {
			return $this->getIsOption( 'enable_upgrade_notification_email', 'Y' );
		}

		/**
		 * A filter on the target email address to which to send upgrade notification emails.
		 *
		 * @param array $aEmailParams
		 * @return array
		 */
		public function autoupdate_email_override( $aEmailParams ) {
			$sOverride = $this->getOption( 'override_email_address', '' );
			if ( !empty( $sOverride ) && is_email( $sOverride ) ) {
				$aEmailParams['to'] = $sOverride;
			}
			return $aEmailParams;
		}

		/**
		 * @filter
		 * @param array $aPluginMeta
		 * @param string $sPluginBaseFileName
		 * @return array
		 */
		public function fAddAutomaticUpdatePluginMeta( $aPluginMeta, $sPluginBaseFileName ) {

			// first we prevent collision between iControlWP <-> Simple Firewall by not duplicating icons
			foreach( $aPluginMeta as $sMetaItem ) {
				if ( strpos( $sMetaItem, 'icwp-pluginautoupdateicon' ) !== false ) {
					return $aPluginMeta;
				}
			}
			$bUpdate = $this->loadWpFunctions()->getIsPluginAutomaticallyUpdated( $sPluginBaseFileName );
			$sHtml = $this->getPluginAutoupdateIconHtml( $bUpdate );
			array_unshift( $aPluginMeta, sprintf( '%s', $sHtml ) );
			return $aPluginMeta;
		}

		/**
		 * Adds the column to the plugins listing table to indicate whether WordPress will automatically update the plugins
		 *
		 * @param array $aColumns
		 * @return array
		 */
		public function fAddPluginsListAutoUpdateColumn( $aColumns ) {
			if ( !isset( $aColumns['icwp_autoupdate'] ) ) {
				$aColumns['icwp_autoupdate'] = 'Auto Update';
				add_action( 'manage_plugins_custom_column', array( $this, 'aPrintPluginsListAutoUpdateColumnContent' ), $this->getHookPriority(), 2 );
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
			$bUpdate = $this->loadWpFunctions()->getIsPluginAutomaticallyUpdated( $sPluginBaseFileName );
			echo $this->getPluginAutoupdateIconHtml( $bUpdate );
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

			$aEmailContent = array(
				sprintf(
					_wpsf__( 'This is a quick notification from the %s that WordPress Automatic Updates just completed on your site with the following results.' ),
					$this->getController()->getHumanName()
				),
				''
			);

			if ( !empty( $aUpdateResults['plugin'] ) && is_array( $aUpdateResults['plugin'] ) ) {
				$bHasPluginUpdates = false;
				$aTempContent[] = _wpsf__( 'Plugins Updated:' );
				foreach( $aUpdateResults['plugin'] as $oUpdateItem ) {
					if ( isset( $oUpdateItem->result ) && $oUpdateItem->result && !empty( $oUpdateItem->name ) ) {
						$aTempContent[] = ' - '.sprintf( 'Plugin "%s" was automatically updated to version "%s"', $oUpdateItem->name, $oUpdateItem->item->new_version );
						$bHasPluginUpdates = true;
					}
				}
				$aTempContent[] = '';

				if ( $bHasPluginUpdates ) {
					$bReallyUpdates = true;
					$aEmailContent = array_merge( $aEmailContent, $aTempContent );
				}
			}

			if ( !empty( $aUpdateResults['theme'] ) && is_array( $aUpdateResults['theme'] ) ) {
				$bHasThemesUpdates = false;
				$aTempContent = array( _wpsf__( 'Themes Updated:' ) );
				foreach( $aUpdateResults['theme'] as $oUpdateItem ) {
					if ( isset( $oUpdateItem->result ) && $oUpdateItem->result && !empty( $oUpdateItem->name ) ) {
						$aTempContent[] = ' - '.sprintf( 'Theme "%s" was automatically updated to version "%s"', $oUpdateItem->name, $oUpdateItem->item->new_version );
						$bHasThemesUpdates = true;
					}
				}
				$aTempContent[] = '';

				if ( $bHasThemesUpdates ) {
					$bReallyUpdates = true;
					$aEmailContent = array_merge( $aEmailContent, $aTempContent );
				}
			}

			if ( !empty( $aUpdateResults['core'] ) && is_array( $aUpdateResults['core'] ) ) {
				$bHasCoreUpdates = false;
				$aTempContent = array( _wpsf__( 'WordPress Core Updated:' ) );
				foreach( $aUpdateResults['core'] as $oUpdateItem ) {
					if ( isset( $oUpdateItem->result ) && !is_wp_error( $oUpdateItem->result ) ) {
						$aTempContent[] = ' - '.sprintf( 'WordPress was automatically updated to "%s"', $oUpdateItem->name );
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

			$aEmailContent[] = _wpsf__( 'Thank you.' );

			$sTitle = sprintf(
				_wpsf__( "Notice - %s" ),
				sprintf( "Automatic Updates Completed For %s", $this->loadWpFunctions()->getSiteName() )
			);
			$this->getEmailProcessor()->sendEmailTo( $this->getOption( 'override_email_address', '' ), $sTitle, $aEmailContent );
		}

		/**
		 * @param boolean $bIsAutoupdate
		 * @return string
		 */
		protected function getPluginAutoupdateIconHtml( $bIsAutoupdate ) {
			return sprintf(
				'<span title="%s" class="icwp-pluginautoupdateicon dashicons dashicons-%s"></span>',
				$bIsAutoupdate ? 'Updates are applied automatically by WordPress' : 'Updates are applied manually by Administrators',
				$bIsAutoupdate ? 'update' : 'hammer'
			);
		}

		/**
		 * Removes all filters that have been added from auto-update related WordPress filters
		 */
		protected function removeAllAutoupdateFilters() {
			$aFilters = array(
				'allow_minor_auto_core_updates',
				'allow_major_auto_core_updates',
				'auto_update_translation',
				'auto_update_plugin',
				'auto_update_theme',
				'automatic_updates_is_vcs_checkout',
				'automatic_updater_disabled'
			);
			foreach( $aFilters as $sFilter ) {
				remove_all_filters( $sFilter );
			}
		}

		/**
		 * @return int
		 */
		protected function getHookPriority() {
			return $this->getFeature()->getDefinition( 'action_hook_priority' );
		}
	}

endif;