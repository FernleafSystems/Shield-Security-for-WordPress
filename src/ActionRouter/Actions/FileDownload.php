<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility\ExternalEntryGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Utility\FileDownloadHandler;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\ImportExport\Export;

class FileDownload extends BaseAction {

	use Traits\IssuesFileDownloads;

	public const SLUG = 'file_download';

	public static function ExternalAccess() :array {
		return [
			ExternalEntryGuard::ENTRY_ORDINARY => ExternalEntryGuard::PROOF_NONCE,
		];
	}

	protected function exec() {
		try {
			$cat = $this->fields()->string( 'download_category', '' );
			if ( empty( $cat ) ) {
				throw new \Exception( __( 'Invalid download request.', 'wp-simple-firewall' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Caught by FileDownload::exec(), which returns it as a plain-text response message.
			}
			$contents = $this->getFileDownloadContents( $cat );

			$this->fileDownloadResponse( $contents[ 'name' ] )
				->fromString( $contents[ 'content' ], [ 'Set-cookie' => 'fileDownload=true; path=/' ] );
		}
		catch ( \Exception $e ) {
			$this->response()->mergePayload( [
				'message' => $e->getMessage(),
			] )->setPayloadSuccess( false );
		}
	}

	/**
	 * @return array{name:string, content:string}
	 * @throws \Exception
	 */
	private function getFileDownloadContents( string $downloadID ) :array {
		$con = self::con();

		switch ( $downloadID ) {

			case 'filelocker':
				$fileDetails = $con->comps->file_locker->handleFileDownloadRequest(
					$this->fields()->positiveInt( 'rid' ),
					$this->fields()->string( 'type', '' )
				);
				break;

			case 'scan_file':
				$fileDetails = ( new FileDownloadHandler() )->downloadByItemId( $this->fields()->positiveInt( 'rid' ) );
				break;

			case 'plugin_export':
				$fileDetails = ( new Export() )->toFile();
				break;

			case 'report_download_pdf':
				$fileDetails = [
					'name'    => wp_rand().'.pdf',
					'content' => self::con()->comps->reports->convertToPdf( $this->fields()->positiveInt( 'rid' ) )
				];
				break;

			default:
				throw new \Exception( __( 'Invalid download request.', 'wp-simple-firewall' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Caught by FileDownload::exec(), which returns it as a plain-text response message.
		}

		return $fileDetails;
	}
}
