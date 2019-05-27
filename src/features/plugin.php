<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_FeatureHandler_Plugin extends ICWP_WPSF_FeatureHandler_BaseWpsf {

	protected function doPostConstruction() {
		parent::doPostConstruction();
		$this->setVisitorIp();
	}

	protected function setupCustomHooks() {
		add_filter( $this->prefix( 'report_email_address' ), [ $this, 'supplyPluginReportEmail' ] );
		add_filter( $this->prefix( 'globally_disabled' ), [ $this, 'filter_IsPluginGloballyDisabled' ] );
		add_filter( $this->prefix( 'google_recaptcha_config' ), [ $this, 'supplyGoogleRecaptchaConfig' ], 10, 0 );
	}

	protected function updateHandler() {
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
			$this->getCon()->getModule( 'insights' )->getUrl_AdminPage()
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

			/** @var ICWP_WPSF_Processor_Plugin $oPro */
			$oPro = $this->getProcessor();
			/** @var Shield\Databases\AdminNotes\Delete $oDel */
			$oDel = $oPro->getSubProcessorNotes()->getDbHandler()->getQueryDeleter();
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
			/** @var ICWP_WPSF_Processor_Plugin $oPro */
			$oPro = $this->getProcessor();
			try {
				$bSuccess = $oPro->getSubProcessorNotes()
								 ->getDbHandler()
								 ->getQueryDeleter()
								 ->deleteById( $sItemId );

				if ( $bSuccess ) {
					$sMessage = 'Note deleted';
				}
				else {
					$sMessage = "Note couldn't be deleted";
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
			/** @var ICWP_WPSF_Processor_Plugin $oP */
			$oP = $this->getProcessor();
			/** @var Shield\Databases\AdminNotes\Insert $oInserter */
			$oInserter = $oP->getSubProcessorNotes()
							->getDbHandler()
							->getQueryInserter();
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
		/** @var ICWP_WPSF_Processor_Plugin $oPro */
		$oPro = $this->getProcessor();
		$oTableBuilder = ( new Shield\Tables\Build\AdminNotes() )
			->setMod( $this )
			->setDbHandler( $oPro->getSubProcessorNotes()->getDbHandler() );

		return [
			'success' => true,
			'html'    => $oTableBuilder->buildTable()
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
	public function supplyGoogleRecaptchaConfig() {
		return [
			'key'    => $this->getOpt( 'google_recaptcha_site_key' ),
			'secret' => $this->getOpt( 'google_recaptcha_secret_key' ),
			'style'  => $this->getOpt( 'google_recaptcha_style' ),
		];
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
	 * @return array
	 */
	private function buildIpAddressMap() {
		$oReq = Services::Request();

		$aOptionData = $this->getOptionsVo()->getRawData_SingleOption( 'visitor_address_source' );
		$aValueOptions = $aOptionData[ 'value_options' ];

		$aMap = [];
		$aEmpties = [];
		foreach ( $aValueOptions as $aOptionValue ) {
			$sKey = $aOptionValue[ 'value_key' ];
			if ( $sKey == 'AUTO_DETECT_IP' ) {
				$sKey = 'Auto Detect';
				$sIp = Services::IP()->getRequestIp().sprintf( ' (%s)', $this->getOpt( 'last_ip_detect_source' ) );
			}
			else {
				$sIp = $oReq->server( $sKey );
			}
			if ( empty( $sIp ) ) {
				$aEmpties[] = sprintf( '%s- %s', $sKey, 'ip not available' );
			}
			else {
				$aMap[] = sprintf( '%s- %s', $sKey, empty( $sIp ) ? 'ip not available' : '<strong>'.$sIp.'</strong>' );
			}
		}
		return array_merge( $aMap, $aEmpties );
	}

	/**
	 * @return string
	 * @throws \Exception
	 */
	public function renderPluginBadge() {
		$oCon = $this->getCon();

		$aData = [
			'ajax' => [
				'plugin_badge_close' => $this->getAjaxActionData( 'plugin_badge_close', true ),
			]
		];
		$sContents = $this->loadRenderer( $oCon->getPath_Templates() )
						  ->setTemplateEnginePhp()
						  ->clearRenderVars()
						  ->setRenderVars( $aData )
						  ->setTemplate( 'snippets/plugin_badge' )
						  ->render();

		$sBadgeText = sprintf(
			__( 'This Site Is Protected By %s', 'wp-simple-firewall' ),
			sprintf(
				'<br /><span style="font-weight: bold;">The %s &rarr;</span>',
				$oCon->getHumanName()
			)
		);
		$sBadgeText = apply_filters( 'icwp_shield_plugin_badge_text', $sBadgeText );
		$bNoFollow = apply_filters( 'icwp_shield_badge_relnofollow', false );
		return sprintf( $sContents,
			$bNoFollow ? 'rel="nofollow"' : '',
			$oCon->getPluginUrl_Image( 'pluginlogo_32x32.png' ),
			$oCon->getHumanName(),
			$sBadgeText
		);
	}

	/**
	 * @return array
	 */
	protected function getDisplayStrings() {
		return Services::DataManipulation()->mergeArraysRecursive(
			parent::getDisplayStrings(),
			[
				'actions_title'   => __( 'Plugin Actions', 'wp-simple-firewall' ),
				'actions_summary' => __( 'E.g. Import/Export', 'wp-simple-firewall' ),
			]
		);
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
	 * @return $this
	 */
	public function updateTestCronLastRunAt() {
		return $this->setOptInsightsAt( 'test_cron_last_run_at' );
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

	/**
	 * @param array $aOptionsParams
	 * @return array
	 * @throws \Exception
	 */
	protected function loadStrings_SectionTitles( $aOptionsParams ) {

		$sName = $this->getCon()->getHumanName();
		switch ( $aOptionsParams[ 'slug' ] ) {

			case 'section_global_security_options' :
				$sTitle = __( 'Global Security Plugin Disable', 'wp-simple-firewall' );
				$sTitleShort = sprintf( __( 'Disable %s', 'wp-simple-firewall' ), $sName );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Use this option to completely disable all active Shield Protection.', 'wp-simple-firewall' ) ),
				];
				break;

			case 'section_defaults' :
				$sTitle = __( 'Plugin Defaults', 'wp-simple-firewall' );
				$sTitleShort = __( 'Plugin Defaults', 'wp-simple-firewall' );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Important default settings used throughout the plugin.', 'wp-simple-firewall' ) ),
				];
				break;

			case 'section_importexport' :
				$sTitle = sprintf( '%s / %s', __( 'Import', 'wp-simple-firewall' ), __( 'Export', 'wp-simple-firewall' ) );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Automatically import options, and deploy configurations across your entire network.', 'wp-simple-firewall' ) ),
					sprintf( __( 'This is a Pro-only feature.', 'wp-simple-firewall' ) ),
				];
				$sTitleShort = sprintf( '%s / %s', __( 'Import', 'wp-simple-firewall' ), __( 'Export', 'wp-simple-firewall' ) );
				break;

			case 'section_general_plugin_options' :
				$sTitle = __( 'General Plugin Options', 'wp-simple-firewall' );
				$sTitleShort = __( 'General Options', 'wp-simple-firewall' );
				break;

			case 'section_third_party_google' :
				$sTitle = __( 'Google reCAPTCHA', 'wp-simple-firewall' );
				$sTitleShort = __( 'Google reCAPTCHA', 'wp-simple-firewall' );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), sprintf( __( 'Setup Google reCAPTCHA for use across %s.', 'wp-simple-firewall' ), $sName ) ),
					sprintf( '%s - %s',
						__( 'Recommendation', 'wp-simple-firewall' ),
						sprintf( __( 'Use of this feature is highly recommend.', 'wp-simple-firewall' ).' '
								 .sprintf( '%s: %s', __( 'Note', 'wp-simple-firewall' ), __( 'You must create your own Google reCAPTCHA API Keys.', 'wp-simple-firewall' ) )
						)
						.sprintf( ' <a href="%s" target="_blank">%s</a>', 'https://www.google.com/recaptcha/admin', __( 'Manage Keys Here', 'wp-simple-firewall' ) )
					),
					sprintf( '%s - %s', __( 'Note', 'wp-simple-firewall' ), sprintf( __( 'Invisible Google reCAPTCHA is available with %s Pro.', 'wp-simple-firewall' ), $sName ) )
				];
				break;

			case 'section_third_party_duo' :
				$sTitle = __( 'Duo Security', 'wp-simple-firewall' );
				$sTitleShort = __( 'Duo Security', 'wp-simple-firewall' );
				break;

			default:
				throw new \Exception( sprintf( 'A section slug was defined but with no associated strings. Slug: "%s".', $aOptionsParams[ 'slug' ] ) );
		}
		$aOptionsParams[ 'title' ] = $sTitle;
		$aOptionsParams[ 'summary' ] = ( isset( $aSummary ) && is_array( $aSummary ) ) ? $aSummary : [];
		$aOptionsParams[ 'title_short' ] = $sTitleShort;
		return $aOptionsParams;
	}

	/**
	 * @param array $aOptionsParams
	 * @return array
	 * @throws \Exception
	 */
	protected function loadStrings_Options( $aOptionsParams ) {

		$sKey = $aOptionsParams[ 'key' ];
		$sPlugName = $this->getCon()->getHumanName();
		switch ( $sKey ) {

			case 'global_enable_plugin_features' :
				$sName = __( 'Enable/Disable Plugin Modules', 'wp-simple-firewall' );
				$sSummary = __( 'Enable/Disable All Plugin Modules', 'wp-simple-firewall' );
				$sDescription = sprintf( __( 'Uncheck this option to disable all %s features.', 'wp-simple-firewall' ), $sPlugName );
				break;

			case 'enable_notes' :
				$sName = sprintf( __( 'Enable %s', 'wp-simple-firewall' ), __( 'Admin Notes', 'wp-simple-firewall' ) );
				$sSummary = __( 'Turn-On Admin Notes Section In Insights Dashboard', 'wp-simple-firewall' );
				$sDescription = __( 'When turned-on it enables administrators to enter custom notes in the Insights Dashboard.', 'wp-simple-firewall' );
				break;

			case 'enable_tracking' :
				$sName = sprintf( __( 'Enable %s Module', 'wp-simple-firewall' ), __( 'Information Gathering', 'wp-simple-firewall' ) );
				$sSummary = __( 'Permit Anonymous Usage Information Gathering', 'wp-simple-firewall' );
				$sDescription = __( 'Allows us to gather information on statistics and features in-use across our client installations.', 'wp-simple-firewall' )
								.' '.__( 'This information is strictly anonymous and contains no personally, or otherwise, identifiable data.', 'wp-simple-firewall' )
								.'<br />'.sprintf( '<a href="%s" target="_blank">%s</a>', $this->getLinkToTrackingDataDump(), __( 'Click to see the exact data that would be sent.', 'wp-simple-firewall' ) );
				break;

			case 'visitor_address_source' :
				$sName = __( 'IP Source', 'wp-simple-firewall' );
				$sSummary = __( 'Which IP Address Is Yours', 'wp-simple-firewall' ).'?';
				$sDescription = __( 'There are many possible ways to detect visitor IP addresses. If Auto-Detect is not working, please select yours from the list.', 'wp-simple-firewall' )
								.'<br />'.__( 'If the option you select becomes unavailable, we will revert to auto detection.', 'wp-simple-firewall' )
								.'<br />'.sprintf(
									__( 'Current source is: %s (%s)', 'wp-simple-firewall' ),
									'<strong>'.$this->getVisitorAddressSource().'</strong>',
									$this->getOpt( 'last_ip_detect_source' )
								)
								.'<br />'
								.'<br />'.implode( '<br />', $this->buildIpAddressMap() );
				break;

			case 'block_send_email_address' :
				$sName = __( 'Report Email', 'wp-simple-firewall' );
				$sSummary = __( 'Where to send email reports', 'wp-simple-firewall' );
				$sDescription = sprintf( __( 'If this is empty, it will default to the blog admin email address: %s', 'wp-simple-firewall' ), '<br /><strong>'.get_bloginfo( 'admin_email' ).'</strong>' );
				break;

			case 'enable_upgrade_admin_notice' :
				$sName = __( 'In-Plugin Notices', 'wp-simple-firewall' );
				$sSummary = __( 'Display Plugin Specific Notices', 'wp-simple-firewall' );
				$sDescription = __( 'Disable this option to hide certain plugin admin notices about available updates and post-update notices.', 'wp-simple-firewall' );
				break;

			case 'display_plugin_badge' :
				$sName = __( 'Show Plugin Badge', 'wp-simple-firewall' );
				$sSummary = __( 'Display Plugin Badge On Your Site', 'wp-simple-firewall' );
				$sDescription = __( 'Enabling this option helps support the plugin by spreading the word about it on your website.', 'wp-simple-firewall' )
								.' '.__( 'The plugin badge also lets visitors know your are taking your website security seriously.', 'wp-simple-firewall' )
								.sprintf( '<br /><strong><a href="%s" target="_blank">%s</a></strong>', 'https://icwp.io/wpsf20', __( 'Read this carefully before enabling this option.', 'wp-simple-firewall' ) );
				break;

			case 'delete_on_deactivate' :
				$sName = __( 'Delete Plugin Settings', 'wp-simple-firewall' );
				$sSummary = __( 'Delete All Plugin Settings Upon Plugin Deactivation', 'wp-simple-firewall' );
				$sDescription = __( 'Careful: Removes all plugin options when you deactivate the plugin', 'wp-simple-firewall' );
				break;

			case 'enable_xmlrpc_compatibility' :
				$sName = __( 'XML-RPC Compatibility', 'wp-simple-firewall' );
				$sSummary = __( 'Allow Login Through XML-RPC To By-Pass Accounts Management Rules', 'wp-simple-firewall' );
				$sDescription = __( 'Enable this if you need XML-RPC functionality e.g. if you use the WordPress iPhone/Android App.', 'wp-simple-firewall' );
				break;

			case 'importexport_enable' :
				$sName = __( 'Allow Import/Export', 'wp-simple-firewall' );
				$sSummary = __( 'Allow Import And Export Of Options On This Site', 'wp-simple-firewall' );
				$sDescription = __( 'Uncheck this box to completely disable import and export of options.', 'wp-simple-firewall' )
								.'<br />'.sprintf( '%s: %s', __( 'Note', 'wp-simple-firewall' ), __( 'Import/Export is a premium-only feature.', 'wp-simple-firewall' ) );
				break;

			case 'importexport_whitelist' :
				$sName = __( 'Export Whitelist', 'wp-simple-firewall' );
				$sSummary = __( 'Whitelisted Sites To Export Options From This Site', 'wp-simple-firewall' );
				$sDescription = __( 'Whitelisted sites may export options from this site without the key.', 'wp-simple-firewall' )
								.'<br />'.__( 'List each site URL on a new line.', 'wp-simple-firewall' )
								.'<br />'.__( 'This is to be used in conjunction with the Master Import Site feature.', 'wp-simple-firewall' );
				break;

			case 'importexport_masterurl' :
				$sName = __( 'Master Import Site', 'wp-simple-firewall' );
				$sSummary = __( 'Automatically Import Options From This Site URL', 'wp-simple-firewall' );
				$sDescription = __( "Supplying a site URL here will make this site an 'Options Slave'.", 'wp-simple-firewall' )
								.'<br />'.__( 'Options will be automatically exported from the Master site each day.', 'wp-simple-firewall' )
								.'<br />'.sprintf( '%s: %s', __( 'Warning', 'wp-simple-firewall' ), __( 'Use of this feature will overwrite existing options and replace them with those from the Master Import Site.', 'wp-simple-firewall' ) );
				break;

			case 'importexport_whitelist_notify' :
				$sName = __( 'Notify Whitelist', 'wp-simple-firewall' );
				$sSummary = __( 'Notify Sites On The Whitelist To Update Options From Master', 'wp-simple-firewall' );
				$sDescription = __( "When enabled, manual options saving will notify sites on the whitelist to export options from the Master site.", 'wp-simple-firewall' );
				break;

			case 'importexport_secretkey' :
				$sName = __( 'Secret Key', 'wp-simple-firewall' );
				$sSummary = __( 'Import/Export Secret Key', 'wp-simple-firewall' );
				$sDescription = __( 'Keep this Secret Key private as it will allow the import and export of options.', 'wp-simple-firewall' );
				break;

			case 'unique_installation_id' :
				$sName = __( 'Installation ID', 'wp-simple-firewall' );
				$sSummary = __( 'Unique Plugin Installation ID', 'wp-simple-firewall' );
				$sDescription = __( 'Keep this ID private.', 'wp-simple-firewall' );
				break;

			case 'google_recaptcha_secret_key' :
				$sName = __( 'reCAPTCHA Secret', 'wp-simple-firewall' );
				$sSummary = __( 'Google reCAPTCHA Secret Key', 'wp-simple-firewall' );
				$sDescription = __( 'Enter your Google reCAPTCHA secret key for use throughout the plugin.', 'wp-simple-firewall' )
								.'<br />'.sprintf( '<strong>%s</strong>: %s', __( 'Important', 'wp-simple-firewall' ), 'reCAPTCHA v3 not supported.' );
				break;

			case 'google_recaptcha_site_key' :
				$sName = __( 'reCAPTCHA Site Key', 'wp-simple-firewall' );
				$sSummary = __( 'Google reCAPTCHA Site Key', 'wp-simple-firewall' );
				$sDescription = __( 'Enter your Google reCAPTCHA site key for use throughout the plugin', 'wp-simple-firewall' )
								.'<br />'.sprintf( '<strong>%s</strong>: %s', __( 'Important', 'wp-simple-firewall' ), 'reCAPTCHA v3 not supported.' );
				break;

			case 'google_recaptcha_style' :
				$sName = __( 'reCAPTCHA Style', 'wp-simple-firewall' );
				$sSummary = __( 'How Google reCAPTCHA Will Be Displayed By Default', 'wp-simple-firewall' );
				$sDescription = __( 'You can choose the reCAPTCHA display format that best suits your site, including the new Invisible Recaptcha', 'wp-simple-firewall' );
				break;

			default:
				throw new \Exception( sprintf( 'An option has been defined but without strings assigned to it. Option key: "%s".', $sKey ) );
		}

		$aOptionsParams[ 'name' ] = $sName;
		$aOptionsParams[ 'summary' ] = $sSummary;
		$aOptionsParams[ 'description' ] = $sDescription;
		return $aOptionsParams;
	}

	/**
	 * @return string
	 */
	private function getSurveyEmail() {
		return base64_decode( $this->getDef( 'survey_email' ) );
	}

	/**
	 * Kept just in-case.
	 */
	protected function old_translations() {
		__( 'IP Whitelist', 'wp-simple-firewall' );
		__( 'IP Address White List', 'wp-simple-firewall' );
		__( 'Any IP addresses on this list will by-pass all Plugin Security Checking.', 'wp-simple-firewall' );
		__( 'Your IP address is: %s', 'wp-simple-firewall' );
		__( 'Choose IP Addresses To Blacklist', 'wp-simple-firewall' );
		__( 'Recommendation - %s', 'wp-simple-firewall' );
		__( 'Blacklist', 'wp-simple-firewall' );
		__( 'Logging', 'wp-simple-firewall' );
		__( 'User "%s" was forcefully logged out as they were not verified by either cookie or IP address (or both).', 'wp-simple-firewall' );
		__( 'User "%s" was found to be un-verified at the given IP Address: "%s".', 'wp-simple-firewall' );
		__( 'Cookie', 'wp-simple-firewall' );
		__( 'IP Address', 'wp-simple-firewall' );
		__( 'IP', 'wp-simple-firewall' );
		__( 'This will restrict all user login sessions to a single browser. Use this if your users have dynamic IP addresses.', 'wp-simple-firewall' );
		__( 'All users will be required to authenticate their login by email-based two-factor authentication, when logging in from a new IP address', 'wp-simple-firewall' );
		__( '2-Factor Auth', 'wp-simple-firewall' );
		__( 'Include Logged-In Users', 'wp-simple-firewall' );
		__( 'You may also enable GASP for logged in users', 'wp-simple-firewall' );
		__( 'Since logged-in users would be expected to be vetted already, this is off by default.', 'wp-simple-firewall' );
		__( 'Security Admin', 'wp-simple-firewall' );
		__( 'Protect your security plugin not just your WordPress site', 'wp-simple-firewall' );
		__( 'Security Admin', 'wp-simple-firewall' );
		__( 'Audit Trail', 'wp-simple-firewall' );
		__( 'Get a view on what happens on your site, when it happens', 'wp-simple-firewall' );
		__( 'Audit Trail Viewer', 'wp-simple-firewall' );
		__( 'Automatic Updates', 'wp-simple-firewall' );
		__( 'Take back full control of WordPress automatic updates', 'wp-simple-firewall' );
		__( 'Comments SPAM', 'wp-simple-firewall' );
		__( 'Block comment SPAM and retain your privacy', 'wp-simple-firewall' );
		__( 'Email', 'wp-simple-firewall' );
		__( 'Firewall', 'wp-simple-firewall' );
		__( 'Automatically block malicious URLs and data sent to your site', 'wp-simple-firewall' );
		__( 'Hack Guard', 'wp-simple-firewall' );
		__( 'HTTP Headers', 'wp-simple-firewall' );
		__( 'Control HTTP Security Headers', 'wp-simple-firewall' );
		__( 'IP Manager', 'wp-simple-firewall' );
		__( 'Manage Visitor IP Address', 'wp-simple-firewall' );
		__( 'WP Lockdown', 'wp-simple-firewall' );
		__( 'Harden the more loosely controlled settings of your site', 'wp-simple-firewall' );
		__( 'Login Guard', 'wp-simple-firewall' );
		__( 'Block brute force attacks and secure user identities with Two-Factor Authentication', 'wp-simple-firewall' );
		__( 'Dashboard', 'wp-simple-firewall' );
		__( 'General Plugin Settings', 'wp-simple-firewall' );
		__( 'Statistics', 'wp-simple-firewall' );
		__( 'Summary of the main security actions taken by this plugin', 'wp-simple-firewall' );
		__( 'Stats Viewer', 'wp-simple-firewall' );
		__( 'Premium Support', 'wp-simple-firewall' );
		__( 'Premium Plugin Support Centre', 'wp-simple-firewall' );
		__( 'User Management', 'wp-simple-firewall' );
		__( 'Get true user sessions and control account sharing, session duration and timeouts', 'wp-simple-firewall' );
		__( 'Two-Factor Authentication', 'wp-simple-firewall' );
	}
}