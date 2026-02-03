<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\CommonDisplayStrings;
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

			if ( !$FS->isAccessibleFile( $lock->path ) ) {
				$originalFileMissing = true;
				$current = '';
			}
			else {
				$originalFileMissing = false;
				$current = (string)$FS->getFileContent( $lock->path );
				if ( empty( $current ) ) {
					$currentContentEmpty = false;
				}
			}

			$data = [
				'error'   => '',
				'success' => false,
				'ajax'    => $FLCon->createFileDownloadLinks( $lock ),
				'flags'   => [
					'has_diff'              => $isDifferent,
					'original_file_missing' => $originalFileMissing,
					'current_content_empty' => $currentContentEmpty ?? false,
				],
				'html'    => [
					'diff' => $isDifferent ? ( new Diff() )->run( $lock, $current ) : '',
				],
				'vars'    => [
					'rid' => $RID,
				],
				'strings' => [
					'no_changes'            => __( 'There have been no changes to the selected file.', 'wp-simple-firewall' ),
					'butt_restore'          => __( 'Restore File', 'wp-simple-firewall' ),
					'butt_accept'           => __( 'Accept Changes', 'wp-simple-firewall' ),
					'file_name'             => CommonDisplayStrings::get( 'name_label' ),
					'file_size'             => __( 'File Size', 'wp-simple-firewall' ),
					'reviewing_locked_file' => __( 'Reviewing Locked File', 'wp-simple-firewall' ),
					'file_details'          => __( 'File Details', 'wp-simple-firewall' ),
					'modified_file'         => __( 'Modified File', 'wp-simple-firewall' ),
					'locked'                => __( 'Locked', 'wp-simple-firewall' ),
					'modified_timestamp'    => __( 'File Modified Timestamp', 'wp-simple-firewall' ),
					'file_modified'         => __( 'File Modified', 'wp-simple-firewall' ),
					'relative_path'         => __( 'Relative Path', 'wp-simple-firewall' ),
					'full_path'             => __( 'Full Path', 'wp-simple-firewall' ),
					'modified'              => __( 'Modified', 'wp-simple-firewall' ),
					'download'              => __( 'Download', 'wp-simple-firewall' ),
					'change_detected_at'    => __( 'Change Detected', 'wp-simple-firewall' ),
					'file_content_original' => __( 'Original File Content', 'wp-simple-firewall' ),
					'file_content_current'  => __( 'Current File Content', 'wp-simple-firewall' ),
					'download_original'     => __( 'Download Original', 'wp-simple-firewall' ),
					'download_modified'     => __( 'Download Modified', 'wp-simple-firewall' ),
					'file_download'         => __( 'File Download', 'wp-simple-firewall' ),
					'file_info'             => __( 'File Info', 'wp-simple-firewall' ),
					'file_accept'           => __( 'Accept File Changes', 'wp-simple-firewall' ),
					'file_accept_checkbox'  => __( 'Are you sure you want to keep the file changes?', 'wp-simple-firewall' ),
					'file_restore'          => __( 'Restore Original File', 'wp-simple-firewall' ),
					'file_restore_checkbox' => __( 'Are you sure you want to restore the original file contents?', 'wp-simple-firewall' ),
					'file_restore_button'   => __( 'Are you sure you want to restore the original file contents?', 'wp-simple-firewall' ),
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
