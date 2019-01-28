<?php

class ICWP_WPSF_WpFunctions_Plugins extends ICWP_WPSF_Foundation {

	/**
	 * @var ICWP_WPSF_WpFunctions_Plugins
	 */
	protected static $oInstance = null;

	private function __construct() {
	}

	/**
	 * @return ICWP_WPSF_WpFunctions_Plugins
	 */
	public static function GetInstance() {
		if ( is_null( self::$oInstance ) ) {
			self::$oInstance = new self();
		}
		return self::$oInstance;
	}

	/**
	 * @param string $sPluginFile
	 * @param bool   $bNetworkWide
	 * @return null|WP_Error
	 */
	public function activate( $sPluginFile, $bNetworkWide = false ) {
		return activate_plugin( $sPluginFile, '', $bNetworkWide );
	}

	/**
	 * @param string $sPluginFile
	 * @param bool   $bNetworkWide
	 * @return null|WP_Error
	 */
	protected function activateQuietly( $sPluginFile, $bNetworkWide = false ) {
		return activate_plugin( $sPluginFile, '', $bNetworkWide, true );
	}

	/**
	 * @param string $sPluginFile
	 * @param bool   $bNetworkWide
	 */
	public function deactivate( $sPluginFile, $bNetworkWide = false ) {
		deactivate_plugins( $sPluginFile, '', $bNetworkWide );
	}

	/**
	 * @param string $sPluginFile
	 * @param bool   $bNetworkWide
	 */
	protected function deactivateQuietly( $sPluginFile, $bNetworkWide = false ) {
		deactivate_plugins( $sPluginFile, true, $bNetworkWide );
	}

	/**
	 * @param string $sFile
	 * @param bool   $bNetworkWide
	 * @return bool
	 */
	public function delete( $sFile, $bNetworkWide = false ) {
		if ( !$this->isInstalled( $sFile ) ) {
			return false;
		}

		if ( $this->isActive( $sFile ) ) {
			$this->deactivate( $sFile, $bNetworkWide );
		}
		$this->uninstall( $sFile );

		// delete the folder
		$sPluginDir = dirname( $sFile );
		if ( $sPluginDir == '.' ) { //it's not within a sub-folder
			$sPluginDir = $sFile;
		}
		$sPath = path_join( WP_PLUGIN_DIR, $sPluginDir );
		return $this->loadFS()->deleteDir( $sPath );
	}

	/**
	 * @param string $sUrlToInstall
	 * @param bool   $bOverwrite
	 * @param bool   $bMaintenanceMode
	 * @return array
	 */
	public function install( $sUrlToInstall, $bOverwrite = true, $bMaintenanceMode = false ) {
		$this->loadWpUpgrades();

		$aResult = array(
			'successful'  => true,
			'plugin_info' => '',
			'errors'      => array()
		);

		$oUpgraderSkin = new ICWP_Upgrader_Skin();
		$oUpgrader = new ICWP_Plugin_Upgrader( $oUpgraderSkin );
		$oUpgrader->setOverwriteMode( $bOverwrite );
		if ( $bMaintenanceMode ) {
			$oUpgrader->maintenance_mode( true );
		}

		ob_start();
		$sInstallResult = $oUpgrader->install( $sUrlToInstall );
		ob_end_clean();

		if ( $bMaintenanceMode ) {
			$oUpgrader->maintenance_mode( false );
		}

		if ( is_wp_error( $oUpgraderSkin->m_aErrors[ 0 ] ) ) {
			$aResult[ 'successful' ] = false;
			$aResult[ 'errors' ] = $oUpgraderSkin->m_aErrors[ 0 ]->get_error_messages();
		}
		else {
			$aResult[ 'plugin_info' ] = $oUpgrader->plugin_info();
		}

		$aResult[ 'feedback' ] = $oUpgraderSkin->getFeedback();
		$aResult[ 'raw' ] = $sInstallResult;
		return $aResult;
	}

	/**
	 * @param $sSlug
	 * @return array|bool
	 */
	public function installFromWpOrg( $sSlug ) {
		include_once( ABSPATH.'wp-admin/includes/plugin-install.php' );

		$api = plugins_api( 'plugin_information', array(
			'slug'   => $sSlug,
			'fields' => array(
				'sections' => false,
			),
		) );

		if ( !is_wp_error( $api ) ) {
			return $this->install( $api->download_link, true, true );
		}
		return false;
	}

	/**
	 * @param string $sFile
	 * @param bool   $bUseBackup
	 * @return bool
	 */
	public function reinstall( $sFile, $bUseBackup = false ) {
		$bSuccess = false;

		if ( $this->isInstalled( $sFile ) ) {

			$sSlug = $this->getSlug( $sFile );
			if ( !empty( $sSlug ) ) {
				$oFS = $this->loadFS();

				$sDir = dirname( path_join( WP_PLUGIN_DIR, $sFile ) );
				$sBackupDir = WP_PLUGIN_DIR.'/../'.basename( $sDir ).'bak'.time();
				if ( $bUseBackup ) {
					rename( $sDir, $sBackupDir );
				}

				$aResult = $this->installFromWpOrg( $sSlug );
				$bSuccess = $aResult[ 'successful' ];
				if ( $bSuccess ) {
					wp_update_plugins(); //refreshes our update information
					if ( $bUseBackup ) {
						$oFS->deleteDir( $sBackupDir );
					}
				}
				else {
					if ( $bUseBackup ) {
						$oFS->deleteDir( $sDir );
						rename( $sBackupDir, $sDir );
					}
				}
			}
		}
		return $bSuccess;
	}

	/**
	 * @param string $sFile
	 * @return array
	 */
	public function update( $sFile ) {
		$this->loadWpUpgrades();

		$aResult = array(
			'successful' => 1,
			'errors'     => array()
		);

		$oUpgraderSkin = new ICWP_Bulk_Plugin_Upgrader_Skin();
		$oUpgrader = new Plugin_Upgrader( $oUpgraderSkin );
		ob_start();
		$oUpgrader->bulk_upgrade( array( $sFile ) );
		ob_end_clean();

		if ( isset( $oUpgraderSkin->m_aErrors[ 0 ] ) ) {
			if ( is_wp_error( $oUpgraderSkin->m_aErrors[ 0 ] ) ) {
				$aResult[ 'successful' ] = 0;
				$aResult[ 'errors' ] = $oUpgraderSkin->m_aErrors[ 0 ]->get_error_messages();
			}
		}
		$aResult[ 'feedback' ] = $oUpgraderSkin->getFeedback();
		return $aResult;
	}

	/**
	 * @param string $sPluginFile
	 * @return true
	 */
	public function uninstall( $sPluginFile ) {
		return uninstall_plugin( $sPluginFile );
	}

	/**
	 * @return boolean|null
	 */
	protected function checkForUpdates() {

		if ( class_exists( 'WPRC_Installer' ) && method_exists( 'WPRC_Installer', 'wprc_update_plugins' ) ) {
			WPRC_Installer::wprc_update_plugins();
			return true;
		}
		else if ( function_exists( 'wp_update_plugins' ) ) {
			return ( wp_update_plugins() !== false );
		}
		return null;
	}

	/**
	 */
	protected function clearUpdates() {
		$sKey = 'update_plugins';
		$oResponse = $this->loadWp()->getTransient( $sKey );
		if ( !is_object( $oResponse ) ) {
			$oResponse = new stdClass();
		}
		$oResponse->last_checked = 0;
		$this->loadWp()->setTransient( $sKey, $oResponse );
	}

	/**
	 * @param string $sValueToCompare
	 * @param string $sKey
	 * @return null|string
	 */
	public function findPluginBy( $sValueToCompare, $sKey = 'Name' ) {
		$sFile = null;

		foreach ( $this->getPlugins() as $sBaseFileName => $aPluginData ) {
			if ( isset( $aPluginData[ $sKey ] ) && $sValueToCompare == $aPluginData[ $sKey ] ) {
				$sFile = $sBaseFileName;
				break;
			}
		}

		return $sFile;
	}

	/**
	 * @param string $sFile - plugin base file, e.g. wp-folder/wp-plugin.php
	 * @return string
	 */
	public function getInstallationDir( $sFile ) {
		return dirname( path_join( WP_PLUGIN_DIR, $sFile ) );
	}

	/**
	 * @param string $sPluginFile
	 * @return array|null
	 */
	public function getPlugin( $sPluginFile ) {
		$aPlugin = null;
		if ( $this->isInstalled( $sPluginFile ) ) {
			$aPlugins = $this->getPlugins();
			$aPlugin = $aPlugins[ $sPluginFile ];
		}
		return $aPlugin;
	}

	/**
	 * @param string $sDirName
	 * @return string|null
	 */
	public function getFileFromDirName( $sDirName ) {
		$sFile = null;
		if ( !empty( $sDirName ) ) {
			foreach ( $this->getInstalledBaseFiles() as $sF ) {
				if ( strpos( $sFile, $sDirName.'/' ) === 0 ) {
					$sFile = $sF;
					break;
				}
			}
		}
		return $sFile;
	}

	/**
	 * @param string $sPluginFile
	 * @return null|stdClass
	 */
	public function getPluginDataAsObject( $sPluginFile ) {
		$aPlugin = $this->getPlugin( $sPluginFile );
		return is_null( $aPlugin ) ? null : $this->loadDP()->convertArrayToStdClass( $aPlugin );
	}

	/**
	 * @param string $sPluginFile
	 * @return int
	 */
	public function getActivePluginLoadPosition( $sPluginFile ) {
		$nPosition = array_search( $sPluginFile, $this->getActivePlugins() );
		return ( $nPosition === false ) ? -1 : $nPosition;
	}

	/**
	 * @return array
	 */
	public function getActivePlugins() {
		$oWp = $this->loadWp();
		$aActive = $oWp->getOption( ( $oWp->isMultisite() ? 'active_sitewide_plugins' : 'active_plugins' ) );
		return is_array( $aActive ) ? $aActive : array();
	}

	/**
	 * @return array
	 */
	public function getInstalledBaseFiles() {
		return array_keys( $this->getPlugins() );
	}

	/**
	 * @return array[]
	 */
	public function getPlugins() {
		if ( !function_exists( 'get_plugins' ) ) {
			require_once( ABSPATH.'wp-admin/includes/plugin.php' );
		}
		$aP = function_exists( 'get_plugins' ) ? get_plugins() : array();
		return is_array( $aP ) ? $aP : array();
	}

	/**
	 * @return stdClass[] - keys are plugin base files
	 */
	public function getAllExtendedData() {
		$oData = $this->loadWp()->getTransient( 'update_plugins' );
		return array_merge(
			isset( $oData->no_update ) ? $oData->no_update : array(),
			isset( $oData->response ) ? $oData->response : array()
		);
	}

	/**
	 * @param $sBaseFile
	 * @return null|stdClass
	 */
	public function getExtendedData( $sBaseFile ) {
		$aData = $this->getAllExtendedData();
		return isset( $aData[ $sBaseFile ] ) ? $aData[ $sBaseFile ] : null;
	}

	/**
	 * @return array
	 */
	public function getAllSlugs() {
		$aSlugs = array();

		foreach ( $this->getAllExtendedData() as $sBaseName => $oPlugData ) {
			if ( isset( $oPlugData->slug ) ) {
				$aSlugs[ $sBaseName ] = $oPlugData->slug;
			}
		}

		return $aSlugs;
	}

	/**
	 * @param $sBaseName
	 * @return string
	 */
	public function getSlug( $sBaseName ) {
		$oPluginInfo = $this->getExtendedData( $sBaseName );
		return isset( $oPluginInfo->slug ) ? $oPluginInfo->slug : '';
	}

	/**
	 * @param string $sFile
	 * @return stdClass|null
	 */
	public function getUpdateInfo( $sFile ) {
		$aU = $this->getUpdates();
		return isset( $aU[ $sFile ] ) ? $aU[ $sFile ] : null;
	}

	/**
	 * @param string $sFile
	 * @return string
	 */
	public function getUpdateNewVersion( $sFile ) {
		$oInfo = $this->getUpdateInfo( $sFile );
		return ( !is_null( $oInfo ) && isset( $oInfo->new_version ) ) ? $oInfo->new_version : '';
	}

	/**
	 * @param bool $bForceUpdateCheck
	 * @return stdClass[]
	 */
	public function getUpdates( $bForceUpdateCheck = false ) {
		if ( $bForceUpdateCheck ) {
			$this->clearUpdates();
			$this->checkForUpdates();
		}
		$aUpdates = $this->loadWp()->getWordpressUpdates( 'plugins' );
		return is_array( $aUpdates ) ? $aUpdates : array();
	}

	/**
	 * @param string $sPluginFile
	 * @return string
	 */
	public function getUrl_Activate( $sPluginFile ) {
		return $this->getUrl_Action( $sPluginFile, 'activate' );
	}

	/**
	 * @param string $sPluginFile
	 * @return string
	 */
	public function getUrl_Deactivate( $sPluginFile ) {
		return $this->getUrl_Action( $sPluginFile, 'deactivate' );
	}

	/**
	 * @param string $sPluginFile
	 * @return string
	 */
	public function getUrl_Upgrade( $sPluginFile ) {
		$aQueryArgs = array(
			'action'   => 'upgrade-plugin',
			'plugin'   => urlencode( $sPluginFile ),
			'_wpnonce' => wp_create_nonce( 'upgrade-plugin_'.$sPluginFile )
		);
		return add_query_arg( $aQueryArgs, self_admin_url( 'update.php' ) );
	}

	/**
	 * @param string $sPluginFile
	 * @param string $sAction
	 * @return string
	 */
	protected function getUrl_Action( $sPluginFile, $sAction ) {
		return add_query_arg(
			array(
				'action'   => $sAction,
				'plugin'   => urlencode( $sPluginFile ),
				'_wpnonce' => wp_create_nonce( $sAction.'-plugin_'.$sPluginFile )
			),
			self_admin_url( 'plugins.php' )
		);
	}

	/**
	 * @param string $sFile
	 * @return bool
	 */
	public function isActive( $sFile ) {
		return ( $this->isInstalled( $sFile ) && is_plugin_active( $sFile ) );
	}

	/**
	 * @param string $sFile The full plugin file.
	 * @return bool
	 */
	public function isInstalled( $sFile ) {
		return in_array( $sFile, $this->getInstalledBaseFiles() );
	}

	/**
	 * @param string $sFile
	 * @return boolean
	 */
	public function isUpdateAvailable( $sFile ) {
		return !is_null( $this->getUpdateInfo( $sFile ) );
	}

	/**
	 * @param string $sBaseName
	 * @return bool
	 */
	public function isWpOrg( $sBaseName ) {
		$oPluginInfo = $this->getExtendedData( $sBaseName );
		return isset( $oPluginInfo->id ) ? strpos( $oPluginInfo->id, 'w.org/' ) === 0 : false;
	}

	/**
	 * @param string $sFile
	 * @param int    $nDesiredPosition
	 */
	public function setActivePluginLoadPosition( $sFile, $nDesiredPosition = 0 ) {
		$oWp = $this->loadWp();

		$aActive = $this->loadDP()
						->setArrayValueToPosition(
							$oWp->getOption( 'active_plugins' ),
							$sFile,
							$nDesiredPosition
						);
		$oWp->updateOption( 'active_plugins', $aActive );

		if ( $oWp->isMultisite() ) {
			$aActive = $this->loadDP()
							->setArrayValueToPosition( $oWp->getOption( 'active_sitewide_plugins' ), $sFile, $nDesiredPosition );
			$oWp->updateOption( 'active_sitewide_plugins', $aActive );
		}
	}

	/**
	 * @param string $sFile
	 */
	public function setActivePluginLoadFirst( $sFile ) {
		$this->setActivePluginLoadPosition( $sFile, 0 );
	}

	/**
	 * @param string $sFile
	 */
	public function setActivePluginLoadLast( $sFile ) {
		$this->setActivePluginLoadPosition( $sFile, 1000 );
	}

	/**
	 * @deprecated
	 * @param string $sPluginFile
	 * @return string
	 */
	public function getLinkPluginUpgrade( $sPluginFile ) {
		return $this->getUrl_Upgrade( $sPluginFile );
	}

	/**
	 * @deprecated
	 * @return array
	 */
	public function getInstalledPluginFiles() {
		return $this->getInstalledBaseFiles();
	}
}