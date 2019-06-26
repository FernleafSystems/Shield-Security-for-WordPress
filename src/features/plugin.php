<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_FeatureHandler_Plugin extends ICWP_WPSF_FeatureHandler_BaseWpsf {

	/**
	 * @var Shield\Databases\AdminNotes\Handler
	 */
	private $oDbh_Notes;

	/**
	 * @var Shield\Databases\GeoIp\Handler
	 */
	private $oDbh_GeoIp;

	protected function doPostConstruction() {
		parent::doPostConstruction();
		$this->setVisitorIp();
	}

	protected function setupCustomHooks() {
		parent::setupCustomHooks();
		add_filter( $this->prefix( 'report_email_address' ), [ $this, 'supplyPluginReportEmail' ] );
		add_filter( $this->prefix( 'globally_disabled' ), [ $this, 'filter_IsPluginGloballyDisabled' ] );
		add_filter( $this->prefix( 'google_recaptcha_config' ), [ $this, 'getGoogleRecaptchaConfig' ], 10, 0 );
	}

	protected function updateHandler() {
		parent::updateHandler();
		$this->deleteAllPluginCrons();
	}

	private function deleteAllPluginCrons() {
		$oWpCron = Services::WpCron();

		foreach ( $oWpCron->getCrons() as $nKey => $aCronArgs ) {
			foreach ( $aCronArgs as $sHook => $aCron ) {
				if ( strpos( $sHook, $this->prefix() ) === 0
					 || strpos( $sHook, $this->prefixOptionKey() ) === 0 ) {
					$oWpCron->deleteCronJob( $sHook );
				}
			}
		}
	}

	/**
	 * A action added to WordPress 'init' hook
	 */
	public function onWpInit() {
		parent::onWpInit();
		$this->getImportExportSecretKey();
	}

	/**
	 * @return bool
	 */
	public function getLastCheckServerIpAtHasExpired() {
		return ( Services::Request()->ts() - $this->getLastCheckServerIpAt() > DAY_IN_SECONDS );
	}

	/**
	 * @return int
	 */
	public function getLastCheckServerIpAt() {
		return $this->getOpt( 'this_server_ip_last_check_at', 0 );
	}

	/**
	 * @return string
	 */
	public function getMyServerIp() {

		$sThisServerIp = $this->getOpt( 'this_server_ip', '' );
		if ( $this->getLastCheckServerIpAtHasExpired() ) {
			$sThisServerIp = Services::IP()->whatIsMyIp();
			if ( !empty( $sThisServerIp ) ) {
				$this->setOpt( 'this_server_ip', $sThisServerIp );
			}
			// we always update so we don't forever check on every single page load
			$this->setOptAt( 'this_server_ip_last_check_at' );
		}
		return $sThisServerIp;
	}

	/**
	 * @return bool
	 */
	public function isDisplayPluginBadge() {
		return $this->isOpt( 'display_plugin_badge', 'Y' )
			   && ( Services::Request()->cookie( $this->getCookieIdBadgeState() ) != 'closed' );
	}

	/**
	 * @param bool $bDisplay
	 * @return $this
	 */
	public function setIsDisplayPluginBadge( $bDisplay ) {
		return $this->setOpt( 'display_plugin_badge', $bDisplay ? 'Y' : 'N' );
	}

	/**
	 * Forcefully sets the Visitor IP address in the Data component for use throughout the plugin
	 */
	protected function setVisitorIp() {
		$oDetector = ( new Shield\Utilities\VisitorIpDetection() )
			->setPotentialHostIps(
				[ $this->getMyServerIp(), Services::Request()->getServerAddress() ]
			);
		if ( !$this->isVisitorAddressSourceAutoDetect() ) {
			$oDetector->setPreferredSource( $this->getVisitorAddressSource() );
		}

		$sIp = $oDetector->detect();
		if ( !empty( $sIp ) ) {
			Services::IP()->setRequestIpAddress( $sIp );
			$this->setOpt( 'last_ip_detect_source', $oDetector->getLastSuccessfulSource() );
		}
	}

	/**
	 * @return string
	 */
	public function getVisitorAddressSource() {
		return $this->getOpt( 'visitor_address_source' );
	}

	/**
	 * @param string $sSource
	 * @return $this
	 */
	public function setVisitorAddressSource( $sSource ) {
		return $this->setOpt( 'visitor_address_source', $sSource );
	}

	/**
	 * @return string
	 */
	public function isVisitorAddressSourceAutoDetect() {
		return $this->getVisitorAddressSource() == 'AUTO_DETECT_IP';
	}

	/**
	 * @return string
	 */
	public function getCookieIdBadgeState() {
		return $this->prefix( 'badgeState' );
	}

	/**
	 * @param array $aAjaxResponse
	 * @return array
	 */
	public function handleAjax( $aAjaxResponse ) {

		if ( empty( $aAjaxResponse ) ) {
			switch ( Services::Request()->request( 'exec' ) ) {

				case 'plugin_badge_close':
					$aAjaxResponse = $this->ajaxExec_PluginBadgeClose();
					break;

				case 'set_plugin_tracking_perm':
					if ( !$this->isTrackingPermissionSet() ) {
						$aAjaxResponse = $this->ajaxExec_SetPluginTrackingPerm();
					}
					break;

				case 'send_deactivate_survey':
					$aAjaxResponse = $this->ajaxExec_SendDeactivateSurvey();
					break;
			}
		}
		return parent::handleAjax( $aAjaxResponse );
	}

	/**
	 * @param array $aAjaxResponse
	 * @return array
	 */
	public function handleAuthAjax( $aAjaxResponse ) {

		if ( empty( $aAjaxResponse ) ) {
			switch ( Services::Request()->request( 'exec' ) ) {

				case 'bulk_action':
					$aAjaxResponse = $this->ajaxExec_BulkItemAction();
					break;

				case 'delete_forceoff':
					$aAjaxResponse = $this->ajaxExec_DeleteForceOff();
					break;

				case 'render_table_adminnotes':
					$aAjaxResponse = $this->ajaxExec_RenderTableAdminNotes();
					break;

				case 'note_delete':
					$aAjaxResponse = $this->ajaxExec_AdminNotesDelete();
					break;

				case 'note_insert':
					$aAjaxResponse = $this->ajaxExec_AdminNotesInsert();
					break;

				case 'import_from_site':
					$aAjaxResponse = $this->ajaxExec_ImportFromSite();
					break;

				default:
					break;
			}
		}
		return parent::handleAuthAjax( $aAjaxResponse );
	}

	/**
	 */
	public function handleModRequest() {
		switch ( Services::Request()->request( 'exec' ) ) {

			case 'export_file_download':
				header( 'Set-Cookie: fileDownload=true; path=/' );
				/** @var ICWP_WPSF_Processor_Plugin $oPro */
				$oPro = $this->getProcessor();
				$oPro->getSubProImportExport()
					 ->doExportDownload();
				break;

			case 'import_file_upload':
				/** @var ICWP_WPSF_Processor_Plugin $oPro */
				$oPro = $this->getProcessor();
				try {
					$oPro->getSubProImportExport()
						 ->importFromUploadFile();
					$bSuccess = true;
					$sMessage = __( 'Options imported successfully', 'wp-simple-firewall' );
				}
				catch ( \Exception $oE ) {
					$bSuccess = false;
					$sMessage = $oE->getMessage();
				}
				$this->loadWpNotices()
					 ->addFlashUserMessage( $sMessage, !$bSuccess );
				Services::Response()->redirect( $this->getUrlImportExport() );
				break;

			default:
				break;
		}
	}

	/**
	 * TODO: build better/dynamic direct linking to insights sub-pages
	 * see also hackprotect getUrlManualScan()
	 */
	private function getUrlImportExport() {
		return add_query_arg(
			[ 'inav' => 'importexport' ],
			$this->getCon()->getModule_Insights()->getUrl_AdminPage()
		);
	}

	/**
	 * @return array
	 */
	private function ajaxExec_BulkItemAction() {
		$oReq = Services::Request();

		$bSuccess = false;

		$aIds = $oReq->post( 'ids' );
		if ( empty( $aIds ) || !is_array( $aIds ) ) {
			$bSuccess = false;
			$sMessage = __( 'No items selected.', 'wp-simple-firewall' );
		}
		else if ( !in_array( $oReq->post( 'bulk_action' ), [ 'delete' ] ) ) {
			$sMessage = __( 'Not a supported action.', 'wp-simple-firewall' );
		}
		else {
			/** @var Shield\Databases\AdminNotes\Delete $oDel */
			$oDel = $this->getDbHandler_Notes()->getQueryDeleter();
			foreach ( $aIds as $nId ) {
				if ( is_numeric( $nId ) ) {
					$oDel->deleteById( $nId );
				}
			}
			$bSuccess = true;
			$sMessage = __( 'Selected items were deleted.', 'wp-simple-firewall' );
		}

		return [
			'success' => $bSuccess,
			'message' => $sMessage,
		];
	}

	/**
	 * @return array
	 */
	public function ajaxExec_PluginBadgeClose() {
		$bSuccess = Services::Response()
							->cookieSet(
								$this->getCookieIdBadgeState(),
								'closed',
								DAY_IN_SECONDS
							);
		return [
			'success' => $bSuccess,
			'message' => $bSuccess ? 'Badge Closed' : 'Badge Not Closed'
		];
	}

	/**
	 * @return array
	 */
	public function ajaxExec_SetPluginTrackingPerm() {
		$this->setPluginTrackingPermission( (bool)Services::Request()->query( 'agree', false ) );
		return [ 'success' => true ];
	}

	/**
	 * @return array
	 */
	public function ajaxExec_SendDeactivateSurvey() {
		$aResults = [];
		foreach ( $_POST as $sKey => $sValue ) {
			if ( strpos( $sKey, 'reason_' ) === 0 ) {
				$aResults[] = str_replace( 'reason_', '', $sKey ).': '.$sValue;
			}
		}
		$this->getEmailProcessor()
			 ->send(
				 $this->getSurveyEmail(),
				 'Shield Deactivation Survey',
				 implode( "\n<br/>", $aResults )
			 );
		return [ 'success' => true ];
	}

	/**
	 * @return array
	 */
	public function ajaxExec_DeleteForceOff() {
		$bStillActive = $this->getCon()
							 ->deleteForceOffFile()
							 ->getIfForceOffActive();
		if ( $bStillActive ) {
			$this->setFlashAdminNotice( __( 'File could not be automatically removed.', 'wp-simple-firewall' ), true );
		}
		return [ 'success' => !$bStillActive ];
	}

	/**
	 * @return array
	 */
	protected function ajaxExec_AdminNotesDelete() {

		$sItemId = Services::Request()->post( 'rid' );
		if ( empty( $sItemId ) ) {
			$sMessage = __( 'Note not found.', 'wp-simple-firewall' );
		}
		else {
			try {
				$bSuccess = $this->getDbHandler_Notes()
								 ->getQueryDeleter()
								 ->deleteById( $sItemId );

				if ( $bSuccess ) {
					$sMessage = __( 'Note deleted', 'wp-simple-firewall' );
				}
				else {
					$sMessage = __( "Note couldn't be deleted", 'wp-simple-firewall' );
				}
			}
			catch ( \Exception $oE ) {
				$sMessage = $oE->getMessage();
			}
		}

		return [
			'success' => true,
			'message' => $sMessage
		];
	}

	private function ajaxExec_ImportFromSite() {
		$bSuccess = false;
		$aFormParams = array_merge(
			[
				'confirm' => 'N'
			],
			$this->getAjaxFormParams()
		);

		// TODO: align with wizard AND combine with file upload errors
		if ( $aFormParams[ 'confirm' ] !== 'Y' ) {
			$sMessage = __( 'Please check the box to confirm your intent to overwrite settings', 'wp-simple-firewall' );
		}
		else {
			$sMasterSiteUrl = $aFormParams[ 'MasterSiteUrl' ];
			$sSecretKey = $aFormParams[ 'MasterSiteSecretKey' ];
			$bEnabledNetwork = $aFormParams[ 'ShieldNetwork' ] === 'Y';
			$bDisableNetwork = $aFormParams[ 'ShieldNetwork' ] === 'N';
			$bNetwork = $bEnabledNetwork ? true : ( $bDisableNetwork ? false : null );

			/** @var ICWP_WPSF_Processor_Plugin $oP */
			$oP = $this->getProcessor();
			/** @var Shield\Databases\AdminNotes\Insert $oInserter */
			$nCode = $oP->getSubProImportExport()
						->runImport( $sMasterSiteUrl, $sSecretKey, $bNetwork );
			$bSuccess = $nCode == 0;
			$sMessage = $bSuccess ? __( 'Options imported successfully', 'wp-simple-firewall' ) : __( 'Options failed to import', 'wp-simple-firewall' );
		}
		return [
			'success' => $bSuccess,
			'message' => $sMessage
		];
	}

	/**
	 * @return array
	 */
	protected function ajaxExec_AdminNotesInsert() {
		$bSuccess = false;
		$aFormParams = $this->getAjaxFormParams();

		$sNote = isset( $aFormParams[ 'admin_note' ] ) ? $aFormParams[ 'admin_note' ] : '';
		if ( !$this->getCanAdminNotes() ) {
			$sMessage = __( 'Sorry, Admin Notes is only available for Pro subscriptions.', 'wp-simple-firewall' );
		}
		else if ( empty( $sNote ) ) {
			$sMessage = __( 'Sorry, but it appears your note was empty.', 'wp-simple-firewall' );
		}
		else {
			/** @var Shield\Databases\AdminNotes\Insert $oInserter */
			$oInserter = $this->getDbHandler_Notes()->getQueryInserter();
			$bSuccess = $oInserter->create( $sNote );
			$sMessage = $bSuccess ? __( 'Note created successfully.', 'wp-simple-firewall' ) : __( 'Note could not be created.', 'wp-simple-firewall' );
		}
		return [
			'success' => $bSuccess,
			'message' => $sMessage
		];
	}

	/**
	 * @return array
	 */
	protected function ajaxExec_RenderTableAdminNotes() {
		return [
			'success' => true,
			'html'    => ( new Shield\Tables\Build\AdminNotes() )
				->setMod( $this )
				->setDbHandler( $this->getDbHandler_Notes() )
				->buildTable()
		];
	}

	/**
	 * @param bool $bOnOrOff
	 * @return $this
	 */
	public function setPluginTrackingPermission( $bOnOrOff = true ) {
		$this->setOpt( 'enable_tracking', $bOnOrOff ? 'Y' : 'N' )
			 ->setOpt( 'tracking_permission_set_at', Services::Request()->ts() )
			 ->savePluginOptions();
		return $this;
	}

	/**
	 * @return array
	 */
	public function getGoogleRecaptchaConfig() {
		$aConfig = [
			'key'    => $this->getOpt( 'google_recaptcha_site_key' ),
			'secret' => $this->getOpt( 'google_recaptcha_secret_key' ),
			'style'  => $this->getOpt( 'google_recaptcha_style' ),
		];
		if ( !$this->isPremium() && $aConfig[ 'style' ] != 'light' ) {
			$aConfig[ 'style' ] = 'light'; // hard-coded light style for non-pro
		}
		return $aConfig;
	}

	/**
	 * @param boolean $bGloballyDisabled
	 * @return boolean
	 */
	public function filter_IsPluginGloballyDisabled( $bGloballyDisabled ) {
		return $bGloballyDisabled || !$this->isOpt( 'global_enable_plugin_features', 'Y' );
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
	 * @return int
	 */
	public function getTrackingLastSentAt() {
		$nTime = (int)$this->getOpt( 'tracking_last_sent_at', 0 );
		return ( $nTime < 0 ) ? 0 : $nTime;
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
	 * @return bool
	 */
	public function isTrackingEnabled() {
		return $this->isOpt( 'enable_tracking', 'Y' );
	}

	/**
	 * @return bool
	 */
	public function isTrackingPermissionSet() {
		return !$this->isOpt( 'tracking_permission_set_at', 0 );
	}

	/**
	 * @return $this
	 */
	public function setTrackingLastSentAt() {
		return $this->setOpt( 'tracking_last_sent_at', Services::Request()->ts() );
	}

	/**
	 * @return bool
	 */
	public function readyToSendTrackingData() {
		return ( Services::Request()->ts() - $this->getTrackingLastSentAt() > WEEK_IN_SECONDS );
	}

	/**
	 * @param string $sEmail
	 * @return string
	 */
	public function supplyPluginReportEmail( $sEmail = '' ) {
		$sE = $this->getOpt( 'block_send_email_address' );
		return Services::Data()->validEmail( $sE ) ? $sE : $sEmail;
	}

	/**
	 * This is the point where you would want to do any options verification
	 */
	protected function doPrePluginOptionsSave() {

		$this->storeRealInstallDate();

		if ( $this->isTrackingEnabled() && !$this->isTrackingPermissionSet() ) {
			$this->setOpt( 'tracking_permission_set_at', Services::Request()->ts() );
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
		return Services::WpGeneral()->getOption( $this->prefixOptionKey( 'install_date' ) );
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

		$sOptKey = $this->prefixOptionKey( 'install_date' );

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
	public function getActivatedAt() {
		return (int)$this->getOpt( 'activated_at', 0 );
	}

	/**
	 * @return bool
	 */
	public function getIfShowIntroVideo() {
		$nNow = Services::Request()->ts();
		return ( $nNow - $this->getActivatedAt() < 8 )
			   && ( $nNow - $this->getInstallDate() < 15 );
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
	 * @return string
	 */
	public function getImportExportMasterImportUrl() {
		return $this->getOpt( 'importexport_masterurl', '' );
	}

	/**
	 * @return bool
	 */
	public function hasImportExportMasterImportUrl() {
		$sMaster = $this->getImportExportMasterImportUrl();
		return !empty( $sMaster );
	}

	/**
	 * @return bool
	 */
	public function hasImportExportWhitelistSites() {
		return ( count( $this->getImportExportWhitelist() ) > 0 );
	}

	/**
	 * @return int
	 */
	public function getImportExportHandshakeExpiresAt() {
		return $this->getOpt( 'importexport_handshake_expires_at', Services::Request()->ts() );
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
	public function getImportExportLastImportHash() {
		return $this->getOpt( 'importexport_last_import_hash', '' );
	}

	/**
	 * @return string
	 */
	protected function getImportExportSecretKey() {
		$sId = $this->getOpt( 'importexport_secretkey', '' );
		if ( empty( $sId ) || $this->isImportExportSecretKeyExpired() ) {
			$sId = sha1( $this->getPluginInstallationId().wp_rand( 0, PHP_INT_MAX ) );
			$this->setOpt( 'importexport_secretkey', $sId )
				 ->setOpt( 'importexport_secretkey_expires_at', Services::Request()->ts() + HOUR_IN_SECONDS );
		}
		return $sId;
	}

	/**
	 * @return bool
	 */
	public function isImportExportPermitted() {
		return $this->isPremium() && $this->isOpt( 'importexport_enable', 'Y' );
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
		$sUrl = $this->loadDP()->validateSimpleHttpUrl( $sUrl );
		if ( $sUrl !== false ) {
			$aWhitelistUrls = $this->getImportExportWhitelist();
			$aWhitelistUrls[] = $sUrl;
			$this->setOpt( 'importexport_whitelist', $aWhitelistUrls )
				 ->savePluginOptions();
		}
		return $this;
	}

	/**
	 * @param string $sUrl
	 * @return $this
	 */
	public function removeUrlFromImportExportWhitelistUrls( $sUrl ) {
		$sUrl = $this->loadDP()->validateSimpleHttpUrl( $sUrl );
		if ( $sUrl !== false ) {
			$aWhitelistUrls = $this->getImportExportWhitelist();
			$sKey = array_search( $sUrl, $aWhitelistUrls );
			if ( $sKey !== false ) {
				unset( $aWhitelistUrls[ $sKey ] );
			}
			$this->setOpt( 'importexport_whitelist', $aWhitelistUrls )
				 ->savePluginOptions();
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
		$oDP = $this->loadDP();

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
		$sUrl = $this->loadDP()->validateSimpleHttpUrl( $this->getImportExportMasterImportUrl() );
		if ( $sUrl === false ) {
			$sUrl = '';
		}
		return $this->setOpt( 'importexport_masterurl', $sUrl );
	}

	/**
	 * @return $this
	 */
	public function startImportExportHandshake() {
		$this->setOpt( 'importexport_handshake_expires_at', Services::Request()->ts() + 30 )
			 ->savePluginOptions();
		return $this;
	}

	/**
	 * @param string $sHash
	 * @return $this
	 */
	public function setImportExportLastImportHash( $sHash ) {
		return $this->setOpt( 'importexport_last_import_hash', $sHash );
	}

	/**
	 * @param string $sUrl
	 * @return $this
	 */
	public function setImportExportMasterImportUrl( $sUrl ) {
		$this->setOpt( 'importexport_masterurl', $sUrl )
			 ->savePluginOptions(); //saving will clean the URL
		return $this;
	}

	/**
	 * @param string $sId
	 * @return bool
	 */
	protected function isValidInstallId( $sId ) {
		return ( !empty( $sId ) && is_string( $sId ) && strlen( $sId ) == 40 );
	}

	/**
	 * @return string
	 * @throws \Exception
	 */
	public function renderPluginBadge() {
		$oCon = $this->getCon();

		$aData = [
			'ajax'    => [
				'plugin_badge_close' => $this->getAjaxActionData( 'plugin_badge_close', true ),
			],
			'flags'   => [
				'nofollow' => apply_filters( 'icwp_shield_badge_relnofollow', false ),
			],
			'hrefs'   => [
				'logo' => $oCon->getPluginUrl_Image( 'shield/shield-security-logo-colour-32px.png' ),
			],
			'strings' => [
				'link' => apply_filters( 'icwp_shield_plugin_badge_text', sprintf(
					__( 'This Site Is Protected By %s', 'wp-simple-firewall' ),
					sprintf(
						'<br /><span style="font-weight: bold;">The %s &rarr;</span>',
						$oCon->getHumanName()
					)
				) ),
				'name' => $oCon->getHumanName()
			]
		];
		return $this->renderTemplate( 'snippets/plugin_badge', $aData );
	}

	/**
	 * @return bool
	 */
	public function isXmlrpcBypass() {
		return $this->isOpt( 'enable_xmlrpc_compatibility', 'Y' );
	}

	/**
	 * @return int
	 */
	public function getTestCronLastRunAt() {
		return (int)$this->getOpt( 'insights_test_cron_last_run_at', 0 );
	}

	/**
	 * @return bool
	 */
	public function getCanAdminNotes() {
		return $this->isPremium() && Services::WpUsers()->isUserAdmin();
	}

	public function insertCustomJsVars_Admin() {
		parent::insertCustomJsVars_Admin();

		if ( Services::WpPost()->isCurrentPage( 'plugins.php' ) ) {
			$sFile = $this->getCon()->getPluginBaseFile();
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

		if ( $this->isModOptEnabled() ) {
			$aThis[ 'key_opts' ][ 'mod' ] = $this->getModDisabledInsight();
		}
		else {
			$sSource = $this->getOptionsVo()->getSelectOptionValueText( 'visitor_address_source' );
			$aThis[ 'key_opts' ][ 'editing' ] = [
				'name'    => __( 'Visitor IP', 'wp-simple-firewall' ),
				'enabled' => true,
				'summary' => sprintf( __( 'Visitor IP address source is: %s', 'wp-simple-firewall' ), $sSource ),
				'weight'  => 0,
				'href'    => $this->getUrl_DirectLinkToOption( 'visitor_address_source' ),
			];

			$bHasSupportEmail = Services::Data()->validEmail( $this->supplyPluginReportEmail() );
			$aThis[ 'key_opts' ][ 'reports' ] = [
				'name'    => __( 'Reporting Email', 'wp-simple-firewall' ),
				'enabled' => $bHasSupportEmail,
				'summary' => $bHasSupportEmail ?
					sprintf( __( 'Email address for reports set to: %s', 'wp-simple-firewall' ), $this->supplyPluginReportEmail() )
					: sprintf( __( 'No address provided - defaulting to: %s', 'wp-simple-firewall' ), Services::WpGeneral()
																											  ->getSiteAdminEmail() ),
				'weight'  => 0,
				'href'    => $this->getUrl_DirectLinkToOption( 'block_send_email_address' ),
			];

			$bRecap = $this->isGoogleRecaptchaReady();
			$aThis[ 'key_opts' ][ 'recap' ] = [
				'name'    => __( 'reCAPTCHA', 'wp-simple-firewall' ),
				'enabled' => $bRecap,
				'summary' => $bRecap ?
					__( 'Google reCAPTCHA keys have been provided', 'wp-simple-firewall' )
					: __( "Google reCAPTCHA keys haven't been provided", 'wp-simple-firewall' ),
				'weight'  => 1,
				'href'    => $this->getUrl_DirectLinkToOption( 'block_send_email_address' ),
			];
		}

		$aAllData[ $this->getSlug() ] = $aThis;
		return $aAllData;
	}

	public function onPluginDelete() {
		$this->getDbHandler_GeoIp()->deleteTable();
		$this->getDbHandler_Notes()->deleteTable();
		parent::onPluginDelete();
	}

	protected function cleanupDatabases() {
		$this->getDbHandler_GeoIp()->autoCleanDb();
		$this->getDbHandler_Notes()->autoCleanDb();
	}

	/**
	 * @return Shield\Databases\GeoIp\Handler
	 */
	public function getDbHandler_GeoIp() {
		if ( !isset( $this->oDbh_GeoIp ) ) {
			try {
				$this->oDbh_GeoIp = ( new Shield\Databases\GeoIp\Handler() )
					->setMod( $this )
					->tableInit();
			}
			catch ( \Exception $oE ) {
			}
		}
		return $this->oDbh_GeoIp;
	}

	/**
	 * @return Shield\Databases\AdminNotes\Handler
	 */
	public function getDbHandler_Notes() {
		if ( !isset( $this->oDbh_Notes ) ) {
			try {
				$this->oDbh_Notes = ( new Shield\Databases\AdminNotes\Handler() )
					->setMod( $this )
					->tableInit();
			}
			catch ( \Exception $oE ) {
			}
		}
		return $this->oDbh_Notes;
	}

	/**
	 * @return Shield\Modules\Plugin\Options
	 */
	protected function loadOptions() {
		return new Shield\Modules\Plugin\Options();
	}

	/**
	 * @return Shield\Modules\Plugin\Strings
	 */
	protected function loadStrings() {
		return new Shield\Modules\Plugin\Strings();
	}

	/**
	 * @return string
	 */
	private function getSurveyEmail() {
		return base64_decode( $this->getDef( 'survey_email' ) );
	}

	/**
	 * @return array
	 * @deprecated
	 */
	public function supplyGoogleRecaptchaConfig() {
		return $this->getGoogleRecaptchaConfig();
	}
}