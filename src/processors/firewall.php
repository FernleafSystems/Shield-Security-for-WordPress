<?php

if ( !class_exists( 'ICWP_FirewallProcessor_V1', false ) ):

	require_once( dirname(__FILE__).ICWP_DS.'base.php' );

	class ICWP_FirewallProcessor_V1 extends ICWP_WPSF_Processor_Base {

		protected $aWhitelistPages;

		/**
		 * @var int
		 */
		private $nLoopProtect;

		/**
		 * @var string
		 */
		private $sFirewallDieMessage;

		/**
		 * @var bool
		 */
		private $bDoFirewallBlock;

		/**
		 * @var string
		 */
		protected $sListItemLabel;

		/**
		 * This is $m_aOrigPageParams after any parameter whitelisting has taken place
		 * @var array
		 */
		protected $aPageParams;

		/**
		 * @var array
		 */
		protected $aRawRequestParams;

		/**
		 * @param ICWP_WPSF_FeatureHandler_Firewall $oFeatureOptions
		 */
		public function __construct( ICWP_WPSF_FeatureHandler_Firewall $oFeatureOptions ) {
			parent::__construct( $oFeatureOptions );

			$sMessage = _wpsf__( "You were blocked by the %s." );
			$this->sFirewallDieMessage = sprintf(
				$sMessage,
				'<a href="http://wordpress.org/plugins/wp-simple-firewall/" target="_blank">'
				.( $this->getController()->getHumanName() )
				.'</a>'
			);
		}

		public function reset() {
			parent::reset();
			$this->nLoopProtect = 0;
		}

		public function run() {
			$this->bDoFirewallBlock = !$this->doFirewallCheck();
			$this->doPreFirewallBlock();
			$this->doFirewallBlock();
		}

		/**
		 * @return bool
		 */
		public function getIfDoFirewallBlock() {
			return isset( $this->bDoFirewallBlock ) ? $this->bDoFirewallBlock : false;
		}

		/**
		 * @return boolean - true if visitor is permitted, false if it should be blocked.
		 */
		public function doFirewallCheck() {
			// Nothing to check in the first place
			if ( count( $this->getRawRequestParams() ) < 1 ) {
				return true;
			}

			$oDp = $this->loadDataProcessor();

			// if we couldn't process the REQUEST_URI parts, we can't firewall so we effectively whitelist without erroring.
			$aRequestParts = $oDp->getRequestUriParts();
			if ( empty( $aRequestParts ) ) {
				$sAuditMessage = sprintf( _wpsf__('Skipping firewall checking for this visit: %s.'), _wpsf__('Parsing the URI failed') );
				$this->addToAuditEntry( $sAuditMessage, 2, 'firewall_skip' );
				return true;
			}

			if ( $this->getIsOption( 'whitelist_admins', 'Y' ) && is_super_admin() ) {
//				$sAuditMessage = sprintf( _wpsf__('Skipping firewall checking for this visit: %s.'), _wpsf__('Logged-in administrators by-pass firewall') );
//				$this->addToAuditEntry( $sAuditMessage, 2, 'firewall_skip' );
				return true;
			}

			// Check if the visitor is excluded from the firewall from the outset.
			if ( $this->isVisitorOnBlacklist() ) {
				$this->sFirewallDieMessage .= ' Your IP is Blacklisted.';
				$sAuditMessage =  _wpsf__('Visitor was black-listed by IP Address.')
					.' '.sprintf( _wpsf__('Label: %s.'), empty( $this->sListItemLabel )? _wpsf__('No label') : $this->sListItemLabel );
				$this->doStatIncrement( 'firewall.blocked.blacklist' );
				return false;
			}

			if ( $this->getOption('ignore_search_engines') == 'Y' && $oDp->IsSearchEngineBot() ) {
				$sAuditMessage = sprintf( _wpsf__('Skipping firewall checking for this visit: %s.'), _wpsf__('Visitor detected as Search Engine Bot') );
				$this->addToAuditEntry( $sAuditMessage, 2, 'firewall_skip' );
				return true;
			}

			$aPageParamsToCheck = $this->getParamsToCheck();
			if ( empty( $aPageParamsToCheck ) ) {
//				$sAuditMessage = sprintf( _wpsf__('Skipping firewall checking for this visit: %s.'), _wpsf__('After whitelist options were applied, there were no page parameters to check') );
//				$this->addToAuditEntry( $sAuditMessage, 1, 'firewall_skip' );
				return true;
			}

			$fIsPermittedVisitor = true;
			if ( $fIsPermittedVisitor && $this->getIsOption( 'block_dir_traversal', 'Y' ) ) {
				$fIsPermittedVisitor = $this->doPassCheckBlockDirTraversal();
			}
			if ( $fIsPermittedVisitor && $this->getIsOption( 'block_sql_queries', 'Y' ) ) {
				$fIsPermittedVisitor = $this->doPassCheckBlockSqlQueries();
			}
			if ( $fIsPermittedVisitor && $this->getIsOption( 'block_wordpress_terms', 'Y' ) ) {
				$fIsPermittedVisitor = $this->doPassCheckBlockWordpressTerms();
			}
			if ( $fIsPermittedVisitor && $this->getIsOption( 'block_field_truncation', 'Y' ) ) {
				$fIsPermittedVisitor = $this->doPassCheckBlockFieldTruncation();
			}
			if ( $fIsPermittedVisitor && $this->getIsOption( 'block_php_code', 'Y' ) ) {
				$fIsPermittedVisitor = $this->doPassCheckPhpCode();
			}
			if ( $fIsPermittedVisitor && $this->getIsOption( 'block_exe_file_uploads', 'Y' ) ) {
				$fIsPermittedVisitor = $this->doPassCheckBlockExeFileUploads();
			}
			if ( $fIsPermittedVisitor && $this->getIsOption( 'block_leading_schema', 'Y' ) ) {
				$fIsPermittedVisitor = $this->doPassCheckBlockLeadingSchema();
			}

			return $fIsPermittedVisitor;
		}

		/**
		 * @return bool
		 */
		protected function doPassCheckBlockDirTraversal() {
			$aTerms = array(
				'etc/passwd',
				'proc/self/environ',
				'../'
			);
			$fPass = $this->doPassCheck( $this->getParamsToCheck(), $aTerms );
			if ( !$fPass ) {
				$sAuditMessage = sprintf( _wpsf__('Firewall Trigger: %s.'), _wpsf__('Directory Traversal') );
				$this->addToAuditEntry( $sAuditMessage, 3, 'firewall_block' );
				$this->doStatIncrement( 'firewall.blocked.dirtraversal' );
			}
			return $fPass;
		}

		protected function doPassCheckBlockSqlQueries() {
			$aTerms = array(
				'/concat\s*\(/i',
				'/group_concat/i',
				'/union.*select/i'
			);
			$fPass = $this->doPassCheck( $this->getParamsToCheck(), $aTerms, true );
			if ( !$fPass ) {
				$sAuditMessage = sprintf( _wpsf__('Firewall Trigger: %s.'), _wpsf__('SQL Queries') );
				$this->addToAuditEntry( $sAuditMessage, 3, 'firewall_block' );
				$this->doStatIncrement( 'firewall.blocked.sqlqueries' );
			}
			return $fPass;
		}

		/**
		 * @return bool
		 */
		protected function doPassCheckBlockWordpressTerms() {
			$aTerms = array(
				'/^wp_/i',
				'/^user_login/i',
				'/^user_pass/i',
				'/0x[0-9a-f][0-9a-f]/i',
				'/\/\*\*\//'
			);

			$fPass = $this->doPassCheck( $this->getParamsToCheck(), $aTerms, true );
			if ( !$fPass ) {
				$sAuditMessage = sprintf( _wpsf__('Firewall Trigger: %s.'), _wpsf__('WordPress Terms') );
				$this->addToAuditEntry( $sAuditMessage, 3, 'firewall_block' );
				$this->doStatIncrement( 'firewall.blocked.wpterms' );
			}
			return $fPass;
		}

		/**
		 * @return bool
		 */
		protected function doPassCheckBlockFieldTruncation() {
			$aTerms = array(
				'/\s{49,}/i',
				'/\x00/'
			);
			$fPass = $this->doPassCheck( $this->getParamsToCheck(), $aTerms, true );
			if ( !$fPass ) {
				$sAuditMessage = sprintf( _wpsf__('Firewall Trigger: %s.'), _wpsf__('Field Truncation') );
				$this->addToAuditEntry( $sAuditMessage, 3, 'firewall_block' );
				$this->doStatIncrement( 'firewall.blocked.fieldtruncation' );
			}
			return $fPass;
		}

		protected function doPassCheckPhpCode() {
			$aTerms = array(
				'/(include|include_once|require|require_once)(\s*\(|\s*\'|\s*"|\s+\w+)/i'
			);
			$fPass = $this->doPassCheck( $this->getParamsToCheck(), $aTerms, true );
			if ( !$fPass ) {
				$sAuditMessage = sprintf( _wpsf__('Firewall Trigger: %s.'), _wpsf__('PHP Code') );
				$this->addToAuditEntry( $sAuditMessage, 3, 'firewall_block' );
				$this->doStatIncrement( 'firewall.blocked.phpcode' );
			}
			return $fPass;
		}

		protected function doPassCheckBlockExeFileUploads() {
			$aTerms = array(
				'/\.dll$/i', '/\.rb$/i', '/\.py$/i', '/\.exe$/i', '/\.php[3-6]?$/i', '/\.pl$/i',
				'/\.perl$/i', '/\.ph[34]$/i', '/\.phl$/i', '/\.phtml$/i', '/\.phtm$/i'
			);

			if ( isset( $_FILES ) && !empty( $_FILES ) ) {
				$aFileNames = array();
				foreach( $_FILES as $aFile ) {
					if ( !empty( $aFile['name'] ) ) {
						$aFileNames[] = $aFile['name'];
					}
				}
				$fPass = $this->doPassCheck( $aFileNames, $aTerms, true );
				if ( !$fPass ) {
					$sAuditMessage = sprintf( _wpsf__('Firewall Trigger: %s.'), _wpsf__('EXE File Uploads') );
					$this->addToAuditEntry( $sAuditMessage, 3, 'firewall_block' );
					$this->doStatIncrement( 'firewall.blocked.exefile' );
				}
				return $fPass;
			}
			return true;
		}

		protected function doPassCheckBlockLeadingSchema() {
			$aTerms = array(
				'/^http/i', '/\.shtml$/i'
			);
			$fPass = $this->doPassCheck( $this->getParamsToCheck(), $aTerms, true );
			if ( !$fPass ) {
				$sAuditMessage = sprintf( _wpsf__('Firewall Trigger: %s.'), _wpsf__('Leading Schema') );
				$this->addToAuditEntry( $sAuditMessage, 3, 'firewall_block' );
				$this->doStatIncrement( 'firewall.blocked.schema' );
			}
			return $fPass;
		}

		/**
		 * Returns false when check fails - that is to say, it should be blocked by the firewall.
		 *
		 * @param array $aParamValues
		 * @param array $aMatchTerms
		 * @param boolean $fRegex
		 * @return boolean
		 */
		private function doPassCheck( $aParamValues, $aMatchTerms, $fRegex = false ) {

			$fFAIL = false;
			foreach ( $aParamValues as $sParam => $mValue ) {
				if ( is_array( $mValue ) ) {

					// Protection against an infinite loop and we limit depth to 3.
					if ( $this->nLoopProtect > 2 ) {
						return true;
					}
					else {
						$this->nLoopProtect++;
					}

					if ( !$this->doPassCheck( $mValue, $aMatchTerms, $fRegex ) ) {
						return false;
					}

					$this->nLoopProtect--;
				}
				else {
					$mValue = (string) $mValue;
					foreach ( $aMatchTerms as $sTerm ) {

						if ( $fRegex && preg_match( $sTerm, $mValue ) ) { //dodgy term pattern found in a parameter value
							$fFAIL = true;
						}
						else if ( strpos( $mValue, $sTerm ) !== false ) { //dodgy term found in a parameter value
							$fFAIL = true;
						}

						if ( $fFAIL ) {
							$this->sFirewallDieMessage .= ' '._wpsf__("Something in the URL, Form or Cookie data wasn't appropriate.");
							$sAuditMessage = _wpsf__('Page parameter failed firewall check.')
								.' '.sprintf( _wpsf__( 'The offending parameter was "%s" with a value of "%s".' ), $sParam, $mValue );
							$this->addToAuditEntry( $sAuditMessage, 3 );
							return false;
						}

					}//foreach
				}
			}//foreach

			return true;
		}

		protected function doPreFirewallBlock() {
			if ( !$this->getIfDoFirewallBlock() ) {
				return;
			}

			switch( $this->getOption( 'block_response' ) ) {
				case 'redirect_die':
					$sEntry = sprintf( _wpsf__('Firewall Block Response: %s.'), _wpsf__('Visitor connection was killed with wp_die()') );
					break;
				case 'redirect_die_message':
					$sEntry = sprintf( _wpsf__('Firewall Block Response: %s.'), _wpsf__('Visitor connection was killed with wp_die() and a message') );
					break;
				case 'redirect_home':
					$sEntry = sprintf( _wpsf__('Firewall Block Response: %s.'), _wpsf__('Visitor was sent HOME') );
					break;
				case 'redirect_404':
					$sEntry = sprintf( _wpsf__('Firewall Block Response: %s.'), _wpsf__('Visitor was sent 404') );
					break;
				default:
					$sEntry = sprintf( _wpsf__('Firewall Block Response: %s.'), _wpsf__('Visitor connection was killed with wp_die() and a message') );
					break;
			}

			$this->addToAuditEntry( $sEntry );

			if ( $this->getIsOption( 'block_send_email', 'Y' ) ) {

				$sRecipient = $this->getPluginDefaultRecipientAddress();
				$fSendSuccess = $this->sendBlockEmail( $sRecipient );
				if ( $fSendSuccess ) {
					$this->addToAuditEntry( sprintf( _wpsf__( 'Successfully sent Firewall Block email alert to: %s' ), $sRecipient ) );
				}
				else {
					$this->addToAuditEntry( sprintf( _wpsf__( 'Failed to send Firewall Block email alert to: %s' ), $sRecipient ) );
				}
			}
		}

		/**
		 */
		protected function doFirewallBlock() {

			if ( !$this->getIfDoFirewallBlock() ) {
				return true;
			}

			$sHomeUrl = $this->loadWpFunctionsProcessor()->getHomeUrl();
			switch( $this->getOption( 'block_response' ) ) {
				case 'redirect_die':
					break;
				case 'redirect_die_message':
					wp_die( $this->sFirewallDieMessage );
					break;
				case 'redirect_home':
					header( "Location: ".$sHomeUrl );
					exit();
					break;
				case 'redirect_404':
					header( "Location: ".$sHomeUrl.'/404' );
					break;
				default:
					break;
			}
			exit();
		}

		/**
		 * @return array
		 */
		protected function getParamsToCheck() {
			if ( isset( $this->aPageParams ) ) {
				return $this->aPageParams;
			}

			$oDp = $this->loadDataProcessor();
			$this->aPageParams = $this->getRawRequestParams();
			$aWhitelistPages = $this->getWhitelistPages();
			$aRequestUriParts = $oDp->getRequestUriParts();
			$sRequestPage = $aRequestUriParts[ 'path' ];

			// first we remove globally whitelist request parameters
			if ( array_key_exists( '*', $aWhitelistPages ) ) {
				foreach ( $aWhitelistPages['*'] as $sWhitelistParam ) {
					if ( array_key_exists( $sWhitelistParam, $this->aPageParams ) ) {
						unset( $this->aPageParams[ $sWhitelistParam ] );
					}
				}
			}

			// If the parameters to check is already empty, we return it to save any further processing.
			if ( empty( $this->aPageParams ) ) {
				return $this->aPageParams;
			}

			// Now we run through the list of whitelist pages
			foreach ( $aWhitelistPages as $sWhitelistPageName => $aWhitelistPageParams ) {

				// if the page is white listed
				if ( strpos( $sRequestPage, $sWhitelistPageName ) !== false ) {

					// if the page has no particular parameters specified there is nothing to check since the whole page is white listed.
					if ( empty( $aWhitelistPageParams ) ) {
						$this->aPageParams = array();
					}
					else {
						// Otherwise we run through any whitelisted parameters and remove them.
						foreach( $aWhitelistPageParams as $sWhitelistParam ) {
							if ( array_key_exists( $sWhitelistParam, $this->aPageParams ) ) {
								unset( $this->aPageParams[ $sWhitelistParam ] );
							}
						}
					}
					break;
				}
			}

			return $this->aPageParams;
		}

		/**
		 * @return array
		 */
		protected function getRawRequestParams() {
			if ( !isset( $this->aRawRequestParams ) ) {
				$this->aRawRequestParams = $this->loadDataProcessor()->getRawRequestParams( $this->getIsOption( 'include_cookie_checks', 'Y' ) );
			}
			return $this->aRawRequestParams;
		}

		protected function getWhitelistPages() {
			if ( !isset( $this->aWhitelistPages ) ) {

				$aDefaultWlPages = array(
					'/wp-admin/options-general.php' => array(),
					'/wp-admin/post-new.php'		=> array(),
					'/wp-admin/page-new.php'		=> array(),
					'/wp-admin/link-add.php'		=> array(),
					'/wp-admin/media-upload.php'	=> array(),
					'/wp-admin/post.php'			=> array( 'content' ),
					'/wp-admin/plugin-editor.php'	=> array( 'newcontent' ),
					'/wp-admin/page.php'			=> array(),
					'/wp-admin/admin-ajax.php'		=> array(),
					'/wp-comments-post.php'			=> array(
						'url',
						'comment'
					),
					'/wp-login.php'					=> array(
						'redirect_to'
					),
					'/wp-admin/'					=> array(
						'_wp_original_http_referer',
						'_wp_http_referer'
					)
				);

				$aCustomWhitelistPageParams = is_array( $this->getOption( 'page_params_whitelist' ) )? $this->getOption( 'page_params_whitelist' ) : array();
				$this->aWhitelistPages = array_merge( $aDefaultWlPages, $aCustomWhitelistPageParams );
			}

			return $this->aWhitelistPages;
		}

		public function isVisitorOnBlacklist() {
			return $this->isIpOnlist( $this->getOption( 'ips_blacklist', array() ), $this->ip(), $this->sListItemLabel );
		}

		/**
		 * @param string $sRecipient
		 * @return bool
		 */
		protected function sendBlockEmail( $sRecipient ) {

			$sIp = $this->loadDataProcessor()->getVisitorIpAddress( true );
			$aMessage = array(
				sprintf( _wpsf__( '%s has blocked a page visit to your site.' ), $this->getController()->getHumanName() ),
				_wpsf__( 'Log details for this visitor are below:' ),
				'- '.sprintf( _wpsf__('IP Address: %s'), $sIp )
			);
			$aMessage = array_merge( $aMessage, $this->getRawAuditMessage( '- ' ) );
			// TODO: Get audit trail messages
			$aMessage[] = sprintf( _wpsf__('You can look up the offending IP Address here: %s'), 'http://ip-lookup.net/?ip='.$sIp );
			$sEmailSubject = sprintf( _wpsf__( 'Firewall Block Email Alert for %s' ), $this->loadWpFunctionsProcessor()->getHomeUrl() );

			$fSendSuccess = $this->getEmailProcessor()->sendEmailTo( $sRecipient, $sEmailSubject, $aMessage );
			return $fSendSuccess;
		}
	}

endif;

if ( !class_exists('ICWP_WPSF_Processor_Firewall') ):
	class ICWP_WPSF_Processor_Firewall extends ICWP_FirewallProcessor_V1 { }
endif;