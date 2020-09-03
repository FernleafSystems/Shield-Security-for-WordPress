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

	public function deleteAllPluginCrons() {
		$con = $this->getCon();
		$oWpCron = Services::WpCron();

		foreach ( $oWpCron->getCrons() as $nKey => $aCronArgs ) {
			foreach ( $aCronArgs as $sHook => $aCron ) {
				if ( strpos( $sHook, $con->prefix() ) === 0
					 || strpos( $sHook, $con->prefixOption() ) === 0 ) {
					$oWpCron->deleteCronJob( $sHook );
				}
			}
		}
	}

	/**
	 * Hooked to the plugin's main plugin_shutdown action
	 */
	public function onPluginShutdown() {
		$preferred = Services::IP()->getIpDetector()->getLastSuccessfulSource();
		if ( !empty( $preferred ) ) {
			$this->getOptions()->setOpt( 'last_ip_detect_source', $preferred );
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
						->fromFileUpload();
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
	public function getPluginReportEmail() :string {
		$e = (string)$this->getOptions()->getOpt( 'block_send_email_address' );
		if ( $this->isPremium() ) {
			$e = apply_filters( $this->getCon()->prefix( 'report_email' ), $e );
		}
		$e = trim( $e );
		return Services::Data()->validEmail( $e ) ? $e : Services::WpGeneral()->getSiteAdminEmail();
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

	public function getInstallDate() :int {
		return (int)$this->getOptions()->getOpt( 'installation_time', 0 );
	}

	/**
	 * @return string
	 */
	public function getOpenSslPrivateKey() {
		$opts = $this->getOptions();
		$key = null;
		$oEnc = Services::Encrypt();
		if ( $oEnc->isSupportedOpenSslDataEncryption() ) {
			$key = $opts->getOpt( 'openssl_private_key' );
			if ( empty( $key ) ) {
				try {
					$aKeys = $oEnc->createNewPrivatePublicKeyPair();
					if ( !empty( $aKeys[ 'private' ] ) ) {
						$key = $aKeys[ 'private' ];
						$opts->setOpt( 'openssl_private_key', base64_encode( $key ) );
						$this->saveModOptions();
					}
				}
				catch ( \Exception $e ) {
				}
			}
			else {
				$key = base64_decode( $key );
			}
		}
		return $key;
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
		$this->getOptions()->setOpt( 'installation_time', $nPluginDate );

		return $nFinal;
	}

	/**
	 * @param string $optionKey
	 */
	protected function cleanRecaptchaKey( $optionKey ) {
		$opts = $this->getOptions();
		$sCaptchaKey = trim( (string)$opts->getOpt( $optionKey, '' ) );
		$nSpacePos = strpos( $sCaptchaKey, ' ' );
		if ( $nSpacePos !== false ) {
			$sCaptchaKey = substr( $sCaptchaKey, 0, $nSpacePos + 1 ); // cut off the string if there's spaces
		}
		$sCaptchaKey = preg_replace( '#[^0-9a-zA-Z_-]#', '', $sCaptchaKey ); // restrict character set
//			if ( strlen( $sCaptchaKey ) != 40 ) {
//				$sCaptchaKey = ''; // need to verify length is 40.
//			}
		$opts->setOpt( $optionKey, $sCaptchaKey );
	}

	/**
	 * Ensure we always a valid installation ID.
	 *
	 * @return string
	 * @deprecated but still used because it aligns with stats collection
	 */
	public function getPluginInstallationId() {
		$ID = $this->getOptions()->getOpt( 'unique_installation_id', '' );

		if ( !$this->isValidInstallId( $ID ) ) {
			$ID = $this->setPluginInstallationId();
		}
		return $ID;
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

	public function setActivatedAt() {
		$this->getOptions()->setOpt( 'activated_at', Services::Request()->ts() );
	}

	/**
	 * @param string $newID - leave empty to reset if the current isn't valid
	 * @return string
	 */
	protected function setPluginInstallationId( $newID = null ) {
		// only reset if it's not of the correct type
		if ( !$this->isValidInstallId( $newID ) ) {
			$newID = $this->genInstallId();
		}
		$this->getOptions()->setOpt( 'unique_installation_id', $newID );
		return $newID;
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
		$list = $this->getOptions()->getOpt( 'importexport_whitelist', [] );
		return is_array( $list ) ? $list : [];
	}

	/**
	 * @return string
	 */
	protected function getImportExportSecretKey() {
		$opts = $this->getOptions();
		$ID = $opts->getOpt( 'importexport_secretkey', '' );
		if ( empty( $ID ) || $this->isImportExportSecretKeyExpired() ) {
			$ID = sha1( $this->getCon()->getSiteInstallationId().wp_rand( 0, PHP_INT_MAX ) );
			$opts->setOpt( 'importexport_secretkey', $ID )
				 ->setOpt( 'importexport_secretkey_expires_at', Services::Request()->ts() + HOUR_IN_SECONDS );
		}
		return $ID;
	}

	protected function isImportExportSecretKeyExpired() :bool {
		return Services::Request()->ts() >
			   $this->getOptions()->getOpt( 'importexport_secretkey_expires_at' );
	}

	public function isImportExportWhitelistNotify() :bool {
		return $this->getOptions()->isOpt( 'importexport_whitelist_notify', 'Y' );
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
			$this->getOptions()->setOpt( 'importexport_whitelist', $aWhitelistUrls );
			$this->saveModOptions();
		}
		return $this;
	}

	/**
	 * @param string $url
	 * @return $this
	 */
	public function removeUrlFromImportExportWhitelistUrls( $url ) {
		$url = Services::Data()->validateSimpleHttpUrl( $url );
		if ( $url !== false ) {
			$aWhitelistUrls = $this->getImportExportWhitelist();
			$sKey = array_search( $url, $aWhitelistUrls );
			if ( $sKey !== false ) {
				unset( $aWhitelistUrls[ $sKey ] );
			}
			$this->getOptions()->setOpt( 'importexport_whitelist', $aWhitelistUrls );
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
		$this->getOptions()->setOpt( 'importexport_whitelist', array_unique( $aCleaned ) );
	}

	protected function cleanImportExportMasterImportUrl() {
		/** @var Plugin\Options $oOpts */
		$oOpts = $this->getOptions();
		$url = Services::Data()->validateSimpleHttpUrl( $oOpts->getImportExportMasterImportUrl() );
		if ( $url === false ) {
			$url = '';
		}
		$this->getOptions()->setOpt( 'importexport_masterurl', $url );
	}

	/**
	 * @param string $url
	 * @return $this
	 */
	public function setImportExportMasterImportUrl( $url ) {
		$this->getOptions()->setOpt( 'importexport_masterurl', $url ); //saving will clean the URL
		return $this->saveModOptions();
	}

	/**
	 * @param string $sId
	 * @return bool
	 */
	protected function isValidInstallId( $sId ) {
		return ( !empty( $sId ) && is_string( $sId ) && strlen( $sId ) == 40 );
	}

	public function isXmlrpcBypass() :bool {
		return $this->getOptions()->isOpt( 'enable_xmlrpc_compatibility', 'Y' );
	}

	/**
	 * @return bool
	 */
	public function getCanAdminNotes() {
		return $this->isPremium() && Services::WpUsers()->isUserAdmin();
	}

	public function insertCustomJsVars_Admin() {
		parent::insertCustomJsVars_Admin();

		$con = $this->getCon();
		if ( Services::WpPost()->isCurrentPage( 'plugins.php' ) ) {
			$sFile = $con->getPluginBaseFile();
			wp_localize_script(
				$con->prefix( 'global-plugin' ),
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
			$con->prefix( 'plugin' ),
			'icwp_wpsf_vars_tourmanager',
			[ 'ajax' => $this->getAjaxActionData( 'mark_tour_finished' ) ]
		);
		wp_localize_script(
			$con->prefix( 'plugin' ),
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
	 * @return string
	 */
	protected function getNamespaceBase() :string {
		return 'Plugin';
	}

	/**
	 * @return string
	 */
	public function getSurveyEmail() {
		return base64_decode( $this->getDef( 'survey_email' ) );
	}
}