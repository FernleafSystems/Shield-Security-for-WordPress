<?php

class ICWP_WPSF_FeatureHandler_BaseWpsf extends ICWP_WPSF_FeatureHandler_Base {

	/**
	 * @var ICWP_WPSF_Processor_Sessions
	 */
	static protected $oSessProcessor;

	/**
	 * @var bool
	 */
	static protected $bIsVerifiedBot;

	/**
	 * @return ICWP_WPSF_Processor_Sessions
	 */
	public function getSessionsProcessor() {
		return self::$oSessProcessor;
	}

	/**
	 * @return \FernleafSystems\Wordpress\Plugin\Shield\Databases\Session\EntryVO|null
	 */
	public function getSession() {
		$oP = $this->getSessionsProcessor();
		return is_null( $oP ) ? null : $oP->getCurrentSession();
	}

	/**
	 * @return bool
	 */
	public function hasSession() {
		return ( $this->getSession() instanceof \FernleafSystems\Wordpress\Plugin\Shield\Databases\Session\EntryVO );
	}

	public function insertCustomJsVars_Admin() {
		parent::insertCustomJsVars_Admin();

		wp_localize_script(
			$this->prefix( 'plugin' ),
			'icwp_wpsf_vars_secadmin',
			array(
				'reqajax'      => $this->getSecAdminCheckAjaxData(),
				'is_sec_admin' => true, // if $nSecTimeLeft > 0
				'timeleft'     => $this->getSecAdminTimeLeft(), // JS uses milliseconds
				'strings'      => array(
					'confirm' => _wpsf__( 'Security Admin session has timed-out.' ).' '._wpsf__( 'Reload now?' ),
					'nearly'  => _wpsf__( 'Security Admin session has nearly timed-out.' ),
					'expired' => _wpsf__( 'Security Admin session has timed-out.' )
				)
			)
		);
	}

	/**
	 * @return int
	 */
	protected function getSecAdminTimeLeft() {
		/** @var ICWP_WPSF_FeatureHandler_AdminAccessRestriction $oFO */
		$oFO = $this->getCon()
					->getModule( 'admin_access_restriction' );
		return $oFO->getSecAdminTimeLeft();
	}

	/**
	 * @return array
	 */
	protected function getGoogleRecaptchaConfig() {
		$aConfig = apply_filters( $this->prefix( 'google_recaptcha_config' ), array() );
		if ( !is_array( $aConfig ) ) {
			$aConfig = array();
		}
		$aConfig = array_merge(
			array(
				'key'    => '',
				'secret' => '',
				'style'  => 'light',
			),
			$aConfig
		);
		if ( !$this->isPremium() && $aConfig[ 'style' ] != 'light' ) {
			$aConfig[ 'style' ] = 'light'; // hard-coded light style for non-pro
		}
		return $aConfig;
	}

	/**
	 * Overridden in the plugin handler getting the option value
	 * @return string
	 */
	public function getGoogleRecaptchaSecretKey() {
		$aConfig = $this->getGoogleRecaptchaConfig();
		return $aConfig[ 'secret' ];
	}

	/**
	 * Overriden in the plugin handler getting the option value
	 * @return string
	 */
	public function getGoogleRecaptchaSiteKey() {
		$aConfig = $this->getGoogleRecaptchaConfig();
		return $aConfig[ 'key' ];
	}

	/**
	 * Overriden in the plugin handler getting the option value
	 * @return string
	 */
	public function getGoogleRecaptchaStyle() {
		$aConfig = $this->getGoogleRecaptchaConfig();
		return $aConfig[ 'style' ];
	}

	/**
	 * @return bool
	 */
	public function isGoogleRecaptchaReady() {
		$sKey = $this->getGoogleRecaptchaSiteKey();
		$sSecret = $this->getGoogleRecaptchaSecretKey();
		return ( !empty( $sSecret ) && !empty( $sKey ) );
	}

	/**
	 * @return bool
	 */
	public function isWlEnabled() {
		/** @var ICWP_WPSF_FeatureHandler_AdminAccessRestriction $oFO */
		$oFO = $this->getCon()
					->getModule( 'admin_access_restriction' );
		return $oFO->isWlEnabled();
	}

	/**
	 * @return array
	 */
	protected function getSecAdminLoginAjaxData() {
		// We set a custom mod_slug so that this module handles the ajax request
		$aAjaxData = $this->getAjaxActionData( 'sec_admin_login' );
		$aAjaxData[ 'mod_slug' ] = $this->prefix( 'admin_access_restriction' );
		return $aAjaxData;
	}

	/**
	 * @return array
	 */
	protected function getSecAdminCheckAjaxData() {
		// We set a custom mod_slug so that this module handles the ajax request
		$aAjaxData = $this->getAjaxActionData( 'sec_admin_check' );
		$aAjaxData[ 'mod_slug' ] = $this->prefix( 'admin_access_restriction' );
		return $aAjaxData;
	}

	/**
	 * @return string
	 */
	public function getPluginDefaultRecipientAddress() {
		return apply_filters( $this->prefix( 'report_email_address' ), $this->loadWp()->getSiteAdminEmail() );
	}

	/**
	 * @param bool $bRenderEmbeddedContent
	 * @return array
	 */
	protected function getBaseDisplayData( $bRenderEmbeddedContent = true ) {
		return $this->loadDP()->mergeArraysRecursive(
			parent::getBaseDisplayData( $bRenderEmbeddedContent ),
			array(
				'ajax'    => array(
					'sec_admin_login' => $this->getSecAdminLoginAjaxData(),
				),
				'strings' => array(
					'go_to_settings'    => _wpsf__( 'Settings' ),
					'on'                => _wpsf__( 'On' ),
					'off'               => _wpsf__( 'Off' ),
					'more_info'         => _wpsf__( 'More Info' ),
					'blog'              => _wpsf__( 'Blog' ),
					'save_all_settings' => _wpsf__( 'Save All Settings' ),
					'options_title'     => _wpsf__( 'Options' ),
					'options_summary'   => _wpsf__( 'Configure Module' ),
					'actions_title'     => _wpsf__( 'Actions and Info' ),
					'actions_summary'   => _wpsf__( 'Perform actions for this module' ),
					'help_title'        => _wpsf__( 'Help' ),
					'help_summary'      => _wpsf__( 'Learn More' ),
					'supply_password'   => _wpsf__( 'Supply Password' ),
					'confirm_password'  => _wpsf__( 'Confirm Password' ),

					'aar_title'                    => _wpsf__( 'Plugin Access Restricted' ),
					'aar_what_should_you_enter'    => _wpsf__( 'This security plugin is restricted to administrators with the Security Access Key.' ),
					'aar_must_supply_key_first'    => _wpsf__( 'Please provide the Security Access Key to manage this plugin.' ),
					'aar_to_manage_must_enter_key' => _wpsf__( 'To manage this plugin you must enter the access key.' ),
					'aar_enter_access_key'         => _wpsf__( 'Enter Access Key' ),
					'aar_submit_access_key'        => _wpsf__( 'Submit Security Admin Key' ),
					'aar_forget_key'               => _wpsf__( "Forgotten Key" ),
				),
				'flags'   => array(
					'has_session' => $this->hasSession()
				),
				'hrefs'   => array(
					'aar_forget_key' => 'https://icwp.io/b5',
				)
			)
		);
	}

	/**
	 * @return array
	 */
	protected function getDisplayStrings() {
		return $this->loadDP()->mergeArraysRecursive(
			parent::getDisplayStrings(),
			array(
				'back_to_dashboard' => sprintf( _wpsf__( 'Back To %s Dashboard' ), $this->getCon()->getHumanName() ),
				'go_to_settings'    => _wpsf__( 'Settings' ),
				'on'                => _wpsf__( 'On' ),
				'off'               => _wpsf__( 'Off' ),
				'more_info'         => _wpsf__( 'More Info' ),
				'blog'              => _wpsf__( 'Blog' ),
				'save_all_settings' => _wpsf__( 'Save All Settings' ),
				'options_title'     => _wpsf__( 'Options' ),
				'options_summary'   => _wpsf__( 'Configure Module' ),
				'actions_title'     => _wpsf__( 'Actions and Info' ),
				'actions_summary'   => _wpsf__( 'Perform actions for this module' ),
				'help_title'        => _wpsf__( 'Help' ),
				'help_summary'      => _wpsf__( 'Learn More' ),

				'aar_title'                    => _wpsf__( 'Plugin Access Restricted' ),
				'aar_what_should_you_enter'    => _wpsf__( 'This security plugin is restricted to administrators with the Security Access Key.' ),
				'aar_must_supply_key_first'    => _wpsf__( 'Please provide the Security Access Key to manage this plugin.' ),
				'aar_to_manage_must_enter_key' => _wpsf__( 'To manage this plugin you must enter the access key.' ),
				'aar_enter_access_key'         => _wpsf__( 'Enter Access Key' ),
				'aar_submit_access_key'        => _wpsf__( 'Submit Security Admin Key' ),
				'aar_forget_key'               => _wpsf__( "Forgotten Key" )
			)
		);
	}

	/**
	 * @return bool
	 */
	public function getIfSupport3rdParty() {
		return $this->isPremium();
	}

	protected function getTranslatedString( $sKey, $sDefault ) {
		$aStrings = array(
			'nonce_failed_empty'    => _wpsf__( 'Nonce security checking failed - the nonce value was empty.' ),
			'nonce_failed_supplied' => _wpsf__( 'Nonce security checking failed - the nonce supplied was "%s".' ),
		);
		return ( isset( $aStrings[ $sKey ] ) ? $aStrings[ $sKey ] : $sDefault );
	}

	/**
	 * @return bool
	 */
	protected function isReadyToExecute() {
		$oOpts = $this->getOptionsVo();
		return ( $oOpts->isModuleRunIfWhitelisted() || !$this->isVisitorWhitelisted() )
			   && ( $oOpts->isModuleRunIfVerifiedBot() || !$this->isVerifiedBot() )
			   && parent::isReadyToExecute();
	}

	/**
	 * @return bool
	 */
	protected function isVisitorWhitelisted() {
		/** @var ICWP_WPSF_Processor_Ips $oPro */
		$oPro = $this->getCon()
					 ->getModule( 'ips' )
					 ->getProcessor();
		return $oPro->isCurrentIpWhitelisted();
	}

	/**
	 * Only test for bots that we can actually verify based on IP, hostname
	 * @return bool
	 */
	public function isVerifiedBot() {
		if ( !isset( self::$bIsVerifiedBot ) ) {
			$oSp = $this->loadServiceProviders();

			$sIp = $this->loadIpService()->getRequestIp();
			$sAgent = (string)$this->loadRequest()->server( 'HTTP_USER_AGENT' );
			if ( empty( $sAgent ) ) {
				$sAgent = 'Unknown';
			}
			self::$bIsVerifiedBot = $oSp->isIp_GoogleBot( $sIp, $sAgent )
									|| $oSp->isIp_BingBot( $sIp, $sAgent )
									|| $oSp->isIp_AppleBot( $sIp, $sAgent )
									|| $oSp->isIp_YahooBot( $sIp, $sAgent )
									|| $oSp->isIp_DuckDuckGoBot( $sIp, $sAgent )
									|| $oSp->isIp_YandexBot( $sIp, $sAgent )
									|| $oSp->isIp_BaiduBot( $sIp, $sAgent );
		}
		return self::$bIsVerifiedBot;
	}

	/**
	 * @return bool
	 */
	public function isXmlrpcBypass() {
		/** @var ICWP_WPSF_FeatureHandler_Plugin $oFO */
		$oFO = $this->getCon()
					->getModule( 'plugin' );
		return $oFO->isXmlrpcBypass();
	}

	/**
	 * @param string[] $aArray
	 * @param string   $sPregReplacePattern
	 * @return string[]
	 */
	protected function cleanStringArray( $aArray, $sPregReplacePattern ) {
		$aCleaned = array();
		if ( !is_array( $aArray ) ) {
			return $aCleaned;
		}

		foreach ( $aArray as $nKey => $sVal ) {
			$sVal = preg_replace( $sPregReplacePattern, '', $sVal );
			if ( !empty( $sVal ) ) {
				$aCleaned[] = $sVal;
			}
		}
		return array_unique( array_filter( $aCleaned ) );
	}

	/**
	 * @return array
	 */
	public function getInsightsOpts() {
		$aOpts = array();
		$oOpts = $this->getOptionsVo();
		foreach ( $oOpts->getOptionsKeys() as $sOpt ) {
			if ( strpos( $sOpt, 'insights_' ) === 0 ) {
				$aOpts[ $sOpt ] = $oOpts->getOpt( $sOpt );
			}
		}
		return $aOpts;
	}

	/**
	 * @param string $sKey
	 * @return $this
	 */
	public function setOptInsightsAt( $sKey ) {
		$sKey = 'insights_'.str_replace( 'insights_', '', $sKey );
		return $this->setOptAt( $sKey );
	}

	/**
	 * @return array
	 */
	protected function getModDisabledInsight() {
		return array(
			'name'    => _wpsf__( 'Module Disabled' ),
			'enabled' => false,
			'summary' => _wpsf__( 'All features of this module are completely disabled' ),
			'weight'  => 2,
			'href'    => $this->getUrl_DirectLinkToOption( $this->getEnableModOptKey() ),
		);
	}

	/**
	 * @param array $aOptionsParams
	 * @return array
	 * @throws \Exception
	 */
	protected function loadStrings_SectionTitlesDefaults( $aOptionsParams ) {

		switch ( $aOptionsParams[ 'slug' ] ) {

			case 'section_user_messages' :
				$sTitle = _wpsf__( 'User Messages' );
				$sTitleShort = _wpsf__( 'User Messages' );
				$aSummary = array(
					sprintf( '%s - %s', _wpsf__( 'Purpose' ), _wpsf__( 'Customize the messages displayed to the user.' ) ),
					sprintf( '%s - %s', _wpsf__( 'Recommendation' ), _wpsf__( 'Use this section if you need to communicate to the user in a particular manner.' ) ),
					sprintf( '%s: %s', _wpsf__( 'Hint' ), sprintf( _wpsf__( 'To reset any message to its default, enter the text exactly: %s' ), 'default' ) )
				);
				break;

			default:
				throw new \Exception( sprintf( 'A section slug was defined but with no associated strings. Slug: "%s".', $aOptionsParams[ 'slug' ] ) );
		}

		return array( $sTitle, $sTitleShort, $aSummary );
	}
}