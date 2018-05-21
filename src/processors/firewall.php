<?php

if ( class_exists( 'ICWP_WPSF_Processor_Firewall', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).DIRECTORY_SEPARATOR.'base_wpsf.php' );

class ICWP_WPSF_Processor_Firewall extends ICWP_WPSF_Processor_BaseWpsf {

	protected $aWhitelistPages;

	/**
	 * @var array
	 */
	private $aDieMessage;

	/**
	 * @var bool
	 */
	private $bDoFirewallBlock;

	/**
	 * @var array
	 */
	protected $aFirewallTripData;

	/**
	 * @var array
	 */
	protected $aPatterns;

	/**
	 * After any parameter whitelisting has been accounted for
	 * @var array
	 */
	protected $aPageParams;

	/**
	 * @var array
	 */
	protected $aRawRequestParams;

	public function run() {
		if ( $this->getIfPerformFirewallScan() && $this->getIfDoFirewallBlock() ) {
			$this->doPreFirewallBlock();
			$this->doFirewallBlock();
		}
	}

	/**
	 * @return bool
	 */
	protected function getIfDoFirewallBlock() {
		if ( !isset( $this->bDoFirewallBlock ) ) {
			$this->bDoFirewallBlock = !$this->isVisitorRequestPermitted();
		}
		return $this->bDoFirewallBlock;
	}

	/**
	 * @return bool
	 */
	protected function getIfPerformFirewallScan() {
		$bPerformScan = true;
		$oDp = $this->loadDataProcessor();

		if ( count( $this->getRawRequestParams() ) == 0 ) {
			$bPerformScan = false;
		}

		// if we couldn't process the REQUEST_URI parts, we can't firewall so we effectively whitelist without erroring.
		$aRequestParts = $oDp->getRequestUriParts();
		if ( $bPerformScan && empty( $aRequestParts ) ) {
			$sAuditMessage = sprintf( _wpsf__( 'Skipping firewall checking for this visit: %s.' ), _wpsf__( 'Parsing the URI failed' ) );
			$this->addToAuditEntry( $sAuditMessage, 2, 'firewall_skip' );
			$bPerformScan = false;
		}

		$aPageParamsToCheck = $this->getParamsToCheck();
		if ( $bPerformScan && empty( $aPageParamsToCheck ) ) {
//				$sAuditMessage = sprintf( _wpsf__('Skipping firewall checking for this visit: %s.'), _wpsf__('After whitelist options were applied, there were no page parameters to check') );
//				$this->addToAuditEntry( $sAuditMessage, 1, 'firewall_skip' );
			$bPerformScan = false;
		}

		if ( $bPerformScan && $this->getOption( 'ignore_search_engines' ) == 'Y' && $oDp->IsSearchEngineBot() ) {
			$sAuditMessage = sprintf( _wpsf__( 'Skipping firewall checking for this visit: %s.' ), _wpsf__( 'Visitor detected as Search Engine Bot' ) );
			$this->addToAuditEntry( $sAuditMessage, 2, 'firewall_skip' );
			$bPerformScan = false;
		}

		// TODO: are we calling is_super_admin() too early?
		if ( $bPerformScan && $this->getIsOption( 'whitelist_admins', 'Y' ) && is_super_admin() ) {
//				$sAuditMessage = sprintf( _wpsf__('Skipping firewall checking for this visit: %s.'), _wpsf__('Logged-in administrators by-pass firewall') );
//				$this->addToAuditEntry( $sAuditMessage, 2, 'firewall_skip' );
			$bPerformScan = false;
		}

		return $bPerformScan;
	}

	/**
	 * @return boolean - true if visitor is permitted, false if it should be blocked.
	 */
	protected function isVisitorRequestPermitted() {

		$bRequestIsPermitted = true;
		if ( $bRequestIsPermitted && $this->getIsOption( 'block_dir_traversal', 'Y' ) ) {
			$bRequestIsPermitted = $this->doPassCheck( 'dirtraversal' );
		}
		if ( $bRequestIsPermitted && $this->getIsOption( 'block_sql_queries', 'Y' ) ) {
			$bRequestIsPermitted = $this->doPassCheck( 'sqlqueries' );
		}
		if ( $bRequestIsPermitted && $this->getIsOption( 'block_wordpress_terms', 'Y' ) ) {
			$bRequestIsPermitted = $this->doPassCheck( 'wpterms' );
		}
		if ( $bRequestIsPermitted && $this->getIsOption( 'block_field_truncation', 'Y' ) ) {
			$bRequestIsPermitted = $this->doPassCheck( 'fieldtruncation' );
		}
		if ( $bRequestIsPermitted && $this->getIsOption( 'block_php_code', 'Y' ) ) {
			$bRequestIsPermitted = $this->doPassCheck( 'phpcode' );
		}
		if ( $bRequestIsPermitted && $this->getIsOption( 'block_leading_schema', 'Y' ) ) {
			$bRequestIsPermitted = $this->doPassCheck( 'schema' );
		}
		if ( $bRequestIsPermitted && $this->getIsOption( 'block_aggressive', 'Y' ) ) {
			$bRequestIsPermitted = $this->doPassCheck( 'aggressive' );
		}
		if ( $bRequestIsPermitted && $this->getIsOption( 'block_exe_file_uploads', 'Y' ) ) {
			$bRequestIsPermitted = $this->doPassCheckBlockExeFileUploads();
		}
		return $bRequestIsPermitted;
	}

	/**
	 * @return bool
	 */
	protected function doPassCheckBlockExeFileUploads() {
		$sKey = 'exefile';
		$bFAIL = false;
		if ( isset( $_FILES ) && !empty( $_FILES ) ) {
			$aFileNames = array();
			foreach ( $_FILES as $aFile ) {
				if ( !empty( $aFile[ 'name' ] ) ) {
					$aFileNames[] = $aFile[ 'name' ];
				}
			}
			$aMatchTerms = $this->getFirewallPatterns( 'exefile' );
			if ( isset( $aMatchTerms[ 'regex' ] ) && is_array( $aMatchTerms[ 'regex' ] ) ) {

				$aMatchTerms[ 'regex' ] = array_map( array( $this, 'prepRegexTerms' ), $aMatchTerms[ 'regex' ] );
				foreach ( $aMatchTerms[ 'regex' ] as $sTerm ) {
					foreach ( $aFileNames as $sParam => $mValue ) {
						if ( is_scalar( $mValue ) && preg_match( $sTerm, (string)$mValue ) ) {
							$bFAIL = true;
							break( 2 );
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
		if ( isset( $aMatchTerms[ 'simple' ] ) && is_array( $aMatchTerms[ 'simple' ] ) ) {

			foreach ( $aMatchTerms[ 'simple' ] as $sTerm ) {
				foreach ( $aParamValues as $sParam => $mValue ) {
					if ( is_scalar( $mValue ) && ( stripos( (string)$mValue, $sTerm ) !== false ) ) {
						$bFAIL = true;
						break( 2 );
					}
				}
			}
		}

		if ( !$bFAIL && isset( $aMatchTerms[ 'regex' ] ) && is_array( $aMatchTerms[ 'regex' ] ) ) {
			$aMatchTerms[ 'regex' ] = array_map( array( $this, 'prepRegexTerms' ), $aMatchTerms[ 'regex' ] );
			foreach ( $aMatchTerms[ 'regex' ] as $sTerm ) {
				foreach ( $aParamValues as $sParam => $mValue ) {
					if ( is_scalar( $mValue ) && preg_match( $sTerm, (string)$mValue ) ) {
						$bFAIL = true;
						break( 2 );
					}
				}
			}
		}

		if ( $bFAIL ) {
			$this->addToFirewallDieMessage( _wpsf__( "Something in the URL, Form or Cookie data wasn't appropriate." ) );
			$sAuditMessage = _wpsf__( 'Page parameter failed firewall check.' )
							 .' '.sprintf( _wpsf__( 'The offending parameter was "%s" with a value of "%s".' ), $sParam, $mValue );
			$this->addToAuditEntry( $sAuditMessage, 3 );
			$this->setFirewallTrip_Parameter( $sParam );
			$this->setFirewallTrip_Value( $mValue );

			$sAuditMessage = sprintf( _wpsf__( 'Firewall Trigger: %s.' ), $this->getFirewallBlockKeyName( $sTermsKey ) );
			$this->addToAuditEntry( $sAuditMessage, 3, 'firewall_block' );
			$this->doStatIncrement( 'firewall.blocked.'.$sTermsKey );
			$this->setFirewallTrip_Class( $sTermsKey );
		}

		return !$bFAIL;
	}

	/**
	 * @param string $sKey
	 * @return array|null
	 */
	protected function getFirewallPatterns( $sKey = null ) {
		if ( !isset( $this->aPatterns ) ) {
			$this->aPatterns = $this->getFeature()->getDefinition( 'firewall_patterns' );
		}
		if ( !empty( $sKey ) ) {
			return isset( $this->aPatterns[ $sKey ] ) ? $this->aPatterns[ $sKey ] : null;
		}
		return $this->aPatterns;
	}

	/**
	 * @param string $sTerm
	 * @return string
	 */
	private function prepRegexTerms( $sTerm ) {
		return '/'.$sTerm.'/i';
	}

	/**
	 */
	protected function doPreFirewallBlock() {

		if ( $this->getIfDoFirewallBlock() ) {
			/** @var ICWP_WPSF_FeatureHandler_Firewall $oFO */
			$oFO = $this->getFeature();

			switch ( $oFO->getBlockResponse() ) {
				case 'redirect_die':
					$sMessage = _wpsf__( 'Visitor connection was killed with wp_die()' );
					break;
				case 'redirect_die_message':
					$sMessage = _wpsf__( 'Visitor connection was killed with wp_die() and a message' );
					break;
				case 'redirect_home':
					$sMessage = _wpsf__( 'Visitor was sent HOME' );
					break;
				case 'redirect_404':
					$sMessage = _wpsf__( 'Visitor was sent 404' );
					break;
				default:
					$sMessage = _wpsf__( 'Unknown' );
					break;
			}

			if ( $this->getIsOption( 'block_send_email', 'Y' ) ) {

				$sRecipient = $this->getPluginDefaultRecipientAddress();
				if ( $this->sendBlockEmail( $sRecipient ) ) {
					$this->addToAuditEntry( sprintf( _wpsf__( 'Successfully sent Firewall Block email alert to: %s' ), $sRecipient ) );
				}
				else {
					$this->addToAuditEntry( sprintf( _wpsf__( 'Failed to send Firewall Block email alert to: %s' ), $sRecipient ) );
				}
			}

			$oFO->setOptInsightsAt( 'last_firewall_block_at' );
			$this->addToAuditEntry( sprintf( _wpsf__( 'Firewall Block Response: %s.' ), $sMessage ) );
			$this->setIpTransgressed(); // black mark this IP
		}
	}

	/**
	 */
	protected function doFirewallBlock() {

		if ( $this->getIfDoFirewallBlock() ) {
			/** @var ICWP_WPSF_FeatureHandler_Firewall $oFO */
			$oFO = $this->getFeature();
			$oWp = $this->loadWp();

			switch ( $oFO->getBlockResponse() ) {
				case 'redirect_die':
					break;
				case 'redirect_die_message':
					$oWp->wpDie( $this->getFirewallDieMessageForDisplay() );
					break;
				case 'redirect_home':
					header( "Location: ".$oWp->getHomeUrl() );
					break;
				case 'redirect_404':
					header( "Location: ".$oWp->getHomeUrl().'/404' );
					break;
				default:
					break;
			}
			exit();
		}
	}

	/**
	 * @return array
	 */
	protected function getFirewallDieMessage() {
		if ( !isset( $this->aDieMessage ) || !is_array( $this->aDieMessage ) ) {
			$this->aDieMessage = array( $this->getFeature()->getTextOpt( 'text_firewalldie' ) );
		}
		return $this->aDieMessage;
	}

	/**
	 * @return string
	 */
	protected function getFirewallDieMessageForDisplay() {
		$aMessages = apply_filters( $this->getFeature()
										 ->prefix( 'firewall_die_message' ), $this->getFirewallDieMessage() );
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
		$this->aDieMessage = $aMessages;
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
			foreach ( $aWhitelistPages[ '*' ] as $sWhitelistParam ) {
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
					foreach ( $aWhitelistPageParams as $sWhitelistParam ) {
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
			$this->aRawRequestParams = $this->loadDataProcessor()
											->getRawRequestParams( $this->getIsOption( 'include_cookie_checks', 'Y' ) );
		}
		return $this->aRawRequestParams;
	}

	protected function getWhitelistPages() {
		if ( !isset( $this->aWhitelistPages ) ) {

			$aDefaultWlPages = array(
				'/wp-admin/options-general.php' => array(),
				'/wp-admin/post-new.php'        => array(),
				'/wp-admin/page-new.php'        => array(),
				'/wp-admin/link-add.php'        => array(),
				'/wp-admin/media-upload.php'    => array(),
				'/wp-admin/post.php'            => array( 'content' ),
				'/wp-admin/plugin-editor.php'   => array( 'newcontent' ),
				'/wp-admin/page.php'            => array(),
				'/wp-admin/admin-ajax.php'      => array(),
				'/wp-comments-post.php'         => array(
					'url',
					'comment'
				),
				'*'                             => array(
					'g-recaptcha-response',
					'verify_sign',
					'txn_id',
					'wp_http_referer',
					'_wp_http_referer',
					'_wp_original_http_referer',
					'pwd',
					'url',
					'referredby',
					'redirect_to',
					'jetpack_sso_original_request',
					'jetpack_sso_redirect_to'
				)
			);

			/** @var ICWP_WPSF_FeatureHandler_Firewall $oFO */
			$oFO = $this->getFeature();
			$aCustomWhitelistPageParams = $oFO->getPageParamWhitelist();
			$this->aWhitelistPages = array_merge_recursive( $aDefaultWlPages, $aCustomWhitelistPageParams );
		}

		return $this->aWhitelistPages;
	}

	/**
	 * @param string $sRecipient
	 * @return bool
	 */
	protected function sendBlockEmail( $sRecipient ) {

		$sIp = $this->ip();
		$aMessage = array(
			sprintf( _wpsf__( '%s has blocked a page visit to your site.' ), $this->getController()->getHumanName() ),
			_wpsf__( 'Log details for this visitor are below:' ),
			'- '.sprintf( _wpsf__( 'IP Address: %s' ), $sIp )
		);
		$aMessage = array_merge( $aMessage, $this->getRawAuditMessage( '- ' ) );
		// TODO: Get audit trail messages
		$aMessage[] = sprintf( _wpsf__( 'You can look up the offending IP Address here: %s' ), 'http://ip-lookup.net/?ip='.$sIp );
		$sEmailSubject = _wpsf__( 'Firewall Block Alert' );

		return $this->getEmailProcessor()
					->sendEmailTo( $sRecipient, $sEmailSubject, $aMessage );
	}

	/**
	 * @return array
	 */
	public function getFirewallTripData() {
		if ( !isset( $this->aFirewallTripData ) || !is_array( $this->aFirewallTripData ) ) {
			$this->aFirewallTripData = array(
				'parameter' => '',
				'value'     => '',
				'class'     => ''
			);
		}
		return $this->aFirewallTripData;
	}

	/**
	 * @param string $sData
	 */
	protected function setFirewallTrip_Parameter( $sData ) {
		$aData = $this->getFirewallTripData();
		$aData[ 'parameter' ] = $sData;
		$this->aFirewallTripData = $aData;
	}

	/**
	 * @param string $sData
	 */
	protected function setFirewallTrip_Value( $sData ) {
		$aData = $this->getFirewallTripData();
		$aData[ 'value' ] = $sData;
		$this->aFirewallTripData = $aData;
	}

	/**
	 * @param string $sData
	 */
	protected function setFirewallTrip_Class( $sData ) {
		$aData = $this->getFirewallTripData();
		$aData[ 'class' ] = $sData;
		$this->aFirewallTripData = $aData;
	}

	/**
	 * @param string $sBlockKey
	 * @return string
	 */
	private function getFirewallBlockKeyName( $sBlockKey ) {
		switch ( $sBlockKey ) {
			case 'dirtraversal':
				$sName = _wpsf__( 'Directory Traversal' );
				break;
			case 'wpterms':
				$sName = _wpsf__( 'WordPress Terms' );
				break;
			case 'fieldtruncation':
				$sName = _wpsf__( 'Field Truncation' );
				break;
			case 'sqlqueries':
				$sName = _wpsf__( 'SQL Queries' );
				break;
			case 'exefile':
				$sName = _wpsf__( 'EXE File Uploads' );
				break;
			case 'schema':
				$sName = _wpsf__( 'Leading Schema' );
				break;
			case 'phpcode':
				$sName = _wpsf__( 'PHP Code' );
				break;
			case 'aggressive':
				$sName = _wpsf__( 'Aggressive Rules' );
				break;
			default:
				$sName = _wpsf__( 'Unknown Rules' );
				break;
		}
		return $sName;
	}
}