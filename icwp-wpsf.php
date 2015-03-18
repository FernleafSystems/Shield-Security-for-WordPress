<?php
/*
 * Plugin Name: WordPress Simple Security Firewall
 * Plugin URI: http://icwp.io/2f
 * Description: Easy-To-Use WordPress Security System
 * Version: 4.5.1
 * Text Domain: wp-simple-firewall
 * Author: iControlWP
 * Author URI: http://icwp.io/2e
 */

/**
 * Copyright (c) 2015 iControlWP <support@icontrolwp.com>
 * All rights reserved.
 *
 * "WordPress Simple Security Firewall" is distributed under the GNU General Public License, Version 2,
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

if ( !defined('ICWP_DS') ) {
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
require_once( dirname(__FILE__).ICWP_DS.'src'.ICWP_DS.'common'.ICWP_DS.'icwp-foundation.php' );

if ( !class_exists( 'ICWP_Wordpress_Simple_Firewall', false ) ):

	class ICWP_Wordpress_Simple_Firewall extends ICWP_WPSF_Foundation {

		/**
		 * @var ICWP_WPSF_Plugin_Controller
		 */
		protected static $oPluginController;

		/**
		 * @param ICWP_WPSF_Plugin_Controller $oPluginController
		 */
		public function __construct( ICWP_WPSF_Plugin_Controller $oPluginController ) {

			// All core values of the plugin are derived from the values stored in this value object.
			self::$oPluginController = $oPluginController;
			$this->getController()->loadAllFeatures();
			add_filter( $this->getController()->doPluginPrefix( 'plugin_update_message' ), array( $this, 'getPluginsListUpdateMessage' ) );

			add_action( 'admin_init',				array( $this, 'onWpAdminInit' ) );
			add_action( 'plugin_action_links',		array( $this, 'onWpPluginActionLinks' ), 10, 4 );
		}

		/**
		 * @return ICWP_WPSF_Plugin_Controller
		 */
		public static function getController() {
			return self::$oPluginController;
		}

		public function onWpAdminInit() {
			$oCon = $this->getController();
			if ( $oCon->getIsValidAdminArea() ) {
				$oDp = $oCon->loadDataProcessor();
				$oWp = $oCon->loadWpFunctionsProcessor();

				$sRedirect = $oDp->FetchPost( 'redirect_page' );
				$sRedirect = empty( $sRedirect ) ? $this->getController()->getPluginUrl_AdminMainPage() : $sRedirect;
				//Someone clicked the button to acknowledge the update
				if ( $oDp->FetchRequest( $oCon->doPluginPrefix( 'hide_update_notice' ), false ) == 1 ) {
					$this->updateVersionUserMeta();
					$oWp->doRedirect( $sRedirect );
				}

				if ( $oDp->FetchRequest( $oCon->doPluginPrefix( 'hide_translation_notice' ), false ) == 1 ) {
					$this->updateTranslationNoticeShownUserMeta();
					$oWp->doRedirect( $sRedirect );
				}

				if ( $oDp->FetchRequest( $oCon->doPluginPrefix( 'hide_mailing_list_signup' ), false ) == 1 ) {
					$this->updateMailingListSignupShownUserMeta();
				}
			}
		}

		public function getPluginsListUpdateMessage( $sMessage ) {
			return _wpsf__( 'Upgrade Now To Keep Your Firewall Up-To-Date With The Latest Features.' );
		}

		/**
		 * On the plugins listing page, hides the edit and deactivate links
		 * for this plugin based on permissions
		 *
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
				if ( !apply_filters( $oCon->doPluginPrefix( 'has_permission_to_submit' ), true ) ) {

					if ( array_key_exists( 'edit', $aActionLinks ) ) {
						unset( $aActionLinks['edit'] );
					}
					if ( array_key_exists( 'deactivate', $aActionLinks ) ) {
						unset( $aActionLinks['deactivate'] );
					}
				}
			}
			return $aActionLinks;
		}

		/**
		 * Updates the current (or supplied user ID) user meta data with the version of the plugin
		 *
		 * @param $nId
		 * @param $sValue
		 */
		protected function updateTranslationNoticeShownUserMeta( $nId = '', $sValue = 'Y' ) {
			$oCon = $this->getController();
			$oCon->loadWpFunctionsProcessor()->updateUserMeta( $oCon->doPluginOptionPrefix( 'plugin_translation_notice' ), $sValue, $nId );
		}

		/**
		 * Updates the current (or supplied user ID) user meta data with the version of the plugin
		 *
		 * @param $nId
		 * @param $sValue
		 */
		protected function updateMailingListSignupShownUserMeta( $nId = '', $sValue = 'Y' ) {
			$oCon = $this->getController();
			$oCon->loadWpFunctionsProcessor()->updateUserMeta( $oCon->doPluginOptionPrefix( 'plugin_mailing_list_signup' ), $sValue, $nId );
		}

		/**
		 * Updates the current (or supplied user ID) user meta data with the version of the plugin
		 *
		 * @param integer $nId
		 */
		protected function updateVersionUserMeta( $nId = null ) {
			$oCon = $this->getController();
			$oCon->loadWpFunctionsProcessor()->updateUserMeta( $oCon->doPluginOptionPrefix( 'current_version' ), $oCon->getVersion(), $nId );
		}
	}

endif;

require_once( dirname(__FILE__).ICWP_DS.'icwp-plugin-controller.php' );

$oICWP_Wpsf_Controller = ICWP_WPSF_Plugin_Controller::GetInstance( __FILE__ );
if ( !is_null( $oICWP_Wpsf_Controller ) ) {
	$oICWP_Wpsf = new ICWP_Wordpress_Simple_Firewall( $oICWP_Wpsf_Controller );
}