<?php
/*
 * Plugin Name: Shield Security
 * Plugin URI: http://icwp.io/2f
 * Description: Powerful, Easy-To-Use #1 Rated WordPress Security System
 * Version: 5.16.4
 * Text Domain: wp-simple-firewall
 * Domain Path: /languages/
 * Author: iControlWP
 * Author URI: http://icwp.io/2e
 */

/**
 * Copyright (c) 2017 iControlWP <support@icontrolwp.com>
 * All rights reserved.
 *
 * "Shield" (formerly WordPress Simple Firewall) is distributed under the GNU
 * General Public License, Version 2, June 1991. Copyright (C) 1989, 1991 Free
 * Software Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110, USA
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

if ( class_exists( 'ICWP_Wordpress_Simple_Firewall', false ) ) {
	error_log( 'Attempting to load the Shield Plugin twice?' );
	return;
}

if ( !defined( 'ICWP_DS' ) ) {
	define( 'ICWP_DS', DIRECTORY_SEPARATOR );
}

if ( !function_exists( '_wpsf_e' ) ) {
	function _wpsf_e( $sStr ) {
		_e( $sStr, 'wp-simple-firewall' );
	}
}
if ( !function_exists( '_wpsf__' ) ) {
	function _wpsf__( $sStr ) {
		return __( $sStr, 'wp-simple-firewall' );
	}
}

// By requiring this file here, we assume we wont need to require it anywhere else.
require_once( dirname( __FILE__ ).DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'common'.DIRECTORY_SEPARATOR.'icwp-foundation.php' );
require_once( dirname( __FILE__ ).DIRECTORY_SEPARATOR.'icwp-plugin-controller.php' );

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
		add_filter( $oController->doPluginPrefix( 'plugin_update_message' ), array( $this, 'getPluginsListUpdateMessage' ) );
		add_action( 'plugin_action_links', array( $this, 'onWpPluginActionLinks' ), 10, 4 );
	}

	/**
	 * @return ICWP_WPSF_Plugin_Controller
	 */
	public static function getController() {
		return self::$oPluginController;
	}

	public function getPluginsListUpdateMessage( $sMessage ) {
		return _wpsf__( 'Upgrade Now To Keep Your Firewall Up-To-Date With The Latest Features.' );
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
	 */
	public static function GetInstance( $oController ) {
		if ( is_null( self::$oInstance ) ) {
			self::$oInstance = new self( $oController );
		}
		return self::$oInstance;
	}
}

$oICWP_Wpsf = null;
try {
	$oICWP_Wpsf_Controller = ICWP_WPSF_Plugin_Controller::GetInstance( __FILE__ );
	$oICWP_Wpsf = ICWP_WPSF_Shield_Security::GetInstance( $oICWP_Wpsf_Controller );
}
catch ( Exception $oE ) {
	if ( is_admin() ) {
		error_log( 'Perhaps due to a failed upgrade, the Shield plugin failed to load certain component(s) - you should remove the plugin and reinstall.' );
		error_log( $oE->getMessage() );
	}
}