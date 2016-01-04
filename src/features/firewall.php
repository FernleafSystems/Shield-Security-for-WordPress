<?php

if ( !class_exists( 'ICWP_WPSF_FeatureHandler_Firewall', false ) ):

	require_once( dirname(__FILE__).ICWP_DS.'base.php' );

	class ICWP_WPSF_FeatureHandler_Firewall extends ICWP_WPSF_FeatureHandler_Base {

		protected function doExecuteProcessor() {
			if ( ! apply_filters( $this->doPluginPrefix( 'visitor_is_whitelisted' ), false ) ) {
				parent::doExecuteProcessor();
			}
		}

		/**
		 */
		public function doPrePluginOptionsSave() {

			$aPageWhitelist = $this->getOpt( 'page_params_whitelist' );
			if ( $aPageWhitelist === false ) {
				$aPageWhitelist = '';
				$this->setOpt( 'page_params_whitelist', $aPageWhitelist );
			}

			$sBlockResponse = $this->getOpt( 'block_response' );
			if ( empty( $sBlockResponse ) ) {
				$sBlockResponse = 'redirect_die_message';
				$this->setOpt( 'block_response', $sBlockResponse );
			}
		}

		/**
		 * @param array $aOptionsParams
		 * @return array
		 * @throws Exception
		 */
		protected function loadStrings_SectionTitles( $aOptionsParams ) {

			switch( $aOptionsParams['section_slug'] ) {

				case 'section_enable_plugin_feature_wordpress_firewall' :
					$sTitle = sprintf( _wpsf__( 'Enable Plugin Feature: %s' ), $this->getMainFeatureName() );
					$aSummary = array(
						sprintf( _wpsf__( 'Purpose - %s' ), _wpsf__( 'The Firewall is designed to analyse data sent to your website and block any requests that appear to be malicious.' ) ),
						sprintf( _wpsf__( 'Recommendation - %s' ), sprintf( _wpsf__( 'Keep the %s feature turned on.' ), _wpsf__( 'Firewall' ) ) )
					);
					$sTitleShort = sprintf( '%s / %s', _wpsf__( 'Enable' ), _wpsf__( 'Disable' ) );
					break;

				case 'section_firewall_blocking_options' :
					$sTitle = _wpsf__( 'Firewall Blocking Options' );
					$aSummary = array(
						_wpsf__( 'Here you choose what kind of malicious data to scan for.' ),
						sprintf( _wpsf__( 'Recommendation - %s' ),
							_wpsf__( 'Turn on as many options here as you can.' ) )
						.' '._wpsf__('If you find an incompatibility or something stops working, un-check 1 option at a time until you find the problem or review the Audit Trail.'),
					);
					$sTitleShort = _wpsf__( 'Firewall Blocking' );
					break;

				case 'section_choose_firewall_block_response' :
					$sTitle = _wpsf__('Choose Firewall Block Response');
					$aSummary = array(
						_wpsf__( 'Here you choose how the plugin will respond when it detects malicious data.' ),
						sprintf( _wpsf__( 'Recommendation - %s' ), sprintf( _wpsf__( 'Choose the option "%s".' ), _wpsf__('Die With Message') ) )
					);
					$sTitleShort = _wpsf__( 'Firewall Response' );
					break;

				case 'section_whitelist' :
					$sTitle = _wpsf__('Whitelists - IPs, Pages, Parameters, and Users that by-pass the Firewall');
					$aSummary = array(
						_wpsf__( 'In principle you should not need to whitelist anything or anyone unless you have discovered a collision with another plugin.' ),
						sprintf( _wpsf__( 'Recommendation - %s' ), _wpsf__( 'Do not whitelist anything unless you are confident in what you are doing.' ) )
					);
					$sTitleShort = _wpsf__( 'Whitelist' );
					break;

				case 'section_blacklist' :
					$sTitle = _wpsf__('Choose IP Addresses To Blacklist');
					$aSummary = array(
						_wpsf__( 'IP Address blacklists are nearly completely useless but is provided here in case absolutely required.' ),
						sprintf( _wpsf__( 'Recommendation - %s' ), _wpsf__( 'Do not blacklist anything unless you are confident in what you are doing.' ) )
					);
					$sTitleShort = _wpsf__( 'Blacklist' );
					break;

				case 'section_firewall_logging' :
					$sTitle = _wpsf__( 'Firewall Logging Options' );
					$sTitleShort = _wpsf__( 'Logging' );
					break;

				default:
					$sTitle = sprintf( _wpsf__( 'Enable Plugin Feature: %s' ), $this->getMainFeatureName() );
					$sTitleShort = sprintf( '%s / %s', _wpsf__( 'Enable' ), _wpsf__( 'Disable' ) );
//					throw new Exception( sprintf( 'A section slug was defined but with no associated strings. Slug: "%s".', $sSectionSlug ) );
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

			$oDp = $this->loadDataProcessor();
			$sKey = $aOptionsParams['key'];

			switch( $sKey ) {

				case 'enable_firewall' :
					$sName = sprintf( _wpsf__( 'Enable %s' ), $this->getMainFeatureName() );
					$sSummary = sprintf( _wpsf__( 'Enable (or Disable) The %s Feature' ), $this->getMainFeatureName() );
					$sDescription = sprintf( _wpsf__( 'Checking/Un-Checking this option will completely turn on/off the whole %s feature.' ), $this->getMainFeatureName() );
					break;

				case 'include_cookie_checks' :
					$sName = _wpsf__( 'Include Cookies' );
					$sSummary = _wpsf__( 'Also Test Cookie Values In Firewall Tests' );
					$sDescription = _wpsf__( 'The firewall tests GET and POST, but with this option checked it will also check COOKIE values.' );
					break;

				case 'block_dir_traversal' :
					$sName = _wpsf__( 'Directory Traversals' );
					$sSummary = _wpsf__( 'Block Directory Traversals' );
					$sDescription = _wpsf__( 'This will block directory traversal paths in in application parameters (e.g. ../, ../../etc/passwd, etc.).' );
					break;

				case 'block_sql_queries' :
					$sName = _wpsf__( 'SQL Queries' );
					$sSummary = _wpsf__( 'Block SQL Queries' );
					$sDescription = _wpsf__( 'This will block sql in application parameters (e.g. union select, concat(, /**/, etc.).' );
					break;

				case 'block_wordpress_terms' :
					$sName = _wpsf__( 'WordPress Terms' );
					$sSummary = _wpsf__( 'Block WordPress Specific Terms' );
					$sDescription = _wpsf__( 'This will block WordPress specific terms in application parameters (wp_, user_login, etc.).' );
					break;

				case 'block_field_truncation' :
					$sName = _wpsf__( 'Field Truncation' );
					$sSummary = _wpsf__( 'Block Field Truncation Attacks' );
					$sDescription = _wpsf__( 'This will block field truncation attacks in application parameters.' );
					break;

				case 'block_php_code' :
					$sName = _wpsf__( 'PHP Code' );
					$sSummary = sprintf( _wpsf__( 'Block %s' ), _wpsf__( 'PHP Code Includes' ) );
					$sDescription = _wpsf__( 'This will block any data that appears to try and include PHP files.' )
									.'<br />'. _wpsf__( 'Will probably block saving within the Plugin/Theme file editors.' );
					break;

				case 'block_exe_file_uploads' :
					$sName = _wpsf__( 'Exe File Uploads' );
					$sSummary = _wpsf__( 'Block Executable File Uploads' );
					$sDescription = _wpsf__( 'This will block executable file uploads (.php, .exe, etc.).' );
					break;

				case 'block_aggressive' :
					$sName = _wpsf__( 'Aggressive Scan' );
					$sSummary = _wpsf__( 'Aggressively Block Data' );
					$sDescription = _wpsf__( 'Uses a set of aggressive rules to detect and block data submitted to your site.' );
					break;

				case 'block_leading_schema' :
					$sName = _wpsf__( 'Leading Schemas' );
					$sSummary = _wpsf__( 'Block Leading Schemas (HTTPS / HTTP)' );
					$sDescription = _wpsf__( 'This will block leading schemas http:// and https:// in application parameters (off by default; may cause problems with other plugins).' );
					break;

				case 'block_response' :
					$sName = _wpsf__( 'Block Response' );
					$sSummary = _wpsf__( 'Choose how the firewall responds when it blocks a request' );
					$sDescription = _wpsf__( 'We recommend dying with a message so you know what might have occurred when the firewall blocks you' );
					break;

				case 'block_send_email' :
					$sName = _wpsf__( 'Send Email Report' );
					$sSummary = _wpsf__( 'When a visitor is blocked the firewall will send an email to the configured email address' );
					$sDescription = _wpsf__( 'Use with caution - if you get hit by automated bots you may send out too many emails and you could get blocked by your host' );
					break;

				case 'page_params_whitelist' :
					$sName = _wpsf__( 'Whitelist Parameters' );
					$sSummary = _wpsf__( 'Detail pages and parameters that are whitelisted (ignored by the firewall)' );
					$sDescription = _wpsf__( 'This should be used with caution and you should only provide parameter names that you must have excluded' );
					break;

				case 'whitelist_admins' :
					$sName = sprintf( _wpsf__( 'Ignore %s' ), _wpsf__( 'Administrators' ) );
					$sSummary = sprintf( _wpsf__( 'Ignore %s' ), _wpsf__( 'Administrators' ) );
					$sDescription = _wpsf__( 'Authenticated administrator users will not be processed by the firewall rules.' );
					break;

				case 'ignore_search_engines' :
					$sName = sprintf( _wpsf__( 'Ignore %s' ), _wpsf__( 'Search Engines' ) );
					$sSummary = _wpsf__( 'Ignore Search Engine Bot Traffic' );
					$sDescription = _wpsf__( 'The firewall will try to recognise search engine spiders/bots and not apply firewall rules to them.' );
					break;

				case 'enable_firewall_log' :
					$sName = _wpsf__( 'Firewall Logging' );
					$sSummary = _wpsf__( 'Turn on Firewall Log' );
					$sDescription = _wpsf__( 'Will log every visit to the site and how the firewall processes it. Not recommended to leave on unless you want to debug something and check the firewall is working as you expect' );
					break;

				default:
					throw new Exception( sprintf( 'An option has been defined but without strings assigned to it. Option key: "%s".', $sKey ) );
			}

			$aOptionsParams['name'] = $sName;
			$aOptionsParams['summary'] = $sSummary;
			$aOptionsParams['description'] = $sDescription;
			return $aOptionsParams;
		}
	}

endif;