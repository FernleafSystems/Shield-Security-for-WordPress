<?php

if ( !class_exists( 'ICWP_WPSF_Processor_Firewall', false ) ):

	require_once( dirname(__FILE__).ICWP_DS.'base.php' );

	class ICWP_WPSF_Processor_Firewall extends ICWP_WPSF_Processor_Base {

		protected $aWhitelistPages;

		/**
		 * @var int
		 */
		private $nLoopProtect;

		/**
		 * @var array
		 */
		private $aFirewallDieMessage;

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
		protected $aFirewallTripData;

		/**
		 * @var array
		 */
		protected $aPatterns;

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
			$this->addToFirewallDieMessage(
				sprintf(
					$sMessage,
					'<a href="http://wordpress.org/plugins/wp-simple-firewall/" target="_blank">'.$this->getController()->getHumanName().'</a>'
				)
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

			$bRequestIsPermitted = true;
			if ( $bRequestIsPermitted && $this->getIsOption( 'block_dir_traversal', 'Y' ) ) {
				$bRequestIsPermitted = $this->doPassCheckBlockDirTraversal();
			}
			if ( $bRequestIsPermitted && $this->getIsOption( 'block_sql_queries', 'Y' ) ) {
				$bRequestIsPermitted = $this->doPassCheckBlockSqlQueries();
			}
			if ( $bRequestIsPermitted && $this->getIsOption( 'block_wordpress_terms', 'Y' ) ) {
				$bRequestIsPermitted = $this->doPassCheckBlockWordpressTerms();
			}
			if ( $bRequestIsPermitted && $this->getIsOption( 'block_field_truncation', 'Y' ) ) {
				$bRequestIsPermitted = $this->doPassCheckBlockFieldTruncation();
			}
			if ( $bRequestIsPermitted && $this->getIsOption( 'block_php_code', 'Y' ) ) {
				$bRequestIsPermitted = $this->doPassCheckPhpCode();
			}
			if ( $bRequestIsPermitted && $this->getIsOption( 'block_exe_file_uploads', 'Y' ) ) {
				$bRequestIsPermitted = $this->doPassCheckBlockExeFileUploads();
			}
			if ( $bRequestIsPermitted && $this->getIsOption( 'block_leading_schema', 'Y' ) ) {
				$bRequestIsPermitted = $this->doPassCheckBlockLeadingSchema();
			}
			return $bRequestIsPermitted;
		}

		/**
		 * @param string $sKey
		 * @return array|null
		 */
		protected function getFirewallPatterns( $sKey = null ) {
			if ( !isset( $this->aPatterns ) ) {
				$this->aPatterns = $this->getFeatureOptions()->getOptionsVo()->getFeatureDefinition( 'firewall_patterns' );
			}
			if ( !empty( $sKey ) ) {
				return isset( $this->aPatterns[ $sKey ] ) ? $this->aPatterns[ $sKey ] : null;
			}
			return $this->aPatterns;
		}

		/**
		 * @return bool
		 */
		protected function doPassCheckBlockDirTraversal() {
			$sKey = 'dirtraversal';
			$fPass = $this->doPassCheck( $sKey );
			if ( !$fPass ) {
				$sAuditMessage = sprintf( _wpsf__('Firewall Trigger: %s.'), _wpsf__('Directory Traversal') );
				$this->addToAuditEntry( $sAuditMessage, 3, 'firewall_block' );
				$this->doStatIncrement( 'firewall.blocked.'.$sKey );
				$this->setFirewallTrip_Class( $sKey );
			}
			return $fPass;
		}

		protected function doPassCheckBlockSqlQueries() {
			$sKey = 'sqlqueries';
			$fPass = $this->doPassCheck( $sKey );
			if ( !$fPass ) {
				$sAuditMessage = sprintf( _wpsf__( 'Firewall Trigger: %s.' ), _wpsf__( 'SQL Queries' ) );
				$this->addToAuditEntry( $sAuditMessage, 3, 'firewall_block' );
				$this->doStatIncrement( 'firewall.blocked.'.$sKey );
				$this->setFirewallTrip_Class( $sKey );
			}
			return $fPass;
		}

		/**
		 * @return bool
		 */
		protected function doPassCheckBlockWordpressTerms() {
			$sKey = 'wpterms';
			$fPass = $this->doPassCheck( $sKey );
			if ( !$fPass ) {
				$sAuditMessage = sprintf( _wpsf__( 'Firewall Trigger: %s.' ), _wpsf__( 'WordPress Terms' ) );
				$this->addToAuditEntry( $sAuditMessage, 3, 'firewall_block' );
				$this->doStatIncrement( 'firewall.blocked.'.$sKey );
				$this->setFirewallTrip_Class( $sKey );
			}
			return $fPass;
		}

		/**
		 * @return bool
		 */
		protected function doPassCheckBlockFieldTruncation() {
			$sKey = 'fieldtruncation';
			$fPass = $this->doPassCheck( $sKey );
			if ( !$fPass ) {
				$sAuditMessage = sprintf( _wpsf__( 'Firewall Trigger: %s.' ), _wpsf__( 'Field Truncation' ) );
				$this->addToAuditEntry( $sAuditMessage, 3, 'firewall_block' );
				$this->doStatIncrement( 'firewall.blocked.'.$sKey );
				$this->setFirewallTrip_Class( $sKey );
			}
			return $fPass;
		}

		/**
		 * @return bool
		 */
		protected function doPassCheckPhpCode() {
			$sKey = 'phpcode';
			$fPass = $this->doPassCheck( $sKey );
			if ( !$fPass ) {
				$sAuditMessage = sprintf( _wpsf__( 'Firewall Trigger: %s.' ), _wpsf__( 'PHP Code' ) );
				$this->addToAuditEntry( $sAuditMessage, 3, 'firewall_block' );
				$this->doStatIncrement( 'firewall.blocked.'.$sKey );
				$this->setFirewallTrip_Class( $sKey );
			}
			return $fPass;
		}

		/**
		 * @return bool
		 */
		protected function doPassCheckBlockLeadingSchema() {
			$sKey = 'schema';
			$fPass = $this->doPassCheck( $sKey );
			if ( !$fPass ) {
				$sAuditMessage = sprintf( _wpsf__( 'Firewall Trigger: %s.' ), _wpsf__( 'Leading Schema' ) );
				$this->addToAuditEntry( $sAuditMessage, 3, 'firewall_block' );
				$this->doStatIncrement( 'firewall.blocked.'.$sKey );
				$this->setFirewallTrip_Class( $sKey );
			}
			return $fPass;
		}

		/**
		 * @return bool
		 */
		protected function doPassCheckBlockExeFileUploads() {
			$sKey = 'exefile';
			$bFAIL = false;
			if ( isset( $_FILES ) && !empty( $_FILES ) ) {
				$aFileNames = array();
				foreach( $_FILES as $aFile ) {
					if ( !empty( $aFile['name'] ) ) {
						$aFileNames[] = $aFile['name'];
					}
				}
				$aMatchTerms = $this->getFirewallPatterns( 'exefile' );
				if ( isset( $aMatchTerms['regex'] ) && is_array( $aMatchTerms['regex'] ) ) {

					$aMatchTerms[ 'regex' ] = array_map( array( $this, 'prepRegexTerms' ), $aMatchTerms[ 'regex' ] );
					foreach ( $aMatchTerms['regex'] as $sTerm ) {
						foreach ( $aFileNames as $sParam => $mValue ) {
							if ( is_scalar( $mValue ) && preg_match( $sTerm, (string)$mValue ) ) {
								$bFAIL = true;
								break(2);
							}
						}
					}
				}
				if ( $bFAIL ) {
					$sAuditMessage = sprintf( _wpsf__( 'Firewall Trigger: %s.' ), _wpsf__( 'EXE File Uploads' ) );
					$this->addToAuditEntry( $sAuditMessage, 3, 'firewall_block' );
					$this->doStatIncrement( 'firewall.blocked.'.$sKey );
					$this->setFirewallTrip_Class( $sKey );
				}
			}
			return !$bFAIL;
		}

		/**
		 * Returns false when check fails - that is, it should be blocked by the firewall.
		 *
		 * @param string $sTermsKey
		 * @return boolean
		 */
		private function doPassCheck( $sTermsKey ) {

			$aMatchTerms = $this->getFirewallPatterns( $sTermsKey );
			$aParamValues = $this->getParamsToCheck();
			if ( empty( $aMatchTerms ) || empty( $aParamValues ) ) {
				return true;
			}

			$sParam = '';
			$mValue = '';

			$bFAIL = false;
			if ( isset( $aMatchTerms['simple'] ) && is_array( $aMatchTerms['simple'] ) ) {

				foreach ( $aMatchTerms['simple'] as $sTerm ) {
					foreach ( $aParamValues as $sParam => $mValue ) {
						if ( is_scalar( $mValue ) && ( strpos( (string)$mValue, $sTerm ) !== false ) ) {
							$bFAIL = true;
							break(2);
						}
					}
				}
			}

			if ( !$bFAIL && isset( $aMatchTerms['regex'] ) && is_array( $aMatchTerms['regex'] ) ) {
				$aMatchTerms[ 'regex' ] = array_map( array( $this, 'prepRegexTerms' ), $aMatchTerms[ 'regex' ] );
				foreach ( $aMatchTerms['regex'] as $sTerm ) {
					foreach ( $aParamValues as $sParam => $mValue ) {
						if ( is_scalar( $mValue ) && preg_match( $sTerm, (string)$mValue ) ) {
							$bFAIL = true;
							break(2);
						}
					}
				}
			}

			if ( $bFAIL ) {
				$this->addToFirewallDieMessage( _wpsf__( "Something in the URL, Form or Cookie data wasn't appropriate." ) );
				$sAuditMessage = _wpsf__( 'Page parameter failed firewall check.' )
					. ' ' . sprintf( _wpsf__( 'The offending parameter was "%s" with a value of "%s".' ), $sParam, $mValue );
				$this->addToAuditEntry( $sAuditMessage, 3 );
				$this->setFirewallTrip_Parameter( $sParam );
				$this->setFirewallTrip_Value( $mValue );
			}

			return !$bFAIL;
		}

		/**
		 * @param string $sTerm
		 * @return string
		 */
		private function prepRegexTerms( $sTerm ) {
			return '/' . $sTerm . '/i';
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

			// We now black mark this IP
			add_filter( $this->getFeatureOptions()->doPluginPrefix( 'ip_black_mark' ), '__return_true' );
		}

		/**
		 */
		protected function doFirewallBlock() {

			if ( !$this->getIfDoFirewallBlock() ) {
				return true;
			}

			$oWp = $this->loadWpFunctionsProcessor();
			$sHomeUrl = $oWp->getHomeUrl();
			switch( $this->getOption( 'block_response' ) ) {
				case 'redirect_die':
					break;
				case 'redirect_die_message':
					$oWp->wpDie( $this->getFirewallDieMessageForDisplay() );
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
		protected function getFirewallDieMessage() {
			if ( !isset( $this->aFirewallDieMessage ) || !is_array( $this->aFirewallDieMessage ) ) {
				$this->aFirewallDieMessage = array();
			}
			return $this->aFirewallDieMessage;
		}

		/**
		 * @return array
		 */
		protected function getFirewallDieMessageForDisplay() {
			$aMessages = apply_filters( $this->getFeatureOptions()->doPluginPrefix( 'firewall_die_message' ), $this->getFirewallDieMessage() );
			if ( !is_array( $aMessages ) ) {
				$aMessages = array();
			}
			return implode( ' ', $aMessages );
		}

		/**
		 * @param string $sMessagePart
		 * @return $this
		 */
		protected function addToFirewallDieMessage( $sMessagePart ) {
			$aMessages = $this->getFirewallDieMessage();
			$aMessages[] = $sMessagePart;
			$this->aFirewallDieMessage = $aMessages;
			return $this;
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
						'_wp_original_http_referer'
					),
					'*' => array(
						'verify_sign',
						'txn_id',
						'_wp_http_referer',
						'url',
						'referredby',
					)
				);

				$aCustomWhitelistPageParams = is_array( $this->getOption( 'page_params_whitelist' ) )? $this->getOption( 'page_params_whitelist' ) : array();
				$this->aWhitelistPages = array_merge_recursive( $aDefaultWlPages, $aCustomWhitelistPageParams );
			}

			return $this->aWhitelistPages;
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

		/**
		 * @return array
		 */
		public function getFirewallTripData() {
			if ( !isset( $this->aFirewallTripData ) || !is_array( $this->aFirewallTripData ) ) {
				$this->aFirewallTripData = array(
					'parameter' => '',
					'value' => '',
					'class' => ''
				);
			}
			return $this->aFirewallTripData;
		}

		/**
		 * @param string $sData
		 */
		protected function setFirewallTrip_Parameter( $sData ) {
			$aData = $this->getFirewallTripData();
			$aData['parameter'] = $sData;
			$this->aFirewallTripData = $aData;
		}

		/**
		 * @param string $sData
		 */
		protected function setFirewallTrip_Value( $sData ) {
			$aData = $this->getFirewallTripData();
			$aData['value'] = $sData;
			$this->aFirewallTripData = $aData;
		}

		/**
		 * @param string $sData
		 */
		protected function setFirewallTrip_Class( $sData ) {
			$aData = $this->getFirewallTripData();
			$aData['class'] = $sData;
			$this->aFirewallTripData = $aData;
		}
	}

endif;