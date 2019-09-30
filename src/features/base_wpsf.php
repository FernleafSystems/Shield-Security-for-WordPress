<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_FeatureHandler_BaseWpsf extends ICWP_WPSF_FeatureHandler_Base {

	/**
	 * @var string[]
	 */
	private static $aStatEvents;

	/**
	 * @var Shield\Databases\AuditTrail\EntryVO[]
	 */
	private static $aAuditLogs;

	/**
	 * @var bool
	 */
	static protected $bIsVerifiedBot;

	/**
	 * @var int
	 */
	static private $nIpOffenceCount = 0;

	/**
	 * @var bool
	 */
	private $bVisitorIsWhitelisted;

	/**
	 * @return \ICWP_WPSF_Processor_Sessions
	 */
	public function getSessionsProcessor() {
		return $this->getCon()
					->getModule_Sessions()
					->getProcessor();
	}

	/**
	 * @return Shield\Databases\Session\Handler
	 */
	public function getDbHandler_Sessions() {
		return $this->getCon()
					->getModule_Sessions()
					->getDbHandler_Sessions();
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
		$oCon = $this->getCon();
		add_action( $oCon->prefix( 'event' ), [ $this, 'eventOffense' ], 10, 2 );
		add_action( $oCon->prefix( 'event' ), [ $this, 'eventAudit' ], 10, 2 );
		add_action( $oCon->prefix( 'event' ), [ $this, 'eventStat' ], 10, 2 );
	}

	/**
	 * @param string $sEvent
	 * @param array  $aMeta
	 * @return $this
	 */
	public function eventAudit( $sEvent = '', $aMeta = [] ) {
		if ( $this->isSupportedEvent( $sEvent ) ) {
			$aDef = $this->getEventDef( $sEvent );
			if ( $aDef[ 'audit' ] && empty( $aMeta[ 'suppress_audit' ] ) ) { // only audit if it's an auditable event
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
			if ( $aDef[ 'offense' ] && empty( $aMeta[ 'suppress_offense' ] ) ) {
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
			if ( $aDef[ 'stat' ] && empty( $aMeta[ 'suppress_stat' ] ) ) { // only stat if it's a statable event
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
	 * @return array
	 */
	public function getGoogleRecaptchaConfig() {
		/** @var Shield\Modules\Plugin\Options $oOpts */
		$oOpts = $this->getCon()
					  ->getModule_Plugin()
					  ->getOptions();
		return $oOpts->getGoogleRecaptchaConfig();
	}

	/**
	 * @return string
	 * @deprecated
	 */
	public function getGoogleRecaptchaSecretKey() {
		return $this->getGoogleRecaptchaConfig()[ 'secret' ];
	}

	/**
	 * @return string
	 * @deprecated
	 */
	public function getGoogleRecaptchaSiteKey() {
		return $this->getGoogleRecaptchaConfig()[ 'key' ];
	}

	/**
	 * @return string
	 */
	public function getGoogleRecaptchaStyle() {
		return $this->getGoogleRecaptchaConfig()[ 'style' ];
	}

	/**
	 * @return bool
	 */
	public function isGoogleRecaptchaReady() {
		$aConfig = $this->getGoogleRecaptchaConfig();
		return ( !empty( $aConfig[ 'secret' ] ) && !empty( $aConfig[ 'key' ] ) );
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
	public function getSecAdminLoginAjaxData() {
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
	 * @return Shield\Modules\BaseShield\ShieldProcessor|mixed
	 */
	public function getProcessor() {
		return parent::getProcessor();
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

	/**
	 * @return bool
	 */
	protected function isReadyToExecute() {
		$oOpts = $this->getOptions();
		return ( $oOpts->isModuleRunIfWhitelisted() || !$this->isVisitorWhitelisted() )
			   && ( $oOpts->isModuleRunIfVerifiedBot() || !$this->isVerifiedBot() )
			   && ( $oOpts->isModuleRunUnderWpCli() || !Services::WpGeneral()->isWpCli() )
			   && parent::isReadyToExecute();
	}

	/**
	 * @return bool
	 */
	public function isVisitorWhitelisted() {
		if ( !isset( $this->bVisitorIsWhitelisted ) ) {
			/** @var \ICWP_WPSF_Processor_Ips $oPro */
			$oPro = $this->getCon()
						 ->getModule_IPs()
						 ->getProcessor();
			$this->bVisitorIsWhitelisted = $oPro->isIpOnWhiteList( Services::IP()->getRequestIp() );
		}
		return $this->bVisitorIsWhitelisted;
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
		return $this->getCon()
					->getModule_Plugin()
					->isXmlrpcBypass();
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
}