<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Lib\Request\FormParams;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan;
use FernleafSystems\Wordpress\Services\Services;

class AjaxHandler extends Shield\Modules\BaseShield\AjaxHandler {

	protected function processAjaxAction( string $action ) :array {

		$req = Services::Request();
		switch ( $action ) {

			case 'scans_start':
				$response = $this->ajaxExec_StartScans();
				break;

			case 'scans_check':
				$response = $this->ajaxExec_CheckScans();
				break;

			case 'item_action':
				$response = $this->ajaxExec_ScanItemAction( $req->post( 'item_action' ), false );
				break;

			case 'bulk_action':
				$response = $this->ajaxExec_ScanItemAction( $req->post( 'bulk_action' ), true );
				break;

			case 'item_asset_deactivate':
			case 'item_asset_reinstall':
			case 'item_delete':
			case 'item_ignore':
			case 'item_repair':
				$response = $this->ajaxExec_ScanItemAction( str_replace( 'item_', '', $action ) );
				break;

			case 'render_table_scan':
				$response = $this->ajaxExec_BuildTableScan();
				break;

			case 'plugin_reinstall':
				$response = $this->ajaxExec_PluginReinstall();
				break;

			case 'filelocker_showdiff':
				$response = $this->ajaxExec_FileLockerShowDiff();
				break;

			case 'filelocker_fileaction':
				$response = $this->ajaxExec_FileLockerFileAction();
				break;

			default:
				$response = parent::processAjaxAction( $action );
		}

		return $response;
	}

	private function ajaxExec_BuildTableScan() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		switch ( Services::Request()->post( 'fScan', '' ) ) {

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
		$FLCon = $mod->getFileLocker();
		$FS = Services::WpFs();

		$nRID = Services::Request()->post( 'rid' );
		$data = [
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
				'file_modified'         => __( 'File Modified' ),
				'relative_path'         => __( 'Relative Path' ),
				'modified'              => __( 'Modified' ),
				'download'              => __( 'Download' ),
				'change_detected_at'    => __( 'Change Detected' ),
				'file_content_original' => __( 'Original File Content' ),
				'file_content_current'  => __( 'Current File Content' ),
				'download_original'     => __( 'Download Original' ),
				'download_modified'     => __( 'Download Modified' ),
				'file_download'         => __( 'File Download' ),
				'file_info'             => __( 'File Info' ),
				'file_accept'           => __( 'Accept File Changes' ),
				'file_accept_checkbox'  => __( 'Are you sure you want to keep the file changes?' ),
				'file_restore'          => __( 'Restore Original File' ),
				'file_restore_checkbox' => __( 'Are you sure you want to restore the original file contents?' ),
				'file_restore_button'   => __( 'Are you sure you want to restore the original file contents?' ),
			]
		];
		try {
			if ( !is_numeric( $nRID ) ) {
				throw new \Exception( 'Not a valid file lock request.' );
			}
			$lock = $FLCon->getFileLock( $nRID );
			if ( !$lock instanceof Databases\FileLocker\EntryVO ) {
				throw new \Exception( 'Not a valid file lock request.' );
			}

			$isDifferent = $lock->detected_at > 0;
			$data[ 'ajax' ] = $FLCon->createFileDownloadLinks( $lock );
			$data[ 'flags' ][ 'has_diff' ] = $isDifferent;
			$data[ 'html' ][ 'diff' ] = $isDifferent ?
				( new FileLocker\Ops\PerformAction() )
					->setMod( $this->getMod() )
					->run( $nRID, 'diff' ) : '';

			$carb = Services::Request()->carbon( true );

			$absPath = wp_normalize_path( ABSPATH );
			$filePath = wp_normalize_path( $lock->file );
			if ( strpos( $filePath, $absPath ) !== false ) {
				$data[ 'vars' ][ 'relative_path' ] = str_replace( $absPath, '/', $filePath );
			}
			else {
				$data[ 'vars' ][ 'relative_path' ] = '../'.basename( $filePath );
			}

			$data[ 'vars' ][ 'relative_path' ] = str_replace( wp_normalize_path( ABSPATH ), '/', wp_normalize_path( $lock->file ) );
			$data[ 'vars' ][ 'locked_at' ] = $carb->setTimestamp( $lock->created_at )->diffForHumans();
			$data[ 'vars' ][ 'file_modified_at' ] =
				Services::WpGeneral()->getTimeStampForDisplay( $FS->getModifiedTime( $lock->file ) );
			$data[ 'vars' ][ 'file_modified_ago' ] =
				$carb->setTimestamp( $FS->getModifiedTime( $lock->file ) )->diffForHumans();
			$data[ 'vars' ][ 'change_detected_at' ] = $carb->setTimestamp( $lock->detected_at )->diffForHumans();
			$data[ 'vars' ][ 'file_size_locked' ] = Shield\Utilities\Tool\FormatBytes::Format( strlen(
				( new FileLocker\Ops\ReadOriginalFileContent() )
					->setMod( $mod )
					->run( $lock )
			), 3 );
			$data[ 'vars' ][ 'file_size_modified' ] = $FS->exists( $lock->file ) ?
				Shield\Utilities\Tool\FormatBytes::Format( $FS->getFileSize( $lock->file ), 3 )
				: 0;
			$data[ 'vars' ][ 'file_name' ] = basename( $lock->file );
			$data[ 'success' ] = true;
		}
		catch ( \Exception $e ) {
			$data[ 'error' ] = $e->getMessage();
		}

		return [
			'success' => $data[ 'success' ],
			'message' => $data[ 'error' ],
			'html'    => $this->getMod()
							  ->renderTemplate(
								  '/wpadmin_pages/insights/scans/results/realtime/file_locker/file_diff.twig',
								  $data,
								  true
							  )
		];
	}

	private function ajaxExec_FileLockerFileAction() :array {
		$req = Services::Request();
		$success = false;

		if ( $req->post( 'confirmed' ) == '1' ) {
			try {
				$success = ( new FileLocker\Ops\PerformAction() )
					->setMod( $this->getMod() )
					->run( $req->post( 'rid' ), $req->post( 'file_action' ) );
				$msg = __( 'Requested action completed successfully.', 'wp-simple-firewall' );
			}
			catch ( \Exception $e ) {
				$msg = __( 'Requested action failed.', 'wp-simple-firewall' );
			}
		}
		else {
			$msg = __( 'Please check the box to confirm this action', 'wp-simple-firewall' );
		}

		return [
			'success' => $success,
			'message' => $msg,
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
	 * @param bool   $isBulkAction
	 * @return array
	 */
	private function ajaxExec_ScanItemAction( $action, $isBulkAction = false ) :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		$success = false;

		if ( $action == 'download' ) {
			// A special case since this action is handled using Javascript
			$success = true;
			$msg = __( 'File download has started.', 'wp-simple-firewall' );
		}
		else {
			if ( $isBulkAction ) {
				$itemIDs = (array)Services::Request()->post( 'ids', [] );
			}
			else {
				$itemIDs = [ Services::Request()->post( 'rid' ) ];
			}
			/** @var int[] $itemIDs */
			$itemIDs = array_filter( array_map( 'intval', $itemIDs ) );

			if ( empty( $itemIDs ) ) {
				$msg = __( 'Unsupported item(s) selected', 'wp-simple-firewall' );
			}
			else {
				try {
					$scanSlugs = [];
					$aSuccessfulItems = [];
					foreach ( $itemIDs as $ID ) {
						/** @var Shield\Databases\Scanner\EntryVO $entry */
						$entry = $mod->getDbHandler_ScanResults()
									 ->getQuerySelector()
									 ->byId( $ID );
						if ( $entry instanceof Shield\Databases\Scanner\EntryVO ) {
							$scanSlugs[] = $entry->scan;
							if ( $mod->getScanCon( $entry->scan )->executeItemAction( $ID, $action ) ) {
								$aSuccessfulItems[] = $ID;
							}
						}
					}

					if ( count( $aSuccessfulItems ) === count( $itemIDs ) ) {
						$success = true;
						$msg = __( 'Action successful.' );
					}
					else {
						$msg = __( 'An error occurred.' ).' '.__( 'Some items may not have been processed.' );
					}

					// We don't rescan for ignores.
					$rescanSlugs = array_diff( $scanSlugs, [ Scan\Controller\Mal::SCAN_SLUG ] );

					if ( empty( $rescanSlugs ) || in_array( $action, [ 'ignore' ] ) ) {
						$msg .= ' '.__( 'Reloading', 'wp-simple-firewall' ).' ...';
					}
					else {
						// rescan
						$mod->getScanQueueController()->startScans( $rescanSlugs );
						$msg .= ' '.__( 'Rescanning', 'wp-simple-firewall' ).' ...';
					}
				}
				catch ( \Exception $e ) {
					$msg = $e->getMessage();
				}
			}
		}

		return [
			'success'     => $success,
			'page_reload' => !in_array( $action, [ 'download' ] ),
			'message'     => $msg,
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
		/** @var Options $opts */
		$opts = $this->getOptions();
		$success = false;
		$reloadPage = false;
		$msg = __( 'No scans were selected', 'wp-simple-firewall' );
		$formParams = FormParams::Retrieve();

		$scanCon = $mod->getScanQueueController();

		if ( !empty( $formParams ) ) {
			$selected = array_keys( $formParams );

			$aUiTrack = $mod->getUiTrack();
			$aUiTrack[ 'selected_scans' ] = array_intersect( array_keys( $formParams ), $opts->getScanSlugs() );
			$mod->setUiTrack( $aUiTrack );

			$toScan = [];
			foreach ( $selected as $slug ) {
				try {
					$thisScanCon = $mod->getScanCon( $slug );
					if ( $thisScanCon->isReady() ) {

						$toScan[] = $slug;

						if ( isset( $formParams[ 'opt_clear_ignore' ] ) ) {
							$thisScanCon->resetIgnoreStatus();
						}
						if ( isset( $formParams[ 'opt_clear_notification' ] ) ) {
							$thisScanCon->resetNotifiedStatus();
						}

						$success = true;
						$reloadPage = true;
						$msg = __( 'Scans started.', 'wp-simple-firewall' ).' '.__( 'Please wait, as this will take a few moments.', 'wp-simple-firewall' );
					}
				}
				catch ( \Exception $e ) {
				}
			}
			$scanCon->startScans( $toScan );
		}

		$isScanRunning = $scanCon->hasRunningScans();

		return [
			'success'       => $success,
			'scans_running' => $isScanRunning,
			'page_reload'   => $reloadPage && !$isScanRunning,
			'message'       => $msg,
		];
	}
}