<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_FeatureHandler_BaseWpsf extends ICWP_WPSF_FeatureHandler_Base {

	use Shield\AuditTrail\Auditor;

	/**
	 * @var string[]
	 */
	private static $aStatEvents;

	/**
	 * @var ICWP_WPSF_Processor_Sessions
	 */
	static protected $oSessProcessor;

	/**
	 * @var bool
	 */
	static protected $bIsVerifiedBot;

	/**
	 * @var string
	 */
	static private $mIpAction;

	/**
	 * @return ICWP_WPSF_Processor_Sessions
	 */
	public function getSessionsProcessor() {
		return self::$oSessProcessor;
	}

	/**
	 * @return Shield\Databases\Session\EntryVO|null
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

	protected function setupCustomHooks() {
		add_action( $this->getCon()->prefix( 'event' ), [ $this, 'eventAudit' ], 10, 2 );
		add_action( $this->getCon()->prefix( 'event' ), [ $this, 'eventStat' ], 10, 1 );
	}

	/**
	 * @param string $sEvent
	 * @param array  $aData
	 * @return $this
	 */
	public function eventAudit( $sEvent = '', $aData = [] ) {
		if ( $this->isSupportedEvent( $sEvent ) ) {
			$aDef = $this->getEventDef( $sEvent );
			if ( $aDef[ 'audit' ] ) { // only audit if it's an auditable event
				$this->createNewAudit( $aDef[ 'slug' ], '', $aDef[ 'cat' ], $sEvent, $aData );
			}
		}
		return $this;
	}

	/**
	 * @param string $sEvent
	 */
	public function eventStat( $sEvent ) {
		if ( $this->isSupportedEvent( $sEvent ) ) {
			$aDef = $this->getEventDef( $sEvent );
			if ( $aDef[ 'stat' ] ) { // only stat if it's a statable event
				$this->addStatEvent( $sEvent );
			}
		}
	}

	/**
	 * @param string $sEvent
	 * @return $this
	 */
	protected function addStatEvent( $sEvent ) {
		if ( !is_array( self::$aStatEvents ) ) {
			self::$aStatEvents = [];
		}
		self::$aStatEvents[] = $sEvent;
		return $this;
	}

	/**
	 * @return string[]
	 */
	public function getStatEvents() {
		return self::$aStatEvents;
	}

	/**
	 * A action added to WordPress 'init' hook
	 */
	public function onWpInit() {
		parent::onWpInit();
		if ( $this->isThisModulePage() && !$this->isWizardPage() && ( $this->getSlug() != 'insights' ) ) {
			$this->redirectToInsightsSubPage();
		}
	}

	protected function redirectToInsightsSubPage() {
		Services::Response()->redirect(
			$this->getCon()->getModule_Insights()->getUrl_AdminPage(),
			[
				'inav'   => 'settings',
				'subnav' => $this->getSlug()
			],
			true, false
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
		$aConfig = apply_filters( $this->prefix( 'google_recaptcha_config' ), [] );
		if ( !is_array( $aConfig ) ) {
			$aConfig = [];
		}
		$aConfig = array_merge(
			[
				'key'    => '',
				'secret' => '',
				'style'  => 'light',
			],
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
		return $this->getCon()->getModule_SecAdmin()->isWlEnabled();
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
		return apply_filters( $this->prefix( 'report_email_address' ), Services::WpGeneral()->getSiteAdminEmail() );
	}

	/**
	 * @return array
	 */
	protected function getBaseDisplayData() {
		$sHelpUrl = $this->isWlEnabled() ? $this->getCon()->getLabels()[ 'AuthorURI' ] : 'https://icwp.io/b5';

		return Services::DataManipulation()->mergeArraysRecursive(
			parent::getBaseDisplayData(),
			[
				'ajax'    => [
					'sec_admin_login' => $this->getSecAdminLoginAjaxData(),
				],
				'flags'   => [
					'has_session' => $this->hasSession()
				],
				'hrefs'   => [
					'aar_forget_key' => $sHelpUrl
				],
				'classes' => [
					'top_container' => implode( ' ', array_filter( [
						'odp-outercontainer',
						$this->isPremium() ? 'is-pro' : 'is-not-pro',
						$this->getModSlug(),
						Services::Request()->query( 'inav', '' )
					] ) )
				],
			]
		);
	}

	/**
	 * @return bool
	 */
	public function getIfSupport3rdParty() {
		return $this->isPremium();
	}

	protected function getTranslatedString( $sKey, $sDefault ) {
		$aStrings = [
			'nonce_failed_empty'    => __( 'Nonce security checking failed - the nonce value was empty.', 'wp-simple-firewall' ),
			'nonce_failed_supplied' => __( 'Nonce security checking failed - the nonce supplied was "%s".', 'wp-simple-firewall' ),
		];
		return ( isset( $aStrings[ $sKey ] ) ? $aStrings[ $sKey ] : $sDefault );
	}

	/**
	 * @return bool
	 */
	protected function isReadyToExecute() {
		$oOpts = $this->getOptionsVo();
		return ( $oOpts->isModuleRunIfWhitelisted() || !$this->isVisitorWhitelisted() )
			   && ( $oOpts->isModuleRunIfVerifiedBot() || !$this->isVerifiedBot() )
			   && ( $oOpts->isModuleRunUnderWpCli() || !Services::WpGeneral()->isWpCli() )
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
	 * @return bool
	 */
	public function isVerifiedBot() {
		if ( !isset( self::$bIsVerifiedBot ) ) {
			$oSp = $this->loadServiceProviders();

			$sIp = Services::IP()->getRequestIp();
			$sAgent = Services::Request()->getUserAgent();
			if ( empty( $sAgent ) ) {
				$sAgent = 'Unknown';
			}
			self::$bIsVerifiedBot = $oSp->isIp_GoogleBot( $sIp, $sAgent )
									|| $oSp->isIp_BingBot( $sIp, $sAgent )
									|| $oSp->isIp_AppleBot( $sIp, $sAgent )
									|| $oSp->isIp_YahooBot( $sIp, $sAgent )
									|| $oSp->isIp_DuckDuckGoBot( $sIp, $sAgent )
									|| $oSp->isIp_YandexBot( $sIp, $sAgent )
									|| ( class_exists( 'ICWP_Plugin' ) && $oSp->isIp_iControlWP( $sIp ) )
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
		$aCleaned = [];
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
		$aOpts = [];
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
		return [
			'name'    => __( 'Module Disabled', 'wp-simple-firewall' ),
			'enabled' => false,
			'summary' => __( 'All features of this module are completely disabled', 'wp-simple-firewall' ),
			'weight'  => 2,
			'href'    => $this->getUrl_DirectLinkToOption( $this->getEnableModOptKey() ),
		];
	}

	/**
	 * @return bool
	 */
	public function getIfIpTransgressed() {
		$mAction = $this->getIpAction();
		return !empty( $mAction ) &&
			   ( ( is_numeric( $mAction ) && $mAction > 0 ) || in_array( $mAction, [ 'block' ] ) );
	}

	/**
	 * @return int|string|null
	 */
	public function getIpAction() {
		return self::$mIpAction;
	}

	/**
	 * Used to mark an IP address for immediate block
	 * @return $this
	 */
	public function setIpBlocked() {
		return $this->setIpAction( 'block' );
	}

	/**
	 * Used to mark an IP address for transgression/black-mark
	 * @param int $nIncrementCount
	 * @return $this
	 */
	public function setIpTransgressed( $nIncrementCount = 1 ) {
		return $this->setIpAction( $nIncrementCount );
	}

	/**
	 * @param string|int $mNewAction
	 * @return $this
	 */
	private function setIpAction( $mNewAction ) {
		if ( in_array( $mNewAction, [ 'block' ] ) ) {
			self::$mIpAction = $mNewAction;
		}
		else if ( empty( self::$mIpAction ) || ( is_numeric( self::$mIpAction ) && $mNewAction > self::$mIpAction ) ) {
			self::$mIpAction = $mNewAction;
		}
		return $this;
	}
}