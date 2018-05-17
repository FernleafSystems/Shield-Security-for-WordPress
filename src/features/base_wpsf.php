<?php

if ( class_exists( 'ICWP_WPSF_FeatureHandler_BaseWpsf', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/base.php' );

class ICWP_WPSF_FeatureHandler_BaseWpsf extends ICWP_WPSF_FeatureHandler_Base {

	/**
	 * @var ICWP_WPSF_Processor_Sessions
	 */
	static protected $oSessProcessor;

	/**
	 * @return ICWP_WPSF_Processor_Sessions
	 */
	public function getSessionsProcessor() {
		return self::$oSessProcessor;
	}

	/**
	 * @return ICWP_WPSF_SessionVO|null
	 */
	public function getSession() {
		$oP = $this->getSessionsProcessor();
		return is_null( $oP ) ? null : $oP->getCurrentSession();
	}

	/**
	 * @return bool
	 */
	public function hasSession() {
		return !is_null( $this->getSession() );
	}

	/**
	 * @return array
	 */
	protected function getGoogleRecaptchaConfig() {
		$aConfig = apply_filters( $this->prefix( 'google_recaptcha_config' ), array() );
		if ( !is_array( $aConfig ) ) {
			$aConfig = array();
		}
		return array_merge(
			array(
				'key'    => '',
				'secret' => '',
				'style'  => 'light',
			),
			$aConfig
		);
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
	public function getIsGoogleRecaptchaReady() {
		$sKey = $this->getGoogleRecaptchaSiteKey();
		$sSecret = $this->getGoogleRecaptchaSecretKey();
		return ( !empty( $sSecret ) && !empty( $sKey ) && $this->loadDP()->getPhpSupportsNamespaces() );
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

					'aar_title'                    => _wpsf__( 'Plugin Access Restricted' ),
					'aar_what_should_you_enter'    => _wpsf__( 'This security plugin is restricted to administrators with the Security Access Key.' ),
					'aar_must_supply_key_first'    => _wpsf__( 'Please provide the Security Access Key to manage this plugin.' ),
					'aar_to_manage_must_enter_key' => _wpsf__( 'To manage this plugin you must enter the access key.' ),
					'aar_enter_access_key'         => _wpsf__( 'Enter Access Key' ),
					'aar_submit_access_key'        => _wpsf__( 'Submit Security Admin Key' ),
					'aar_forget_key'               => _wpsf__( "Forgotten Key" )
				),
				'flags'   => array(
					'has_session' => $this->hasSession()
				),
				'hrefs'   => array(
					'aar_forget_key' => 'http://icwp.io/b5',
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
	protected function isVisitorWhitelisted() {
		return apply_filters( $this->prefix( 'visitor_is_whitelisted' ), false );
	}

	/**
	 * @return bool
	 */
	public function isXmlrpcBypass() {
		return $this->getConn()
					->getModule( 'plugin' )
					->isXmlrpcBypass();
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
		return $this->setOptAt( 'insights_'.$sKey );
	}

	/**
	 * @param array $aOptionsParams
	 * @return array
	 * @throws Exception
	 */
	protected function loadStrings_SectionTitlesDefaults( $aOptionsParams ) {

		switch ( $aOptionsParams[ 'slug' ] ) {

			case 'section_user_messages' :
				$sTitle = _wpsf__( 'User Messages' );
				$sTitleShort = _wpsf__( 'User Messages' );
				$aSummary = array(
					sprintf( _wpsf__( 'Purpose - %s' ), _wpsf__( 'Customize the messages displayed to the user.' ) ),
					sprintf( _wpsf__( 'Recommendation - %s' ), _wpsf__( 'Use this section if you need to communicate to the user in a particular manner.' ) ),
					sprintf( _wpsf__( 'Hint - %s' ), sprintf( _wpsf__( 'To reset any message to its default, enter the text exactly: %s' ), 'default' ) )
				);
				break;

			default:
				throw new Exception( sprintf( 'A section slug was defined but with no associated strings. Slug: "%s".', $aOptionsParams[ 'slug' ] ) );
		}

		return array( $sTitle, $sTitleShort, $aSummary );
	}
}