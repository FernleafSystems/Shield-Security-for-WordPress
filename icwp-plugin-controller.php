<?php
/**
 * Copyright (c) 2019 One Dollar Plugin <support@onedollarplugin.com>
 * All rights reserved.
 * "Shield" (formerly WordPress Simple Firewall) is distributed under the GNU
 * General Public License, Version 2, June 1991. Copyright (C) 1989, 1991 Free
 * Software Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110, USA
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

use \FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_Plugin_Controller extends ICWP_WPSF_Foundation {

	/**
	 * @var \stdClass
	 */
	private static $oControllerOptions;

	/**
	 * @var ICWP_WPSF_Plugin_Controller
	 */
	public static $oInstance;

	/**
	 * @var string
	 */
	private $sRootFile;

	/**
	 * @var boolean
	 */
	protected $bRebuildOptions;

	/**
	 * @var string
	 */
	protected $sForceOffFile;

	/**
	 * @var bool
	 */
	protected $bResetPlugin;

	/**
	 * @var bool
	 */
	protected $bPluginDeleting = false;

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
	 * @var string
	 */
	private $sConfigOptionsHashWhenLoaded;

	/**
	 * @var boolean
	 */
	private $bMeetsBasePermissions;

	/**
	 * @var string
	 */
	protected $sAdminNoticeError = '';

	/**
	 * @var ICWP_WPSF_FeatureHandler_Plugin
	 */
	protected $oFeatureHandlerPlugin;

	/**
	 * @var ICWP_WPSF_FeatureHandler_Base[]
	 */
	protected $aModules;

	/**
	 * @param string $sRootFile
	 * @return ICWP_WPSF_Plugin_Controller
	 * @throws Exception
	 */
	public static function GetInstance( $sRootFile = null ) {
		if ( !isset( self::$oInstance ) ) {
			self::$oInstance = new self( $sRootFile );
		}
		return self::$oInstance;
	}

	/**
	 * @param string $sRootFile
	 * @throws Exception
	 */
	private function __construct( $sRootFile ) {
		$this->sRootFile = $sRootFile;
		$this->loadServices();
		$this->checkMinimumRequirements();
		$this->doRegisterHooks();
		$this->doLoadTextDomain();
	}

	/**
	 * @throws Exception
	 */
	private function loadServices() {
		Services::GetInstance();
	}

	/**
	 * @return array
	 * @throws \Exception
	 */
	private function readPluginSpecification() {
		$aSpec = array();
		$sContents = $this->loadDP()->readFileContentsUsingInclude( $this->getPathPluginSpec() );
		if ( !empty( $sContents ) ) {
			$aSpec = json_decode( $sContents, true );
			if ( empty( $aSpec ) ) {
				throw new Exception( 'Could not load to process the plugin spec configuration.' );
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
			if ( version_compare( $this->loadDP()->getPhpVersion(), $sMinimumPhp, '<' ) ) {
				$aRequirementsMessages[] = sprintf( 'PHP does not meet minimum version. Your version: %s.  Required Version: %s.', PHP_VERSION, $sMinimumPhp );
				$bMeetsRequirements = false;
			}
		}

		$sMinimumWp = $this->getPluginSpec_Requirement( 'wordpress' );
		if ( !empty( $sMinimumWp ) ) {
			$sWpVersion = $this->loadWp()->getVersion();
			if ( version_compare( $sWpVersion, $sMinimumWp, '<' ) ) {
				$aRequirementsMessages[] = sprintf( 'WordPress does not meet minimum version. Your version: %s.  Required Version: %s.', $sWpVersion, $sMinimumWp );
				$bMeetsRequirements = false;
			}
		}

		if ( !$bMeetsRequirements ) {
			$this->aRequirementsMessages = $aRequirementsMessages;
			add_action( 'admin_notices', array( $this, 'adminNoticeDoesNotMeetRequirements' ) );
			add_action( 'network_admin_notices', array( $this, 'adminNoticeDoesNotMeetRequirements' ) );
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
					'requirements'     => $aMessages,
					'summary_title'    => sprintf( 'Web Hosting requirements for Plugin "%s" are not met and you should deactivate the plugin.', $this->getHumanName() ),
					'more_information' => 'Click here for more information on requirements'
				),
				'hrefs'   => array(
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
	 */
	public function adminNoticePluginFailedToLoad() {
		$aDisplayData = array(
			'strings' => array(
				'summary_title'    => 'Perhaps due to a failed upgrade, the Shield plugin failed to load certain component(s) - you should remove the plugin and reinstall.',
				'more_information' => $this->sAdminNoticeError
			)
		);
		$this->loadRenderer( $this->getPath_Templates() )
			 ->setTemplate( 'notices/plugin-failed-to-load' )
			 ->setRenderVars( $aDisplayData )
			 ->display();
	}

	/**
	 * All our module page names are prefixed
	 * @return bool
	 */
	public function isThisPluginModuleRequest() {
		return strpos( $this->loadRequest()->query( 'page' ), $this->prefix() ) === 0;
	}

	/**
	 * @return array
	 */
	protected function getRequirementsMessages() {
		if ( !isset( $this->aRequirementsMessages ) ) {
			$this->aRequirementsMessages = array(
				'<h4>Shield Security Plugin - minimum site requirements are not met:</h4>'
			);
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
		do_action( $this->prefix( 'pre_deactivate_plugin' ) );
		if ( $this->isPluginAdmin() ) {
			do_action( $this->prefix( 'deactivate_plugin' ) );
			if ( apply_filters( $this->prefix( 'delete_on_deactivate' ), false ) ) {
				$this->bPluginDeleting = true;
				do_action( $this->prefix( 'delete_plugin' ) );
				$this->deletePluginControllerOptions();
			}
		}
		$this->deleteCronJobs();
	}

	public function onWpActivatePlugin() {
		do_action( $this->prefix( 'plugin_activate' ) );
		$this->loadAllFeatures( true, true );
	}

	/**
	 */
	protected function doRegisterHooks() {
		$this->registerActivationHooks();

		add_action( 'init', array( $this, 'onWpInit' ), -1000 );
		add_action( 'admin_init', array( $this, 'onWpAdminInit' ) );
		add_action( 'wp_loaded', array( $this, 'onWpLoaded' ) );

		add_action( 'admin_menu', array( $this, 'onWpAdminMenu' ) );
		add_action( 'network_admin_menu', array( $this, 'onWpAdminMenu' ) );

		if ( $this->loadWp()->isAjax() ) {
			add_action( 'wp_ajax_'.$this->prefix(), array( $this, 'ajaxAction' ) );
			add_action( 'wp_ajax_nopriv_'.$this->prefix(), array( $this, 'ajaxAction' ) );
		}

		$sBaseFile = $this->getPluginBaseFile();
		add_filter( 'all_plugins', array( $this, 'filter_hidePluginFromTableList' ) );
		add_filter( 'all_plugins', array( $this, 'doPluginLabels' ) );
		add_filter( 'plugin_action_links_'.$sBaseFile, array( $this, 'onWpPluginActionLinks' ), 50, 1 );
		add_filter( 'plugin_row_meta', array( $this, 'onPluginRowMeta' ), 50, 2 );
		add_filter( 'site_transient_update_plugins', array( $this, 'filter_hidePluginUpdatesFromUI' ) );
		add_action( 'in_plugin_update_message-'.$sBaseFile, array( $this, 'onWpPluginUpdateMessage' ) );
		add_filter( 'site_transient_update_plugins', array( $this, 'blockIncompatibleUpdates' ) );
		add_filter( 'auto_update_plugin', array( $this, 'onWpAutoUpdate' ), 500, 2 );
		add_filter( 'set_site_transient_update_plugins', array( $this, 'setUpdateFirstDetectedAt' ) );

		add_action( 'shutdown', array( $this, 'onWpShutdown' ) );
		add_action( 'wp_logout', array( $this, 'onWpLogout' ) );

		// GDPR
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'onWpPrivacyRegisterExporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'onWpPrivacyRegisterEraser' ) );

		// outsource the collection of admin notices
		if ( is_admin() ) {
			$oNofics = $this->loadWpNotices();
			$oNofics->setPrefix( $this->prefix() );
			add_filter( $this->prefix( 'ajaxAuthAction' ), array( $oNofics, 'handleAuthAjax' ) );
		}
	}

	/**
	 */
	public function onWpAdminInit() {
		if ( $this->getPluginSpec_Property( 'show_dashboard_widget' ) === true ) {
			add_action( 'wp_dashboard_setup', array( $this, 'onWpDashboardSetup' ) );
		}
		add_action( 'admin_enqueue_scripts', array( $this, 'onWpEnqueueAdminCss' ), 100 );
		add_action( 'admin_enqueue_scripts', array( $this, 'onWpEnqueueAdminJs' ), 5 );
	}

	/**
	 */
	public function onWpLoaded() {
		if ( $this->isValidAdminArea() ) {
			$this->doPluginFormSubmit();
		}
	}

	/**
	 */
	public function onWpInit() {
		$this->getMeetsBasePermissions();
		add_action( 'wp_enqueue_scripts', array( $this, 'onWpEnqueueFrontendCss' ), 99 );
	}

	/**
	 */
	public function onWpAdminMenu() {
		if ( $this->isValidAdminArea() ) {
			$this->createPluginMenu();
		}
	}

	/**
	 */
	public function onWpDashboardSetup() {
		if ( $this->isValidAdminArea() && $this->isPluginAdmin() ) {
			wp_add_dashboard_widget(
				$this->prefix( 'dashboard_widget' ),
				apply_filters( $this->prefix( 'dashboard_widget_title' ), $this->getHumanName() ),
				array( $this, 'displayDashboardWidget' )
			);
		}
	}

	public function displayDashboardWidget() {
		$aContent = apply_filters( $this->prefix( 'dashboard_widget_content' ), array() );
		echo implode( '', $aContent );
	}

	public function ajaxAction() {
		$sNonceAction = $this->loadRequest()->request( 'exec' );
		check_ajax_referer( $sNonceAction, 'exec_nonce' );

		$sAction = $this->loadWpUsers()->isUserLoggedIn() ? 'ajaxAuthAction' : 'ajaxNonAuthAction';
		ob_start();
		$aResponseData = apply_filters( $this->prefix( $sAction ), array() );
		if ( empty( $aResponseData ) ) {
			$aResponseData = apply_filters( $this->prefix( 'ajaxAction' ), $aResponseData );
		}
		$sNoise = ob_get_clean();

		if ( is_array( $aResponseData ) && isset( $aResponseData[ 'success' ] ) ) {
			$bSuccess = $aResponseData[ 'success' ];
		}
		else {
			$bSuccess = false;
			$aResponseData = array();
		}

		wp_send_json(
			array(
				'success' => $bSuccess,
				'data'    => $aResponseData,
				'noise'   => $sNoise
			)
		);
	}

	/**
	 * @return string
	 */
	public function getOptionsEncoding() {
		$sEncoding = $this->getPluginSpec_Property( 'options_encoding' );
		return in_array( $sEncoding, array( 'yaml', 'json' ) ) ? $sEncoding : 'yaml';
	}

	/**
	 * @return bool
	 */
	protected function createPluginMenu() {

		$bHideMenu = apply_filters( $this->prefix( 'filter_hidePluginMenu' ), !$this->getPluginSpec_Menu( 'show' ) );
		if ( $bHideMenu ) {
			return true;
		}

		if ( $this->getPluginSpec_Menu( 'top_level' ) ) {

			$aLabels = $this->getPluginLabels();
			$sMenuTitle = empty( $aLabels[ 'MenuTitle' ] ) ? $this->getPluginSpec_Menu( 'title' ) : $aLabels[ 'MenuTitle' ];
			if ( is_null( $sMenuTitle ) ) {
				$sMenuTitle = $this->getHumanName();
			}

			$sMenuIcon = $this->getPluginUrl_Image( $this->getPluginSpec_Menu( 'icon_image' ) );
			$sIconUrl = empty( $aLabels[ 'icon_url_16x16' ] ) ? $sMenuIcon : $aLabels[ 'icon_url_16x16' ];

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

				$aPluginMenuItems = apply_filters( $this->prefix( 'submenu_items' ), array() );
				if ( !empty( $aPluginMenuItems ) ) {
					foreach ( $aPluginMenuItems as $sMenuTitle => $aMenu ) {
						list( $sMenuItemText, $sMenuItemId, $aMenuCallBack, $bShowItem ) = $aMenu;
						add_submenu_page(
							$bShowItem ? $sFullParentMenuId : null,
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
		if ( isset( $submenu[ $sFullParentMenuId ] ) ) {
			unset( $submenu[ $sFullParentMenuId ][ 0 ] );
		}
	}

	/**
	 * Displaying all views now goes through this central function and we work out
	 * what to display based on the name of current hook/filter being processed.
	 */
	public function onDisplayTopMenu() {
	}

	/**
	 * @param array  $aPluginMeta
	 * @param string $sPluginFile
	 * @return array
	 */
	public function onPluginRowMeta( $aPluginMeta, $sPluginFile ) {

		if ( $sPluginFile == $this->getPluginBaseFile() ) {
			$aMeta = $this->getPluginSpec_PluginMeta();

			$sLinkTemplate = '<strong><a href="%s" target="%s">%s</a></strong>';
			foreach ( $aMeta as $aMetaLink ) {
				$sSettingsLink = sprintf( $sLinkTemplate, $aMetaLink[ 'href' ], "_blank", $aMetaLink[ 'name' ] );;
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

		if ( $this->isValidAdminArea() ) {

			if ( array_key_exists( 'edit', $aActionLinks ) ) {
				unset( $aActionLinks[ 'edit' ] );
			}

			$aLinksToAdd = $this->getPluginSpec_ActionLinks( 'add' );
			if ( is_array( $aLinksToAdd ) ) {

				$bPro = $this->isPremiumActive();
				$oDP = $this->loadDP();
				$sLinkTemplate = '<a href="%s" target="%s" title="%s">%s</a>';
				foreach ( $aLinksToAdd as $aLink ) {
					$aLink = array_merge(
						array(
							'highlight' => false,
							'show'      => 'always',
							'name'      => '',
							'title'     => '',
							'href'      => '',
							'target'    => '_top',
						),
						$aLink
					);

					$sShow = $aLink[ 'show' ];
					$bShow = ( $sShow == 'always' ) || ( $bPro && $sShow == 'pro' ) || ( !$bPro && $sShow == 'free' );
					if ( !$oDP->isValidUrl( $aLink[ 'href' ] ) && method_exists( $this, $aLink[ 'href' ] ) ) {
						$aLink[ 'href' ] = $this->{$aLink[ 'href' ]}();
					}

					if ( !$bShow || !$oDP->isValidUrl( $aLink[ 'href' ] )
						 || empty( $aLink[ 'name' ] ) || empty( $aLink[ 'href' ] ) ) {
						continue;
					}

					$sLink = sprintf( $sLinkTemplate, $aLink[ 'href' ], $aLink[ 'target' ], $aLink[ 'title' ], $aLink[ 'name' ] );
					if ( $aLink[ 'highlight' ] ) {
						$sLink = sprintf( '<span style="font-weight: bold;">%s</span>', $sLink );
					}

					$aActionLinks = array_merge(
						array( $this->prefix( sanitize_key( $aLink[ 'name' ] ) ) => $sLink ),
						$aActionLinks
					);
				}
			}
		}
		return $aActionLinks;
	}

	public function onWpEnqueueFrontendCss() {

		$aFrontendIncludes = $this->getPluginSpec_Include( 'frontend' );
		if ( isset( $aFrontendIncludes[ 'css' ] ) && !empty( $aFrontendIncludes[ 'css' ] ) && is_array( $aFrontendIncludes[ 'css' ] ) ) {
			foreach ( $aFrontendIncludes[ 'css' ] as $sCssAsset ) {
				$sUnique = $this->prefix( $sCssAsset );
				wp_register_style( $sUnique, $this->getPluginUrl_Css( $sCssAsset.'.css' ), ( empty( $sDependent ) ? false : $sDependent ), $this->getVersion() );
				wp_enqueue_style( $sUnique );
				$sDependent = $sUnique;
			}
		}
	}

	public function onWpEnqueueAdminJs() {
		$sVers = $this->getVersion();

		$aAdminJs = $this->getPluginSpec_Include( 'admin' );
		if ( !empty( $aAdminJs[ 'js' ] ) && is_array( $aAdminJs[ 'js' ] ) ) {
			$sDep = false;
			foreach ( $aAdminJs[ 'css' ] as $sAsset ) {
				$sUrl = $this->getPluginUrl_Js( $sAsset.'.js' );
				if ( !empty( $sUrl ) ) {
					$sUnique = $this->prefix( $sAsset );
					wp_register_script( $sUnique, $sUrl, $sDep ? array( $sDep ) : array(), $sVers );
					wp_enqueue_script( $sUnique );
					$sDep = $sUnique;
				}
			}
		}

		if ( $this->getIsPage_PluginAdmin() ) {
			$aAdminJs = $this->getPluginSpec_Include( 'plugin_admin' );
			if ( !empty( $aAdminJs[ 'js' ] ) && is_array( $aAdminJs[ 'js' ] ) ) {
				$sDep = false;
				foreach ( $aAdminJs[ 'js' ] as $sAsset ) {

					// Built-in handles
					if ( in_array( $sAsset, array( 'jquery' ) ) ) {
						if ( wp_script_is( $sAsset, 'registered' ) ) {
							wp_enqueue_script( $sAsset );
							$sDep = $sAsset;
						}
						continue;
					}

					$sUrl = $this->getPluginUrl_Js( $sAsset.'.js' );
					if ( !empty( $sUrl ) ) {
						$sUnique = $this->prefix( $sAsset );
						wp_register_script( $sUnique, $sUrl, $sDep ? array( $sDep ) : array(), $sVers );
						wp_enqueue_script( $sUnique );
						$sDep = $sUnique;
					}
				}
			}
		}
	}

	public function onWpEnqueueAdminCss() {

		if ( $this->isValidAdminArea() ) {
			$aAdminCss = $this->getPluginSpec_Include( 'admin' );
			if ( !empty( $aAdminCss[ 'css' ] ) && is_array( $aAdminCss[ 'css' ] ) ) {
				$sDependent = false;
				foreach ( $aAdminCss[ 'css' ] as $sCssAsset ) {
					$sUrl = $this->getPluginUrl_Css( $sCssAsset.'.css' );
					if ( !empty( $sUrl ) ) {
						$sUnique = $this->prefix( $sCssAsset );
						wp_register_style( $sUnique, $sUrl, $sDependent, $this->getVersion().rand() );
						wp_enqueue_style( $sUnique );
						$sDependent = $sUnique;
					}
				}
			}
		}

		if ( $this->getIsPage_PluginAdmin() ) {
			$aAdminCss = $this->getPluginSpec_Include( 'plugin_admin' );
			if ( !empty( $aAdminCss[ 'css' ] ) && is_array( $aAdminCss[ 'css' ] ) ) {
				$sDependent = false;
				foreach ( $aAdminCss[ 'css' ] as $sCssAsset ) {
					$sUrl = $this->getPluginUrl_Css( $sCssAsset.'.css' );
					if ( !empty( $sUrl ) ) {
						$sUnique = $this->prefix( $sCssAsset );
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
		$sMessage = _wpsf__( 'Upgrade Now To Keep Your Security Up-To-Date With The Latest Features.' );
		if ( empty( $sMessage ) ) {
			$sMessage = '';
		}
		else {
			$sMessage = sprintf(
				' <span class="%s plugin_update_message">%s</span>',
				$this->getPluginPrefix(),
				$sMessage
			);
		}
		echo $sMessage;
	}

	/**
	 * Use logic in here to prevent display of future incompatible updates
	 * @param stdClass $oUpdates
	 * @return stdClass
	 */
	public function blockIncompatibleUpdates( $oUpdates ) {
		/*
		 * No longer used: prevent upgrades to v7.0 for php < 5.4
		$sFile = $this->getPluginBaseFile();
		if ( !empty( $oUpdates->response ) && isset( $oUpdates->response[ $sFile ] ) ) {
			if ( version_compare( $oUpdates->response[ $sFile ]->new_version, '7.0.0', '>=' )
				 && !$this->loadDP()->getPhpVersionIsAtLeast( '5.4.0' ) ) {
				unset( $oUpdates->response[ $sFile ] );
			}
		}
		 */
		return $oUpdates;
	}

	/**
	 * This will hook into the saving of plugin update information and if there is an update for this plugin, it'll add
	 * a data stamp to state when the update was first detected.
	 * @param stdClass $oPluginUpdateData
	 * @return stdClass
	 */
	public function setUpdateFirstDetectedAt( $oPluginUpdateData ) {

		if ( !empty( $oPluginUpdateData ) && !empty( $oPluginUpdateData->response )
			 && isset( $oPluginUpdateData->response[ $this->getPluginBaseFile() ] ) ) {
			// i.e. there's an update available

			$sNewVersion = $this->loadWpPlugins()->getUpdateNewVersion( $this->getPluginBaseFile() );
			if ( !empty( $sNewVersion ) ) {
				$oConOptions = $this->getPluginControllerOptions();
				if ( !isset( $oConOptions->update_first_detected ) || ( count( $oConOptions->update_first_detected ) > 3 ) ) {
					$oConOptions->update_first_detected = array();
				}
				if ( !isset( $oConOptions->update_first_detected[ $sNewVersion ] ) ) {
					$oConOptions->update_first_detected[ $sNewVersion ] = $this->loadRequest()->ts();
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
	 * @param boolean       $bDoAutoUpdate
	 * @param string|object $mItem
	 * @return boolean
	 */
	public function onWpAutoUpdate( $bDoAutoUpdate, $mItem ) {
		$oWp = $this->loadWp();
		$oWpPlugins = $this->loadWpPlugins();

		$sFile = $oWp->getFileFromAutomaticUpdateItem( $mItem );

		// The item in question is this plugin...
		if ( $sFile === $this->getPluginBaseFile() ) {
			$sAutoupdateSpec = $this->getPluginSpec_Property( 'autoupdate' );

			$oConOptions = $this->getPluginControllerOptions();

			if ( !$oWp->isRunningAutomaticUpdates() && $sAutoupdateSpec == 'confidence' ) {
				$sAutoupdateSpec = 'yes'; // so that we appear to be automatically updating
			}

			$sNewVersion = $oWpPlugins->getUpdateNewVersion( $sFile );

			/** We block automatic updates for Shield v7+ if PHP < 5.3 */
//			if ( version_compare( $sNewVersion, '7.0.0', '>=' )
//				 && !$this->loadDP()->getPhpVersionIsAtLeast( '5.3' )
//			) {
//				$sAutoupdateSpec = 'block';
//			}

			switch ( $sAutoupdateSpec ) {

				case 'yes' :
					$bDoAutoUpdate = true;
					break;

				case 'block' :
					$bDoAutoUpdate = false;
					break;

				case 'confidence' :
					$bDoAutoUpdate = false;
					$nAutoupdateDays = $this->getPluginSpec_Property( 'autoupdate_days' );
					$sNewVersion = $oWpPlugins->getUpdateNewVersion( $sFile );
					if ( !empty( $sNewVersion ) ) {
						$nFirstDetected = isset( $oConOptions->update_first_detected[ $sNewVersion ] ) ? $oConOptions->update_first_detected[ $sNewVersion ] : 0;
						$nTimeUpdateAvailable = $this->loadRequest()->ts() - $nFirstDetected;
						$bDoAutoUpdate = ( $nFirstDetected > 0 && ( $nTimeUpdateAvailable > DAY_IN_SECONDS*$nAutoupdateDays ) );
					}
					break;

				case 'pass' :
					break;

				default:
					break;
			}
		}
		return $bDoAutoUpdate;
	}

	/**
	 * @param array $aPlugins
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
				$aPlugins[ $sPluginFile ][ $sLabelKey ] = $sLabel;
			}
		}

		return $aPlugins;
	}

	/**
	 * @return array
	 */
	public function getPluginLabels() {

		$aLabels = array_map( 'stripslashes', apply_filters( $this->prefix( 'plugin_labels' ), $this->getPluginSpec_Labels() ) );

		$oDP = $this->loadDP();
		foreach ( array( '16x16', '32x32', '128x128' ) as $sSize ) {
			$sKey = 'icon_url_'.$sSize;
			if ( !empty( $aLabels[ $sKey ] ) && !$oDP->isValidUrl( $aLabels[ $sKey ] ) ) {
				$aLabels[ $sKey ] = $this->getPluginUrl_Image( $aLabels[ $sKey ] );
			}
		}

		return $aLabels;
	}

	/**
	 * Hooked to 'shutdown'
	 */
	public function onWpShutdown() {
		do_action( $this->prefix( 'pre_plugin_shutdown' ) );
		do_action( $this->prefix( 'plugin_shutdown' ) );
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
		$oFS = $this->loadFS();
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
	 * @param array $aPlugins
	 * @return array
	 */
	public function filter_hidePluginFromTableList( $aPlugins ) {

		$bHide = apply_filters( $this->prefix( 'hide_plugin' ), false );
		if ( !$bHide ) {
			return $aPlugins;
		}

		$sPluginBaseFileName = $this->getPluginBaseFile();
		if ( isset( $aPlugins[ $sPluginBaseFileName ] ) ) {
			unset( $aPlugins[ $sPluginBaseFileName ] );
		}
		return $aPlugins;
	}

	/**
	 * Added to the WordPress filter ('site_transient_update_plugins') in order to remove visibility of updates
	 * from the WordPress Admin UI.
	 * In order to ensure that WordPress still checks for plugin updates it will not remove this plugin from
	 * the list of plugins if DOING_CRON is set to true.
	 * @uses $this->fHeadless if the plugin is headless, it is hidden
	 * @param StdClass $oPlugins
	 * @return StdClass
	 */
	public function filter_hidePluginUpdatesFromUI( $oPlugins ) {

		if ( $this->loadWp()->isCron() ) {
			return $oPlugins;
		}
		if ( !apply_filters( $this->prefix( 'hide_plugin_updates' ), false ) ) {
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
		if ( !$this->isPluginFormSubmit() ) {
			return false;
		}

		// do all the plugin feature/options saving
		do_action( $this->prefix( 'form_submit' ) );

		if ( $this->getIsPage_PluginAdmin() ) {
			$oWp = $this->loadWp();
			$oWp->doRedirect( $oWp->getUrl_CurrentAdminPage() );
		}
		return true;
	}

	/**
	 * @param string $sSuffix
	 * @param string $sGlue
	 * @return string
	 */
	public function prefix( $sSuffix = '', $sGlue = '-' ) {
		$sPrefix = $this->getPluginPrefix( $sGlue );

		if ( $sSuffix == $sPrefix || strpos( $sSuffix, $sPrefix.$sGlue ) === 0 ) { //it already has the full prefix
			return $sSuffix;
		}

		return sprintf( '%s%s%s', $sPrefix, empty( $sSuffix ) ? '' : $sGlue, empty( $sSuffix ) ? '' : $sSuffix );
	}

	/**
	 * @param string $sSuffix
	 * @return string
	 */
	public function prefixOption( $sSuffix = '' ) {
		return $this->prefix( $sSuffix, '_' );
	}

	/**
	 * @param string $sKey
	 * @return mixed|null
	 */
	protected function getPluginSpec_ActionLinks( $sKey ) {
		$oOpts = $this->getPluginControllerOptions();
		return isset( $oOpts->plugin_spec[ 'action_links' ][ $sKey ] ) ? $oOpts->plugin_spec[ 'action_links' ][ $sKey ] : array();
	}

	/**
	 * @param string $sKey
	 * @return mixed|null
	 */
	protected function getPluginSpec_Include( $sKey ) {
		$oConOptions = $this->getPluginControllerOptions();
		return isset( $oConOptions->plugin_spec[ 'includes' ][ $sKey ] ) ? $oConOptions->plugin_spec[ 'includes' ][ $sKey ] : null;
	}

	/**
	 * @param string $sKey
	 * @return array|string
	 */
	protected function getPluginSpec_Labels( $sKey = '' ) {
		$oConOptions = $this->getPluginControllerOptions();
		$aLabels = isset( $oConOptions->plugin_spec[ 'labels' ] ) ? $oConOptions->plugin_spec[ 'labels' ] : array();

		if ( empty( $sKey ) ) {
			return $aLabels;
		}

		return isset( $oConOptions->plugin_spec[ 'labels' ][ $sKey ] ) ? $oConOptions->plugin_spec[ 'labels' ][ $sKey ] : null;
	}

	/**
	 * @param string $sKey
	 * @return mixed|null
	 */
	protected function getPluginSpec_Menu( $sKey ) {
		$oConOptions = $this->getPluginControllerOptions();
		return isset( $oConOptions->plugin_spec[ 'menu' ][ $sKey ] ) ? $oConOptions->plugin_spec[ 'menu' ][ $sKey ] : null;
	}

	/**
	 * @param string $sKey
	 * @return mixed|null
	 */
	protected function getPluginSpec_Path( $sKey ) {
		$oConOptions = $this->getPluginControllerOptions();
		return isset( $oConOptions->plugin_spec[ 'paths' ][ $sKey ] ) ? $oConOptions->plugin_spec[ 'paths' ][ $sKey ] : null;
	}

	/**
	 * @param string $sKey
	 * @return mixed|null
	 */
	protected function getPluginSpec_Property( $sKey ) {
		$oConOptions = $this->getPluginControllerOptions();
		return isset( $oConOptions->plugin_spec[ 'properties' ][ $sKey ] ) ? $oConOptions->plugin_spec[ 'properties' ][ $sKey ] : null;
	}

	/**
	 * @return array
	 */
	protected function getPluginSpec_PluginMeta() {
		$oConOptions = $this->getPluginControllerOptions();
		return ( isset( $oConOptions->plugin_spec[ 'plugin_meta' ] ) && is_array( $oConOptions->plugin_spec[ 'plugin_meta' ] ) ) ? $oConOptions->plugin_spec[ 'plugin_meta' ] : array();
	}

	/**
	 * @param string $sKey
	 * @return mixed|null
	 */
	protected function getPluginSpec_Requirement( $sKey ) {
		$oConOptions = $this->getPluginControllerOptions();
		return isset( $oConOptions->plugin_spec[ 'requirements' ][ $sKey ] ) ? $oConOptions->plugin_spec[ 'requirements' ][ $sKey ] : null;
	}

	/**
	 * @return string
	 */
	public function getBasePermissions() {
		return $this->getPluginSpec_Property( 'base_permissions' );
	}

	/**
	 * @param bool $bCheckUserPerms - do we check the logged-in user permissions
	 * @return bool
	 */
	public function isValidAdminArea( $bCheckUserPerms = false ) {
		if ( $bCheckUserPerms && did_action( 'init' ) && !$this->isPluginAdmin() ) {
			return false;
		}

		$oWp = $this->loadWp();
		if ( !$oWp->isMultisite() && is_admin() ) {
			return true;
		}
		else if ( $oWp->isMultisite() && $this->getIsWpmsNetworkAdminOnly() && ( is_network_admin() || $oWp->isAjax() ) ) {
			return true;
		}
		return false;
	}

	/**
	 * @return bool
	 */
	public function isModulePage() {
		return strpos( Services::Request()->query( 'page' ), $this->prefix() ) === 0;
	}

	/**
	 * only ever consider after WP INIT (when a logged-in user is recognised)
	 * @return bool
	 */
	public function isPluginAdmin() {
		return apply_filters( $this->prefix( 'bypass_is_plugin_admin' ), false )
			   || ( $this->getMeetsBasePermissions() // takes care of did_action('init)
					&& apply_filters( $this->prefix( 'is_plugin_admin' ), true )
			   );
	}

	/**
	 * @return bool
	 */
	public function isPluginDeleting() {
		return (bool)$this->bPluginDeleting;
	}

	/**
	 * DO NOT CHANGE THIS IMPLEMENTATION. We call this as early as possible so that the
	 * current_user_can() never gets caught up in an infinite loop of permissions checking
	 * @return boolean
	 */
	public function getMeetsBasePermissions() {
		if ( did_action( 'init' ) && !isset( $this->bMeetsBasePermissions ) ) {
			$this->bMeetsBasePermissions = current_user_can( $this->getBasePermissions() );
		}
		return isset( $this->bMeetsBasePermissions ) ? $this->bMeetsBasePermissions : false;
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
	 * @return string
	 */
	public function getHumanName() {
		$aLabels = $this->getPluginLabels();
		return empty( $aLabels[ 'Name' ] ) ? $this->getPluginSpec_Property( 'human_name' ) : $aLabels[ 'Name' ];
	}

	/**
	 * @return string
	 */
	public function isLoggingEnabled() {
		return $this->getPluginSpec_Property( 'logging_enabled' );
	}

	/**
	 * @return bool
	 */
	public function getIsPage_PluginAdmin() {
		return ( strpos( $this->loadWp()->getCurrentWpAdminPage(), $this->getPluginPrefix() ) === 0 );
	}

	/**
	 * @return bool
	 */
	public function getIsPage_PluginMainDashboard() {
		return ( $this->loadWp()->getCurrentWpAdminPage() == $this->getPluginPrefix() );
	}

	/**
	 * @return bool
	 */
	protected function isPluginFormSubmit() {
		if ( $this->loadWp()->isAjax() || ( empty( $_POST ) && empty( $_GET ) ) ) {
			return false;
		}

		$aFormSubmitOptions = array( 'plugin_form_submit', 'icwp_link_action' );

		$oReq = $this->loadRequest();
		foreach ( $aFormSubmitOptions as $sOption ) {
			if ( !is_null( $oReq->request( $sOption, false ) ) ) {
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
		$sModifiedTime = $this->loadFS()->getModifiedTime( $sSpecPath );

		$this->bRebuildOptions = true;

		if ( isset( $oConOptions->hash ) && is_string( $oConOptions->hash ) && ( $oConOptions->hash == $sCurrentHash ) ) {
			$this->bRebuildOptions = false;
		}
		else if ( isset( $oConOptions->mod_time ) && ( $sModifiedTime < $oConOptions->mod_time ) ) {
			$this->bRebuildOptions = false;
		}

		$oConOptions->hash = $sCurrentHash;
		$oConOptions->mod_time = $sModifiedTime;
		return $this->bRebuildOptions;
	}

	/**
	 * @return bool
	 */
	public function isUpgrading() {
		return $this->getIsRebuildOptionsFromFile();
	}

	/**
	 * @return boolean
	 */
	public function getIsResetPlugin() {
		if ( !isset( $this->bResetPlugin ) ) {
			$bExists = $this->loadFS()->isFile( $this->getPath_Flags( 'reset' ) );
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
		return add_query_arg( array( 'ver' => $this->getVersion() ), plugins_url( $sPath, $this->getRootFile() ) );
	}

	/**
	 * @param string $sAsset
	 * @return string
	 */
	public function getPluginUrl_Asset( $sAsset ) {
		$sUrl = '';
		$sAssetPath = $this->getPath_Assets( $sAsset );
		if ( $this->loadFS()->exists( $sAssetPath ) ) {
			$sUrl = $this->getPluginUrl( $this->getPluginSpec_Path( 'assets' ).'/'.$sAsset );
			return $this->loadWpIncludes()->addIncludeModifiedParam( $sUrl, $sAssetPath );
		}
		return $sUrl;
	}

	/**
	 * @param string $sAsset
	 * @return string
	 */
	public function getPluginUrl_Css( $sAsset ) {
		return $this->getPluginUrl_Asset( 'css/'.$sAsset );
	}

	/**
	 * @param string $sAsset
	 * @return string
	 */
	public function getPluginUrl_Image( $sAsset ) {
		return $this->getPluginUrl_Asset( 'images/'.$sAsset );
	}

	/**
	 * @param string $sAsset
	 * @return string
	 */
	public function getPluginUrl_Js( $sAsset ) {
		return $this->getPluginUrl_Asset( 'js/'.$sAsset );
	}

	/**
	 * @return string
	 */
	public function getPluginUrl_AdminMainPage() {
		return $this->loadCorePluginFeatureHandler()->getUrl_AdminPage();
	}

	/**
	 * @param string $sAsset
	 * @return string
	 */
	public function getPath_Assets( $sAsset = '' ) {
		$sBase = path_join( $this->getRootDir(), $this->getPluginSpec_Path( 'assets' ) );
		return empty( $sAsset ) ? $sBase : path_join( $sBase, $sAsset );
	}

	/**
	 * @param string $sFlag
	 * @return string
	 */
	public function getPath_Flags( $sFlag = '' ) {
		$sBase = path_join( $this->getRootDir(), $this->getPluginSpec_Path( 'flags' ) );
		return empty( $sFlag ) ? $sBase : path_join( $sBase, $sFlag );
	}

	/**
	 * @param string $sTmpFile
	 * @return string
	 */
	public function getPath_Temp( $sTmpFile = '' ) {
		$sTempPath = null;
		$oFs = $this->loadFS();

		$sBase = path_join( $this->getRootDir(), $this->getPluginSpec_Path( 'temp' ) );
		if ( $oFs->mkdir( $sBase ) ) {
			$sTempPath = $sBase;
		}
		return empty( $sTmpFile ) ? $sTempPath : path_join( $sTempPath, $sTmpFile );
	}

	/**
	 * @param string $sAsset
	 * @return string
	 */
	public function getPath_AssetCss( $sAsset = '' ) {
		return $this->getPath_Assets( 'css/'.$sAsset );
	}

	/**
	 * @param string $sAsset
	 * @return string
	 */
	public function getPath_AssetJs( $sAsset = '' ) {
		return $this->getPath_Assets( 'js/'.$sAsset );
	}

	/**
	 * @param string $sAsset
	 * @return string
	 */
	public function getPath_AssetImage( $sAsset = '' ) {
		return $this->getPath_Assets( 'images/'.$sAsset );
	}

	/**
	 * @param string $sSlug
	 * @return string
	 */
	public function getPath_ConfigFile( $sSlug ) {
		return $this->getPath_SourceFile( sprintf( 'config/feature-%s.php', $sSlug ) );
	}

	/**
	 * @return string
	 */
	public function getPath_Languages() {
		return path_join( $this->getRootDir(), $this->getPluginSpec_Path( 'languages' ) ).'/';
	}

	/**
	 * Get the path to a library source file
	 * @param string $sLibFile
	 * @return string
	 */
	public function getPath_LibFile( $sLibFile = '' ) {
		return $this->getPath_SourceFile( 'lib/'.$sLibFile );
	}

	/**
	 * @return string
	 */
	public function getPath_Autoload() {
		return $this->getPath_SourceFile( $this->getPluginSpec_Path( 'autoload' ) );
	}

	/**
	 * Get the directory for the plugin source files with the trailing slash
	 * @param string $sSourceFile
	 * @return string
	 */
	public function getPath_SourceFile( $sSourceFile = '' ) {
		$sBase = path_join( $this->getRootDir(), $this->getPluginSpec_Path( 'source' ) ).'/';
		return empty( $sSourceFile ) ? $sBase : path_join( $sBase, $sSourceFile );
	}

	/**
	 * @return string
	 */
	public function getPath_Templates() {
		return path_join( $this->getRootDir(), $this->getPluginSpec_Path( 'templates' ) ).'/';
	}

	/**
	 * @param string $sTemplate
	 * @return string
	 */
	public function getPath_TemplatesFile( $sTemplate ) {
		return path_join( $this->getPath_Templates(), $sTemplate );
	}

	/**
	 * @return string
	 */
	private function getPathPluginSpec() {
		return path_join( $this->getRootDir(), 'plugin-spec.php' );
	}

	/**
	 * Get the root directory for the plugin with the trailing slash
	 * @return string
	 */
	public function getRootDir() {
		return dirname( $this->getRootFile() ).DIRECTORY_SEPARATOR;
	}

	/**
	 * @return string
	 */
	public function getRootFile() {
		if ( !isset( $this->sRootFile ) ) {
			$this->sRootFile = __FILE__;
		}
		return $this->sRootFile;
	}

	/**
	 * @return int
	 */
	public function getReleaseTimestamp() {
		return $this->getPluginSpec_Property( 'release_timestamp' );
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
	 * @return mixed|stdClass
	 */
	protected function getPluginControllerOptions() {
		if ( !isset( self::$oControllerOptions ) ) {

			self::$oControllerOptions = $this->loadWp()->getOption( $this->getPluginControllerOptionsKey() );
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
	protected function deleteCronJobs() {
		$oWpCron = $this->loadWpCronProcessor();
		$aCrons = $oWpCron->getCrons();

		$sPattern = sprintf( '#^(%s|%s)#', $this->getParentSlug(), $this->getPluginSlug() );
		foreach ( $aCrons as $aCron ) {
			if ( is_array( $aCrons ) ) {
				foreach ( $aCron as $sKey => $aCronEntry ) {
					if ( is_string( $sKey ) && preg_match( $sPattern, $sKey ) ) {
						$oWpCron->deleteCronJob( $sKey );
					}
				}
			}
		}
	}

	/**
	 * @return bool
	 */
	public function isPremiumExtensionsEnabled() {
		return (bool)$this->getPluginSpec_Property( 'enable_premium' );
	}

	/**
	 * @return bool
	 */
	public function isPremiumActive() {
		return apply_filters( $this->getPremiumLicenseFilterName(), false );
	}

	/**
	 * @return string
	 */
	public function getPremiumLicenseFilterName() {
		return $this->prefix( 'license_is_valid'.$this->getUniqueRequestId( false ) );
	}

	/**
	 * @return bool
	 */
	public function isRelabelled() {
		return apply_filters( $this->prefix( 'is_relabelled' ), false );
	}

	/**
	 */
	protected function saveCurrentPluginControllerOptions() {
		$oOptions = $this->getPluginControllerOptions();
		if ( $this->sConfigOptionsHashWhenLoaded != md5( serialize( $oOptions ) ) ) {
			add_filter( $this->prefix( 'bypass_is_plugin_admin' ), '__return_true' );
			$this->loadWp()->updateOption( $this->getPluginControllerOptionsKey(), $oOptions );
			remove_filter( $this->prefix( 'bypass_is_plugin_admin' ), '__return_true' );
		}
	}

	/**
	 * This should always be used to modify or delete the options as it works within the Admin Access Permission system.
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
		if ( $this->isPluginAdmin() && function_exists( 'deactivate_plugins' ) ) {
			deactivate_plugins( $this->getPluginBaseFile() );
		}
	}

	/**
	 */
	public function clearSession() {
		$this->loadRequest()->setDeleteCookie( $this->getPluginPrefix() );
		self::$sSessionId = null;
	}

	/**
	 * @return $this
	 */
	public function deleteForceOffFile() {
		if ( $this->getIfForceOffActive() ) {
			$this->loadFS()->deleteFile( $this->getForceOffFilePath() );
			$this->sForceOffFile = null;
			clearstatcache();
		}
		return $this;
	}

	/**
	 * Returns true if you're overriding OFF.  We don't do override ON any more (as of 3.5.1)
	 */
	public function getIfForceOffActive() {
		return ( $this->getForceOffFilePath() !== false );
	}

	/**
	 * @return null|string
	 */
	protected function getForceOffFilePath() {
		if ( !isset( $this->sForceOffFile ) ) {
			$oFs = $this->loadFS();
			$sFile = $oFs->fileExistsInDir( 'forceOff', $this->getRootDir(), false );
			$this->sForceOffFile = ( !is_null( $sFile ) && $oFs->isFile( $sFile ) ) ? $sFile : false;
		}
		return $this->sForceOffFile;
	}

	/**
	 * @param boolean $bSetIfNeeded
	 * @return string
	 */
	public function getSessionId( $bSetIfNeeded = true ) {
		if ( empty( self::$sSessionId ) ) {
			self::$sSessionId = $this->loadRequest()->cookie( $this->getPluginPrefix(), '' );
			if ( empty( self::$sSessionId ) && $bSetIfNeeded ) {
				self::$sSessionId = md5( uniqid( $this->getPluginPrefix() ) );
				$this->setSessionCookie();
			}
		}
		return self::$sSessionId;
	}

	/**
	 * @param bool $bSetIfNeeded
	 * @return string
	 */
	public function getUniqueRequestId( $bSetIfNeeded = true ) {
		if ( !isset( self::$sRequestId ) ) {
			self::$sRequestId = md5(
				$this->getSessionId( $bSetIfNeeded ).$this->loadIpService()->getRequestIp().$this->loadRequest()
																								 ->ts().wp_rand()
			);
		}
		return self::$sRequestId;
	}

	/**
	 * @return string
	 */
	public function getShortRequestId() {
		return substr( $this->getUniqueRequestId( false ), 0, 10 );
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
		$oWp = $this->loadWp();
		$oReq = $this->loadRequest();
		$oReq->setCookie(
			$this->getPluginPrefix(),
			$this->getSessionId(),
			$oReq->ts() + DAY_IN_SECONDS*30,
			$oWp->getCookiePath(),
			$oWp->getCookieDomain(),
			false
		);
	}

	/**
	 * We let the exception from the core plugin feature to bubble up because it's fairly critical.
	 * @return ICWP_WPSF_FeatureHandler_Plugin
	 * @throws Exception from loadFeatureHandler()
	 */
	public function &loadCorePluginFeatureHandler() {
		if ( !isset( $this->oFeatureHandlerPlugin ) ) {
			$this->loadFeatureHandler(
				array(
					'slug'          => 'plugin',
					'storage_key'   => 'plugin',
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

		$oCoreModule = $this->loadCorePluginFeatureHandler();

		$bSuccess = true;
		foreach ( $oCoreModule->getActivePluginFeatures() as $sSlug => $aFeatureProperties ) {
			try {
				$this->loadFeatureHandler( $aFeatureProperties, $bRecreate, $bFullBuild );
				$bSuccess = true;
			}
			catch ( Exception $oE ) {
				if ( $this->isValidAdminArea() && $this->isPluginAdmin() ) {
					$this->sAdminNoticeError = $oE->getMessage();
					add_action( 'admin_notices', array( $this, 'adminNoticePluginFailedToLoad' ) );
					add_action( 'network_admin_notices', array( $this, 'adminNoticePluginFailedToLoad' ) );
				}
			}
		}

		do_action( $this->prefix( 'run_processors' ) );

		return $bSuccess;
	}

	/**
	 * @param string $sSlug
	 * @return ICWP_WPSF_FeatureHandler_Base|null
	 */
	public function getModule( $sSlug ) {
		if ( !is_array( $this->aModules ) ) {
			$this->aModules = array();
		}
		$oModule = isset( $this->aModules[ $sSlug ] ) ? $this->aModules[ $sSlug ] : null;
		if ( !is_null( $oModule ) && !( $oModule instanceof ICWP_WPSF_FeatureHandler_Base ) ) {
			$oModule = null;
		}
		return $oModule;
	}

	/**
	 * @return ICWP_WPSF_FeatureHandler_Base[]
	 */
	public function getModules() {
		return is_array( $this->aModules ) ? $this->aModules : array();
	}

	/**
	 * @param array $aModProps
	 * @param bool  $bRecreate
	 * @param bool  $bFullBuild
	 * @return mixed
	 * @throws Exception
	 */
	public function loadFeatureHandler( $aModProps, $bRecreate = false, $bFullBuild = false ) {

		$sModSlug = $aModProps[ 'slug' ];

		$oHandler = $this->getModule( $sModSlug );
		if ( !empty( $oHandler ) ) {
			return $oHandler;
		}

		if ( !empty( $aModProps[ 'min_php' ] )
			 && !$this->loadDP()->getPhpVersionIsAtLeast( $aModProps[ 'min_php' ] ) ) {
			return null;
		}

		$sFeatureName = str_replace( ' ', '', ucwords( str_replace( '_', ' ', $sModSlug ) ) );
		$sOptionsVarName = sprintf( 'oFeatureHandler%s', $sFeatureName ); // e.g. oFeatureHandlerPlugin

		// e.g. ICWP_WPSF_FeatureHandler_Plugin
		$sClassName = sprintf( '%s_FeatureHandler_%s', strtoupper( $this->getPluginPrefix( '_' ) ), $sFeatureName );

		// All this to prevent fatal errors if the plugin doesn't install/upgrade correctly
		if ( class_exists( $sClassName ) ) {
			if ( !isset( $this->{$sOptionsVarName} ) || $bRecreate ) {
				$this->{$sOptionsVarName} = new $sClassName( $this, $aModProps );
			}
			if ( $bFullBuild ) {
				$this->{$sOptionsVarName}->buildOptions();
			}
		}
		else {
			$sMessage = sprintf( 'Class "%s" is missing', $sClassName );
			throw new Exception( $sMessage );
		}

		$this->aModules[ $sModSlug ] = $this->{$sOptionsVarName};
		return $this->{$sOptionsVarName};
	}

	/**
	 * @return ICWP_UserMeta
	 */
	public function getCurrentUserMeta() {
		return $this->loadWpUsers()->metaVoForUser( $this->prefix() );
	}

	/**
	 * @param $oUser WP_User
	 * @return ICWP_UserMeta
	 */
	public function getUserMeta( $oUser ) {
		return $this->loadWpUsers()->metaVoForUser( $this->prefix(), $oUser->ID );
	}

	/**
	 * @param array[] $aRegistered
	 * @return array[]
	 */
	public function onWpPrivacyRegisterExporter( $aRegistered ) {
		if ( !is_array( $aRegistered ) ) {
			$aRegistered = array(); // account for crap plugins that do-it-wrong.
		}

		$aRegistered[] = array(
			'exporter_friendly_name' => $this->getHumanName(),
			'callback'               => array( $this, 'wpPrivacyExport' ),
		);
		return $aRegistered;
	}

	/**
	 * @param array[] $aRegistered
	 * @return array[]
	 */
	public function onWpPrivacyRegisterEraser( $aRegistered ) {
		if ( !is_array( $aRegistered ) ) {
			$aRegistered = array(); // account for crap plugins that do-it-wrong.
		}

		$aRegistered[] = array(
			'eraser_friendly_name' => $this->getHumanName(),
			'callback'             => array( $this, 'wpPrivacyErase' ),
		);
		return $aRegistered;
	}

	/**
	 * @param string $sEmail
	 * @param int    $nPage
	 * @return array
	 */
	public function wpPrivacyExport( $sEmail, $nPage = 1 ) {

		$bValid = $this->loadDP()->validEmail( $sEmail )
				  && ( $this->loadWpUsers()->getUserByEmail( $sEmail ) instanceof WP_User );

		return array(
			'data' => $bValid ? apply_filters( $this->prefix( 'wpPrivacyExport' ), array(), $sEmail, $nPage ) : array(),
			'done' => true,
		);
	}

	/**
	 * @param string $sEmail
	 * @param int    $nPage
	 * @return array
	 */
	public function wpPrivacyErase( $sEmail, $nPage = 1 ) {

		$bValidUser = $this->loadDP()->validEmail( $sEmail )
					  && ( $this->loadWpUsers()->getUserByEmail( $sEmail ) instanceof WP_User );

		$aResult = array(
			'items_removed'  => $bValidUser,
			'items_retained' => false,
			'messages'       => $bValidUser ? array() : array( 'Email address not valid or does not belong to a user.' ),
			'done'           => true,
		);
		if ( $bValidUser ) {
			$aResult = apply_filters( $this->prefix( 'wpPrivacyErase' ), $aResult, $sEmail, $nPage );
		}
		return $aResult;
	}

	/**
	 * v5.4.1: Nasty looping bug in here where this function was called within the 'user_has_cap' filter
	 * so we removed the "current_user_can()" or any such sub-call within this function
	 * @deprecated v6.10.7
	 * @return bool
	 */
	public function getHasPermissionToManage() {
		if ( apply_filters( $this->prefix( 'bypass_permission_to_manage' ), false ) ) {
			return true;
		}
		return ( $this->isPluginAdmin() && apply_filters( $this->prefix( 'is_plugin_admin' ), true ) );
	}
}