<?php

class ICWP_WPSF_Processor_Plugin_ImportExport extends ICWP_WPSF_Processor_BaseWpsf {

	public function run() {
		/** @var ICWP_WPSF_FeatureHandler_Plugin $oFO */
		$oFO = $this->getMod();

		add_action( $this->prefix( 'importexport_notify' ), array( $this, 'runWhitelistNotify' ) );

		if ( $oFO->hasImportExportMasterImportUrl() ) {
			try {
				$this->setupCronImport();
			}
			catch ( \Exception $oE ) {
				error_log( $oE->getMessage() );
			}
		}
	}

	public function runWhitelistNotify() {
		/** @var ICWP_WPSF_FeatureHandler_Plugin $oFO */
		$oFO = $this->getMod();

		if ( $oFO->hasImportExportWhitelistSites() ) {

			$oFs = $this->loadFS();
			foreach ( $oFO->getImportExportWhitelist() as $sUrl ) {
				$oFs->getUrl(
					$sUrl,
					array(
						'blocking' => false,
						'body'     => array( 'shield_action' => 'importexport_updatenotified' )
					)
				);
			}

			$this->addToAuditEntry(
				_wpsf__( 'Sent notifications to whitelisted sites for required options import.' ),
				1,
				'options_import_notify'
			);
		}
	}

	public function runAction() {
		switch ( $this->loadRequest()->query( 'shield_action' ) ) {

			case 'importexport_export':
				add_action( 'init', array( $this, 'runOptionsExport' ) );
				break;

			case 'importexport_handshake':
				add_action( 'init', array( $this, 'runOptionsExportHandshake' ) );
				break;

			case 'importexport_updatenotified':
				add_action( 'init', array( $this, 'runOptionsUpdateNotified' ) );
				break;

			default:
				break;
		}
	}

	/**
	 * This is called from a remote site when this site sends out an export request to another
	 * site but without a secret key i.e. it assumes it's on the white list. We give a 30 second
	 * window for the handshake to complete.  We do not explicitly fail.
	 */
	public function runOptionsExportHandshake() {
		/** @var ICWP_WPSF_FeatureHandler_Plugin $oFO */
		$oFO = $this->getMod();
		if ( $oFO->isPremium() && $oFO->isImportExportPermitted() &&
			 ( $this->loadRequest()->ts() < $oFO->getImportExportHandshakeExpiresAt() ) ) {
			echo json_encode( array( 'success' => true ) );
			die();
		}
		else {
			return;
		}
	}

	/**
	 * We've been notified that there's an update to pull in from the master site so we set a cron to do this.
	 */
	public function runOptionsUpdateNotified() {
		/** @var ICWP_WPSF_FeatureHandler_Plugin $oFO */
		$oFO = $this->getMod();

		$sCronHook = $oFO->prefix( 'importexport_updatenotified' );
		if ( wp_next_scheduled( $sCronHook ) ) {
			wp_clear_scheduled_hook( $sCronHook );
		}

		if ( !wp_next_scheduled( $sCronHook ) ) {

			wp_schedule_single_event( $this->loadRequest()->ts() + 12, $sCronHook );

			preg_match( '#.*WordPress/.*\s+(.*)\s?#', $this->loadRequest()->server( 'HTTP_USER_AGENT' ), $aMatches );
			if ( !empty( $aMatches[ 1 ] ) && filter_var( $aMatches[ 1 ], FILTER_VALIDATE_URL ) ) {
				$sUrl = parse_url( $aMatches[ 1 ], PHP_URL_HOST );
				if ( !empty( $sUrl ) ) {
					$sUrl = 'Site: '.$sUrl;
				}
			}
			else {
				$sUrl = '';
			}

			$this->addToAuditEntry(
				_wpsf__( 'Received notification that options import required.' )
				.' '.sprintf( _wpsf__( 'Current master site: %s' ), $oFO->getImportExportMasterImportUrl() ),
				1,
				'options_import_notified',
				$sUrl
			);
		}
	}

	/**
	 */
	public function runOptionsExport() {
		/** @var ICWP_WPSF_FeatureHandler_Plugin $oFO */
		$oFO = $this->getMod();
		$oReq = $this->loadRequest();

		$sSecretKey = $oReq->query( 'secret', '' );
		$bNetwork = $oReq->query( 'network', '' ) === 'Y';
		$sUrl = $this->loadDP()->validateSimpleHttpUrl( $oReq->query( 'url', '' ) );

		if ( !$oFO->isImportExportSecretKey( $sSecretKey ) && !$this->isUrlOnWhitelist( $sUrl ) ) {
			return; // we show no signs of responding to invalid secret keys or unwhitelisted URLs
		}

		$bSuccess = false;
		$aData = array();

		if ( !$oFO->isPremium() ) {
			$nCode = 1;
			$sMessage = sprintf( _wpsf__( 'Not currently running %s Pro.' ), $this->getCon()->getHumanName() );
		}
		else if ( !$oFO->isImportExportPermitted() ) {
			$nCode = 2;
			$sMessage = _wpsf__( 'Export of options is currently disabled.' );
		}
		else if ( !$this->verifyUrlWithHandshake( $sUrl ) ) {
			$nCode = 3;
			$sMessage = _wpsf__( 'Handshake verification failed.' );
		}
		else {
			$nCode = 0;
			$bSuccess = true;
			$aData = apply_filters( $oFO->prefix( 'gather_options_for_export' ), array() );
			$sMessage = 'Options Exported Successfully';

			$this->addToAuditEntry(
				sprintf( _wpsf__( 'Options exported to site %s.' ), $sUrl ), 1, 'options_exported'
			);

			if ( $bNetwork ) {
				$oFO->addUrlToImportExportWhitelistUrls( $sUrl );
				$this->addToAuditEntry(
					sprintf( _wpsf__( 'Site added to export white list: %s.' ), $sUrl ),
					1,
					'export_whitelist_site_added'
				);
			}
		}

		$aResponse = array(
			'success' => $bSuccess,
			'code'    => $nCode,
			'message' => $sMessage,
			'data'    => $aData,
		);
		echo json_encode( $aResponse );
		die();
	}

	/**
	 * @param string $sUrl
	 * @return bool
	 */
	protected function isUrlOnWhitelist( $sUrl ) {
		/** @var ICWP_WPSF_FeatureHandler_Plugin $oFO */
		$oFO = $this->getMod();
		return !empty( $sUrl ) && in_array( $sUrl, $oFO->getImportExportWhitelist() );
	}

	/**
	 * @param string $sUrl
	 * @return bool
	 */
	protected function verifyUrlWithHandshake( $sUrl ) {
		$bVerified = false;

		if ( !empty( $sUrl ) ) {
			$sFinalUrl = add_query_arg(
				array( 'shield_action' => 'importexport_handshake' ),
				$sUrl
			);
			$aParts = @json_decode( $this->loadFS()->getUrlContent( $sFinalUrl ), true );
			$bVerified = !empty( $aParts ) && is_array( $aParts )
						 && isset( $aParts[ 'success' ] ) && ( $aParts[ 'success' ] === true );
		}

		return $bVerified;
	}

	/**
	 * @throws \Exception
	 */
	protected function setupCronImport() {
		/** @var ICWP_WPSF_FeatureHandler_Plugin $oFO */
		$oFO = $this->getMod();
		$this->loadWpCronProcessor()
			 ->setNextRun( strtotime( 'tomorrow 1am' ) - get_option( 'gmt_offset' )*HOUR_IN_SECONDS + rand( 0, 1800 ) )
			 ->createCronJob( $this->getCronName(), array( $this, 'cron_autoImport' ) );
		// For auto update whitelist notifications:
		add_action( $oFO->prefix( 'importexport_updatenotified' ), array( $this, 'cron_autoImport' ) );
		add_action( $this->getMod()->prefix( 'deactivate_plugin' ), array( $this, 'deleteCron' ) );
	}

	/**
	 * @param string $sMasterSiteUrl
	 * @param string $sSecretKey
	 * @param bool   $bEnableNetwork
	 * @param string $sSiteResponse
	 * @return int
	 */
	public function runImport( $sMasterSiteUrl, $sSecretKey = '', $bEnableNetwork = false, &$sSiteResponse = '' ) {
		/** @var ICWP_WPSF_FeatureHandler_Plugin $oFO */
		$oFO = $this->getMod();
		$oDP = $this->loadDP();

		$aParts = parse_url( $sMasterSiteUrl );

		$bCheckKeyFormat = !$oFO->hasImportExportMasterImportUrl();
		$sSecretKey = preg_replace( '#[^0-9a-z]#i', '', $sSecretKey );

		if ( $bCheckKeyFormat && empty( $sSecretKey ) ) {
			$nErrorCode = 1;
		}
		else if ( $bCheckKeyFormat && strlen( $sSecretKey ) != 40 ) {
			$nErrorCode = 2;
		}
		else if ( $bCheckKeyFormat && preg_match( '#[^0-9a-z]#i', $sSecretKey ) ) {
			$nErrorCode = 3; //unused
		}
		else if ( empty( $aParts ) ) {
			$nErrorCode = 4;
		}
		else if ( $oDP->validateSimpleHttpUrl( $sMasterSiteUrl ) === false ) {
			$nErrorCode = 4; // a final check
		}
		else {
			$bReady = true;
			$aEssential = array( 'scheme', 'host' );
			foreach ( $aEssential as $sKey ) {
				$bReady = $bReady && !empty( $aParts[ $sKey ] );
			}

			$sMasterSiteUrl = $oDP->validateSimpleHttpUrl( $sMasterSiteUrl ); // final clean

			if ( !$bReady || !$sMasterSiteUrl ) {
				$nErrorCode = 4;
			}
			else {
				$oFO->startImportExportHandshake();

				$aData = array(
					'shield_action' => 'importexport_export',
					'secret'        => $sSecretKey,
					'url'           => $this->loadWp()->getHomeUrl()
				);
				// Don't send the network setup request if it's the cron.
				if ( !$this->loadWp()->isCron() ) {
					$aData[ 'network' ] = $bEnableNetwork ? 'Y' : 'N';
				}

				$sFinalUrl = add_query_arg( $aData, $sMasterSiteUrl );
				$sResponse = $this->loadFS()->getUrlContent( $sFinalUrl );
				$aParts = @json_decode( $sResponse, true );

				if ( empty( $aParts ) ) {
					$nErrorCode = 5;
				}
				else if ( !isset( $aParts[ 'success' ] ) || !$aParts[ 'success' ] ) {

					if ( empty ( $aParts[ 'message' ] ) ) {
						$nErrorCode = 6;
					}
					else {
						$nErrorCode = 7;
						$sSiteResponse = $aParts[ 'message' ]; // This is crap because we can't use Response objects
					}
				}
				else if ( empty( $aParts[ 'data' ] ) || !is_array( $aParts[ 'data' ] ) ) {
					$nErrorCode = 8;
				}
				else {
					$sHash = md5( serialize( $aParts[ 'data' ] ) );
					if ( $sHash != $oFO->getImportExportLastImportHash() ) {
						do_action( $oFO->prefix( 'import_options' ), $aParts[ 'data' ] );
						$this->addToAuditEntry(
							sprintf( _wpsf__( 'Options imported from %s.' ), $sMasterSiteUrl ),
							1,
							'options_imported'
						);
						$oFO->setImportExportLastImportHash( md5( serialize( $aParts[ 'data' ] ) ) );

						// Fix for the overwriting of the Master Site URL with an empty string.
						if ( !$oFO->hasImportExportMasterImportUrl() ) {
							$oFO->setImportExportMasterImportUrl( $sMasterSiteUrl );
						}
					}

					// if it's network enabled, we save the new master URL.
					if ( $bEnableNetwork ) {
						$this->addToAuditEntry(
							sprintf( _wpsf__( 'Master Site URL set to %s.' ), $sMasterSiteUrl ),
							1,
							'options_master_set'
						);
						$oFO->setImportExportMasterImportUrl( $sMasterSiteUrl );
					}

					$nErrorCode = 0;
				}
			}
		}

		return $nErrorCode;
	}

	public function cron_autoImport() {
		/** @var ICWP_WPSF_FeatureHandler_Plugin $oFO */
		$oFO = $this->getMod();
		$this->runImport( $oFO->getImportExportMasterImportUrl() );
	}

	public function deleteCron() {
		$this->loadWpCronProcessor()->deleteCronJob( $this->getCronName() );
	}

	/**
	 * @return string
	 */
	protected function getCronName() {
		$oFO = $this->getMod();
		return $oFO->prefixOptionKey( $oFO->getDef( 'importexport_cron_name' ) );
	}
}