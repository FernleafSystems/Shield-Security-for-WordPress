<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops\Diff;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Decorate\FormatBytes;

class ScansFileLockerDiff extends BaseScans {

	public const SLUG = 'filelocker_showdiff';
	public const TEMPLATE = '/wpadmin_pages/insights/scans/results/realtime/file_locker/file_diff.twig';

	protected function getRenderData() :array {
		$FLCon = self::con()->comps->file_locker;
		$FS = Services::WpFs();

		try {
			$RID = (int)$this->action_data[ 'rid' ] ?? -1;
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
					'diff' => $isDifferent ? ( new Diff() )->run( $lock ) : '',
				],
				'vars'    => [
					'rid' => $RID,
				],
				'strings' => [
					'no_changes'            => __( 'There have been no changes to the selected file.' ),
					'butt_restore'          => __( 'Restore File' ),
					'butt_accept'           => __( 'Accept Changes' ),
					'file_name'             => __( 'Name' ),
					'file_size'             => __( 'File Size' ),
					'reviewing_locked_file' => __( 'Reviewing Locked File' ),
					'file_details'          => __( 'File Details' ),
					'modified_file'         => __( 'Modified File' ),
					'locked'                => __( 'Locked' ),
					'modified_timestamp'    => __( 'File Modified Timestamp' ),
					'file_modified'         => __( 'File Modified' ),
					'relative_path'         => __( 'Relative Path' ),
					'full_path'             => __( 'Full Path' ),
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
			$filePath = wp_normalize_path( $lock->path );
			if ( \strpos( $filePath, $absPath ) !== false ) {
				$data[ 'vars' ][ 'relative_path' ] = \str_replace( $absPath, '/', $filePath );
			}
			else {
				$data[ 'vars' ][ 'relative_path' ] = '../'.basename( $filePath );
			}
			$data[ 'vars' ][ 'full_path' ] = $filePath;

			$data[ 'vars' ][ 'relative_path' ] = \str_replace( wp_normalize_path( ABSPATH ), '/', wp_normalize_path( $lock->path ) );
			$data[ 'vars' ][ 'locked_at' ] = $carb->setTimestamp( $lock->created_at )->diffForHumans();
			$data[ 'vars' ][ 'file_modified_at' ] =
				Services::WpGeneral()->getTimeStampForDisplay( $FS->getModifiedTime( $lock->path ) );
			$data[ 'vars' ][ 'file_modified_ago' ] =
				$carb->setTimestamp( $FS->getModifiedTime( $lock->path ) )->diffForHumans();
			$data[ 'vars' ][ 'change_detected_at' ] = $carb->setTimestamp( $lock->detected_at )->diffForHumans();
			$data[ 'vars' ][ 'file_size_modified' ] = $FS->exists( $lock->path ) ? FormatBytes::Format( $FS->getFileSize( $lock->path ), 3 ) : 0;
			$data[ 'vars' ][ 'file_name' ] = \basename( $lock->path );

			$data[ 'vars' ][ 'file_size_locked' ] = FormatBytes::Format( \strlen( ( new FileLocker\Ops\ReadOriginalFileContent() )->run( $lock ) ), 3 );

			$data[ 'success' ] = true;
		}
		catch ( \Exception $e ) {
			throw new ActionException( $e->getMessage() );
		}

		return Services::DataManipulation()->mergeArraysRecursive( parent::getRenderData(), $data );
	}
}