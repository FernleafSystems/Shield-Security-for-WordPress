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
		$aResponse = [];

		switch ( $sAction ) {

			case 'start_scans':
				$aResponse = $this->ajaxExec_StartScans();
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
		$oTablePro = $oP->getSubProScanner()->getScannerFromSlug( $sScannerSlug );

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
					$sMessage = 'Successfully completed. Re-scanning and reloading ...';
				}
				else {
					$sMessage = 'An error occurred - not all items may have been processed. Re-scanning and reloading ...';
				}
				$oTablePro->doScan();
			}
			catch ( \Exception $oE ) {
				$sMessage = $oE->getMessage();
			}
		}

		return [
			'success'     => $bSuccess,
			'page_reload' => in_array( $sScannerSlug, [ 'apc', 'ptg' ] ),
			'message'     => $sMessage,
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
		$oScanPro = $oP->getSubProScanner();
		if ( !empty( $aFormParams ) ) {
			foreach ( array_keys( $aFormParams ) as $sScan ) {

				$oTablePro = $oScanPro->getScannerFromSlug( $sScan );

				if ( !empty( $oTablePro ) && $oTablePro->isAvailable() ) {
					$oTablePro->doScan();

					if ( isset( $aFormParams[ 'opt_clear_ignore' ] ) ) {
						$oTablePro->resetIgnoreStatus();
					}
					if ( isset( $aFormParams[ 'opt_clear_notification' ] ) ) {
						$oTablePro->resetNotifiedStatus();
					}

					$bSuccess = true;
					$bPageReload = true;
					$sMessage = __( 'Scans completed.', 'wp-simple-firewall' ).' '.__( 'Reloading page', 'wp-simple-firewall' ).'...';
				}
			}
		}

		return [
			'success'     => $bSuccess,
			'page_reload' => $bPageReload,
			'message'     => $sMessage,
		];
	}
}