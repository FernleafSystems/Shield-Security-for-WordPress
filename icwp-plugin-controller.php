<?php
/**
 * Copyright (c) 2017 iControlWP <support@icontrolwp.com>
 * All rights reserved.
 *
 * "WordPress Simple Firewall" is distributed under the GNU General Public License, Version 2,
 * June 1991. Copyright (C) 1989, 1991 Free Software Foundation, Inc., 51 Franklin
 * St, Fifth Floor, Boston, MA 02110, USA
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR
 * ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
 * ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

if ( !class_exists( 'ICWP_WPSF_Plugin_Controller', false ) ) :

class ICWP_WPSF_Plugin_Controller extends ICWP_WPSF_Foundation {

	/**
	 * @var stdClass
	 */
	private static $oControllerOptions;

	/**
	 * @var ICWP_WPSF_Plugin_Controller
	 */
	public static $oInstance;

	/**
	 * @var string
	 */
	private static $sRootFile;

	/**
	 * @var boolean
	 */
	protected $bRebuildOptions;

	/**
	 * @var boolean
	 */
	protected $bForceOffState;

	/**
	 * @var boolean
	 */
	protected $bResetPlugin;

	/**
	 * @var string
	 */
	private $sPluginUrl;

	/**
	 * @var string
	 */
	private $sPluginBaseFile;

	/**
	 * @var array
	 */
	private $aRequirementsMessages;

	/**
	 * @var array
	 */
	private $aImportedOptions;

	/**
	 * @var string
	 */
	protected static $sSessionId;

	/**
	 * @var string
	 */
	protected static $sRequestId;

	/**
	 * @var string
	 */
	private $sConfigOptionsHashWhenLoaded;

	/**
	 * @var boolean
	 */
	protected $bMeetsBasePermissions = false;

	/**
	 * @param $sRootFile
	 * @return ICWP_WPSF_Plugin_Controller
	 */
	public static function GetInstance( $sRootFile ) {
		if ( !isset( self::$oInstance ) ) {
			try {
				self::$oInstance = new self( $sRootFile );
			}
			catch( Exception $oE ) {
				return null;
			}
		}
		return self::$oInstance;
	}

	/**
	 * @param string $sRootFile
	 * @throws Exception
	 */
	private function __construct( $sRootFile ) {
		self::$sRootFile = $sRootFile;
		$this->checkMinimumRequirements();
		add_action( 'plugins_loaded', array( $this, 'onWpPluginsLoaded' ), 0 ); // this hook then registers everything
		$this->loadWpTrack();
		$this->loadFactory(); // so we know it's loaded whenever we need it. Cuz we need it.
	}

	/**
	 * @return array
	 * @throws Exception
	 */
	private function readPluginSpecification() {
		$aSpec = array();
		$sContents = $this->loadDataProcessor()->readFileContentsUsingInclude( $this->getPathPluginSpec() );
		if ( !empty( $sContents ) ) {
			$aSpec = json_decode( $sContents, true );
			if ( empty( $aSpec ) ) {
				throw new Exception( 'YAML parser could not load to process the plugin spec configuration.' );
			}
		}
		return $aSpec;
	}

	/**
	 * @param bool $bCheckOnlyFrontEnd
	 * @throws Exception
	 */
	private function checkMinimumRequirements( $bCheckOnlyFrontEnd = true ) {
		if ( $bCheckOnlyFrontEnd && !is_admin() ) {
			return;
		}

		$bMeetsRequirements = true;
		$aRequirementsMessages = $this->getRequirementsMessages();

		$sMinimumPhp = $this->getPluginSpec_Requirement( 'php' );
		if ( !empty( $sMinimumPhp ) ) {
			if ( version_compare( phpversion(), $sMinimumPhp, '<' ) ) {
				$aRequirementsMessages[] = sprintf( 'PHP does not meet minimum version. Your version: %s.  Required Version: %s.', PHP_VERSION, $sMinimumPhp );
				$bMeetsRequirements = false;
			}
		}

		$sMinimumWp = $this->getPluginSpec_Requirement( 'wordpress' );
		if ( !empty( $sMinimumWp ) ) {
			$sWpVersion = $this->loadWpFunctionsProcessor()->getWordpressVersion();
			if ( version_compare( $sWpVersion, $sMinimumWp, '<' ) ) {
				$aRequirementsMessages[] = sprintf( 'WordPress does not meet minimum version. Your version: %s.  Required Version: %s.', $sWpVersion, $sMinimumWp );
				$bMeetsRequirements = false;
			}
		}

		if ( !$bMeetsRequirements ) {
			$this->aRequirementsMessages = $aRequirementsMessages;
			add_action(	'admin_menu', array( $this, 'adminNoticeDoesNotMeetRequirements' ) );
			add_action(	'network_admin_notices', array( $this, 'adminNoticeDoesNotMeetRequirements' ) );
			throw new Exception( 'Plugin does not meet minimum requirements' );
		}
	}

	/**
	 */
	public function adminNoticeDoesNotMeetRequirements() {
		$aMessages = $this->getRequirementsMessages();
		if ( !empty( $aMessages ) && is_array( $aMessages ) ) {
			$aDisplayData = array(
				'strings' => array(
					'requirements' => $aMessages,
					'summary_title' => sprintf( 'Web Hosting requirements for Plugin "%s" are not met and you should deactivate the plugin.', $this->getHumanName() ),
					'more_information' => 'Click here for more information on requirements'
				),
				'hrefs' => array(
					'more_information' => sprintf( 'https://wordpress.org/plugins/%s/faq', $this->getTextDomain() )
				)
			);

			$this->loadRenderer( $this->getPath_Templates() )
				 ->setTemplate( 'notices/does-not-meet-requirements' )
				 ->setRenderVars( $aDisplayData )
				 ->display();
		}
	}

	/**
	 * @return array
	 */
	protected function getRequirementsMessages() {
		if ( !isset( $this->aRequirementsMessages ) ) {
			$this->aRequirementsMessages = array();
		}
		return $this->aRequirementsMessages;
	}

	/**
	 * Registers the plugins activation, deactivate and uninstall hooks.
	 */
	protected function registerActivationHooks() {
		register_activation_hook( $this->getRootFile(), array( $this, 'onWpActivatePlugin' ) );
		register_deactivation_hook( $this->getRootFile(), array( $this, 'onWpDeactivatePlugin' ) );
		//	register_uninstall_hook( $this->oPluginVo->getRootFile(), array( $this, 'onWpUninstallPlugin' ) );
	}

	/**
	 */
	public function onWpDeactivatePlugin() {
		if ( current_user_can( $this->getBasePermissions() ) && apply_filters( $this->doPluginPrefix( 'delete_on_deactivate' ), false ) ) {
			do_action( $this->doPluginPrefix( 'delete_plugin' ) );
			$this->deletePluginControllerOptions();
		}
	}

	public function onWpActivatePlugin() {
		do_action( $this->doPluginPrefix( 'plugin_activate' ) );
		$this->loadAllFeatures( true, true );
	}

	/**
	 */
	public function onWpPluginsLoaded() {
		$this->doLoadTextDomain();
		$this->doRegisterHooks();
	}

	/**
	 */
	protected function doRegisterHooks() {
		$this->registerActivationHooks();

		add_action( 'init',			        			array( $this, 'onWpInit' ) );
		add_action( 'admin_init',						array( $this, 'onWpAdminInit' ) );
		add_action( 'wp_loaded',			    		array( $this, 'onWpLoaded' ) );

		add_action( 'admin_menu',						array( $this, 'onWpAdminMenu' ) );
		add_action(	'network_admin_menu',				array( $this, 'onWpAdminMenu' ) );

		add_filter( 'all_plugins', 						array( $this, 'filter_hidePluginFromTableList' ) );
		add_filter( 'all_plugins',						array( $this, 'doPluginLabels' ) );
		add_filter( 'plugin_action_links_'.$this->getPluginBaseFile(), array( $this, 'onWpPluginActionLinks' ), 50, 1 );
		add_filter( 'plugin_row_meta',					array( $this, 'onPluginRowMeta' ), 50, 2 );
		add_filter( 'site_transient_update_plugins',	array( $this, 'filter_hidePluginUpdatesFromUI' ) );
		add_action( 'in_plugin_update_message-'.$this->getPluginBaseFile(), array( $this, 'onWpPluginUpdateMessage' ) );

		add_filter( 'auto_update_plugin',						array( $this, 'onWpAutoUpdate' ), 10001, 2 );
		add_filter( 'set_site_transient_update_plugins',		array( $this, 'setUpdateFirstDetectedAt' ) );

		add_action( 'shutdown',							array( $this, 'onWpShutdown' ) );
		add_action( 'wp_logout', array( $this, 'onWpLogout' ) );

		// outsource the collection of admin notices
		if ( is_admin() ) {
			$this->loadAdminNoticesProcessor()->setActionPrefix( $this->doPluginPrefix() );
		}
	}

	/**
	 */
	public function onWpAdminInit() {
		if ( $this->getPluginSpec_Property( 'show_dashboard_widget' ) === true ) {
			add_action( 'wp_dashboard_setup', array( $this, 'onWpDashboardSetup' ) );
		}
		add_action( 'admin_enqueue_scripts', 	array( $this, 'onWpEnqueueAdminCss' ), 100 );
		add_action( 'admin_enqueue_scripts', 	array( $this, 'onWpEnqueueAdminJs' ), 99 );
	}

	/**
	 */
	public function onWpLoaded() {
		if ( $this->getIsValidAdminArea() ) {
			$this->doPluginFormSubmit();
			$this->downloadOptionsExport();
		}
	}

	/**
	 */
	public function onWpInit() {
		add_action( 'wp_enqueue_scripts', array( $this, 'onWpEnqueueFrontendCss' ), 99 );
		$this->bMeetsBasePermissions = current_user_can( $this->getBasePermissions() );
	}

	/**
	 * @return bool
	 */
	public function onWpAdminMenu() {
		return ( $this->getIsValidAdminArea() ? $this->createPluginMenu() : true );
	}

	/**
	 */
	public function onWpDashboardSetup() {
		if ( $this->getIsValidAdminArea() ) {
			wp_add_dashboard_widget(
				$this->doPluginPrefix( 'dashboard_widget' ),
				apply_filters( $this->doPluginPrefix( 'dashboard_widget_title' ), $this->getHumanName() ),
				array( $this, 'displayDashboardWidget' )
			);
		}
	}

	public function displayDashboardWidget() {
		$aContent = apply_filters( $this->doPluginPrefix( 'dashboard_widget_content' ), array() );
		echo implode( '', $aContent );
	}

	/**
	 * v5.4.1: Nasty looping bug in here where this function was called within the 'user_has_cap' filter
	 * so we removed the "current_user_can()" or any such sub-call within this function
	 * @return bool
	 */
	public function getHasPermissionToManage() {
		if ( apply_filters( $this->doPluginPrefix( 'bypass_permission_to_manage' ), false ) ) {
			return true;
		}
		return ( $this->getMeetsBasePermissions() && apply_filters( $this->doPluginPrefix( 'has_permission_to_manage' ), true ) );
	}

	/**
	 * Must be simple and cannot contain anything that would call filter "user_has_cap", e.g. current_user_can()
	 * @return boolean
	 */
	public function getMeetsBasePermissions() {
		return $this->bMeetsBasePermissions;
	}

	/**
	 */
	public function getHasPermissionToView() {
		return $this->getHasPermissionToManage(); // TODO: separate view vs manage
	}

	/**
	 * @uses die()
	 */
	private function downloadOptionsExport() {
		$oDp = $this->loadDataProcessor();
		if ( $oDp->FetchGet( 'icwp_shield_export' ) == 1 ) {
			$aExportOptions = apply_filters( $this->doPluginPrefix( 'gather_options_for_export' ), array() );
			if ( !empty( $aExportOptions ) && is_array( $aExportOptions ) ) {
				$oDp->downloadStringAsFile(
					wp_json_encode( $aExportOptions ),
					'shield_options_export-'
					. $this->loadWpFunctionsProcessor()->getHomeUrl( true )
					.'-'.date('ymdHis').'.txt'
				);
			}
		}
	}

	/**
	 * @return string
	 */
	public function getOptionsEncoding() {
		$sEncoding = $this->getPluginSpec_Property( 'options_encoding' );
		return in_array( $sEncoding, array( 'yaml', 'json' ) ) ? $sEncoding : 'yaml';
	}

	/**
	 * @uses die()
	 */
	public function getOptionsImportFromFile() {

		if ( !isset( $this->aImportedOptions ) ) {
			$this->aImportedOptions = array();

			$sFile = path_join( $this->getRootDir(), 'shield_options_export.txt' );
			$oFS = $this->loadFileSystemProcessor();
			if ( $oFS->isFile( $sFile ) ) {
				$sOptionsString = $oFS->getFileContent( $sFile );
				if ( !empty( $sOptionsString ) && is_string( $sOptionsString ) ) {
					$aOptions = json_decode( $sOptionsString, true );
					if ( !empty( $aOptions ) && is_array( $aOptions ) ) {
						$this->aImportedOptions = $aOptions;
					}
				}
				$oFS->deleteFile( $sFile );
			}
		}
		return $this->aImportedOptions;
	}

	/**
	 * @return bool
	 */
	protected function createPluginMenu() {

		$bHideMenu = apply_filters( $this->doPluginPrefix( 'filter_hidePluginMenu' ), !$this->getPluginSpec_Menu( 'show' ) );
		if ( $bHideMenu ) {
			return true;
		}

		if ( $this->getPluginSpec_Menu( 'top_level' ) ) {

			$aPluginLabels = $this->getPluginLabels();

			$sMenuTitle = $this->getPluginSpec_Menu( 'title' );
			if ( is_null( $sMenuTitle ) ) {
				$sMenuTitle = $this->getHumanName();
			}

			$sMenuIcon = $this->getPluginUrl_Image( $this->getPluginSpec_Menu( 'icon_image' ) );
			$sIconUrl = empty( $aPluginLabels['icon_url_16x16'] ) ? $sMenuIcon : $aPluginLabels['icon_url_16x16'];

			$sFullParentMenuId = $this->getPluginPrefix();
			add_menu_page(
				$this->getHumanName(),
				$sMenuTitle,
				$this->getBasePermissions(),
				$sFullParentMenuId,
				array( $this, $this->getPluginSpec_Menu( 'callback' ) ),
				$sIconUrl
			);

			if ( $this->getPluginSpec_Menu( 'has_submenu' ) ) {

				$aPluginMenuItems = apply_filters( $this->doPluginPrefix( 'filter_plugin_submenu_items' ), array() );
				if ( !empty( $aPluginMenuItems ) ) {
					foreach ( $aPluginMenuItems as $sMenuTitle => $aMenu ) {
						list( $sMenuItemText, $sMenuItemId, $aMenuCallBack ) = $aMenu;
						add_submenu_page(
							$sFullParentMenuId,
							$sMenuTitle,
							$sMenuItemText,
							$this->getBasePermissions(),
							$sMenuItemId,
							$aMenuCallBack
						);
					}
				}
			}

			if ( $this->getPluginSpec_Menu( 'do_submenu_fix' ) ) {
				$this->fixSubmenu();
			}
		}
		return true;
	}

	protected function fixSubmenu() {
		global $submenu;
		$sFullParentMenuId = $this->getPluginPrefix();
		if ( isset( $submenu[$sFullParentMenuId] ) ) {
			unset( $submenu[$sFullParentMenuId][0] );
		}
	}

	/**
	 * Displaying all views now goes through this central function and we work out
	 * what to display based on the name of current hook/filter being processed.
	 */
	public function onDisplayTopMenu() { }

	/**
	 * @param array $aPluginMeta
	 * @param string $sPluginFile
	 * @return array
	 */
	public function onPluginRowMeta( $aPluginMeta, $sPluginFile ) {

		if ( $sPluginFile == $this->getPluginBaseFile() ) {
			$aMeta = $this->getPluginSpec_PluginMeta();

			$sLinkTemplate = '<strong><a href="%s" target="%s">%s</a></strong>';
			foreach( $aMeta as $aMetaLink ){
				$sSettingsLink = sprintf( $sLinkTemplate, $aMetaLink['href'], "_blank", $aMetaLink['name'] ); ;
				array_push( $aPluginMeta, $sSettingsLink );
			}
		}
		return $aPluginMeta;
	}

	/**
	 * @param array $aActionLinks
	 * @return array
	 */
	public function onWpPluginActionLinks( $aActionLinks ) {

		if ( $this->getIsValidAdminArea() ) {

			$aLinksToAdd = $this->getPluginSpec_ActionLinks( 'add' );
			if ( !empty( $aLinksToAdd ) && is_array( $aLinksToAdd ) ) {

				$sLinkTemplate = '<a href="%s" target="%s">%s</a>';
				foreach( $aLinksToAdd as $aLink ){
					if ( empty( $aLink['name'] ) || ( empty( $aLink['url_method_name'] ) && empty( $aLink['href'] ) ) ) {
						continue;
					}

					if ( !empty( $aLink['url_method_name'] ) ) {
						$sMethod = $aLink['url_method_name'];
						if ( method_exists( $this, $sMethod ) ) {
							$sSettingsLink = sprintf( $sLinkTemplate, $this->{$sMethod}(), "_top", $aLink['name'] ); ;
							array_unshift( $aActionLinks, $sSettingsLink );
						}
					}
					else if ( !empty( $aLink['href'] ) ) {
						$sSettingsLink = sprintf( $sLinkTemplate, $aLink['href'], "_blank", $aLink['name'] ); ;
						array_unshift( $aActionLinks, $sSettingsLink );
					}

				}
			}
		}
		return $aActionLinks;
	}

	public function onWpEnqueueFrontendCss() {

		$aFrontendIncludes = $this->getPluginSpec_Include( 'frontend' );
		if ( isset( $aFrontendIncludes['css'] ) && !empty( $aFrontendIncludes['css'] ) && is_array( $aFrontendIncludes['css'] ) ) {
			foreach( $aFrontendIncludes['css'] as $sCssAsset ) {
				$sUnique = $this->doPluginPrefix( $sCssAsset );
				wp_register_style( $sUnique, $this->getPluginUrl_Css( $sCssAsset.'.css' ), ( empty( $sDependent ) ? false : $sDependent ), $this->getVersion() );
				wp_enqueue_style( $sUnique );
				$sDependent = $sUnique;
			}
		}
	}

	public function onWpEnqueueAdminJs() {

		if ( $this->getIsPage_PluginAdmin() ) {
			$aAdminJs = $this->getPluginSpec_Include( 'plugin_admin' );
			if ( isset( $aAdminJs['js'] ) && !empty( $aAdminJs['js'] ) && is_array( $aAdminJs['js'] ) ) {
				$sDependent = false;
				foreach( $aAdminJs['js'] as $sJsAsset ) {
					$sUrl = $this->getPluginUrl_Js( $sJsAsset . '.js' );
					if ( !empty( $sUrl ) ) {
						$sUnique = $this->doPluginPrefix( $sJsAsset );
						wp_register_script( $sUnique, $sUrl, $sDependent, $this->getVersion() );
						wp_enqueue_script( $sUnique );
						$sDependent = $sUnique;
					}
				}
			}
		}
	}

	public function onWpEnqueueAdminCss() {

		if ( $this->getIsValidAdminArea() ) {
			$aAdminCss = $this->getPluginSpec_Include( 'admin' );
			if ( isset( $aAdminCss['css'] ) && !empty( $aAdminCss['css'] ) && is_array( $aAdminCss['css'] ) ) {
				$sDependent = false;
				foreach( $aAdminCss['css'] as $sCssAsset ) {
					$sUrl = $this->getPluginUrl_Css( $sCssAsset . '.css' );
					if ( !empty( $sUrl ) ) {
						$sUnique = $this->doPluginPrefix( $sCssAsset );
						wp_register_style( $sUnique, $sUrl, $sDependent, $this->getVersion().rand() );
						wp_enqueue_style( $sUnique );
						$sDependent = $sUnique;
					}
				}
			}
		}

		if ( $this->getIsPage_PluginAdmin() ) {
			$aAdminCss = $this->getPluginSpec_Include( 'plugin_admin' );
			if ( isset( $aAdminCss['css'] ) && !empty( $aAdminCss['css'] ) && is_array( $aAdminCss['css'] ) ) {
				$sDependent = false;
				foreach( $aAdminCss['css'] as $sCssAsset ) {
					$sUrl = $this->getPluginUrl_Css( $sCssAsset . '.css' );
					if ( !empty( $sUrl ) ) {
						$sUnique = $this->doPluginPrefix( $sCssAsset );
						wp_register_style( $sUnique, $sUrl, $sDependent, $this->getVersion().rand() );
						wp_enqueue_style( $sUnique );
						$sDependent = $sUnique;
					}
				}
			}
		}
	}

	/**
	 * Displays a message in the plugins listing when a plugin has an update available.
	 */
	public function onWpPluginUpdateMessage() {
		$sDefault = sprintf( 'Upgrade Now To Get The Latest Available %s Features.', $this->getHumanName() );
		$sMessage = apply_filters( $this->doPluginPrefix( 'plugin_update_message' ), $sDefault );
		if ( empty( $sMessage ) ) {
			$sMessage = '';
		}
		else {
			$sMessage = sprintf(
				'<div class="%s plugin_update_message">%s</div>',
				$this->getPluginPrefix(),
				$sMessage
			);
		}
		echo $sMessage;
	}

	/**
	 * This will hook into the saving of plugin update information and if there is an update for this plugin, it'll add
	 * a data stamp to state when the update was first detected.
	 *
	 * @param stdClass $oPluginUpdateData
	 * @return stdClass
	 */
	public function setUpdateFirstDetectedAt( $oPluginUpdateData ) {

		if ( !empty( $oPluginUpdateData ) && !empty( $oPluginUpdateData->response )
			&& isset( $oPluginUpdateData->response[ $this->getPluginBaseFile() ] ) ) {
			// i.e. there's an update available
			$sNewVersion = $this->loadWpFunctionsProcessor()->getPluginUpdateNewVersion( $this->getPluginBaseFile() );
			if ( !empty( $sNewVersion ) ) {
				$oConOptions = $this->getPluginControllerOptions();
				if ( !isset( $oConOptions->update_first_detected ) || ( count( $oConOptions->update_first_detected ) > 3 ) ) {
					$oConOptions->update_first_detected = array();
				}
				if ( !isset( $oConOptions->update_first_detected[ $sNewVersion ] ) ) {
					$oConOptions->update_first_detected[ $sNewVersion ] = $this->loadDataProcessor()->time();
				}

				// a bit of cleanup to remove the old-style entries which would gather foreva-eva
				foreach ( $oConOptions as $sKey => $aData ) {
					if ( strpos( $sKey, 'update_first_detected_' ) !== false ) {
						unset( $oConOptions->{$sKey} );
					}
				}
			}
		}

		return $oPluginUpdateData;
	}

	/**
	 * This is a filter method designed to say whether WordPress plugin upgrades should be permitted,
	 * based on the plugin settings.
	 *
	 * @param boolean $bDoAutoUpdate
	 * @param string|object $mItemToUpdate
	 * @return boolean
	 */
	public function onWpAutoUpdate( $bDoAutoUpdate, $mItemToUpdate ) {

		if ( is_object( $mItemToUpdate ) && !empty( $mItemToUpdate->plugin ) ) { // 3.8.2+
			$sItemFile = $mItemToUpdate->plugin;
		}
		else if ( is_string( $mItemToUpdate ) && !empty( $mItemToUpdate ) ) { //pre-3.8.2
			$sItemFile = $mItemToUpdate;
		}
		else {
			// at this point we don't have a slug/file to use so we just return the current update setting
			return $bDoAutoUpdate;
		}

		// The item in question is this plugin...
		if ( $sItemFile === $this->getPluginBaseFile() ) {
			$sAutoupdateSpec = $this->getPluginSpec_Property( 'autoupdate' );

			$oWp = $this->loadWpFunctionsProcessor();
			$oConOptions = $this->getPluginControllerOptions();

			if ( !$oWp->getIsRunningAutomaticUpdates() && $sAutoupdateSpec == 'confidence' ) {
				$sAutoupdateSpec = 'yes';
			}

			switch( $sAutoupdateSpec ) {

				case 'yes' :
					$bDoAutoUpdate = true;
					break;

				case 'block' :
					$bDoAutoUpdate = false;
					break;

				case 'confidence' :
					$bDoAutoUpdate = false;
					$sNewVersion = $oWp->getPluginUpdateNewVersion( $this->getPluginBaseFile() );
					if ( !empty( $sNewVersion ) ) {
						$nFirstDetected = isset( $oConOptions->update_first_detected[ $sNewVersion ] ) ? $oConOptions->update_first_detected[ $sNewVersion ] : 0;
						$nTimeUpdateAvailable =  $this->loadDataProcessor()->time() - $nFirstDetected;
						$bDoAutoUpdate = ( $nFirstDetected > 0 && ( $nTimeUpdateAvailable > DAY_IN_SECONDS * 2 ) );
					}
					break;

				case 'pass' :
					// Add block to version 6.0 if PHP < 5.3
					$sNewVersion = $oWp->getPluginUpdateNewVersion( $this->getPluginBaseFile() );
					if ( version_compare( $sNewVersion, '6.0.0', '>=' ) && !$this->loadDataProcessor()->getPhpVersionIsAtLeast( '5.3.0' ) ) {
						$bDoAutoUpdate = false;
					}
					break;

				default:
					break;

			}
		}
		return $bDoAutoUpdate;
	}

	/**
	 * @param array $aPlugins
	 *
	 * @return array
	 */
	public function doPluginLabels( $aPlugins ) {
		$aLabelData = $this->getPluginLabels();
		if ( empty( $aLabelData ) ) {
			return $aPlugins;
		}

		$sPluginFile = $this->getPluginBaseFile();
		// For this plugin, overwrite any specified settings
		if ( array_key_exists( $sPluginFile, $aPlugins ) ) {
			foreach ( $aLabelData as $sLabelKey => $sLabel ) {
				$aPlugins[$sPluginFile][$sLabelKey] = $sLabel;
			}
		}

		return $aPlugins;
	}

	/**
	 * @return array
	 */
	public function getPluginLabels() {
		return apply_filters( $this->doPluginPrefix( 'plugin_labels' ), $this->getPluginSpec_Labels() );
	}

	/**
	 * Hooked to 'shutdown'
	 */
	public function onWpShutdown() {
		do_action( $this->doPluginPrefix( 'pre_plugin_shutdown' ) );
		do_action( $this->doPluginPrefix( 'plugin_shutdown' ) );
		$this->saveCurrentPluginControllerOptions();
		$this->deleteFlags();
	}

	/**
	 */
	public function onWpLogout() {
		if ( $this->hasSessionId() ) {
			$this->clearSession();
		}
	}

	/**
	 */
	protected function deleteFlags() {
		$oFS = $this->loadFileSystemProcessor();
		if ( $oFS->exists( $this->getPath_Flags( 'rebuild' ) ) ) {
			$oFS->deleteFile( $this->getPath_Flags( 'rebuild' ) );
		}
		if ( $this->getIsResetPlugin() ) {
			$oFS->deleteFile( $this->getPath_Flags( 'reset' ) );
		}
	}

	/**
	 * Added to a WordPress filter ('all_plugins') which will remove this particular plugin from the
	 * list of all plugins based on the "plugin file" name.
	 *
	 * @param array $aPlugins
	 * @return array
	 */
	public function filter_hidePluginFromTableList( $aPlugins ) {

		$bHide = apply_filters( $this->doPluginPrefix( 'hide_plugin' ), false );
		if ( !$bHide ) {
			return $aPlugins;
		}

		$sPluginBaseFileName = $this->getPluginBaseFile();
		if ( isset( $aPlugins[$sPluginBaseFileName] ) ) {
			unset( $aPlugins[$sPluginBaseFileName] );
		}
		return $aPlugins;
	}

	/**
	 * Added to the WordPress filter ('site_transient_update_plugins') in order to remove visibility of updates
	 * from the WordPress Admin UI.
	 *
	 * In order to ensure that WordPress still checks for plugin updates it will not remove this plugin from
	 * the list of plugins if DOING_CRON is set to true.
	 *
	 * @uses $this->fHeadless if the plugin is headless, it is hidden
	 * @param StdClass $oPlugins
	 * @return StdClass
	 */
	public function filter_hidePluginUpdatesFromUI( $oPlugins ) {

		if ( $this->loadWpFunctionsProcessor()->getIsCron() ) {
			return $oPlugins;
		}
		if ( ! apply_filters( $this->doPluginPrefix( 'hide_plugin_updates' ), false ) ) {
			return $oPlugins;
		}
		if ( isset( $oPlugins->response[ $this->getPluginBaseFile() ] ) ) {
			unset( $oPlugins->response[ $this->getPluginBaseFile() ] );
		}
		return $oPlugins;
	}

	/**
	 */
	protected function doLoadTextDomain() {
		return load_plugin_textdomain(
			$this->getTextDomain(),
			false,
			plugin_basename( $this->getPath_Languages() )
		);
	}

	/**
	 * @return bool
	 */
	protected function doPluginFormSubmit() {
		if ( !$this->getIsPluginFormSubmit() ) {
			return false;
		}

		// do all the plugin feature/options saving
		do_action( $this->doPluginPrefix( 'form_submit' ) );

		if ( $this->getIsPage_PluginAdmin() ) {
			$oWp = $this->loadWpFunctionsProcessor();
			$oWp->doRedirect( $oWp->getUrl_CurrentAdminPage() );
		}
		return true;
	}

	/**
	 * @param string $sSuffix
	 * @param string $sGlue
	 * @return string
	 */
	public function doPluginPrefix( $sSuffix = '', $sGlue = '-' ) {
		$sPrefix = $this->getPluginPrefix( $sGlue );

		if ( $sSuffix == $sPrefix || strpos( $sSuffix, $sPrefix.$sGlue ) === 0 ) { //it already has the full prefix
			return $sSuffix;
		}

		return sprintf( '%s%s%s', $sPrefix, empty($sSuffix)? '' : $sGlue, empty($sSuffix)? '' : $sSuffix );
	}

	/**
	 * @param string $sSuffix
	 * @return string
	 */
	public function doPluginOptionPrefix( $sSuffix = '' ) {
		return $this->doPluginPrefix( $sSuffix, '_' );
	}

	/**
	 * @param string $sKey
	 * @return mixed|null
	 */
	protected function getPluginSpec_ActionLinks( $sKey ) {
		$oConOptions = $this->getPluginControllerOptions();
		return isset( $oConOptions->plugin_spec['action_links'][$sKey] ) ? $oConOptions->plugin_spec['action_links'][$sKey] : null;
	}

	/**
	 * @param string $sKey
	 * @return mixed|null
	 */
	protected function getPluginSpec_Include( $sKey ) {
		$oConOptions = $this->getPluginControllerOptions();
		return isset( $oConOptions->plugin_spec['includes'][$sKey] ) ? $oConOptions->plugin_spec['includes'][$sKey] : null;
	}

	/**
	 * @param string $sKey
	 * @return array|string
	 */
	protected function getPluginSpec_Labels( $sKey = '' ) {
		$oConOptions = $this->getPluginControllerOptions();
		$aLabels = isset( $oConOptions->plugin_spec['labels'] ) ? $oConOptions->plugin_spec[ 'labels' ] : array();
		//Prep the icon urls
		if ( !empty( $aLabels['icon_url_16x16'] ) ) {
			$aLabels['icon_url_16x16'] = $this->getPluginUrl_Image( $aLabels['icon_url_16x16'] );
		}
		if ( !empty( $aLabels['icon_url_32x32'] ) ) {
			$aLabels['icon_url_32x32'] = $this->getPluginUrl_Image( $aLabels['icon_url_32x32'] );
		}

		if ( empty( $sKey ) ) {
			return $aLabels;
		}

		return isset( $oConOptions->plugin_spec['labels'][$sKey] ) ? $oConOptions->plugin_spec['labels'][$sKey] : null;
	}

	/**
	 * @param string $sKey
	 * @return mixed|null
	 */
	protected function getPluginSpec_Menu( $sKey ) {
		$oConOptions = $this->getPluginControllerOptions();
		return isset( $oConOptions->plugin_spec['menu'][$sKey] ) ? $oConOptions->plugin_spec['menu'][$sKey] : null;
	}

	/**
	 * @param string $sKey
	 * @return mixed|null
	 */
	protected function getPluginSpec_Path( $sKey ) {
		$oConOptions = $this->getPluginControllerOptions();
		return isset( $oConOptions->plugin_spec['paths'][$sKey] ) ? $oConOptions->plugin_spec['paths'][$sKey] : null;
	}

	/**
	 * @param string $sKey
	 * @return mixed|null
	 */
	protected function getPluginSpec_Property( $sKey ) {
		$oConOptions = $this->getPluginControllerOptions();
		return isset( $oConOptions->plugin_spec['properties'][$sKey] ) ? $oConOptions->plugin_spec['properties'][$sKey] : null;
	}

	/**
	 * @return array
	 */
	protected function getPluginSpec_PluginMeta() {
		$oConOptions = $this->getPluginControllerOptions();
		return ( isset( $oConOptions->plugin_spec['plugin_meta'] ) && is_array( $oConOptions->plugin_spec['plugin_meta'] ) ) ? $oConOptions->plugin_spec['plugin_meta'] : array();
	}

	/**
	 * @param string $sKey
	 * @return mixed|null
	 */
	protected function getPluginSpec_Requirement( $sKey ) {
		$oConOptions = $this->getPluginControllerOptions();
		return isset( $oConOptions->plugin_spec['requirements'][$sKey] ) ? $oConOptions->plugin_spec['requirements'][$sKey] : null;
	}

	/**
	 * @return string
	 */
	public function getBasePermissions() {
		return $this->getPluginSpec_Property( 'base_permissions' );
	}

	/**
	 * @param bool $bCheckUserPermissions
	 * @return bool
	 */
	public function getIsValidAdminArea( $bCheckUserPermissions = true ) {
		if ( $bCheckUserPermissions && $this->loadWpTrack()->getWpActionHasFired( 'init' ) && !current_user_can( $this->getBasePermissions() ) ) {
			return false;
		}

		$oWp = $this->loadWpFunctionsProcessor();
		if ( !$oWp->isMultisite() && is_admin() ) {
			return true;
		}
		else if ( $oWp->isMultisite() && $this->getIsWpmsNetworkAdminOnly() && is_network_admin() ) {
			return true;
		}
		return false;
	}

	/**
	 * @param string
	 * @return string
	 */
	public function getOptionStoragePrefix() {
		return $this->getPluginPrefix( '_' ).'_';
	}

	/**
	 * @param string
	 * @return string
	 */
	public function getPluginPrefix( $sGlue = '-' ) {
		return sprintf( '%s%s%s', $this->getParentSlug(), $sGlue, $this->getPluginSlug() );
	}

	/**
	 * Default is to take the 'Name' from the labels section but can override with "human_name" from property section.
	 *
	 * @return string
	 */
	public function getHumanName() {
		$aLabels = $this->getPluginLabels();
		return empty( $aLabels['Name'] ) ? $this->getPluginSpec_Property( 'human_name' ) : $aLabels['Name'] ;
	}

	/**
	 * @return string
	 */
	public function getIsLoggingEnabled() {
		return $this->getPluginSpec_Property( 'logging_enabled' );
	}

	/**
	 * @return bool
	 */
	public function getIsPage_PluginAdmin() {
		return ( strpos( $this->loadWpFunctionsProcessor()->getCurrentWpAdminPage(), $this->getPluginPrefix() ) === 0 );
	}

	/**
	 * @return bool
	 */
	public function getIsPage_PluginMainDashboard() {
		return ( $this->loadWpFunctionsProcessor()->getCurrentWpAdminPage() == $this->getPluginPrefix() );
	}

	/**
	 * @return bool
	 */
	protected function getIsPluginFormSubmit() {
		if ( empty( $_POST ) && empty( $_GET ) ) {
			return false;
		}

		$aFormSubmitOptions = array(
			$this->doPluginOptionPrefix( 'plugin_form_submit' ),
			'icwp_link_action'
		);

		$oDp = $this->loadDataProcessor();
		foreach( $aFormSubmitOptions as $sOption ) {
			if ( !is_null( $oDp->FetchRequest( $sOption, false ) ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @return boolean
	 */
	public function getIsRebuildOptionsFromFile() {
		if ( isset( $this->bRebuildOptions ) ) {
			return $this->bRebuildOptions;
		}

		// The first choice is to look for the file hash. If it's "always" empty, it means we could never
		// hash the file in the first place so it's not ever effectively used and it falls back to the rebuild file
		$oConOptions = $this->getPluginControllerOptions();
		$sSpecPath = $this->getPathPluginSpec();
		$sCurrentHash = @md5_file( $sSpecPath );
		$sModifiedTime = $this->loadFileSystemProcessor()->getModifiedTime( $sSpecPath );

		if ( empty( $oConOptions->plugin_spec ) ) {
			$this->bRebuildOptions = true;
		}
		else if ( !empty( $oConOptions->hash ) && is_string( $oConOptions->hash ) && strlen( $oConOptions->hash ) == 32 ) {

			if ( $oConOptions->hash == $sCurrentHash ) {
				$this->bRebuildOptions = false;
			}
			else {
				$this->bRebuildOptions = true;
			}
		}
		else if ( !empty( $oConOptions->mod_time ) ) {
			$this->bRebuildOptions = $sModifiedTime > $oConOptions->mod_time;
		}
		else {
			$this->bRebuildOptions = (bool) $this->loadFileSystemProcessor()->isFile( $this->getPath_Flags( 'rebuild' ) );
		}
		$oConOptions->hash = $sCurrentHash;
		$oConOptions->mod_time = $sModifiedTime;
		return $this->bRebuildOptions;
	}

	/**
	 * @return boolean
	 */
	public function getIsResetPlugin() {
		if ( !isset( $this->bResetPlugin ) ) {
			$bExists = $this->loadFileSystemProcessor()->isFile( $this->getPath_Flags( 'reset' ) );
			$this->bResetPlugin = (bool)$bExists;
		}
		return $this->bResetPlugin;
	}

	/**
	 * @return boolean
	 */
	public function getIsWpmsNetworkAdminOnly() {
		return $this->getPluginSpec_Property( 'wpms_network_admin_only' );
	}

	/**
	 * @return string
	 */
	public function getParentSlug() {
		return $this->getPluginSpec_Property( 'slug_parent' );
	}

	/**
	 * This is the path to the main plugin file relative to the WordPress plugins directory.
	 *
	 * @return string
	 */
	public function getPluginBaseFile() {
		if ( !isset( $this->sPluginBaseFile ) ) {
			$this->sPluginBaseFile = plugin_basename( $this->getRootFile() );
		}
		return $this->sPluginBaseFile;
	}

	/**
	 * @return string
	 */
	public function getPluginSlug() {
		return $this->getPluginSpec_Property( 'slug_plugin' );
	}

	/**
	 * @param string $sPath
	 * @return string
	 */
	public function getPluginUrl( $sPath = '' ) {
		if ( empty( $this->sPluginUrl ) ) {
			$this->sPluginUrl = plugins_url( '/', $this->getRootFile() );
		}
		return $this->sPluginUrl.$sPath;
	}

	/**
	 * @param string $sAsset
	 *
	 * @return string
	 */
	public function getPluginUrl_Asset( $sAsset ) {
		if ( $this->loadFileSystemProcessor()->exists( $this->getPath_Assets( $sAsset ) ) ) {
			return $this->getPluginUrl( $this->getPluginSpec_Path( 'assets' ).'/'.$sAsset );
		}
		return '';
	}

	/**
	 * @param string $sAsset
	 *
	 * @return string
	 */
	public function getPluginUrl_Css( $sAsset ) {
		return $this->getPluginUrl_Asset( 'css/'.$sAsset );
	}

	/**
	 * @param string $sAsset
	 *
	 * @return string
	 */
	public function getPluginUrl_Image( $sAsset ) {
		return $this->getPluginUrl_Asset( 'images/'.$sAsset );
	}

	/**
	 * @param string $sAsset
	 *
	 * @return string
	 */
	public function getPluginUrl_Js( $sAsset ) {
		return $this->getPluginUrl_Asset( 'js/'.$sAsset );
	}

	/**
	 * @return string
	 */
	public function getPluginUrl_AdminMainPage() {
		return $this->loadCorePluginFeatureHandler()->getFeatureAdminPageUrl();
	}

	/**
	 * @param string $sAsset
	 * @return string
	 */
	public function getPath_Assets( $sAsset = '' ) {
		return $this->getRootDir().$this->getPluginSpec_Path( 'assets' ).DIRECTORY_SEPARATOR.$sAsset;
	}

	/**
	 * @param string $sFlag
	 * @return string
	 */
	public function getPath_Flags( $sFlag = '' ) {
		return $this->getRootDir().$this->getPluginSpec_Path( 'flags' ).DIRECTORY_SEPARATOR.$sFlag;
	}

	/**
	 * @param string $sTmpFile
	 * @return string
	 */
	public function getPath_Temp( $sTmpFile = '' ) {
		$oFs = $this->loadFileSystemProcessor();
		$sTempPath = $this->getRootDir() . $this->getPluginSpec_Path( 'temp' ) . DIRECTORY_SEPARATOR;
		if ( $oFs->mkdir( $sTempPath ) ) {
			return $this->getRootDir().$this->getPluginSpec_Path( 'temp' ).DIRECTORY_SEPARATOR.$sTmpFile;
		}
		return null;
	}

	/**
	 * @param string $sAsset
	 * @return string
	 */
	public function getPath_AssetCss( $sAsset = '' ) {
		return $this->getPath_Assets( 'css'.DIRECTORY_SEPARATOR.$sAsset );
	}

	/**
	 * @param string $sAsset
	 * @return string
	 */
	public function getPath_AssetJs( $sAsset = '' ) {
		return $this->getPath_Assets( 'js'.DIRECTORY_SEPARATOR.$sAsset );
	}

	/**
	 * @param string $sAsset
	 * @return string
	 */
	public function getPath_AssetImage( $sAsset = '' ) {
		return $this->getPath_Assets( 'images'.DIRECTORY_SEPARATOR.$sAsset );
	}

	/**
	 * get the root directory for the plugin with the trailing slash
	 *
	 * @return string
	 */
	public function getPath_Languages() {
		return $this->getRootDir().$this->getPluginSpec_Path( 'languages' ).DIRECTORY_SEPARATOR;
	}

	/**
	 * get the root directory for the plugin with the trailing slash
	 *
	 * @return string
	 */
	public function getPath_Source() {
		return $this->getRootDir().$this->getPluginSpec_Path( 'source' ).DIRECTORY_SEPARATOR;
	}

	/**
	 * Get the directory for the plugin source files with the trailing slash
	 *
	 * @param string $sSourceFile
	 * @return string
	 */
	public function getPath_SourceFile( $sSourceFile = '' ) {
		return $this->getPath_Source().$sSourceFile;
	}

	/**
	 * Get the path to a library source file
	 *
	 * @param string $sLibFile
	 * @return string
	 */
	public function getPath_LibFile( $sLibFile = '' ) {
		return $this->getPath_Source().'lib'.DIRECTORY_SEPARATOR.$sLibFile;
	}

	/**
	 * @return string
	 */
	public function getPath_Templates() {
		return $this->getRootDir().$this->getPluginSpec_Path( 'templates' ).DIRECTORY_SEPARATOR;
	}

	/**
	 * @param string $sTemplate
	 * @return string
	 */
	public function getPath_TemplatesFile( $sTemplate ) {
		return $this->getPath_Templates().$sTemplate;
	}

	/**
	 * @return string
	 */
	private function getPathPluginSpec() {
		return $this->getRootDir().'plugin-spec.php';
	}

	/**
	 * Get the root directory for the plugin with the trailing slash
	 *
	 * @return string
	 */
	public function getRootDir() {
		return dirname( $this->getRootFile() ).DIRECTORY_SEPARATOR;
	}

	/**
	 * @return string
	 */
	public function getRootFile() {
		if ( !isset( self::$sRootFile ) ) {
			self::$sRootFile = __FILE__;
		}
		return self::$sRootFile;
	}

	/**
	 * @return string
	 */
	public function getTextDomain() {
		return $this->getPluginSpec_Property( 'text_domain' );
	}

	/**
	 * @return string
	 */
	public function getVersion() {
		return $this->getPluginSpec_Property( 'version' );
	}

	/**
	 * @return stdClass
	 */
	protected function getPluginControllerOptions() {
		if ( !isset( self::$oControllerOptions ) ) {

			self::$oControllerOptions = $this->loadWpFunctionsProcessor()->getOption( $this->getPluginControllerOptionsKey() );
			if ( !is_object( self::$oControllerOptions ) ) {
				self::$oControllerOptions = new stdClass();
			}

			// Used at the time of saving during WP Shutdown to determine whether saving is necessary. TODO: Extend to plugin options
			if ( empty( $this->sConfigOptionsHashWhenLoaded ) ) {
				$this->sConfigOptionsHashWhenLoaded = md5( serialize( self::$oControllerOptions ) );
			}

			if ( $this->getIsRebuildOptionsFromFile() ) {
				self::$oControllerOptions->plugin_spec = $this->readPluginSpecification();
			}
		}
		return self::$oControllerOptions;
	}

	/**
	 */
	protected function deletePluginControllerOptions() {
		$this->setPluginControllerOptions( false );
		$this->saveCurrentPluginControllerOptions();
	}

	/**
	 */
	protected function saveCurrentPluginControllerOptions() {
		$oOptions = $this->getPluginControllerOptions();
		if ( $this->sConfigOptionsHashWhenLoaded != md5( serialize( $oOptions ) ) ) {
			add_filter( $this->doPluginPrefix( 'bypass_permission_to_manage' ), '__return_true' );
			$this->loadWpFunctionsProcessor()->updateOption( $this->getPluginControllerOptionsKey(), $oOptions );
			remove_filter( $this->doPluginPrefix( 'bypass_permission_to_manage' ), '__return_true' );
		}
	}

	/**
	 * This should always be used to modify or delete the options as it works within the Admin Access Permission system.
	 *
	 * @param stdClass|bool $oOptions
	 * @return $this
	 */
	protected function setPluginControllerOptions( $oOptions ) {
		self::$oControllerOptions = $oOptions;
		return $this;
	}

	/**
	 * @return string
	 */
	private function getPluginControllerOptionsKey() {
		return strtolower( get_class() );
	}

	/**
	 * @param string $sPathToLib
	 * @return mixed
	 */
	public function loadLib( $sPathToLib ) {
		return include( $this->getPath_LibFile( $sPathToLib ) );
	}

	/**
	 */
	public function deactivateSelf() {
		if ( $this->getIsValidAdminArea() && function_exists( 'deactivate_plugins' ) ) {
			deactivate_plugins( $this->getPluginBaseFile() );
		}
	}

	/**
	 */
	public function clearSession() {
		$this->loadDataProcessor()->setDeleteCookie( $this->getPluginPrefix() );
		self::$sSessionId = null;
	}

	/**
	 * Returns true if you're overriding OFF.  We don't do override ON any more (as of 3.5.1)
	 */
	public function getIfOverrideOff() {
		if ( !isset( $this->bForceOffState ) ) {
			$this->bForceOffState = $this->loadFileSystemProcessor()->fileExistsInDir( 'forceOff', $this->getRootDir(), false );
		}
		return $this->bForceOffState;
	}

	/**
	 * @param boolean $bSetIfNeeded
	 * @return string
	 */
	public function getSessionId( $bSetIfNeeded = true ) {
		if ( empty( self::$sSessionId ) ) {
			self::$sSessionId = $this->loadDataProcessor()->FetchCookie( $this->getPluginPrefix(), '' );
			if ( empty( self::$sSessionId ) && $bSetIfNeeded ) {
				self::$sSessionId = md5( uniqid( $this->getPluginPrefix() ) );
				$this->setSessionCookie();
			}
		}
		return self::$sSessionId;
	}

	/**
	 * @return string
	 */
	public function getUniqueRequestId() {
		if ( !isset( self::$sRequestId ) ) {
			$oDp = $this->loadDataProcessor();
			self::$sRequestId = md5( $this->getSessionId( false ).$oDp->getVisitorIpAddress().$oDp->time() );
		}
		return self::$sRequestId;
	}

	/**
	 * @return string
	 */
	public function hasSessionId() {
		$sSessionId = $this->getSessionId( false );
		return !empty( $sSessionId );
	}

	/**
	 */
	protected function setSessionCookie() {
		$oWp = $this->loadWpFunctionsProcessor();
		$this->loadDataProcessor()->setCookie(
			$this->getPluginPrefix(),
			$this->getSessionId(),
			$this->loadDataProcessor()->time() + DAY_IN_SECONDS*30,
			$oWp->getCookiePath(),
			$oWp->getCookieDomain(),
			false
		);
	}

	/**
	 * @return ICWP_WPSF_FeatureHandler_Plugin
	 */
	public function &loadCorePluginFeatureHandler() {
		if ( !isset( $this->oFeatureHandlerPlugin ) ) {
			$this->loadFeatureHandler(
				array(
					'slug' => 'plugin',
					'load_priority' => 10
				)
			);
		}
		return $this->oFeatureHandlerPlugin;
	}

	/**
	 * @param bool $bRecreate
	 * @param bool $bFullBuild
	 * @return bool
	 */
	public function loadAllFeatures( $bRecreate = false, $bFullBuild = false ) {

		$oMainPluginFeature = $this->loadCorePluginFeatureHandler();
		$aPluginFeatures = $oMainPluginFeature->getActivePluginFeatures();

		$bSuccess = true;
		foreach( $aPluginFeatures as $sSlug => $aFeatureProperties ) {
			try {
				$this->loadFeatureHandler( $aFeatureProperties, $bRecreate, $bFullBuild );
				$bSuccess = true;
			}
			catch( Exception $oE ) {
				$this->loadWpFunctionsProcessor()->wpDie( $oE->getMessage() );
			}
		}
		return $bSuccess;
	}

	/**
	 * @param array $aFeatureProperties
	 * @param bool $bRecreate
	 * @param bool $bFullBuild
	 * @return mixed
	 * @throws Exception
	 */
	public function loadFeatureHandler( $aFeatureProperties, $bRecreate = false, $bFullBuild = false ) {

		$sFeatureSlug = $aFeatureProperties['slug'];

		$sFeatureName = str_replace( ' ', '', ucwords( str_replace( '_', ' ', $sFeatureSlug ) ) );
		$sOptionsVarName = sprintf( 'oFeatureHandler%s', $sFeatureName ); // e.g. oFeatureHandlerPlugin

		if ( isset( $this->{$sOptionsVarName} ) ) {
			return $this->{$sOptionsVarName};
		}

		$sSourceFile = $this->getPath_SourceFile(
			sprintf(
				'features/%s.php',
				$sFeatureSlug
			)
		); // e.g. features/firewall.php
		$sClassName = sprintf(
			'%s_%s_FeatureHandler_%s',
			strtoupper( $this->getParentSlug() ),
			strtoupper( $this->getPluginSlug() ),
			$sFeatureName
		); // e.g. ICWP_WPSF_FeatureHandler_Plugin

		require_once( $sSourceFile );
		if ( $bRecreate || !isset( $this->{$sOptionsVarName} ) ) {
			$this->{$sOptionsVarName} = new $sClassName( $this, $aFeatureProperties );
		}
		if ( $bFullBuild ) {
			$this->{$sOptionsVarName}->buildOptions();
		}
		return $this->{$sOptionsVarName};
	}
}
endif;