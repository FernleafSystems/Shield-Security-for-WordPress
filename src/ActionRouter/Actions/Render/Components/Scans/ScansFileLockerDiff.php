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
			$RID = $this->fields()->positiveInt( 'rid' );
			$lock = $FLCon->getFileLock( $RID );
			$isDifferent = $lock->detected_at > 0;

			$originalFileMissing = !$FS->isAccessibleFile( $lock->path );
			$current = $originalFileMissing ? '' : (string)$FS->getFileContent( $lock->path );

			$original = ( new FileLocker\Ops\ReadOriginalFileContent() )->run( $lock );
			$diff = '';
			$diffAvailable = false;
			if ( $isDifferent ) {
				try {
					$diff = $this->generateDiff( $original, $current );
					$diffAvailable = true;
				}
				catch ( \Throwable $e ) {
					// Diff availability is represented explicitly in the render contract.
				}
			}

			$data = [
				'error'   => '',
				'success' => false,
				'ajax'    => [
					'original' => $FLCon->createFileDownloadLink( $lock, 'original' ),
					// Only a changed file is offered, and one that's gone or empty can't be downloaded: no link then.
					'current'  => $isDifferent && !empty( $current ) ? $FLCon->createFileDownloadLink( $lock, 'current' ) : '',
				],
				'flags'   => [
					'has_diff'              => $isDifferent,
					'diff_available'        => $diffAvailable,
					'original_file_missing' => $originalFileMissing,
					'current_content_empty' => !$originalFileMissing && empty( $current ),
				],
				'html'    => [
					'diff' => $diff,
				],
				'vars'    => [
					'rid'           => $RID,
					// The page sends the Accept it showed, so a file that changes state meanwhile can't switch it.
					'accept_action' => $originalFileMissing ? 'accept_removal' : 'accept',
				],
				'strings' => [
					'butt_restore'          => __( 'Restore File', 'wp-simple-firewall' ),
					'butt_accept'           => $originalFileMissing ? __( 'Accept Removal', 'wp-simple-firewall' ) : __( 'Accept Changes', 'wp-simple-firewall' ),
					'file_size'             => __( 'File Size', 'wp-simple-firewall' ),
					'file_details'          => __( 'File Details', 'wp-simple-firewall' ),
					'modified_file'         => __( 'Modified File', 'wp-simple-firewall' ),
					'locked'                => __( 'Locked', 'wp-simple-firewall' ),
					'file_modified'         => __( 'File Modified', 'wp-simple-firewall' ),
					'relative_path'         => __( 'Relative Path', 'wp-simple-firewall' ),
					'download_original'     => __( 'Download Original File', 'wp-simple-firewall' ),
					'download_current'      => __( 'Download Modified File', 'wp-simple-firewall' ),
					'change_detected_at'    => __( 'Change Detected', 'wp-simple-firewall' ),
					'file_content_original' => __( 'Original File Content', 'wp-simple-firewall' ),
					'file_content_current'  => __( 'Current File Content', 'wp-simple-firewall' ),
					'file_accept'           => $originalFileMissing ? __( 'Accept File Removal', 'wp-simple-firewall' ) : __( 'Accept File Changes', 'wp-simple-firewall' ),
					'file_accept_checkbox'  => $originalFileMissing ?
						__( 'Are you sure the file should stay removed? File Locker will stop locking it.', 'wp-simple-firewall' )
						: __( 'Are you sure you want to keep the file changes?', 'wp-simple-firewall' ),
					'file_restore'          => __( 'Restore Original File', 'wp-simple-firewall' ),
					'file_restore_checkbox' => __( 'Are you sure you want to restore the original file contents?', 'wp-simple-firewall' ),
					'diff_unavailable'      => __( 'The file comparison could not be displayed. File details and actions are still available below.', 'wp-simple-firewall' ),
				]
			];

			$carb = Services::Request()->carbon( true );

			$data[ 'vars' ][ 'relative_path' ] = \str_replace( wp_normalize_path( ABSPATH ), '/', wp_normalize_path( $lock->path ) );
			$data[ 'vars' ][ 'locked_at' ] = $carb->setTimestamp( $lock->created_at )->diffForHumans();
			$data[ 'vars' ][ 'file_modified_at' ] =
				Services::WpGeneral()->getTimeStampForDisplay( $FS->getModifiedTime( $lock->path ) );
			$data[ 'vars' ][ 'file_modified_ago' ] =
				$carb->setTimestamp( $FS->getModifiedTime( $lock->path ) )->diffForHumans();
			$data[ 'vars' ][ 'change_detected_at' ] = $carb->setTimestamp( $lock->detected_at )->diffForHumans();
			$data[ 'vars' ][ 'file_size_modified' ] = $FS->exists( $lock->path ) ? FormatBytes::Format( $FS->getFileSize( $lock->path ), 3 ) : 0;

			$data[ 'vars' ][ 'file_size_locked' ] = FormatBytes::Format( \strlen( $original ), 3 );

			$data[ 'success' ] = true;
		}
		catch ( \Exception $e ) {
			throw new ActionException( $e->getMessage() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Plain-text message: reaches the AJAX render response as an ActionException, sent as JSON text.
		}

		return Services::DataManipulation()->mergeArraysRecursive( parent::getRenderData(), $data );
	}

	protected function generateDiff( string $original, string $current ) :string {
		return ( new Diff() )->run( $original, $current );
	}
}
