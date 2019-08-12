<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class AjaxHandler extends Shield\Modules\Base\AjaxHandlerShield {

	/**
	 * @param string $sAction
	 * @return array
	 */
	protected function processAjaxAction( $sAction ) {

		switch ( $sAction ) {

			case 'scans_start':
				$aResponse = $this->ajaxExec_StartScans();
				break;

			case 'scans_check':
				$aResponse = $this->ajaxExec_CheckScans();
				break;

			case 'bulk_action':
				$aResponse = $this->ajaxExec_ScanItemAction( Services::Request()->post( 'bulk_action' ) );
				break;

			case 'item_asset_accept':
			case 'item_asset_deactivate':
			case 'item_asset_reinstall':
			case 'item_delete':
			case 'item_ignore':
			case 'item_repair':
				$aResponse = $this->ajaxExec_ScanItemAction( str_replace( 'item_', '', $sAction ) );
				break;

			case 'render_table_scan':
				$aResponse = $this->ajaxExec_BuildTableScan();
				break;

			case 'plugin_reinstall':
				$aResponse = $this->ajaxExec_PluginReinstall();
				break;

			default:
				$aResponse = parent::processAjaxAction( $sAction );
		}

		return $aResponse;
	}

	/**
	 * @return array
	 */
	private function ajaxExec_BuildTableScan() {
		$oMod = $this->getMod();

		switch ( Services::Request()->post( 'fScan' ) ) {

			case 'apc':
				$oTableBuilder = new Shield\Tables\Build\ScanApc();
				break;

			case 'mal':
				$oTableBuilder = new Shield\Tables\Build\ScanMal();
				break;

			case 'wcf':
				$oTableBuilder = new Shield\Tables\Build\ScanWcf();
				break;

			case 'ptg':
				$oTableBuilder = new Shield\Tables\Build\ScanPtg();
				break;

			case 'ufc':
				$oTableBuilder = new Shield\Tables\Build\ScanUfc();
				break;

			case 'wpv':
				$oTableBuilder = new Shield\Tables\Build\ScanWpv();
				break;

			default:
				break;
		}

		if ( empty( $oTableBuilder ) ) {
			$sHtml = 'SCAN SLUG NOT SPECIFIED';
		}
		else {
			$sHtml = $oTableBuilder
				->setMod( $oMod )
				->setDbHandler( $oMod->getDbHandler() )
				->buildTable();
		}

		return [
			'success' => !empty( $oTableBuilder ),
			'html'    => $sHtml
		];
	}

	/**
	 * @return array
	 */
	private function ajaxExec_PluginReinstall() {
		$oMod = $this->getMod();
		$oReq = Services::Request();

		$bReinstall = (bool)$oReq->post( 'reinstall' );
		$bActivate = (bool)$oReq->post( 'activate' );
		$sFile = sanitize_text_field( wp_unslash( $oReq->post( 'file' ) ) );

		if ( $bReinstall ) {
			/** @var \ICWP_WPSF_Processor_HackProtect $oP */
			$oP = $oMod->getProcessor();
			$bActivate = $oP->getSubProScanner()
							->getSubProcessorPtg()
							->reinstall( $sFile )
						 && $bActivate;
		}

		if ( $bActivate ) {
			Services::WpPlugins()->activate( $sFile );
		}

		return [ 'success' => true ];
	}

	/**
	 * @param string $sAction
	 * @return array
	 */
	private function ajaxExec_ScanItemAction( $sAction ) {
		$oMod = $this->getMod();
		$oReq = Services::Request();

		$bSuccess = false;

		$sItemId = $oReq->post( 'rid' );
		$aItemIds = $oReq->post( 'ids' );
		$sScannerSlug = $oReq->post( 'fScan' );

		/** @var \ICWP_WPSF_Processor_HackProtect $oP */
		$oP = $oMod->getProcessor();
		$oScanner = $oP->getSubProScanner();
		$oTablePro = $oScanner->getScannerFromSlug( $sScannerSlug );

		if ( empty( $oTablePro ) ) {
			$sMessage = __( 'Unsupported scanner', 'wp-simple-firewall' );
		}
		else if ( empty( $sItemId ) && ( empty( $aItemIds ) || !is_array( $aItemIds ) ) ) {
			$sMessage = __( 'Unsupported item(s) selected', 'wp-simple-firewall' );
		}
		else {
			if ( empty( $aItemIds ) ) {
				$aItemIds = [ $sItemId ];
			}

			try {
				$aSuccessfulItems = [];

				foreach ( $aItemIds as $sId ) {
					if ( $oTablePro->executeItemAction( $sId, $sAction ) ) {
						$aSuccessfulItems[] = $sId;
					}
				}

				if ( count( $aSuccessfulItems ) === count( $aItemIds ) ) {
					$bSuccess = true;
					$sMessage = __( 'Action successful.' );
				}
				else {
					$sMessage = __( 'An error occurred.' ).' '.__( 'Some items may not have been processed.' );
				}

				// We don't rescan for ignores.
				if ( !in_array( $sAction, [ 'ignore' ] ) ) {
					$oScanner->launchScans( [ $sScannerSlug ] );
					$sMessage .= ' '.__( 'Rescanning', 'wp-simple-firewall' ).' ...';
				}
				else {
					$sMessage .= ' '.__( 'Reloading', 'wp-simple-firewall' ).' ...';
				}
			}
			catch ( \Exception $oE ) {
				$sMessage = $oE->getMessage();
			}
		}

		return [
			'success'     => $bSuccess,
			'page_reload' => true,
			'message'     => $sMessage,
		];
	}

	/**
	 * @return array
	 */
	private function ajaxExec_CheckScans() {
		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();
		/** @var \ICWP_WPSF_Processor_HackProtect $oP */
		$oP = $oMod->getProcessor();
		/** @var Strings $oStrings */
		$oStrings = $oMod->getStrings();
		$oScanPro = $oP->getSubProScanner();
		$oScanCon = $oScanPro->getAsyncScanController();
		$oJob = $oScanCon->loadScansJob();
//		$oScanCon->abortAllScans();
		$aCurrent = $oJob->getCurrentScan();
		$bHasCurrent = !empty( $aCurrent );
		if ( $bHasCurrent ) {
			$sCurrentScan = $oStrings->getScanName( $aCurrent[ 'id' ] );
		}
		else {
			$sCurrentScan = __( 'No scan running.', 'wp-simple-firewall' );
		}

		return [
			'success' => true,
			'running' => $oScanPro->getScansRunningStates(),
			'vars'    => [
				'progress_html' => $oMod->renderTemplate(
					'/wpadmin_pages/insights/scans/modal_progress_snippet.twig',
					[
						'current_scan'    => __( 'Current Scan', 'wp-simple-firewall' ),
						'scan'            => $sCurrentScan,
						'remaining_scans' => sprintf( __( '%s of %s scans remaining.', 'wp-simple-firewall' ),
							count( $oJob->getUnfinishedScans() ), count( $oJob->getInitiatedScans() ) ),
						'progress'        => 100*$oJob->getScanJobProgress(),
						'patience_1'      => __( 'Please be patient.', 'wp-simple-firewall' ),
						'patience_2'      => __( 'Some scans can take quite a while to complete.', 'wp-simple-firewall' ),
						'completed'       => __( 'Scans completed.', 'wp-simple-firewall' ).' '.__( 'Reloading page', 'wp-simple-firewall' ).'...'
					],
					true
				),
			]
		];
	}

	/**
	 * @return array
	 */
	private function ajaxExec_StartScans() {
		$oMod = $this->getMod();
		$bSuccess = false;
		$bPageReload = false;
		$sMessage = __( 'No scans were selected', 'wp-simple-firewall' );
		$aFormParams = $this->getAjaxFormParams();

		/** @var \ICWP_WPSF_Processor_HackProtect $oP */
		$oP = $oMod->getProcessor();
		$oScanner = $oP->getSubProScanner();
		if ( !empty( $aFormParams ) ) {
			$aSelectedScans = array_keys( $aFormParams );

			$aUiTrack = $oMod->getUiTrack();
			$aUiTrack[ 'selected_scans' ] = $aSelectedScans;
			$oMod->setUiTrack( $aUiTrack );

			$aScansToRun = [];
			foreach ( $aSelectedScans as $sScanSlug ) {

				$oTablePro = $oScanner->getScannerFromSlug( $sScanSlug );

				if ( !empty( $oTablePro ) && $oTablePro->isAvailable() ) {
					$bAsync = true;
					$aScansToRun[] = $sScanSlug;

					if ( isset( $aFormParams[ 'opt_clear_ignore' ] ) ) {
						$oTablePro->resetIgnoreStatus();
					}
					if ( isset( $aFormParams[ 'opt_clear_notification' ] ) ) {
						$oTablePro->resetNotifiedStatus();
					}

					$bSuccess = true;
					$bPageReload = true;
					$sMessage = $bAsync ?
						__( 'Scans started.', 'wp-simple-firewall' ).' '.__( 'Please wait, as this will take a few moments.', 'wp-simple-firewall' )
						: __( 'Scans completed.', 'wp-simple-firewall' ).' '.__( 'Reloading page', 'wp-simple-firewall' );
				}
			}
			$oScanner->launchScans( $aScansToRun );
		}

		$bScansRunning = $oScanner->hasRunningScans();
		return [
			'success'       => $bSuccess,
			'scans_running' => $bScansRunning,
			'page_reload'   => $bPageReload && !$bScansRunning,
			'message'       => $sMessage,
		];
	}
}