<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Lib\Request\FormParams;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan;
use FernleafSystems\Wordpress\Services\Services;

class AjaxHandler extends Shield\Modules\BaseShield\AjaxHandler {

	protected function getAjaxActionCallbackMap( bool $isAuth ) :array {
		$map = parent::getAjaxActionCallbackMap( $isAuth );
		if ( $isAuth ) {
			$map = array_merge( $map, [
				'scanresults_action'    => [ $this, 'ajaxExec_ScanResultsAction' ],
				'scans_start'           => [ $this, 'ajaxExec_StartScans' ],
				'scans_check'           => [ $this, 'ajaxExec_CheckScans' ],
				'plugin_reinstall'      => [ $this, 'ajaxExec_PluginReinstall' ],
				'filelocker_showdiff'   => [ $this, 'ajaxExec_FileLockerShowDiff' ],
				'filelocker_fileaction' => [ $this, 'ajaxExec_FileLockerFileAction' ],
			] );
		}
		return $map;
	}

	public function ajaxExec_FileLockerShowDiff() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$FLCon = $mod->getFileLocker();
		$FS = Services::WpFs();

		$nRID = (int)Services::Request()->post( 'rid' );

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
			$lock = $FLCon->getFileLock( $nRID );

			$isDifferent = $lock->detected_at > 0;
			$data[ 'ajax' ] = $FLCon->createFileDownloadLinks( $lock );
			$data[ 'flags' ][ 'has_diff' ] = $isDifferent;
			$data[ 'html' ][ 'diff' ] = $isDifferent ?
				( new FileLocker\Ops\PerformAction() )
					->setMod( $this->getMod() )
					->run( $lock, 'diff' ) : '';

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

	public function ajaxExec_FileLockerFileAction() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$FLCon = $mod->getFileLocker();
		$req = Services::Request();
		$success = false;

		if ( $req->post( 'confirmed' ) == '1' ) {
			try {
				$lock = $FLCon->getFileLock( (int)$req->post( 'rid' ) );
				$success = ( new FileLocker\Ops\PerformAction() )
					->setMod( $this->getMod() )
					->run( $lock, (string)$req->post( 'file_action' ) );
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

	public function ajaxExec_PluginReinstall() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$req = Services::Request();

		$activate = $req->post( 'activate' );
		$file = sanitize_text_field( wp_unslash( $req->post( 'file' ) ) );

		if ( $req->post( 'reinstall' ) ) {
			/** @var Scan\Controller\Afs $scan */
			$scan = $mod->getScansCon()->getScanCon( 'afs' );
			$activate = $scan->actionPluginReinstall( $file );
		}

		if ( $activate ) {
			Services::WpPlugins()->activate( $file );
		}

		return [ 'success' => true ];
	}

	public function ajaxExec_CheckScans() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var Strings $strings */
		$strings = $mod->getStrings();

		$statusChecker = ( new Scan\Init\ScansStatus() )->setMod( $mod );
		$queueCon = $mod->getScanQueueController();
		$current = $statusChecker->current();
		$hasCurrent = !empty( $current );
		if ( $hasCurrent ) {
			$currentScan = $strings->getScanName( $current );
		}
		else {
			$currentScan = __( 'No scan running.', 'wp-simple-firewall' );
		}

		$running = $statusChecker->enqueued();

		if ( count( $running ) === 0 ) {
			$remainingScans = __( 'No scans remaining.', 'wp-simple-firewall' );
		}
		else {
			$remainingScans = sprintf( __( '%s scans remaining.', 'wp-simple-firewall' ),
				count( $running ) );
		}

		return [
			'success' => true,
			'running' => $queueCon->getScansRunningStates(),
			'vars'    => [
				'progress_html' => $mod->renderTemplate(
					'/wpadmin_pages/insights/scans/modal/progress_snippet.twig',
					[
						'current_scan'    => __( 'Current Scan', 'wp-simple-firewall' ),
						'scan'            => $currentScan,
						'remaining_scans' => $remainingScans,
						'progress'        => 100*$queueCon->getScanJobProgress(),
						'patience_1'      => __( 'Please be patient.', 'wp-simple-firewall' ),
						'patience_2'      => __( 'Some scans can take quite a while to complete.', 'wp-simple-firewall' ),
						'completed'       => __( 'Scans completed.', 'wp-simple-firewall' ).' '.__( 'Reloading page', 'wp-simple-firewall' ).'...'
					],
					true
				),
			]
		];
	}

	public function ajaxExec_StartScans() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var Options $opts */
		$opts = $this->getOptions();
		$success = false;
		$reloadPage = false;
		$msg = __( 'No scans were selected', 'wp-simple-firewall' );
		$params = FormParams::Retrieve();

		if ( !empty( $params ) ) {
			$uiTrack = $mod->getUiTrack();
			$uiTrack->selected_scans = array_intersect( array_keys( $params ), $opts->getScanSlugs() );
			$mod->setUiTrack( $uiTrack );

			$resetIgnore = (bool)( $params[ 'opt_clear_ignore' ] ?? false );
			if ( $mod->getScansCon()->startNewScans( $uiTrack->selected_scans, $resetIgnore ) ) {
				$success = true;
				$reloadPage = true;
				$msg = __( 'Scans started.', 'wp-simple-firewall' ).' '.__( 'Please wait, as this will take a few moments.', 'wp-simple-firewall' );
			}
		}

		$isScanRunning = $mod->getScanQueueController()->hasRunningScans();

		return [
			'success'       => $success,
			'scans_running' => $isScanRunning,
			'page_reload'   => $reloadPage && !$isScanRunning,
			'message'       => $msg,
		];
	}

	public function ajaxExec_ScanResultsAction() :array {
		try {
			return ( new Lib\ScanTables\DelegateAjaxHandler() )
				->setMod( $this->getMod() )
				->processAjaxAction();
		}
		catch ( \Exception $e ) {
			return [
				'success'     => false,
				'page_reload' => true,
				'message'     => $e->getMessage(),
			];
		}
	}
}