<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\Components\Scans;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Exceptions\ActionException;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool\FormatBytes;
use FernleafSystems\Wordpress\Services\Services;

class ScansFileLockerDiff extends BaseScans {

	const SLUG = 'filelocker_showdiff';
	const TEMPLATE = '/wpadmin_pages/insights/scans/results/realtime/file_locker/file_diff.twig';

	protected function getRenderData() :array {
		/** @var ModCon $mod */
		$mod = $this->primary_mod;
		$FLCon = $mod->getFileLocker();
		$FS = Services::WpFs();

		try {
			$RID = (int)Services::Request()->post( 'rid' );
			$lock = $FLCon->getFileLock( $RID );
			$isDifferent = $lock->detected_at > 0;

			$data = [
				'error'   => '',
				'success' => false,
				'ajax'    => $FLCon->createFileDownloadLinks( $lock ),
				'flags'   => [
					'has_diff' => $isDifferent,
				],
				'html'    => [
					'diff' => $isDifferent ?
						( new FileLocker\Ops\PerformAction() )
							->setMod( $mod )
							->run( $lock, 'diff' ) : '',
				],
				'vars'    => [
					'rid' => $RID,
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
			$data[ 'vars' ][ 'file_size_modified' ] = $FS->exists( $lock->file ) ?
				FormatBytes::Format( $FS->getFileSize( $lock->file ), 3 )
				: 0;
			$data[ 'vars' ][ 'file_name' ] = basename( $lock->file );

			$data[ 'vars' ][ 'file_size_locked' ] = FormatBytes::Format( strlen(
				( new FileLocker\Ops\ReadOriginalFileContent() )
					->setMod( $mod )
					->run( $lock ) // potential exception
			), 3 );

			$data[ 'success' ] = true;
		}
		catch ( \Exception $e ) {
			throw new ActionException( $e->getMessage() );
		}

		return Services::DataManipulation()->mergeArraysRecursive( parent::getRenderData(), $data );
	}
}