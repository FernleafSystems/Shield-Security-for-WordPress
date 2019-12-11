<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan;
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

			case 'item_action':
				$aResponse = $this->ajaxExec_ScanItemAction( Services::Request()->post( 'item_action' ) );
				break;

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
		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();

		$sScanSlug = Services::Request()->post( 'fScan' );
		switch ( $sScanSlug ) {

			case 'aggregate':
				$oTableBuilder = new Shield\Tables\Build\ScanAggregate();
				break;

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
			$sHtml = '<div class="alert alert-danger m-0">SCAN SLUG NOT SUPPORTED</div>';
		}
		else {
			if ( method_exists( $oTableBuilder, 'setScanActionVO' ) ) {
				$oTableBuilder->setScanActionVO( ( new Scan\ScanActionFromSlug() )->getAction( $sScanSlug ) );
			}
			$sHtml = $oTableBuilder
				->setMod( $oMod )
				->setDbHandler( $oMod->getDbHandler_ScanResults() )
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
		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();
		$oScanCon = $oMod->getScanController();
		$oReq = Services::Request();

		$bSuccess = false;
		$bPageReload = true;

		$sItemId = $oReq->post( 'rid' );
		$aItemIds = $oReq->post( 'ids' );

		/** @var \ICWP_WPSF_Processor_HackProtect $oP */
		$oP = $oMod->getProcessor();

		$oDbh = $oMod->getDbHandler_ScanResults();

		if ( $sAction == 'download' ) {
			// A special case since this action is handled using Javascript
			$bSuccess = true;
			$bPageReload = false;
			$sMessage = __( 'File download has started.', 'wp-simple-firewall' );
		}
		elseif ( empty( $sItemId ) && ( empty( $aItemIds ) || !is_array( $aItemIds ) ) ) {
			$sMessage = __( 'Unsupported item(s) selected', 'wp-simple-firewall' );
		}
		else {
			if ( empty( $aItemIds ) ) {
				$aItemIds = [ $sItemId ];
			}

			$aItemIds = array_filter( array_map( function ( $sId ) {
				return is_numeric( $sId ) ? (int)$sId : false;
			}, $aItemIds ) );

			try {
				$aSuccessfulItems = [];

				$aScanSlugs = [];
				foreach ( $aItemIds as $sId ) {
					/** @var Shield\Databases\Scanner\EntryVO $oEntry */
					$oEntry = $oDbh->getQuerySelector()
								   ->byId( $sId );
					if ( $oEntry instanceof Shield\Databases\Scanner\EntryVO ) {
						$aScanSlugs[] = $oEntry->scan;
						$bItemActionSuccess = $oP->getSubProScanner()
												 ->getScannerFromSlug( $oEntry->scan )
												 ->executeItemAction( $sId, $sAction );
						if ( $bItemActionSuccess ) {
							$aSuccessfulItems[] = $sId;
						}
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
					$oScanCon->startScans( $aScanSlugs );
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
			'page_reload' => $bPageReload,
			'message'     => $sMessage,
		];
	}

	/**
	 * @return array
	 */
	private function ajaxExec_CheckScans() {
		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();
		/** @var Strings $oStrings */
		$oStrings = $oMod->getStrings();
		$oDbH = $oMod->getDbHandler_ScanQueue();
		/** @var Shield\Databases\ScanQueue\Select $oSel */
		$oSel = $oDbH->getQuerySelector();

		$oQueCon = $oMod->getScanController();
		$sCurrent = $oSel->getCurrentScan();
		$bHasCurrent = !empty( $sCurrent );
		if ( $bHasCurrent ) {
			$sCurrentScan = $oStrings->getScanName( $sCurrent );
		}
		else {
			$sCurrentScan = __( 'No scan running.', 'wp-simple-firewall' );
		}

		return [
			'success' => true,
			'running' => $oQueCon->getScansRunningStates(),
			'vars'    => [
				'progress_html' => $oMod->renderTemplate(
					'/wpadmin_pages/insights/scans/modal_progress_snippet.twig',
					[
						'current_scan'    => __( 'Current Scan', 'wp-simple-firewall' ),
						'scan'            => $sCurrentScan,
						'remaining_scans' => sprintf( __( '%s of %s scans remaining.', 'wp-simple-firewall' ),
							count( $oSel->getUnfinishedScans() ), count( $oSel->getInitiatedScans() ) ),
						'progress'        => 100*$oQueCon->getScanJobProgress(),
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
		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();
		$bSuccess = false;
		$bPageReload = false;
		$sMessage = __( 'No scans were selected', 'wp-simple-firewall' );
		$aFormParams = $this->getAjaxFormParams();

		$oScanCon = $oMod->getScanController();

		if ( !empty( $aFormParams ) ) {
			$aSelectedScans = array_keys( $aFormParams );

			$aUiTrack = $oMod->getUiTrack();
			$aUiTrack[ 'selected_scans' ] = $aSelectedScans;
			$oMod->setUiTrack( $aUiTrack );

			$aScansToStart = [];
			foreach ( $aSelectedScans as $sScanSlug ) {
				$oThisScanCon = $oMod->getScanCon( $sScanSlug );
				if ( $oThisScanCon->isScanningAvailable() ) {

					$aScansToStart[] = $sScanSlug;

					if ( isset( $aFormParams[ 'opt_clear_ignore' ] ) ) {
						$oThisScanCon->resetIgnoreStatus();
					}
					if ( isset( $aFormParams[ 'opt_clear_notification' ] ) ) {
						$oThisScanCon->resetNotifiedStatus();
					}

					$bSuccess = true;
					$bPageReload = true;
					$sMessage = __( 'Scans started.', 'wp-simple-firewall' ).' '.__( 'Please wait, as this will take a few moments.', 'wp-simple-firewall' );
				}
			}
			$oScanCon->startScans( $aScansToStart );
		}

		$bScansRunning = $oScanCon->hasRunningScans();

		return [
			'success'       => $bSuccess,
			'scans_running' => $bScansRunning,
			'page_reload'   => $bPageReload && !$bScansRunning,
			'message'       => $sMessage,
		];
	}
}