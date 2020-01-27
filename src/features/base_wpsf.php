<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities;

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
	protected static $bIsVerifiedBot;

	/**
	 * @var int
	 */
	private static $nIpOffenceCount = 0;

	/**
	 * @var bool
	 */
	private static $bVisitorIsWhitelisted;

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
	 * @uses echo()
	 */
	public function displayModuleAdminPage() {
		if ( $this->canDisplayOptionsForm() ) {
			parent::displayModuleAdminPage();
		}
		else {
			echo $this->renderRestrictedPage();
		}
	}

	/**
	 * @return array
	 */
	public function getBaseDisplayData() {
		return Services::DataManipulation()->mergeArraysRecursive(
			parent::getBaseDisplayData(),
			[
				'ajax'    => [
					'sec_admin_login' => $this->getSecAdminLoginAjaxData(),
				],
				'flags'   => [
					'show_promo'  => !$this->isPremium(),
					'has_session' => $this->hasSession()
				],
				'hrefs'   => [
					'aar_forget_key' => $this->isWlEnabled() ?
						$this->getCon()->getLabels()[ 'AuthorURI' ] : 'https://shsec.io/gc'
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
	 * @return string
	 */
	protected function renderRestrictedPage() {
		/** @var Shield\Modules\SecurityAdmin\Options $oSecOpts */
		$oSecOpts = $this->getCon()
						 ->getModule_SecAdmin()
						 ->getOptions();
		$aData = Services::DataManipulation()
						 ->mergeArraysRecursive(
							 $this->getBaseDisplayData(),
							 [
								 'ajax'    => [
									 'restricted_access' => $this->getAjaxActionData( 'restricted_access' ),
								 ],
								 'strings' => [
									 'force_remove_email' => __( "If you've forgotten your key, a link can be sent to the plugin administrator email address to remove this restriction.", 'wp-simple-firewall' ),
									 'click_email'        => __( "Click here to send the verification email.", 'wp-simple-firewall' ),
									 'send_to_email'      => sprintf( __( "Email will be sent to %s", 'wp-simple-firewall' ),
										 Utilities\Obfuscate::Email( $this->getPluginDefaultRecipientAddress() ) ),
									 'no_email_override'  => __( "The Security Administrator has restricted the use of the email override feature.", 'wp-simple-firewall' ),
								 ],
								 'flags'   => [
									 'allow_email_override' => $oSecOpts->isEmailOverridePermitted()
								 ]
							 ]
						 );
		return $this->renderTemplate( '/wpadmin_pages/security_admin/index.twig', $aData, true );
	}

	/**
	 * @return bool
	 */
	public function getIfSupport3rdParty() {
		return $this->isPremium();
	}

	/**
	 * @return bool
	 * @throws \Exception
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
		if ( !isset( self::$bVisitorIsWhitelisted ) ) {
			$oIp = ( new Shield\Modules\IPs\Lib\Ops\LookupIpOnList() )
				->setDbHandler( $this->getCon()->getModule_IPs()->getDbHandler_IPs() )
				->setIP( Services::IP()->getRequestIp() )
				->setListTypeWhite()
				->lookup();
			self::$bVisitorIsWhitelisted = $oIp instanceof Shield\Databases\IPs\EntryVO;
		}
		return self::$bVisitorIsWhitelisted;
	}

	/**
	 * @return bool
	 */
	public function isVerifiedBot() {
		if ( !isset( self::$bIsVerifiedBot ) ) {
			$oSP = Services::ServiceProviders();

			$sIp = Services::IP()->getRequestIp();
			$sAgent = Services::Request()->getUserAgent();
			if ( empty( $sAgent ) ) {
				$sAgent = 'Unknown';
			}
			self::$bIsVerifiedBot = $oSP->isIp_GoogleBot( $sIp, $sAgent )
									|| $oSP->isIp_BingBot( $sIp, $sAgent )
									|| $oSP->isIp_AppleBot( $sIp, $sAgent )
									|| $oSP->isIp_YahooBot( $sIp, $sAgent )
									|| $oSP->isIp_DuckDuckGoBot( $sIp, $sAgent )
									|| $oSP->isIp_YandexBot( $sIp, $sAgent )
									|| ( class_exists( 'ICWP_Plugin' ) && $oSP->isIp_iControlWP( $sIp ) )
									|| $oSP->isIp_BaiduBot( $sIp, $sAgent );
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