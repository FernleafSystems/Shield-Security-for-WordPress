<?php

if ( !class_exists( 'ICWP_WPSF_FeatureHandler_Lockdown', false ) ):

	require_once( dirname(__FILE__).DIRECTORY_SEPARATOR.'base_wpsf.php' );

	class ICWP_WPSF_FeatureHandler_Lockdown extends ICWP_WPSF_FeatureHandler_BaseWpsf {

		protected function doExecuteProcessor() {
			if ( ! apply_filters( $this->doPluginPrefix( 'visitor_is_whitelisted' ), false ) ) {
				parent::doExecuteProcessor();
			}
		}

		public function doPrePluginOptionsSave() {
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
					$aSummary = array(
						sprintf( _wpsf__( 'Purpose - %s' ), _wpsf__( 'Lockdown helps secure-up certain loosely-controlled WordPress settings on your site.' ) ),
						sprintf( _wpsf__( 'Recommendation - %s' ), sprintf( _wpsf__( 'Keep the %s feature turned on.' ), _wpsf__( 'Lockdown' ) ) )
					);
					$sTitleShort = sprintf( '%s / %s', _wpsf__( 'Enable' ), _wpsf__( 'Disable' ) );
					break;

				case 'section_system_lockdown' :
					$sTitle = _wpsf__( 'WordPress System Lockdown' );
					$aSummary = array(
						sprintf( _wpsf__( 'Purpose - %s' ), _wpsf__( 'Lockdown certain core WordPress system features.' ) ),
						sprintf( _wpsf__( 'Recommendation - %s' ), _wpsf__( 'This depends on your usage and needs for certain WordPress functions and features.' ) )
					);
					$sTitleShort = _wpsf__( 'System' );
					break;

				case 'section_permission_access_options' :
					$sTitle = _wpsf__( 'Permissions and Access Options' );
					$aSummary = array(
						sprintf( _wpsf__( 'Purpose - %s' ), _wpsf__( 'Provides finer control of certain WordPress permissions.' ) ),
						sprintf( _wpsf__( 'Recommendation - %s' ), _wpsf__( 'Only enable SSL if you have a valid certificate installed.' ) )
					);
					$sTitleShort = _wpsf__( 'Permissions' );
					break;

				case 'section_wordpress_obscurity_options' :
					$sTitle = _wpsf__( 'WordPress Obscurity Options' );
					$aSummary = array(
						sprintf( _wpsf__( 'Purpose - %s' ), _wpsf__( 'Obscures certain WordPress settings from public view.' ) ),
						sprintf( _wpsf__( 'Recommendation - %s' ), _wpsf__( 'Obscurity is not true security and so these settings are down to your personal tastes.' ) )
					);
					$sTitleShort = _wpsf__( 'Obscurity' );
					break;

				default:
					throw new Exception( sprintf( 'A section slug was defined but with no associated strings. Slug: "%s".', $sSectionSlug ) );
			}
			$aOptionsParams['section_title'] = $sTitle;
			$aOptionsParams['section_summary'] = ( isset( $aSummary ) && is_array( $aSummary ) ) ? $aSummary : array();
			$aOptionsParams['section_title_short'] = $sTitleShort;
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

				case 'disable_xmlrpc' :
					$sName = sprintf( _wpsf__( 'Disable %s' ), 'XML-RPC' );
					$sSummary = sprintf( _wpsf__( 'Disable The %s System' ), 'XML-RPC' );
					$sDescription = sprintf( _wpsf__( 'Checking this option will completely turn off the whole %s system.' ), 'XML-RPC' );
					break;

				case 'disable_file_editing' :
					$sName = _wpsf__( 'Disable File Editing' );
					$sSummary = _wpsf__( 'Disable Ability To Edit Files From Within WordPress' );
					$sDescription = _wpsf__( 'Removes the option to directly edit any files from within the WordPress admin area.' )
									.'<br />'._wpsf__( 'Equivalent to setting "DISALLOW_FILE_EDIT" to TRUE.' );
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

				case 'block_author_discovery' :
					$sName = _wpsf__( 'Block Username Fishing' );
					$sSummary = _wpsf__( 'Block the ability to discover WordPress usernames based on author IDs' );
					$sDescription = sprintf( _wpsf__( 'When enabled, any URL requests containing "%s" will be killed.' ), 'author=' )
					.'<br />'. sprintf( _wpsf__( 'Warning: %s' ), _wpsf__( 'Enabling this option may interfere with expected operations of your site.' ) );
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

			$sWpConfigPath = $oWpFs->exists( ABSPATH.'wp-config.php' )? ABSPATH.'wp-config.php' : ABSPATH.'..'.DIRECTORY_SEPARATOR.'wp-config.php';

			if ( !$oWpFs->exists( $sWpConfigPath ) ) {
				return false;
			}
			$mResult = $oWpFs->getCanReadWriteFile( $sWpConfigPath );
			return !empty( $mResult );
		}
	}

endif;