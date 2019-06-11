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
	 * @var Shield\Databases\AuditTrail\EntryVO[]
	 */
	private static $aAuditLogs;

	/**
	 * @var ICWP_WPSF_Processor_Sessions
	 */
	static protected $oSessProcessor;

	/**
	 * @var bool
	 */
	static protected $bIsVerifiedBot;

	/**
	 * @var int
	 */
	static private $nIpOffenceCount = 0;

	/**
	 * @return ICWP_WPSF_Processor_Sessions
	 */
	public function getSessionsProcessor() {
		/** @var ICWP_WPSF_Processor_Sessions $oP */
		$oP = $this->getCon()
				   ->getModule_Sessions()
				   ->getProcessor();
		return $oP;
	}

	/**
	 * @return Shield\Databases\Session\Handler
	 */
	public function getDbHandler_Sessions() {
		return $this->getCon()
					->getModule_Sessions()
					->getDbHandler();
	}

	/**
	 * @return Shield\Databases\GeoIp\Handler
	 */
	public function getDbHandler_GeoIp() {
		return $this->getCon()
					->getModule_Plugin()
					->getDbHandler_GeoIp();
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
		add_action( $this->getCon()->prefix( 'event' ), [ $this, 'eventOffense' ], 10, 2 );
		add_action( $this->getCon()->prefix( 'event' ), [ $this, 'eventAudit' ], 10, 2 );
		add_action( $this->getCon()->prefix( 'event' ), [ $this, 'eventStat' ], 10, 2 );
	}

	/**
	 */
	protected function updateHandler() {
		$aMap = [
			'insights_last_comment_block_at'  => 'spam_block_bot',
			'insights_last_firewall_block_at' => 'firewall_block',
			'insights_last_scan_ufc_at'       => 'ufc_scan_run',
			'insights_last_scan_apc_at'       => 'apc_scan_run',
			'insights_last_scan_wcf_at'       => 'wcf_scan_run',
			'insights_last_scan_ptg_at'       => 'ptg_scan_run',
			'insights_last_scan_wpv_at'       => 'wpv_scan_run',
			'insights_last_transgression_at'  => 'ip_offense',
			'insights_last_ip_block_at'       => 'conn_kill',
			'insights_xml_block_at'           => 'block_xml',
			'insights_restapi_block_at'       => 'block_anonymous_restapi',
			'insights_last_2fa_login_at'      => '2fa_success',
			'insights_last_login_block_at'    => 'block_login',
			'insights_test_cron_last_run_at'  => 'test_cron_run',
			'insights_last_password_block_at' => 'password_policy_block',
		];
		foreach ( $this->getOptionsVo()->getOptionsKeys() as $sOpt ) {
			if ( strpos( $sOpt, 'insights_' ) === 0 && isset( $aMap[ $sOpt ] ) && $this->getOpt( $sOpt ) > 0 ) {
				$this->addStatEvent( $aMap[ $sOpt ], [ 'ts' => $this->getOpt( $sOpt ) ] );
				$this->setOpt( $sOpt, 0 );
			}
		}
		/** @var Shield\Databases\Events\Handler $oDbh */
		$oDbh = $this->getCon()->getModule_Events()->getDbHandler();
		$oDbh->commitEvents( $this->getRegisteredEvents( true ) );
	}

	/**
	 * @param string $sEvent
	 * @param array  $aMeta
	 * @return $this
	 */
	public function eventAudit( $sEvent = '', $aMeta = [] ) {
		if ( $this->isSupportedEvent( $sEvent ) ) {
			$aDef = $this->getEventDef( $sEvent );
			if ( $aDef[ 'audit' ] ) { // only audit if it's an auditable event
				$oEntry = new Shield\Databases\AuditTrail\EntryVO();
				$oEntry->event = $sEvent;
				$oEntry->category = $aDef[ 'cat' ];
				$oEntry->context = $aDef[ 'context' ];
				$oEntry->meta = isset( $aMeta[ 'audit' ] ) ? $aMeta[ 'audit' ] : [];
				if ( !is_array( self::$aAuditLogs ) ) {
					self::$aAuditLogs = [];
				}
				self::$aAuditLogs[ $sEvent ] = $oEntry;
			}
		}
		return $this;
	}

	/**
	 * @param string $sEvent
	 * @param array  $aMeta
	 */
	public function eventOffense( $sEvent, $aMeta = [] ) {
		if ( $this->isSupportedEvent( $sEvent ) ) {
			$aDef = $this->getEventDef( $sEvent );
			if ( $aDef[ 'offense' ] ) {
				self::$nIpOffenceCount = max(
					(int)self::$nIpOffenceCount,
					isset( $aMeta[ 'offense_count' ] ) ? $aMeta[ 'offense_count' ] : 1
				);
			}
		}
	}

	/**
	 * @param string $sEvent
	 * @param array  $aMeta
	 */
	public function eventStat( $sEvent, $aMeta = [] ) {
		if ( $this->isSupportedEvent( $sEvent ) ) {
			$aDef = $this->getEventDef( $sEvent );
			if ( $aDef[ 'stat' ] ) { // only stat if it's a statable event
				$this->addStatEvent( $sEvent, $aMeta );
			}
		}
	}

	/**
	 * @param string $sEvent
	 * @param array  $aMeta
	 * @return $this
	 */
	protected function addStatEvent( $sEvent, $aMeta = [] ) {
		if ( !is_array( self::$aStatEvents ) ) {
			self::$aStatEvents = [];
		}
		self::$aStatEvents[ $sEvent ] = isset( $aMeta[ 'ts' ] ) ? $aMeta[ 'ts' ] : Services::Request()->ts();
		return $this;
	}

	/**
	 * @param bool $bFlush
	 * @return Shield\Databases\AuditTrail\EntryVO[]
	 */
	public function getRegisteredAuditLogs( $bFlush = false ) {
		$aEntries = self::$aAuditLogs;
		if ( $bFlush ) {
			self::$aAuditLogs = [];
		}
		return is_array( $aEntries ) ? $aEntries : [];
	}

	/**
	 * @param bool $bFlush
	 * @return string[]
	 */
	public function getRegisteredEvents( $bFlush = false ) {
		$aEntries = self::$aStatEvents;
		if ( $bFlush ) {
			self::$aStatEvents = [];
		}
		return is_array( $aEntries ) ? $aEntries : [];
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
		return $this->getCon()
					->getModule_SecAdmin()
					->getSecAdminTimeLeft();
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
		return $this->getIpOffenceCount() > 0;
	}

	/**
	 * @return int
	 */
	public function getIpOffenceCount() {
		return isset( self::$nIpOffenceCount ) ? self::$nIpOffenceCount : 0;
	}

	/**
	 * @param string $sKey
	 * @return $this
	 * @deprecated 7.5
	 */
	public function setOptInsightsAt( $sKey ) {
		return $this;
	}

	/**
	 * Used to mark an IP address for immediate block
	 * @return $this
	 * @deprecated
	 */
	public function setIpBlocked() {
		return $this;
	}

	/**
	 * @return int
	 * @deprecated 7.5
	 */
	public function getIpAction() {
		return $this->getIpOffenceCount();
	}

	/**
	 * Used to mark an IP address for transgression/black-mark
	 * @param int $nIncrementCount
	 * @return $this
	 * @deprecated 7.5
	 */
	public function setIpTransgressed( $nIncrementCount = 1 ) {
		return $this;
	}
}