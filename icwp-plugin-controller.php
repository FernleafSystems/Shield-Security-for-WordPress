<?php
/**
 * Copyright (c) 2015 iControlWP <support@icontrolwp.com>
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
	 * @var string
	 */
	private $sFlashMessage;

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
	 * @var string
	 */
	protected static $sSessionId;

	/**
	 * @var string
	 */
	protected static $sRequestId;

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
		$this->doRegisterHooks();
	}

	/**
	 * @return array
	 * @throws Exception
	 */
	private function readPluginSpecification() {
		$aSpec = array();
		$sSpecPath = $this->getRootDir().'plugin-spec.php';
		$sContents = include( $sSpecPath );
		if ( !empty( $sContents ) ) {
			$aSpec = $this->loadYamlProcessor()->parseYamlString( $sContents );
			if ( is_null( $aSpec ) ) {
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
	 */
	protected function doRegisterHooks() {
		$this->registerActivationHooks();
		add_action( 'plugins_loaded',					array( $this, 'onWpPluginsLoaded' ) );

		add_action( 'init',			        			array( $this, 'onWpInit' ) );
		add_action( 'admin_init',						array( $this, 'onWpAdminInit' ) );
		add_action( 'wp_loaded',			    		array( $this, 'onWpLoaded' ) );

		add_action( 'admin_menu',						array( $this, 'onWpAdminMenu' ) );
		add_action(	'network_admin_menu',				array( $this, 'onWpAdminMenu' ) );
		add_action( 'admin_notices',					array( $this, 'onWpAdminNotices' ) );
		add_action( 'network_admin_notices',			array( $this, 'onWpAdminNotices' ) );

		add_filter( 'all_plugins', 						array( $this, 'filter_hidePluginFromTableList' ) );
		add_filter( 'all_plugins',						array( $this, 'doPluginLabels' ) );
		add_filter( 'plugin_action_links_'.$this->getPluginBaseFile(), array( $this, 'onWpPluginActionLinks' ), 50, 1 );
		add_filter( 'site_transient_update_plugins',	array( $this, 'filter_hidePluginUpdatesFromUI' ) );
		add_action( 'in_plugin_update_message-'.$this->getPluginBaseFile(), array( $this, 'onWpPluginUpdateMessage' ) );

		add_filter( 'auto_update_plugin',						array( $this, 'onWpAutoUpdate' ), 10001, 2 );
		add_filter( 'set_site_transient_update_plugins',		array( $this, 'setUpdateFirstDetectedAt' ) );

		add_action( 'shutdown',							array( $this, 'onWpShutdown' ) );
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
//		add_filter( $this->doPluginPrefix( 'has_permission_to_view' ), array( $this, 'filter_hasPermissionToView' ) );
//		add_filter( $this->doPluginPrefix( 'has_permission_to_submit' ), array( $this, 'filter_hasPermissionToSubmit' ) );
	}

	/**
	 */
	public function onWpAdminInit() {
		add_action( 'admin_enqueue_scripts', 	array( $this, 'onWpEnqueueAdminCss' ), 99 );
		add_action( 'admin_enqueue_scripts', 	array( $this, 'onWpEnqueueAdminJs' ), 99 );
	}

	/**
	 */
	public function onWpLoaded() {
		if ( $this->getIsValidAdminArea() ) {
			$this->doPluginFormSubmit();
			$this->readFlashMessage();
		}
	}

	/**
	 */
	public function onWpInit() {
		add_action( 'wp_enqueue_scripts', array( $this, 'onWpEnqueueFrontendCss' ), 99 );
	}

	/**
	 * @return bool
	 */
	public function onWpAdminMenu() {
		return ( $this->getIsValidAdminArea() ? $this->createPluginMenu() : true );
	}

	/**
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

			$sMenuIcon = $this->getPluginSpec_Menu( 'icon_image' );
			$sIconUrl = empty( $sMenuIcon ) ? $aPluginLabels['icon_url_16x16'] : $this->getPluginUrl_Image( $sMenuIcon );

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
	 * @param array $aActionLinks
	 * @return array
	 */
	public function onWpPluginActionLinks( $aActionLinks ) {

		if ( $this->getIsValidAdminArea() ) {

			$aLinksToAdd = $this->getPluginSpec_ActionLinks( 'add' );
			if ( !empty( $aLinksToAdd ) && is_array( $aLinksToAdd ) ) {
				foreach( $aLinksToAdd as $aLink ){
					if ( empty( $aLink['name'] ) || empty( $aLink['url_method_name'] ) ) {
						continue;
					}
					$sMethod = $aLink['url_method_name'];
					if ( method_exists( $this, $sMethod ) ) {
						$sSettingsLink = sprintf( '<a href="%s">%s</a>', $this->{$sMethod}(), $aLink['name'] ); ;
						array_unshift( $aActionLinks, $sSettingsLink );
					}
				}
			}
		}
		return $aActionLinks;
	}

	/**
	 */
	public function onWpAdminNotices() {
		if ( $this->getIsValidAdminArea() ) {
			$aAdminNotices = apply_filters( $this->doPluginPrefix( 'admin_notices' ), array() );
			if ( !empty( $aAdminNotices ) && is_array( $aAdminNotices ) ) {
				foreach( $aAdminNotices as $sAdminNotice ) {
					echo $sAdminNotice;
				}
			}
			$this->flashNotice();
		}
		return true;
	}

	public function addFlashMessage( $sMessage ) {
		$this->loadDataProcessor()->setCookie( $this->doPluginPrefix( 'flash' ), esc_attr( $sMessage ) );
	}

	protected function readFlashMessage() {

		$oDp = $this->loadDataProcessor();
		$sCookieName = $this->doPluginPrefix( 'flash' );
		$sMessage = $oDp->FetchCookie( $sCookieName, '' );
		if ( !empty( $sMessage ) ) {
			$this->sFlashMessage = sanitize_text_field( $sMessage );
		}
		$oDp->setDeleteCookie( $sCookieName );
	}

	protected function flashNotice() {
		if ( !empty( $this->sFlashMessage ) ) {
			$aDisplayData = array( 'message' => $this->sFlashMessage );
			$this->loadRenderer( $this->getPath_Templates() )
				 ->setTemplate( 'notices/flash-message' )
				 ->setRenderVars( $aDisplayData )
				 ->display();
		}
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

		if ( !empty( $oPluginUpdateData ) && !empty( $oPluginUpdateData->response ) ) {
			// i.e. there's an update available
			if ( isset( $oPluginUpdateData->response[ $this->getPluginBaseFile() ] ) ) {

				$sNewVersion = $this->loadWpFunctionsProcessor()->getPluginUpdateNewVersion( $this->getPluginBaseFile() );
				if ( !empty( $sNewVersion ) ) {
					$sKey = 'update_first_detected_'.$sNewVersion;
					$oConOptions = $this->getPluginControllerOptions();
					if ( !isset( $oConOptions->{$sKey} ) ) {
						$oConOptions->{$sKey} = $this->loadDataProcessor()->time();
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
	 *
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
						$sNewVersionKey = 'update_first_detected_'.$sNewVersion;
						$nFirstDetected = isset( $oConOptions->{$sNewVersionKey} ) ? $oConOptions->{$sNewVersionKey} : 0;
						$nTimeUpdateAvailable =  $this->loadDataProcessor()->time() - $nFirstDetected;
						$bDoAutoUpdate = ( $nFirstDetected > 0 && ( $nTimeUpdateAvailable > DAY_IN_SECONDS * 2 ) );
					}
					break;

				case 'pass' :
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
		do_action( $this->doPluginPrefix( 'plugin_shutdown' ) );
		$this->saveCurrentPluginControllerOptions();
		$this->deleteRebuildFlag();
	}

	/**
	 */
	protected function deleteRebuildFlag() {
		if ( $this->getIsRebuildOptionsFromFile() ) {
			$this->loadFileSystemProcessor()->deleteFile( $this->getPath_Flags( 'rebuild' ) );
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
	 * @param boolean $bHasPermission
	 * @return boolean
	 */
	public function filter_hasPermissionToView( $bHasPermission = true ) {
		return $this->filter_hasPermissionToSubmit( $bHasPermission );
	}

	/**
	 * @param boolean $bHasPermission
	 * @return boolean
	 */
	public function filter_hasPermissionToSubmit( $bHasPermission = true ) {
		// first a basic admin check
		return $bHasPermission && is_super_admin() && current_user_can( $this->getBasePermissions() );
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
		if ( $bCheckUserPermissions && !current_user_can( $this->getBasePermissions() ) ) {
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
		if ( !isset( $this->bRebuildOptions ) ) {
			$bExists = $this->loadFileSystemProcessor()->isFile( $this->getPath_Flags( 'rebuild' ) );
			$this->bRebuildOptions = is_null( $bExists ) ? false : $bExists;
		}
		return $this->bRebuildOptions;
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
	 *
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
	 * @param string $sFeature
	 * @return string
	 */
	public function getPluginUrl_AdminPage( $sFeature = 'plugin' ) {
		$sUrl = sprintf( 'admin.php?page=%s', $this->doPluginPrefix( $sFeature ) );
		if ( $this->getIsWpmsNetworkAdminOnly() ) {
			$sUrl = network_admin_url( $sUrl );
		}
		else {
			$sUrl = admin_url( $sUrl );
		}
		return $sUrl;
	}

	/**
	 * @return string
	 */
	public function getPluginUrl_AdminMainPage() {
		return $this->getPluginUrl_AdminPage();
	}

	/**
	 * @param string $sAsset
	 * @return string
	 */
	public function getPath_Assets( $sAsset = '' ) {
		return $this->getRootDir().$this->getPluginSpec_Path( 'assets' ).ICWP_DS.$sAsset;
	}

	/**
	 * @param string $sFlag
	 * @return string
	 */
	public function getPath_Flags( $sFlag = '' ) {
		return $this->getRootDir().$this->getPluginSpec_Path( 'flags' ).ICWP_DS.$sFlag;
	}

	/**
	 * @param string $sTmpFile
	 * @return string
	 */
	public function getPath_Temp( $sTmpFile = '' ) {
		$oFs = $this->loadFileSystemProcessor();
		$sTempPath = $this->getRootDir() . $this->getPluginSpec_Path( 'temp' ) . ICWP_DS;
		if ( $oFs->mkdir( $sTempPath ) ) {
			return $this->getRootDir().$this->getPluginSpec_Path( 'temp' ).ICWP_DS.$sTmpFile;
		}
		return null;
	}

	/**
	 * @param string $sAsset
	 * @return string
	 */
	public function getPath_AssetCss( $sAsset = '' ) {
		return $this->getPath_Assets( 'css'.ICWP_DS.$sAsset );
	}

	/**
	 * @param string $sAsset
	 * @return string
	 */
	public function getPath_AssetJs( $sAsset = '' ) {
		return $this->getPath_Assets( 'js'.ICWP_DS.$sAsset );
	}

	/**
	 * @param string $sAsset
	 * @return string
	 */
	public function getPath_AssetImage( $sAsset = '' ) {
		return $this->getPath_Assets( 'images'.ICWP_DS.$sAsset );
	}

	/**
	 * get the root directory for the plugin with the trailing slash
	 *
	 * @return string
	 */
	public function getPath_Languages() {
		return $this->getRootDir().$this->getPluginSpec_Path( 'languages' ).ICWP_DS;
	}

	/**
	 * get the root directory for the plugin with the trailing slash
	 *
	 * @return string
	 */
	public function getPath_Source() {
		return $this->getRootDir().$this->getPluginSpec_Path( 'source' ).ICWP_DS;
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
		return $this->getPath_Source().'lib'.ICWP_DS.$sLibFile;
	}

	/**
	 * @return string
	 */
	public function getPath_Views() {
		return $this->getRootDir().$this->getPluginSpec_Path( 'views' ).ICWP_DS;
	}

	/**
	 * Retrieve the full path to the plugin view
	 *
	 * @param string $sView
	 * @return string
	 */
	public function getPath_ViewsFile( $sView ) {
		return $this->getPath_Views().$sView.'.php';
	}

	/**
	 * @return string
	 */
	public function getPath_Templates() {
		return $this->getRootDir().$this->getPluginSpec_Path( 'templates' ).ICWP_DS;
	}

	/**
	 * @param string $sTemplate
	 * @return string
	 */
	public function getPath_TemplatesFile( $sTemplate ) {
		return $this->getPath_Templates().$sTemplate;
	}

	/**
	 * @param string $sSnippet
	 * @return string
	 */
	public function getPath_ViewsSnippet( $sSnippet ) {
		return $this->getPath_Views().'snippets'.ICWP_DS.$sSnippet.'.php';
	}

	/**
	 * Get the root directory for the plugin with the trailing slash
	 *
	 * @return string
	 */
	public function getRootDir() {
		return dirname( $this->getRootFile() ).ICWP_DS;
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

			if ( $this->getIsRebuildOptionsFromFile()
				 || ( $this->loadDataProcessor()->time() > ( isset( self::$oControllerOptions->rebuild_time ) ? self::$oControllerOptions->rebuild_time : 0 ) )
				 || !isset( self::$oControllerOptions->plugin_spec ) || empty( self::$oControllerOptions->plugin_spec ) ) {

				self::$oControllerOptions->plugin_spec = $this->readPluginSpecification();
				self::$oControllerOptions->rebuild_time = $this->loadDataProcessor()->time() + MINUTE_IN_SECONDS * 5;
			}
		}
		return self::$oControllerOptions;
	}

	/**
	 */
	protected function deletePluginControllerOptions() {
		$this->setPluginControllerOptions( false );
	}

	/**
	 * @return bool
	 */
	protected function saveCurrentPluginControllerOptions() {
		$this->setPluginControllerOptions( $this->getPluginControllerOptions() );
	}

	/**
	 * This should always be used to modify or delete the options as it works within the Admin Access Permission system.
	 *
	 * @param stdClass|bool $oOptions
	 * @return bool
	 */
	protected function setPluginControllerOptions( $oOptions ) {
		add_filter( $this->doPluginPrefix( 'has_permission_to_submit' ), '__return_true' );
		$bUpdated = $this->loadWpFunctionsProcessor()->updateOption( $this->getPluginControllerOptionsKey(), $oOptions );
		remove_filter( $this->doPluginPrefix( 'has_permission_to_submit' ), '__return_true' );
		return $bUpdated;
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
	 * @return string
	 */
	public function getSessionId() {
		if ( !isset( self::$sSessionId ) ) {
			self::$sSessionId = $this->loadDataProcessor()->FetchCookie( $this->getPluginPrefix(), '' );
			if ( empty( self::$sSessionId ) ) {
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
			self::$sRequestId = md5( $this->getSessionId().$oDp->getVisitorIpAddress().$oDp->time() );
		}
		return self::$sRequestId;
	}

	/**
	 */
	protected function setSessionCookie() {
		$oWp = $this->loadWpFunctionsProcessor();
		setcookie(
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
	public function loadCorePluginFeatureHandler() {
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
				wp_die( $oE->getMessage() );
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