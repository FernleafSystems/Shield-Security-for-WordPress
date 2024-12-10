<?php
/*
 * Plugin Name: Shield Security
 * Plugin URI: https://clk.shldscrty.com/2f
 * Description: Powerful, Easy-To-Use #1 Rated WordPress Security System
 * Version: 20.1.2
 * Text Domain: wp-simple-firewall
 * Domain Path: /languages
 * Author: Shield Security
 * Author URI: https://clk.shldscrty.com/bv
 * Requires at least: 5.7
 * Requires PHP: 7.4
 */

/**
 * Copyright (c) 2025 Shield Security <support@getshieldsecurity.com>
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

if ( defined( 'ABSPATH' ) ) {
	if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
		global $sIcwpWpsfPluginFile;
		$sIcwpWpsfPluginFile = plugin_basename( __FILE__ );
		include_once( dirname( __FILE__ ).'/unsupported.php' );
	}
	elseif ( @is_file( dirname( __FILE__ ).'/src/lib/vendor/autoload.php' ) ) {

		try {
			require_once( dirname( __FILE__ ).'/plugin_compatibility.php' );

			require_once( dirname( __FILE__ ).'/plugin_autoload.php' );

			add_action( 'plugins_loaded', 'icwp_wpsf_init', 1 ); // use 0 for extensions to ensure hooks have been added.
			function icwp_wpsf_init() {
				$rootFile = __FILE__;
				require_once( dirname( __FILE__ ).'/plugin_init.php' );
			}

			register_activation_hook( __FILE__, 'icwp_wpsf_onactivate' );
			function icwp_wpsf_onactivate() {
				icwp_wpsf_init();
				try {
					\FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller::GetInstance()->onWpActivatePlugin();
				}
				catch ( \Exception|\Error $e ) {
				}
			}
		}
		catch ( \Exception $e ) {
		}
	}
	else {
		add_action( 'admin_notices', function () {
			echo sprintf( '<div class="error"><h4>%s</h4><p>%s</p></div>',
				'Shield Security Plugin - Broken Installation',
				implode( '<br/>', [
					'It appears the Shield Security plugin was not upgraded/installed correctly.',
					"We check to ensure critical files are present in-case a faulty installation breaks your site.",
					'Try refreshing this page, and if you continue to see this notice, we recommend that you reinstall the Shield Security plugin.'
				] )
			);
		} );
	}
}