<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan;
use FernleafSystems\Wordpress\Services\Services;

class AjaxHandler extends Shield\Modules\BaseShield\AjaxHandler {

	protected function processAjaxAction( string $action ) :array {

		$oReq = Services::Request();
		switch ( $action ) {

			case 'scans_start':
				$aResponse = $this->ajaxExec_StartScans();
				break;

			case 'scans_check':
				$aResponse = $this->ajaxExec_CheckScans();
				break;

			case 'item_action':
				$aResponse = $this->ajaxExec_ScanItemAction( $oReq->post( 'item_action' ), false );
				break;

			case 'bulk_action':
				$aResponse = $this->ajaxExec_ScanItemAction( $oReq->post( 'bulk_action' ), true );
				break;

			case 'item_asset_deactivate':
			case 'item_asset_reinstall':
			case 'item_delete':
			case 'item_ignore':
			case 'item_repair':
				$aResponse = $this->ajaxExec_ScanItemAction( str_replace( 'item_', '', $action ) );
				break;

			case 'render_table_scan':
				$aResponse = $this->ajaxExec_BuildTableScan();
				break;

			case 'plugin_reinstall':
				$aResponse = $this->ajaxExec_PluginReinstall();
				break;

			case 'filelocker_showdiff':
				$aResponse = $this->ajaxExec_FileLockerShowDiff();
				break;

			case 'filelocker_fileaction':
				$aResponse = $this->ajaxExec_FileLockerFileAction();
				break;

			default:
				$aResponse = parent::processAjaxAction( $action );
		}

		return $aResponse;
	}

	private function ajaxExec_BuildTableScan() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();

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
			$sHtml = $oTableBuilder
				->setMod( $mod )
				->setDbHandler( $mod->getDbHandler_ScanResults() )
				->render();
		}

		return [
			'success' => !empty( $oTableBuilder ),
			'html'    => $sHtml
		];
	}

	private function ajaxExec_FileLockerShowDiff() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$oFLCon = $mod->getFileLocker();
		$FS = Services::WpFs();

		$nRID = Services::Request()->post( 'rid' );
		$aData = [
			'error'   => '',
			'success' => false,
			'flags'   => [
				'has_diff' => false,
			],
			'html'    => [
				'diff' => '',
			],
			'vars'    => [
				'rid' => $nRID,
			],
			'strings' => [
				'no_changes'            => __( 'There have been no changes to the selected file.' ),
				'please_review'         => __( 'Please review the changes below and accept them, or restore the original file contents.' ),
				'butt_restore'          => __( 'Restore File' ),
				'butt_accept'           => __( 'Accept Changes' ),
				'file_name'             => __( 'Name' ),
				'file_size'             => __( 'File Size' ),
				'locked_file'           => __( 'Locked File' ),
				'modified_file'         => __( 'Modified File' ),
				'locked'                => __( 'Locked' ),
				'modified_timestamp'    => __( 'File Modified Timestamp' ),
				'modified'              => __( 'Modified' ),
				'download'              => __( 'Download' ),
				'change_detected_at'    => __( 'Change Detected' ),
				'file_content_original' => __( 'Original File Content' ),
				'file_content_current'  => __( 'Current File Content' ),
				'download_original'     => __( 'Download Original' ),
				'download_modified'     => __( 'Download Modified' ),
				'file_download'         => __( 'File Download' ),
				'file_info'             => __( 'File Info' ),
				'file_accept'           => __( 'File Accept' ),
				'file_accept_checkbox'  => __( 'Are you sure you want to keep the file changes?' ),
				'file_restore'          => __( 'File Restore' ),
				'file_restore_checkbox' => __( 'Are you sure you want to restore the original file contents?' ),
				'file_restore_button'   => __( 'Are you sure you want to restore the original file contents?' ),
			]
		];
		try {

			$oLock = $oFLCon->getFileLock( $nRID );
			$bDiff = $oLock->detected_at > 0;
			$aData[ 'ajax' ] = $oFLCon->createFileDownloadLinks( $oLock );
			$aData[ 'flags' ][ 'has_diff' ] = $bDiff;
			$aData[ 'html' ][ 'diff' ] = $bDiff ?
				( new FileLocker\Ops\PerformAction() )
					->setMod( $this->getMod() )
					->run( $nRID, 'diff' ) : '';

			$oCarb = Services::Request()->carbon( true );
			$aData[ 'vars' ][ 'locked_at' ] = $oCarb->setTimestamp( $oLock->created_at )->diffForHumans();
			$aData[ 'vars' ][ 'file_modified_at' ] =
				Services::WpGeneral()->getTimeStampForDisplay( $FS->getModifiedTime( $oLock->file ) );
			$aData[ 'vars' ][ 'change_detected_at' ] = $oCarb->setTimestamp( $oLock->detected_at )->diffForHumans();
			$aData[ 'vars' ][ 'file_size_locked' ] = Shield\Utilities\Tool\FormatBytes::Format( strlen(
				( new FileLocker\Ops\ReadOriginalFileContent() )
					->setMod( $mod )
					->run( $oLock )
			), 3 );
			$aData[ 'vars' ][ 'file_size_modified' ] = $FS->exists( $oLock->file ) ?
				Shield\Utilities\Tool\FormatBytes::Format( $FS->getFileSize( $oLock->file ), 3 )
				: 0;
			$aData[ 'vars' ][ 'file_name' ] = basename( $oLock->file );
			$aData[ 'success' ] = true;
		}
		catch ( \Exception $oE ) {
			$aData[ 'error' ] = $oE->getMessage();
		}

		return [
			'success' => $aData[ 'success' ],
			'message' => $aData[ 'error' ],
			'html'    => $this->getMod()
							  ->renderTemplate(
								  '/wpadmin_pages/insights/scans/realtime/file_locker/file_diff.twig',
								  $aData,
								  true
							  )
		];
	}

	private function ajaxExec_FileLockerFileAction() :array {
		$oReq = Services::Request();
		$bSuccess = false;

		if ( $oReq->post( 'confirmed' ) == '1' ) {
			$nRID = $oReq->post( 'rid' );
			$sAction = $oReq->post( 'file_action' );
			try {
				$bSuccess = ( new FileLocker\Ops\PerformAction() )
					->setMod( $this->getMod() )
					->run( $nRID, $sAction );
				$sMessage = __( 'Requested action completed successfully.', 'wp-simple-firewall' );
			}
			catch ( \Exception $oE ) {
				$sMessage = __( 'Requested action failed.', 'wp-simple-firewall' );
			}
		}
		else {
			$sMessage = __( 'Please check the box to confirm this action', 'wp-simple-firewall' );
		}

		return [
			'success' => $bSuccess,
			'message' => $sMessage,
		];
	}

	private function ajaxExec_PluginReinstall() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$req = Services::Request();

		$bReinstall = (bool)$req->post( 'reinstall' );
		$bActivate = (bool)$req->post( 'activate' );
		$file = sanitize_text_field( wp_unslash( $req->post( 'file' ) ) );

		if ( $bReinstall ) {
			/** @var Scan\Controller\Ptg $scan */
			$scan = $mod->getScansCon()->getScanCon( 'ptg' );
			$bActivate = $scan->actionPluginReinstall( $file );
		}

		if ( $bActivate ) {
			Services::WpPlugins()->activate( $file );
		}

		return [ 'success' => true ];
	}

	/**
	 * @param string $action
	 * @param bool   $bIsBulkAction
	 * @return array
	 */
	private function ajaxExec_ScanItemAction( $action, $bIsBulkAction = false ) :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		$success = false;

		if ( $action == 'download' ) {
			// A special case since this action is handled using Javascript
			$success = true;
			$sMessage = __( 'File download has started.', 'wp-simple-firewall' );
		}
		else {
			if ( $bIsBulkAction ) {
				$aItemIdsToProcess = (array)Services::Request()->post( 'ids', [] );
			}
			else {
				$aItemIdsToProcess = [ Services::Request()->post( 'rid' ) ];
			}
			/** @var int[] $aItemIdsToProcess */
			$aItemIdsToProcess = array_filter( array_map( 'intval', $aItemIdsToProcess ) );

			if ( empty( $aItemIdsToProcess ) ) {
				$sMessage = __( 'Unsupported item(s) selected', 'wp-simple-firewall' );
			}
			else {
				try {
					$aScanSlugs = [];
					$aSuccessfulItems = [];
					foreach ( $aItemIdsToProcess as $nId ) {
						/** @var Shield\Databases\Scanner\EntryVO $entry */
						$entry = $mod->getDbHandler_ScanResults()
									 ->getQuerySelector()
									 ->byId( $nId );
						if ( $entry instanceof Shield\Databases\Scanner\EntryVO ) {
							$aScanSlugs[] = $entry->scan;
							if ( $mod->getScanCon( $entry->scan )->executeItemAction( $nId, $action ) ) {
								$aSuccessfulItems[] = $nId;
							}
						}
					}

					if ( count( $aSuccessfulItems ) === count( $aItemIdsToProcess ) ) {
						$success = true;
						$sMessage = __( 'Action successful.' );
					}
					else {
						$sMessage = __( 'An error occurred.' ).' '.__( 'Some items may not have been processed.' );
					}

					// We don't rescan for ignores.
					if ( in_array( $action, [ 'ignore' ] ) ) {
						$sMessage .= ' '.__( 'Reloading', 'wp-simple-firewall' ).' ...';
					}
					else {
						// rescan
						$mod->getScanQueueController()->startScans( $aScanSlugs );
						$sMessage .= ' '.__( 'Rescanning', 'wp-simple-firewall' ).' ...';
					}
				}
				catch ( \Exception $oE ) {
					$sMessage = $oE->getMessage();
				}
			}
		}

		return [
			'success'     => $success,
			'page_reload' => !in_array( $action, [ 'download' ] ),
			'message'     => $sMessage,
		];
	}

	private function ajaxExec_CheckScans() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var Strings $oStrings */
		$oStrings = $mod->getStrings();
		/** @var Shield\Databases\ScanQueue\Select $oSel */
		$oSel = $mod->getDbHandler_ScanQueue()->getQuerySelector();

		$oQueCon = $mod->getScanQueueController();
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
				'progress_html' => $mod->renderTemplate(
					'/wpadmin_pages/insights/scans/modal/progress_snippet.twig',
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

	private function ajaxExec_StartScans() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$bSuccess = false;
		$bPageReload = false;
		$sMessage = __( 'No scans were selected', 'wp-simple-firewall' );
		$aFormParams = $this->getAjaxFormParams();

		$oScanCon = $mod->getScanQueueController();

		if ( !empty( $aFormParams ) ) {
			$aSelectedScans = array_keys( $aFormParams );

			$aUiTrack = $mod->getUiTrack();
			$aUiTrack[ 'selected_scans' ] = $aSelectedScans;
			$mod->setUiTrack( $aUiTrack );

			$aScansToStart = [];
			foreach ( $aSelectedScans as $sScanSlug ) {
				$oThisScanCon = $mod->getScanCon( $sScanSlug );
				if ( !empty( $oThisScanCon ) && $oThisScanCon->isScanningAvailable() ) {

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