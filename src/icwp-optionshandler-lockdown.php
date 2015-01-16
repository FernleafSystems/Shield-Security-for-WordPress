<?php
/**
 * Copyright (c) 2015 iControlWP <support@icontrolwp.com>
 * All rights reserved.
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

require_once( 'icwp-optionshandler-base.php' );

if ( !class_exists( 'ICWP_WPSF_FeatureHandler_Lockdown', false ) ):

	class ICWP_WPSF_FeatureHandler_Lockdown extends ICWP_WPSF_FeatureHandler_Base {

		/**
		 * @return string
		 */
		protected function getProcessorClassName() {
			return 'ICWP_WPSF_Processor_Lockdown';
		}

		protected function doExecuteProcessor() {
			$sIp = $this->loadDataProcessor()->getVisitorIpAddress();
			$aIpWhitelist = apply_filters( $this->doPluginPrefix( 'ip_whitelist' ), array() );
			if ( is_array( $aIpWhitelist ) && ( in_array( $sIp, $aIpWhitelist )  ) ) {
				return;
			}
			parent::doExecuteProcessor();
		}

		public function doPrePluginOptionsSave() {

//		if ( $this->getOpt( 'action_reset_auth_salts' ) == 'Y' ) {
//			$this->setOpt( 'action_reset_auth_salts', 'P' );
//		}
//		else if ( $this->getOpt( 'action_reset_auth_salts' ) == 'P' ) {
//			$this->setOpt( 'action_reset_auth_salts', 'N' );
//		}

			$sCurrent = $this->getOpt( 'mask_wordpress_version' );
			if ( !empty( $sCurrent ) ) {
				$this->setOpt( 'mask_wordpress_version', preg_replace( '/[^a-z0-9_.-]/i', '', $sCurrent ) );
			}
		}

		/**
		 * @param array $aOptionsParams
		 * @return array
		 * @throws Exception
		 */
		protected function loadStrings_SectionTitles( $aOptionsParams ) {

			$sSectionSlug = $aOptionsParams['section_slug'];
			switch( $aOptionsParams['section_slug'] ) {

				case 'section_enable_plugin_feature_wordpress_lockdown' :
					$sTitle = sprintf( _wpsf__( 'Enable Plugin Feature: %s' ), $this->getMainFeatureName() );
					break;

				case 'section_permission_access_options' :
					$sTitle = _wpsf__('Permissions and Access Options');
					break;

				case 'section_wordpress_obscurity_options' :
					$sTitle = _wpsf__('WordPress Obscurity Options');
					break;

				default:
					throw new Exception( sprintf( 'A section slug was defined but with no associated strings. Slug: "%s".', $sSectionSlug ) );
			}
			$aOptionsParams['section_title'] = $sTitle;
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

				case 'enable_lockdown' :
					$sName = sprintf( _wpsf__( 'Enable %s' ), $this->getMainFeatureName() );
					$sSummary = sprintf( _wpsf__( 'Enable (or Disable) The %s Feature' ), $this->getMainFeatureName() );
					$sDescription = sprintf( _wpsf__( 'Checking/Un-Checking this option will completely turn on/off the whole %s feature.' ), $this->getMainFeatureName() );
					break;

				case 'disable_file_editing' :
					$sName = _wpsf__( 'Disable File Editing' );
					$sSummary = _wpsf__( 'Disable Ability To Edit Files From Within WordPress' );
					$sDescription = _wpsf__( 'Removes the option to directly edit any files from within the WordPress admin area.' )
									.'<br />'._wpsf__( 'Equivalent to setting "DISALLOW_FILE_EDIT" to TRUE.' );
					break;

				case 'force_ssl_login' :
					$sName = _wpsf__( 'Force SSL Login' );
					$sSummary = _wpsf__( 'Forces Login Form To Be Submitted Over SSL' );
					$sDescription = _wpsf__( 'Please only enable this option if you have a valid SSL certificate installed.' )
									.'<br />'._wpsf__( 'Equivalent to setting FORCE_SSL_LOGIN to TRUE.' );
					break;

				case 'force_ssl_admin' :
					$sName = _wpsf__( 'Force SSL Admin' );
					$sSummary = _wpsf__( 'Forces WordPress Admin Dashboard To Be Delivered Over SSL' );
					$sDescription = _wpsf__( 'Please only enable this option if you have a valid SSL certificate installed.' )
									.'<br />'._wpsf__( 'Equivalent to setting "FORCE_SSL_ADMIN" to TRUE.' );
					break;

				case 'mask_wordpress_version' :
					$sName = _wpsf__( 'Mask WordPress Version' );
					$sSummary = _wpsf__( 'Prevents Public Display Of Your WordPress Version' );
					$sDescription = _wpsf__( 'Enter how you would like your WordPress version displayed publicly. Leave blank to disable this feature.' )
									.'<br />'._wpsf__( 'Warning: This may interfere with WordPress plugins that rely on the $wp_version variable.' );
					break;

				case 'hide_wordpress_generator_tag' :
					$sName = _wpsf__( 'WP Generator Tag' );
					$sSummary = _wpsf__( 'Remove WP Generator Meta Tag' );
					$sDescription = _wpsf__( 'Remove a meta tag from your WordPress pages that publicly displays that your site is WordPress and its current version.' );
					break;

				default:
					throw new Exception( sprintf( 'An option has been defined but without strings assigned to it. Option key: "%s".', $sKey ) );
			}

			$aOptionsParams['name'] = $sName;
			$aOptionsParams['summary'] = $sSummary;
			$aOptionsParams['description'] = $sDescription;
			return $aOptionsParams;
		}

		protected function getCanDoAuthSalts() {
			$oWpFs = $this->loadFileSystemProcessor();

			if ( !$oWpFs->getCanWpRemoteGet() ) {
				return false;
			}

			if ( !$oWpFs->getCanDiskWrite() ) {
				return false;
			}

			$sWpConfigPath = $oWpFs->exists( ABSPATH.'wp-config.php' )? ABSPATH.'wp-config.php' : ABSPATH.'..'.ICWP_DS.'wp-config.php';

			if ( !$oWpFs->exists( $sWpConfigPath ) ) {
				return false;
			}
			$mResult = $oWpFs->getCanReadWriteFile( $sWpConfigPath );
			return !empty( $mResult );
		}
	}

endif;