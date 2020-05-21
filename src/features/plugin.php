<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities;

class ICWP_WPSF_FeatureHandler_Plugin extends ICWP_WPSF_FeatureHandler_BaseWpsf {

	/**
	 * @var Plugin\Lib\ImportExport\ImportExportController
	 */
	private $oImportExportController;

	/**
	 * @var Plugin\Components\PluginBadge
	 */
	private $oPluginBadgeController;

	/**
	 * @var Shield\Utilities\ReCaptcha\Enqueue
	 */
	private $oCaptchaEnqueue;

	/**
	 * @var Shield\ShieldNetApi\ShieldNetApiController
	 */
	private $oShieldNetApiController;

	/**
	 * @return Plugin\Lib\ImportExport\ImportExportController
	 */
	public function getImpExpController() {
		if ( !isset( $this->oImportExportController ) ) {
			$this->oImportExportController = ( new Plugin\Lib\ImportExport\ImportExportController() )
				->setMod( $this );
		}
		return $this->oImportExportController;
	}

	/**
	 * @return Plugin\Components\PluginBadge
	 */
	public function getPluginBadgeCon() {
		if ( !isset( $this->oPluginBadgeController ) ) {
			$this->oPluginBadgeController = ( new Plugin\Components\PluginBadge() )
				->setMod( $this );
		}
		return $this->oPluginBadgeController;
	}

	/**
	 * @return Shield\ShieldNetApi\ShieldNetApiController
	 */
	public function getShieldNetApiController() {
		if ( !isset( $this->oShieldNetApiController ) ) {
			$this->oShieldNetApiController = ( new Shield\ShieldNetApi\ShieldNetApiController() )
				->setMod( $this );
		}
		return $this->oShieldNetApiController;
	}

	protected function doPostConstruction() {
		$this->setVisitorIpSource();
	}

	protected function preProcessOptions() {
		( new Plugin\Lib\Captcha\CheckCaptchaSettings() )
			->setMod( $this )
			->checkAll();
	}

	/**
	 * @param string $sSection
	 * @return array
	 */
	protected function getSectionWarnings( $sSection ) {
		$aWarnings = [];

		switch ( $sSection ) {
			case 'section_third_party_captcha':
				/** @var Plugin\Options $oOpts */
				$oOpts = $this->getOptions();
				if ( $this->getCaptchaCfg()->ready ) {
					if ( $oOpts->getOpt( 'captcha_checked_at' ) < 0 ) {
						( new Plugin\Lib\Captcha\CheckCaptchaSettings() )
							->setMod( $this )
							->checkAll();
					}
					if ( $oOpts->getOpt( 'captcha_checked_at' ) == 0 ) {
						$aWarnings[] = sprintf(
							__( "Your captcha key and secret haven't been verified.", 'wp-simple-firewall' ).' '
							.__( "Please double-check and make sure you haven't mixed them about, and then re-save.", 'wp-simple-firewall' )
						);
					}
				}
				break;
		}

		return $aWarnings;
	}

	protected function updateHandler() {
		parent::updateHandler();
		$this->deleteAllPluginCrons();
	}

	private function deleteAllPluginCrons() {
		$oCon = $this->getCon();
		$oWpCron = Services::WpCron();

		foreach ( $oWpCron->getCrons() as $nKey => $aCronArgs ) {
			foreach ( $aCronArgs as $sHook => $aCron ) {
				if ( strpos( $sHook, $this->prefix() ) === 0
					 || strpos( $sHook, $oCon->prefixOption() ) === 0 ) {
					$oWpCron->deleteCronJob( $sHook );
				}
			}
		}
	}

	/**
	 * Hooked to the plugin's main plugin_shutdown action
	 */
	public function onPluginShutdown() {
		$sPreferredSource = Services::IP()->getIpDetector()->getLastSuccessfulSource();
		if ( !empty( $sPreferredSource ) ) {
			$this->setOpt( 'last_ip_detect_source', $sPreferredSource );
		}
		parent::onPluginShutdown();
	}

	/**
	 * A action added to WordPress 'init' hook
	 */
	public function onWpInit() {
		parent::onWpInit();
		$this->getImportExportSecretKey();
	}

	/**
	 * Forcefully sets preferred Visitor IP source in the Data component for use throughout the plugin
	 */
	private function setVisitorIpSource() {
		/** @var Plugin\Options $oOpts */
		$oOpts = $this->getOptions();
		if ( !$oOpts->isIpSourceAutoDetect() ) {
			Services::IP()->setIpDetector(
				( new Utilities\Net\VisitorIpDetection() )->setPreferredSource( $oOpts->getIpSource() )
			);
		}
	}

	/**
	 * @inheritDoc
	 */
	protected function handleModAction( $sAction ) {
		switch ( $sAction ) {

			case 'export_file_download':
				header( 'Set-Cookie: fileDownload=true; path=/' );
				( new Plugin\Lib\ImportExport\Export() )
					->setMod( $this )
					->toFile();
				break;

			case 'import_file_upload':
				try {
					( new Plugin\Lib\ImportExport\Import() )
						->setMod( $this )
						->fromFile();
					$bSuccess = true;
					$sMessage = __( 'Options imported successfully', 'wp-simple-firewall' );
				}
				catch ( \Exception $oE ) {
					$bSuccess = false;
					$sMessage = $oE->getMessage();
				}
				$this->setFlashAdminNotice( $sMessage, !$bSuccess );
				Services::Response()->redirect(
					$this->getCon()->getModule_Insights()->getUrl_SubInsightsPage( 'importexport' )
				);
				break;

			default:
				break;
		}
	}

	/**
	 * @return bool
	 */
	public function getCanSiteCallToItself() {
		$oHttp = Services::HttpRequest();
		return $oHttp->get( Services::WpGeneral()->getHomeUrl() ) && $oHttp->lastResponse->getCode() < 400;
	}

	/**
	 * @return array
	 */
	public function getActivePluginFeatures() {
		$aActiveFeatures = $this->getDef( 'active_plugin_features' );

		$aPluginFeatures = [];
		if ( !empty( $aActiveFeatures ) && is_array( $aActiveFeatures ) ) {

			foreach ( $aActiveFeatures as $nPosition => $aFeature ) {
				if ( isset( $aFeature[ 'hidden' ] ) && $aFeature[ 'hidden' ] ) {
					continue;
				}
				$aPluginFeatures[ $aFeature[ 'slug' ] ] = $aFeature;
			}
		}
		return $aPluginFeatures;
	}

	/**
	 * @return string
	 */
	public function getLinkToTrackingDataDump() {
		return add_query_arg(
			[ 'shield_action' => 'dump_tracking_data' ],
			Services::WpGeneral()->getAdminUrl()
		);
	}

	/**
	 * @return string
	 */
	public function getPluginReportEmail() {
		$sE = (string)$this->getOpt( 'block_send_email_address' );
		if ( $this->isPremium() ) {
			$sE = apply_filters( $this->prefix( 'report_email' ), $sE );
		}
		$sE = trim( $sE );
		return Services::Data()->validEmail( $sE ) ? $sE : Services::WpGeneral()->getSiteAdminEmail();
	}

	/**
	 * This is the point where you would want to do any options verification
	 */
	protected function doPrePluginOptionsSave() {
		/** @var Plugin\Options $oOpts */
		$oOpts = $this->getOptions();

		$this->storeRealInstallDate();

		if ( $oOpts->isTrackingEnabled() && !$oOpts->isTrackingPermissionSet() ) {
			$oOpts->setOpt( 'tracking_permission_set_at', Services::Request()->ts() );
		}

		$this->cleanRecaptchaKey( 'google_recaptcha_site_key' );
		$this->cleanRecaptchaKey( 'google_recaptcha_secret_key' );

		$this->cleanImportExportWhitelistUrls();
		$this->cleanImportExportMasterImportUrl();

		$this->setPluginInstallationId();
	}

	/**
	 * @return int
	 */
	public function getFirstInstallDate() {
		return Services::WpGeneral()->getOption( $this->getCon()->prefixOption( 'install_date' ) );
	}

	/**
	 * @return int
	 */
	public function getInstallDate() {
		return $this->getOpt( 'installation_time', 0 );
	}

	/**
	 * @return string
	 */
	public function getOpenSslPrivateKey() {
		$sKey = null;
		$oEnc = Services::Encrypt();
		if ( $oEnc->isSupportedOpenSslDataEncryption() ) {
			$sKey = $this->getOpt( 'openssl_private_key' );
			if ( empty( $sKey ) ) {
				try {
					$aKeys = $oEnc->createNewPrivatePublicKeyPair();
					if ( !empty( $aKeys[ 'private' ] ) ) {
						$sKey = $aKeys[ 'private' ];
						$this->setOpt( 'openssl_private_key', base64_encode( $sKey ) );
						$this->saveModOptions();
					}
				}
				catch ( \Exception $oE ) {
				}
			}
			else {
				$sKey = base64_decode( $sKey );
			}
		}
		return $sKey;
	}

	/**
	 * @return string|null
	 */
	public function getOpenSslPublicKey() {
		$sKey = null;
		if ( $this->hasOpenSslPrivateKey() ) {
			try {
				$sKey = Services::Encrypt()->getPublicKeyFromPrivateKey( $this->getOpenSslPrivateKey() );
			}
			catch ( \Exception $oE ) {
			}
		}
		return $sKey;
	}

	/**
	 * @return bool
	 */
	public function hasOpenSslPrivateKey() {
		$sKey = $this->getOpenSslPrivateKey();
		return !empty( $sKey );
	}

	/**
	 * @return int - the real install timestamp
	 */
	public function storeRealInstallDate() {
		$oWP = Services::WpGeneral();
		$nNow = Services::Request()->ts();

		$sOptKey = $this->getCon()->prefixOption( 'install_date' );

		$nWpDate = $oWP->getOption( $sOptKey );
		if ( empty( $nWpDate ) ) {
			$nWpDate = $nNow;
		}

		$nPluginDate = $this->getInstallDate();
		if ( $nPluginDate == 0 ) {
			$nPluginDate = $nNow;
		}

		$nFinal = min( $nPluginDate, $nWpDate );
		$oWP->updateOption( $sOptKey, $nFinal );
		$this->setOpt( 'installation_time', $nPluginDate );

		return $nFinal;
	}

	/**
	 * @param string $sOptionKey
	 */
	protected function cleanRecaptchaKey( $sOptionKey ) {
		$sCaptchaKey = trim( (string)$this->getOpt( $sOptionKey, '' ) );
		$nSpacePos = strpos( $sCaptchaKey, ' ' );
		if ( $nSpacePos !== false ) {
			$sCaptchaKey = substr( $sCaptchaKey, 0, $nSpacePos + 1 ); // cut off the string if there's spaces
		}
		$sCaptchaKey = preg_replace( '#[^0-9a-zA-Z_-]#', '', $sCaptchaKey ); // restrict character set
//			if ( strlen( $sCaptchaKey ) != 40 ) {
//				$sCaptchaKey = ''; // need to verify length is 40.
//			}
		$this->setOpt( $sOptionKey, $sCaptchaKey );
	}

	/**
	 * Ensure we always a valid installation ID.
	 *
	 * @return string
	 * @deprecated but still used because it aligns with stats collection
	 */
	public function getPluginInstallationId() {
		$sId = $this->getOpt( 'unique_installation_id', '' );

		if ( !$this->isValidInstallId( $sId ) ) {
			$sId = $this->setPluginInstallationId();
		}
		return $sId;
	}

	/**
	 * @return int
	 */
	public function getActivateLength() {
		return Services::Request()->ts() - (int)$this->getOptions()->getOpt( 'activated_at', 0 );
	}

	/**
	 * hidden 20200121
	 * @return bool
	 */
	public function getIfShowIntroVideo() {
		return false && ( $this->getActivateLength() < 8 )
			   && ( Services::Request()->ts() - $this->getInstallDate() < 15 );
	}

	/**
	 * @return Plugin\Lib\TourManager
	 */
	public function getTourManager() {
		return ( new Plugin\Lib\TourManager() )->setMod( $this );
	}

	/**
	 * @return $this
	 */
	public function setActivatedAt() {
		return $this->setOpt( 'activated_at', Services::Request()->ts() );
	}

	/**
	 * @param string $sNewId - leave empty to reset if the current isn't valid
	 * @return string
	 */
	protected function setPluginInstallationId( $sNewId = null ) {
		// only reset if it's not of the correct type
		if ( !$this->isValidInstallId( $sNewId ) ) {
			$sNewId = $this->genInstallId();
		}
		$this->setOpt( 'unique_installation_id', $sNewId );
		return $sNewId;
	}

	/**
	 * @return string
	 */
	protected function genInstallId() {
		return sha1(
			$this->getInstallDate()
			.Services::WpGeneral()->getWpUrl()
			.Services::WpDb()->getPrefix()
		);
	}

	/**
	 * @return bool
	 */
	public function hasImportExportWhitelistSites() {
		return ( count( $this->getImportExportWhitelist() ) > 0 );
	}

	/**
	 * @return string[]
	 */
	public function getImportExportWhitelist() {
		$aWhitelist = $this->getOpt( 'importexport_whitelist', [] );
		return is_array( $aWhitelist ) ? $aWhitelist : [];
	}

	/**
	 * @return string
	 */
	protected function getImportExportSecretKey() {
		$sId = $this->getOpt( 'importexport_secretkey', '' );
		if ( empty( $sId ) || $this->isImportExportSecretKeyExpired() ) {
			$sId = sha1( $this->getCon()->getSiteInstallationId().wp_rand( 0, PHP_INT_MAX ) );
			$this->setOpt( 'importexport_secretkey', $sId )
				 ->setOpt( 'importexport_secretkey_expires_at', Services::Request()->ts() + HOUR_IN_SECONDS );
		}
		return $sId;
	}

	/**
	 * @return bool
	 */
	protected function isImportExportSecretKeyExpired() {
		return ( Services::Request()->ts() > $this->getOpt( 'importexport_secretkey_expires_at' ) );
	}

	/**
	 * @return bool
	 */
	public function isImportExportWhitelistNotify() {
		return $this->isOpt( 'importexport_whitelist_notify', 'Y' );
	}

	/**
	 * @param string $sUrl
	 * @return $this
	 */
	public function addUrlToImportExportWhitelistUrls( $sUrl ) {
		$sUrl = Services::Data()->validateSimpleHttpUrl( $sUrl );
		if ( $sUrl !== false ) {
			$aWhitelistUrls = $this->getImportExportWhitelist();
			$aWhitelistUrls[] = $sUrl;
			$this->setOpt( 'importexport_whitelist', $aWhitelistUrls );
			$this->saveModOptions();
		}
		return $this;
	}

	/**
	 * @param string $sUrl
	 * @return $this
	 */
	public function removeUrlFromImportExportWhitelistUrls( $sUrl ) {
		$sUrl = Services::Data()->validateSimpleHttpUrl( $sUrl );
		if ( $sUrl !== false ) {
			$aWhitelistUrls = $this->getImportExportWhitelist();
			$sKey = array_search( $sUrl, $aWhitelistUrls );
			if ( $sKey !== false ) {
				unset( $aWhitelistUrls[ $sKey ] );
			}
			$this->setOpt( 'importexport_whitelist', $aWhitelistUrls );
			$this->saveModOptions();
		}
		return $this;
	}

	/**
	 * @param string $sKey
	 * @return bool
	 */
	public function isImportExportSecretKey( $sKey ) {
		return ( !empty( $sKey ) && $this->getImportExportSecretKey() == $sKey );
	}

	/**
	 * @return $this
	 */
	protected function cleanImportExportWhitelistUrls() {
		$oDP = Services::Data();

		$aCleaned = [];
		$aWhitelistUrls = $this->getImportExportWhitelist();
		foreach ( $aWhitelistUrls as $nKey => $sUrl ) {

			$sUrl = $oDP->validateSimpleHttpUrl( $sUrl );
			if ( $sUrl !== false ) {
				$aCleaned[] = $sUrl;
			}
		}
		return $this->setOpt( 'importexport_whitelist', array_unique( $aCleaned ) );
	}

	/**
	 * @return $this
	 */
	protected function cleanImportExportMasterImportUrl() {
		/** @var Plugin\Options $oOpts */
		$oOpts = $this->getOptions();
		$sUrl = Services::Data()->validateSimpleHttpUrl( $oOpts->getImportExportMasterImportUrl() );
		if ( $sUrl === false ) {
			$sUrl = '';
		}
		return $this->setOpt( 'importexport_masterurl', $sUrl );
	}

	/**
	 * @param string $sUrl
	 * @return $this
	 */
	public function setImportExportMasterImportUrl( $sUrl ) {
		$this->setOpt( 'importexport_masterurl', $sUrl ); //saving will clean the URL
		return $this->saveModOptions();
	}

	/**
	 * @param string $sId
	 * @return bool
	 */
	protected function isValidInstallId( $sId ) {
		return ( !empty( $sId ) && is_string( $sId ) && strlen( $sId ) == 40 );
	}

	/**
	 * @return bool
	 */
	public function isXmlrpcBypass() {
		return $this->isOpt( 'enable_xmlrpc_compatibility', 'Y' );
	}

	/**
	 * @return bool
	 */
	public function getCanAdminNotes() {
		return $this->isPremium() && Services::WpUsers()->isUserAdmin();
	}

	public function insertCustomJsVars_Admin() {
		parent::insertCustomJsVars_Admin();

		$oCon = $this->getCon();
		if ( Services::WpPost()->isCurrentPage( 'plugins.php' ) ) {
			$sFile = $oCon->getPluginBaseFile();
			wp_localize_script(
				$this->prefix( 'global-plugin' ),
				'icwp_wpsf_vars_plugin',
				[
					'file'  => $sFile,
					'ajax'  => [
						'send_deactivate_survey' => $this->getAjaxActionData( 'send_deactivate_survey' ),
					],
					'hrefs' => [
						'deactivate' => Services::WpPlugins()->getUrl_Deactivate( $sFile ),
					],
				]
			);
			wp_enqueue_script( 'jquery-ui-dialog' ); // jquery and jquery-ui should be dependencies, didn't check though...
			wp_enqueue_style( 'wp-jquery-ui-dialog' );
		}

		wp_localize_script(
			$oCon->prefix( 'plugin' ),
			'icwp_wpsf_vars_tourmanager',
			[ 'ajax' => $this->getAjaxActionData( 'mark_tour_finished' ) ]
		);
		wp_localize_script(
			$this->prefix( 'plugin' ),
			'icwp_wpsf_vars_plugin',
			[
				'strings' => [
					'downloading_file'         => __( 'Downloading file, please wait...', 'wp-simple-firewall' ),
					'problem_downloading_file' => __( 'There was a problem downloading the file.', 'wp-simple-firewall' ),
				],
			]
		);
	}

	/**
	 * @param array $aAllData
	 * @return array
	 */
	public function addInsightsConfigData( $aAllData ) {
		$aThis = [
			'strings'      => [
				'title' => __( 'General Settings', 'wp-simple-firewall' ),
				'sub'   => sprintf( __( 'General %s Settings', 'wp-simple-firewall' ), $this->getCon()
																							->getHumanName() ),
			],
			'key_opts'     => [],
			'href_options' => $this->getUrl_AdminPage()
		];

		$oOpts = $this->getOptions();
		if ( $this->isModOptEnabled() ) {
			$aThis[ 'key_opts' ][ 'mod' ] = $this->getModDisabledInsight();
		}
		else {
			$aThis[ 'key_opts' ][ 'editing' ] = [
				'name'    => __( 'Visitor IP', 'wp-simple-firewall' ),
				'enabled' => true,
				'summary' => sprintf( __( 'Visitor IP address source is: %s', 'wp-simple-firewall' ),
					__( $oOpts->getSelectOptionValueText( 'visitor_address_source' ), 'wp-simple-firewall' ) ),
				'weight'  => 0,
				'href'    => $this->getUrl_DirectLinkToOption( 'visitor_address_source' ),
			];

			$bHasSupportEmail = Services::Data()->validEmail( $this->getOpt( 'block_send_email_address' ) );
			$aThis[ 'key_opts' ][ 'reports' ] = [
				'name'    => __( 'Reporting Email', 'wp-simple-firewall' ),
				'enabled' => $bHasSupportEmail,
				'summary' => $bHasSupportEmail ?
					sprintf( __( 'Email address for reports set to: %s', 'wp-simple-firewall' ), $this->getPluginReportEmail() )
					: sprintf( __( 'No address provided - defaulting to: %s', 'wp-simple-firewall' ), $this->getPluginReportEmail() ),
				'weight'  => 0,
				'href'    => $this->getUrl_DirectLinkToOption( 'block_send_email_address' ),
			];

			$bRecap = $this->getCaptchaCfg()->ready;
			$aThis[ 'key_opts' ][ 'recap' ] = [
				'name'    => __( 'CAPTCHA', 'wp-simple-firewall' ),
				'enabled' => $bRecap,
				'summary' => $bRecap ?
					__( 'CAPTCHA keys have been provided', 'wp-simple-firewall' )
					: __( "CAPTCHA keys haven't been provided", 'wp-simple-firewall' ),
				'weight'  => 1,
				'href'    => $this->getUrl_DirectLinkToSection( 'section_third_party_captcha' ),
			];
		}

		$aAllData[ $this->getSlug() ] = $aThis;
		return $aAllData;
	}

	/**
	 * @return Shield\Databases\GeoIp\Handler
	 */
	public function getDbHandler_GeoIp() {
		return $this->getDbH( 'geoip' );
	}

	/**
	 * @return Shield\Databases\AdminNotes\Handler
	 */
	public function getDbHandler_Notes() {
		return $this->getDbH( 'notes' );
	}

	/**
	 * @return Shield\Utilities\ReCaptcha\Enqueue
	 */
	public function getCaptchaEnqueue() {
		if ( !isset( $this->oCaptchaEnqueue ) ) {
			$this->oCaptchaEnqueue = ( new Shield\Utilities\ReCaptcha\Enqueue() )->setMod( $this );
		}
		return $this->oCaptchaEnqueue;
	}

	/**
	 * @param array $aOptParams
	 * @return array
	 */
	protected function buildOptionForUi( $aOptParams ) {
		$aOptParams = parent::buildOptionForUi( $aOptParams );
		if ( $aOptParams[ 'key' ] === 'visitor_address_source' ) {
			$aNewOptions = [];
			$oIPDet = Services::IP()->getIpDetector();
			foreach ( $aOptParams[ 'value_options' ] as $sValKey => $sSource ) {
				if ( $sValKey == 'AUTO_DETECT_IP' ) {
					$aNewOptions[ $sValKey ] = $sSource;
				}
				else {
					$sIPs = implode( ', ', $oIPDet->getIpsFromSource( $sSource ) );
					$aNewOptions[ $sValKey ] = sprintf( '%s (%s)',
						$sSource, empty( $sIPs ) ? '-' : $sIPs );
				}
			}
			$aOptParams[ 'value_options' ] = $aNewOptions;
		}
		return $aOptParams;
	}

	/**
	 * @return string
	 */
	protected function getNamespaceBase() {
		return 'Plugin';
	}

	/**
	 * @return string
	 */
	public function getSurveyEmail() {
		return base64_decode( $this->getDef( 'survey_email' ) );
	}

	/**
	 * @return bool
	 * @deprecated 9.0
	 */
	public function isDisplayPluginBadge() {
		return false;
	}

	/**
	 * @return string
	 * @deprecated 9.0
	 */
	public function getCookieIdBadgeState() {
		return $this->prefix( 'badgeState' );
	}

	/**
	 * @return string
	 * @deprecated 9.0
	 */
	public function supplyPluginReportEmail() {
		return $this->getPluginReportEmail();
	}
}