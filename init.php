<?php
/** @var string $sRootFile */
global $oICWP_Wpsf;

if ( isset( $oICWP_Wpsf ) ) {
	error_log( 'Attempting to load the Shield Plugin twice?' );
	return;
}

// By requiring this file here, we assume we wont need to require it anywhere else.
require_once( dirname( __FILE__ ).'/icwp-plugin-controller.php' );

class ICWP_Wordpress_Simple_Firewall extends ICWP_WPSF_Foundation {

	/**
	 * @var ICWP_WPSF_Plugin_Controller
	 */
	protected static $oPluginController;

	/**
	 * @param ICWP_WPSF_Plugin_Controller $oController
	 */
	protected function __construct( ICWP_WPSF_Plugin_Controller $oController ) {

		// All core values of the plugin are derived from the values stored in this value object.
		self::$oPluginController = $oController;
		$this->getController()->loadAllFeatures();
		add_filter( $oController->prefix( 'plugin_update_message' ), array(
			$this,
			'getPluginsListUpdateMessage'
		) );
		add_action( 'plugin_action_links', array( $this, 'onWpPluginActionLinks' ), 10, 4 );
	}

	/**
	 * @return ICWP_WPSF_Plugin_Controller
	 */
	public static function getController() {
		return self::$oPluginController;
	}

	/**
	 * @param string $sMessage
	 * @return string
	 */
	public function getPluginsListUpdateMessage( $sMessage ) {
		return _wpsf__( 'Upgrade Now To Keep Your Security Up-To-Date With The Latest Features.' );
	}

	/**
	 * On the plugins listing page, hides the edit and deactivate links
	 * for this plugin based on permissions
	 * @param $aActionLinks
	 * @param $sPluginFile
	 * @return mixed
	 */
	public function onWpPluginActionLinks( $aActionLinks, $sPluginFile ) {
		$oCon = $this->getController();
		if ( !$oCon->getIsValidAdminArea() ) {
			return $aActionLinks;
		}

		if ( $sPluginFile == $oCon->getPluginBaseFile() ) {
			if ( !$oCon->getHasPermissionToManage() ) {

				if ( array_key_exists( 'edit', $aActionLinks ) ) {
					unset( $aActionLinks[ 'edit' ] );
				}
				if ( array_key_exists( 'deactivate', $aActionLinks ) ) {
					unset( $aActionLinks[ 'deactivate' ] );
				}
			}
		}
		return $aActionLinks;
	}
}

class ICWP_WPSF_Shield_Security extends ICWP_Wordpress_Simple_Firewall {

	/**
	 * @var ICWP_Wordpress_Simple_Firewall
	 */
	protected static $oInstance = null;

	/**
	 * @param ICWP_WPSF_Plugin_Controller $oController
	 * @return self
	 * @throws Exception
	 */
	public static function GetInstance( $oController = null ) {
		if ( is_null( self::$oInstance ) ) {
			if ( is_null( $oController ) || !( $oController instanceof ICWP_WPSF_Plugin_Controller ) ) {
				throw new Exception( 'Trying to create a Shield Plugin instance without a valid Controller' );
			}
			self::$oInstance = new self( $oController );
		}
		return self::$oInstance;
	}
}

try {
	$oICWP_Wpsf_Controller = ICWP_WPSF_Plugin_Controller::GetInstance( $sRootFile );
	$oICWP_Wpsf = ICWP_WPSF_Shield_Security::GetInstance( $oICWP_Wpsf_Controller );
}
catch ( Exception $oE ) {
	if ( is_admin() ) {
		error_log( 'Perhaps due to a failed upgrade, the Shield plugin failed to load certain component(s) - you should remove the plugin and reinstall.' );
		error_log( $oE->getMessage() );
	}
}