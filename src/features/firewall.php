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

if ( !class_exists( 'ICWP_WPSF_FeatureHandler_Firewall', false ) ):

	require_once( dirname(__FILE__).ICWP_DS.'base.php' );

	class ICWP_WPSF_FeatureHandler_Firewall extends ICWP_WPSF_FeatureHandler_Base {

		/**
		 * @return string
		 */
		protected function getProcessorClassName() {
			return 'ICWP_WPSF_Processor_Firewall';
		}

		protected function doExecuteProcessor() {
			if ( ! apply_filters( $this->doPluginPrefix( 'visitor_is_whitelisted' ), false ) ) {
				parent::doExecuteProcessor();
			}
		}

		/**
		 */
		public function doPrePluginOptionsSave() {

			$aIpBlacklist = $this->getOpt( 'ips_blacklist' );
			if ( $aIpBlacklist === false ) {
				$aIpBlacklist = '';
				$this->setOpt( 'ips_blacklist', $aIpBlacklist );
			}
			$this->processIpFilter( 'ips_blacklist', 'icwp_simple_firewall_blacklist_ips' );

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

			$this->setOpt( 'ips_whitelist', '' );
		}

		/**
		 * @param array $aOptionsParams
		 * @return array
		 * @throws Exception
		 */
		protected function loadStrings_SectionTitles( $aOptionsParams ) {

			$sSectionSlug = $aOptionsParams['section_slug'];
			switch( $aOptionsParams['section_slug'] ) {

				case 'section_enable_plugin_feature_wordpress_firewall' :
					$sTitle = sprintf( _wpsf__( 'Enable Plugin Feature: %s' ), $this->getMainFeatureName() );
					break;

				case 'section_firewall_blocking_options' :
					$sTitle = _wpsf__('Firewall Blocking Options');
					break;

				case 'section_choose_firewall_block_response' :
					$sTitle = _wpsf__('Choose Firewall Block Response');
					break;

				case 'section_whitelist' :
					$sTitle = _wpsf__('Whitelists - IPs, Pages, Parameters, and Users that by-pass the Firewall');
					break;

				case 'section_blacklist' :
					$sTitle = _wpsf__('Choose IP Addresses To Blacklist');
					break;

				case 'section_firewall_logging' :
					$sTitle = _wpsf__('Firewall Logging Options');
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

				case 'ips_blacklist' :
					$sName = _wpsf__( 'Blacklist IP Addresses' );
					$sSummary = _wpsf__( 'Choose IP Addresses that are always blocked from accessing the site' );
					$sDescription = _wpsf__( 'Take a new line per address. Each IP Address must be valid and will be checked.' );
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

		/**
		 * @return bool
		 */
		protected function doExtraSubmitProcessing() {
			$oDp = $this->loadDataProcessor();

			$this->addRawIpsToFirewallList( 'ips_whitelist', array( $oDp->FetchGet( 'whiteip' ) ) );
			$this->removeRawIpsFromFirewallList( 'ips_whitelist', array( $oDp->FetchGet( 'unwhiteip' ) ) );
			$this->addRawIpsToFirewallList( 'ips_blacklist', array( $oDp->FetchGet( 'blackip' ) ) );
			$this->removeRawIpsFromFirewallList( 'ips_blacklist', array( $oDp->FetchGet( 'unblackip' ) ) );
			return true;
		}

		/**
		 * @param $insListName
		 * @param $inaNewIps
		 */
		public function addRawIpsToFirewallList( $insListName, $inaNewIps ) {
			if ( empty( $inaNewIps ) ) {
				return;
			}

			$aIplist = $this->getOpt( $insListName );
			if ( empty( $aIplist ) ) {
				$aIplist = array();
			}
			$aNewList = array();
			foreach( $inaNewIps as $sAddress ) {
				$aNewList[ $sAddress ] = '';
			}
			$oDp = $this->loadDataProcessor();
			$this->setOpt( $insListName, $oDp->addNewRawIps( $aIplist, $aNewList ) );
		}

		public function removeRawIpsFromFirewallList( $insListName, $inaRemoveIps ) {
			if ( empty( $inaRemoveIps ) ) {
				return;
			}

			$aIplist = $this->getOpt( $insListName );
			if ( empty( $aIplist ) || empty( $inaRemoveIps ) ) {
				return;
			}
			$oDp = $this->loadDataProcessor();
			$this->setOpt( $insListName, $oDp->removeRawIps( $aIplist, $inaRemoveIps ) );
		}

	}

endif;